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
			device_hash char(64) NOT NULL DEFAULT '',
			page varchar(255) NOT NULL DEFAULT '',
			gclid varchar(255) NOT NULL DEFAULT '',
			utm_source varchar(100) NOT NULL DEFAULT '',
			utm_medium varchar(100) NOT NULL DEFAULT '',
			utm_campaign varchar(150) NOT NULL DEFAULT '',
			conv_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY device_hash (device_hash),
			KEY created_at (created_at)
		) {$charset};" );

		update_option( 'evsms_db_version', EVSMS_DB_VERSION );
	}

	/**
	 * Rezerwuje numer vouchera (wiersz w statusie pending).
	 *
	 * @return int|false ID (autonumer) albo false.
	 */
	public static function create( $phone, $code, $ip_hash, $device_hash = '', $source = array() ) {
		global $wpdb;

		$source = wp_parse_args( $source, array( 'page' => '', 'gclid' => '', 'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '' ) );

		$ok = $wpdb->insert(
			self::table(),
			array(
				'phone'        => $phone,
				'code'         => $code,
				'status'       => self::STATUS_PENDING,
				'ip_hash'      => $ip_hash,
				'device_hash'  => $device_hash,
				'page'         => $source['page'],
				'gclid'        => $source['gclid'],
				'utm_source'   => $source['utm_source'],
				'utm_medium'   => $source['utm_medium'],
				'utm_campaign' => $source['utm_campaign'],
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Liczba wysłanych voucherów z danego urządzenia od $since (czas lokalny, jak created_at).
	 */
	public static function count_by_device( $device_hash, $since ) {
		global $wpdb;
		$table = self::table();

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE device_hash = %s AND status = %s AND created_at >= %s",
			$device_hash,
			self::STATUS_SENT,
			$since
		) );
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
	 * Zapisuje, że przeglądarka przekazała sygnał konwersji do warstwy tagowania (tylko raz dla wysłanych voucherów).
	 *
	 * @return bool Czy zapis się udał.
	 */
	public static function mark_converted( $id ) {
		global $wpdb;
		$table = self::table();

		return (bool) $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET conv_at = %s WHERE id = %d AND status = %s AND conv_at IS NULL",
			current_time( 'mysql' ),
			$id,
			self::STATUS_SENT
		) );
	}

	/**
	 * Podpis dowodzący, że identyfikator vouchera dostała przeglądarka, której go wydano.
	 */
	public static function conv_token( $id ) {
		return substr( hash_hmac( 'sha256', 'evsms-conv|' . (int) $id, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Statystyki voucherów: wysłane, zarejestrowany sygnał tagowania i z gclid.
	 */
	public static function conversion_stats() {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS sent, SUM(conv_at IS NOT NULL) AS confirmed, SUM(gclid <> '') AS with_gclid
			FROM {$table} WHERE status = %s",
			self::STATUS_SENT
		), ARRAY_A );

		return array(
			'sent'       => (int) ( $row['sent'] ?? 0 ),
			'confirmed'  => (int) ( $row['confirmed'] ?? 0 ),
			'with_gclid' => (int) ( $row['with_gclid'] ?? 0 ),
		);
	}

	/**
	 * Wysłane vouchery do eksportu CSV (od najstarszego).
	 */
	public static function sent_for_export() {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC",
			self::STATUS_SENT
		) );
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
	 * Liczba numerów z ostatnich $seconds sekund (do limitu globalnego); nieudane wysyłki nie liczą się,
	 * żeby awaria SMSAPI nie blokowała formularza ani nie wywoływała fałszywego alarmu o limicie.
	 */
	public static function count_since( $seconds ) {
		global $wpdb;
		$table = self::table();

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status <> %s",
			wp_date( 'Y-m-d H:i:s', time() - (int) $seconds ),
			self::STATUS_FAILED
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
