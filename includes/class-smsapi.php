<?php
/**
 * Minimalny klient SMSAPI.pl (REST: POST https://api.smsapi.pl/sms.do, OAuth Bearer).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Smsapi {

	const ENDPOINT        = 'https://api.smsapi.pl/sms.do';
	const ENDPOINT_BACKUP = 'https://api2.smsapi.pl/sms.do';

	/**
	 * Wysyła pojedynczego SMS-a.
	 *
	 * @param string $to      Numer w formacie 48XXXXXXXXX.
	 * @param string $message Treść.
	 * @return array{success:bool, sms_id?:string, error?:string}
	 */
	public static function send( $to, $message ) {
		$token = EVSMS_Settings::token();
		if ( '' === $token ) {
			return array( 'success' => false, 'error' => 'Brak tokenu SMSAPI w konfiguracji.' );
		}

		$body = array(
			'to'           => $to,
			'message'      => $message,
			'format'       => 'json',
			'encoding'     => 'utf-8',
			'skip_foreign' => 1,
		);

		// ą → a itd.: SMS mieści 160 znaków zamiast 70
		if ( EVSMS_Settings::get( 'normalize' ) ) {
			$body['normalize'] = 1;
		}

		$sender = EVSMS_Settings::get( 'sender' );
		if ( '' !== $sender ) {
			$body['from'] = $sender;
		}

		// Tryb testowy SMSAPI: żądanie jest walidowane, ale SMS nie jest wysyłany ani naliczany
		if ( EVSMS_Settings::get( 'test_mode' ) ) {
			$body['test'] = 1;
		}

		$args = array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'body'    => $body,
		);

		$response = wp_remote_post( self::ENDPOINT, $args );

		// Zapasowy endpoint tylko przy błędzie transportu / 5xx, żeby nie zdublować wysyłki
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 500 ) {
			$response = wp_remote_post( self::ENDPOINT_BACKUP, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => 'HTTP: ' . $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'error'   => 'Nieprawidłowa odpowiedź SMSAPI (HTTP ' . wp_remote_retrieve_response_code( $response ) . ').',
			);
		}

		if ( isset( $data['error'] ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'SMSAPI %s: %s', $data['error'], $data['message'] ?? '' ),
			);
		}

		if ( empty( $data['count'] ) || empty( $data['list'][0]['id'] ) ) {
			return array( 'success' => false, 'error' => 'SMSAPI nie przyjęło wiadomości.' );
		}

		return array( 'success' => true, 'sms_id' => (string) $data['list'][0]['id'] );
	}
}
