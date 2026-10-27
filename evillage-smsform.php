<?php
/**
 * Plugin Name: EVILLAGE.PL - SMS Form (kupon rabatowy)
 * Description: Formularz pobrania kuponu rabatowego wysyłanego SMS-em przez SMSAPI. Po wysłaniu kuponu wysyła e-mail z numerem vouchera (autonumer). Blok „SMS Kupon” lub shortcode [evillage_smsform]
 * Version: 1.2.1
 * Author: Rafał Skonieczka
 * Author URI: http://evillage.pl
 * Text Domain: evillage-smsform
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EVSMS_VERSION', '1.2.1' );
define( 'EVSMS_DB_VERSION', '1' );
define( 'EVSMS_PLUGIN_FILE', __FILE__ );
define( 'EVSMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVSMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVSMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader dla klas wtyczki (EVSMS_Foo_Bar => includes/class-foo-bar.php).
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'EVSMS_';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$class_name = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
	$file       = EVSMS_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Inicjalizacja wtyczki.
 */
function evsms_init() {
	// Aktualizacja schematu po wgraniu nowej wersji bez ponownej aktywacji
	if ( get_option( 'evsms_db_version' ) !== EVSMS_DB_VERSION ) {
		EVSMS_Vouchers::install();
	}

	EVSMS_Settings::init();
	EVSMS_Form::init();
}
add_action( 'plugins_loaded', 'evsms_init' );

/**
 * Aktywacja wtyczki: tabela voucherów + domyślne ustawienia.
 */
function evsms_activate() {
	EVSMS_Vouchers::install();

	if ( false === get_option( EVSMS_Settings::OPTION ) ) {
		add_option( EVSMS_Settings::OPTION, EVSMS_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'evsms_activate' );
