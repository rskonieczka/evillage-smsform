<?php
/**
 * Odinstalowanie: usuwa ustawienia (z tokenem SMSAPI) i dane tymczasowe.
 * Tabela voucherów zostaje celowo – rejestr ma wartość księgową.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'evsms_settings' );
delete_option( 'evsms_db_version' );

// Liczniki limitów (IP: evsms_rl_*, urządzenia: evsms_dv_*, nowe urządzenia: evsms_nd_*) i flagi alertów
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '\_transient\_evsms\_%'
		OR option_name LIKE '\_transient\_timeout\_evsms\_%'"
);
