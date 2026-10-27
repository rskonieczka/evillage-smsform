<?php
/**
 * Rejestr voucherów. Numer vouchera = AUTO_INCREMENT tabeli,
 * więc nadawanie numerów jest atomowe także przy równoległych żądaniach.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Vouchers {

	const STATUS_PENDING = 'pending';
	const STATUS_SENT    = 'sent';
	const STATUS_FAILED  = 'failed';

	/**
	 * Nazwa tabeli.
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'evsms_vouchers';
	}

	/**
	 * Tworzy/aktualizuje tabelę.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			phone varchar(20) NOT NULL,
			code varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			sms_id varchar(64) NOT NULL DEFAULT '',
			error text NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY created_at (created_at)
		) {$charset};" );

		update_option( 'evsms_db_version', EVSMS_DB_VERSION );
	}

	/**
	 * Rezerwuje numer vouchera (wiersz w statusie pending).
	 *
	 * @return int|false ID (autonumer) albo false.
	 */
	public static function create( $phone, $code, $ip_hash ) {
		global $wpdb;

		$ok = $wpdb->insert(
			self::table(),
			array(
				'phone'      => $phone,
				'code'       => $code,
				'status'     => self::STATUS_PENDING,
				'ip_hash'    => $ip_hash,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Oznacza voucher jako wysłany.
	 */
	public static function mark_sent( $id, $sms_id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => self::STATUS_SENT, 'sms_id' => (string) $sms_id ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Oznacza voucher jako nieudany (numer zostaje w rejestrze dla audytu).
	 */
	public static function mark_failed( $id, $error ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => self::STATUS_FAILED, 'error' => (string) $error ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Czy numer telefonu otrzymał już kupon.
	 */
	public static function phone_has_voucher( $phone ) {
		global $wpdb;
		$table = self::table();

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE phone = %s AND status = %s LIMIT 1",
			$phone,
			self::STATUS_SENT
		) );
	}

	/**
	 * Liczba voucherów wg statusu, np. ['sent' => 12, 'failed' => 1].
	 */
	public static function count_by_status() {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );

		return wp_list_pluck( (array) $rows, 'total', 'status' );
	}

	/**
	 * Ostatnie wpisy do podglądu w panelu.
	 */
	public static function latest( $limit = 50 ) {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
			$limit
		) );
	}

	/**
	 * Formatuje numer vouchera, np. EV-000123.
	 */
	public static function format_number( $id ) {
		$prefix = EVSMS_Settings::get( 'voucher_prefix' );
		return $prefix . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	}
}
