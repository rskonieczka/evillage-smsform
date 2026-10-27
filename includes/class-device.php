<?php
/**
 * Ochrona przed floodem z jednego urządzenia.
 *
 * Urządzenie = losowy identyfikator w podpisanym ciasteczku HttpOnly, wydawany przy pobraniu nonce.
 * Na nim opierają się: odstęp między próbami, limit prób na godzinę i limit kuponów w okresie.
 * Żeby czyszczenie ciasteczek nie obchodziło limitów, liczba nowych urządzeń z jednego IP jest ograniczona.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Device {

	const COOKIE = 'evsms_device';

	/** Identyfikator z bieżącego żądania (ustawiony też w tym samym żądaniu przez ensure()). */
	private static $current = null;

	/**
	 * Skrót IP (HMAC) – wspólny dla limitów IP i rejestru voucherów.
	 */
	public static function ip_hash() {
		return hash_hmac( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), wp_salt( 'nonce' ) );
	}

	/**
	 * Skrót urządzenia z ważnego ciasteczka ('' = brak lub podrobione).
	 */
	public static function current_hash() {
		$id = self::current_id();
		return '' === $id ? '' : hash_hmac( 'sha256', $id, wp_salt( 'nonce' ) );
	}

	/**
	 * Zapewnia identyfikator urządzenia (wywoływane przy pobraniu nonce).
	 *
	 * @return bool false, gdy z tego IP wydano już za dużo nowych urządzeń.
	 */
	public static function ensure() {
		if ( '' !== self::current_id() ) {
			return true;
		}

		$max = (int) EVSMS_Settings::get( 'device_new_per_ip' );
		if ( $max > 0 && ! self::hit( 'evsms_nd_' . substr( self::ip_hash(), 0, 32 ), $max ) ) {
			return false;
		}

		$id = bin2hex( random_bytes( 16 ) );
		setcookie( self::COOKIE, $id . '.' . self::sign( $id ), array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		self::$current = $id;

		return true;
	}

	/**
	 * Czy urządzenie wykorzystało już limit kuponów w okresie.
	 */
	public static function is_claimed( $device_hash ) {
		$days = (int) EVSMS_Settings::get( 'device_days' );
		$max  = (int) EVSMS_Settings::get( 'device_coupons' );
		if ( $days <= 0 || $max <= 0 ) {
			return false;
		}

		// created_at jest w czasie lokalnym (current_time('mysql')), więc granicę liczymy tak samo
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		return EVSMS_Vouchers::count_by_device( $device_hash, $since ) >= $max;
	}

	/**
	 * Sprawdza flood z urządzenia i zapisuje próbę.
	 *
	 * @return array|null null = można wysyłać; ['code' => 'cooldown', 'retry_after' => s] albo ['code' => 'device_limit'].
	 */
	public static function check_and_record( $device_hash ) {
		$key      = 'evsms_dv_' . substr( $device_hash, 0, 32 );
		$cooldown = (int) EVSMS_Settings::get( 'device_cooldown' );
		$max      = (int) EVSMS_Settings::get( 'device_attempts' );
		$now      = time();

		$state = get_transient( $key );
		if ( ! is_array( $state ) || $now - $state['start'] >= HOUR_IN_SECONDS ) {
			$state = array( 'count' => 0, 'start' => $now, 'last' => 0 );
		}

		if ( $cooldown > 0 && $state['last'] && $now - $state['last'] < $cooldown ) {
			return array( 'code' => 'cooldown', 'retry_after' => $cooldown - ( $now - $state['last'] ) );
		}
		if ( $max > 0 && $state['count'] >= $max ) {
			return array( 'code' => 'device_limit' );
		}

		$state['count']++;
		$state['last'] = $now;
		set_transient( $key, $state, HOUR_IN_SECONDS );

		return null;
	}

	/**
	 * Identyfikator z ciasteczka po weryfikacji podpisu.
	 */
	private static function current_id() {
		if ( null !== self::$current ) {
			return self::$current;
		}

		$raw   = (string) ( $_COOKIE[ self::COOKIE ] ?? '' );
		$parts = explode( '.', $raw );

		self::$current = ( 2 === count( $parts ) && preg_match( '/^[a-f0-9]{32}$/', $parts[0] ) && hash_equals( self::sign( $parts[0] ), $parts[1] ) )
			? $parts[0]
			: '';

		return self::$current;
	}

	private static function sign( $id ) {
		return substr( hash_hmac( 'sha256', $id, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Licznik w stałym oknie godzinowym; false po przekroczeniu $max.
	 */
	private static function hit( $key, $max ) {
		$state = get_transient( $key );
		if ( ! is_array( $state ) ) {
			$state = array( 'count' => 0, 'start' => time() );
		}
		if ( $state['count'] >= $max ) {
			return false;
		}

		$state['count']++;
		set_transient( $key, $state, max( 1, HOUR_IN_SECONDS - ( time() - $state['start'] ) ) );

		return true;
	}
}
