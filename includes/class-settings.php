<?php
/**
 * Ustawienia wtyczki (Ustawienia → SMS Kupon): konfiguracja, komunikaty i rejestr voucherów.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Settings {

	const OPTION = 'evsms_settings';
	const PAGE   = 'evsms-settings';

	/**
	 * Inicjalizacja.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'test_mode_notice' ) );
		add_filter( 'plugin_action_links_' . EVSMS_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Komunikaty widoczne dla klienta, edytowalne w panelu: klucz => [grupa, etykieta, domyślna treść, podpowiedź].
	 */
	public static function messages() {
		return array(
			'form_title'        => array( 'Formularz', 'Tytuł', 'Odbierz kupon rabatowy', '' ),
			'form_subtitle'     => array( 'Formularz', 'Podtytuł', 'Wpisz numer telefonu – kod wyślemy SMS-em w ciągu minuty.', '' ),
			'form_label'        => array( 'Formularz', 'Etykieta pola telefonu', 'Numer telefonu komórkowego', '' ),
			'form_button'       => array( 'Formularz', 'Treść przycisku', 'Wyślij kupon', '' ),
			'form_button_busy'  => array( 'Formularz', 'Przycisk podczas wysyłania', 'Wysyłanie…', '' ),
			'success_message'   => array( 'Po wysłaniu', 'Nagłówek', 'Gotowe! Kupon został wysłany SMS-em.', '' ),
			'success_meta'      => array( 'Po wysłaniu', 'Opis', 'Wysłaliśmy SMS na numer {phone}. Zwykle dociera w ciągu minuty.', '{phone} – zamaskowany numer, np. +48 600 *** 200' ),
			'err_phone_empty'   => array( 'Błędy', 'Brak numeru', 'Wpisz numer telefonu.', '' ),
			'err_phone_length'  => array( 'Błędy', 'Niepoprawny numer', 'Numer powinien mieć 9 cyfr, np. 600 100 200.', '' ),
			'err_phone_blocked' => array( 'Błędy', 'Zabroniony początek numeru', 'Numery zaczynające się od {prefix} nie są obsługiwane. Podaj numer komórkowy.', '{prefix} – dopasowany początek, np. 70' ),
			'err_consent'       => array( 'Błędy', 'Brak zgody', 'Zaznacz zgodę, abyśmy mogli wysłać SMS.', '' ),
			'err_duplicate'     => array( 'Błędy', 'Numer już otrzymał kupon', 'Ten numer otrzymał już kupon – sprawdź wcześniejsze SMS-y.', '' ),
			'err_sms_failed'    => array( 'Błędy', 'SMS nie został wysłany', 'Nie udało się wysłać SMS-a. Sprawdź numer i spróbuj ponownie.', '' ),
			'err_rate_limit'    => array( 'Błędy', 'Za dużo prób', 'Zbyt wiele prób. Spróbuj ponownie za godzinę.', '' ),
			'err_busy'          => array( 'Błędy', 'Wysyłka w toku', 'Trwa wysyłanie kuponu na ten numer. Spróbuj za chwilę.', '' ),
			'err_session'       => array( 'Błędy', 'Wygasła sesja', 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.', '' ),
			'err_generic'       => array( 'Błędy', 'Błąd ogólny', 'Wystąpił błąd. Spróbuj ponownie później.', '' ),
		);
	}

	/**
	 * Znane zakresy numerów, na które nie wysyłamy kuponu: klucz => [etykieta, początki numeru].
	 * Numery komórkowe (45, 50, 51, 53, 57, 60, 66, 69, 72, 73, 78, 79, 88) nie należą do żadnej grupy.
	 */
	public static function prefix_presets() {
		return array(
			'premium'  => array( 'Numery o podwyższonej opłacie – 70x (700, 701, 703, 704, 705, 707, 708…)', array( '70' ) ),
			'infoline' => array( 'Infolinie – 80x (800 bezpłatne, 801 wspólnych kosztów…)', array( '80' ) ),
			'voip'     => array( 'Numery VoIP / nomadyczne – 39x', array( '39' ) ),
			'landline' => array(
				'Numery stacjonarne – 49 stref (12, 22, 32, 42, 58, 61, 71…) – nie odbierają SMS-ów',
				array( '12', '13', '14', '15', '16', '17', '18', '22', '23', '24', '25', '29', '32', '33', '34', '41', '42', '43', '44', '46', '48', '52', '54', '55', '56', '58', '59', '61', '62', '63', '65', '67', '68', '71', '74', '75', '76', '77', '81', '82', '83', '84', '85', '86', '87', '89', '91', '94', '95' ),
			),
		);
	}

	/**
	 * Kolory i wygląd formularza: klucz => [etykieta, zmienna CSS, podpowiedź]. Puste = kolor z motywu.
	 */
	public static function colors() {
		return array(
			'color_accent'      => array( 'Przycisk i akcenty', '--evsms-accent', 'Tło przycisku, ramka aktywnego pola, ikona.' ),
			'color_button_text' => array( 'Tekst przycisku', '--evsms-on-accent', '' ),
			'color_text'        => array( 'Tekst', '--evsms-text', 'Tytuł, etykiety i opisy.' ),
			'color_bg'          => array( 'Tło karty', '--evsms-bg', 'Domyślnie delikatny odcień koloru akcentu.' ),
			'color_input_bg'    => array( 'Tło pola telefonu', '--evsms-input-bg', '' ),
			'color_border'      => array( 'Ramki', '--evsms-border', '' ),
			'color_error'       => array( 'Błędy', '--evsms-error', '' ),
			'color_success'     => array( 'Sukces', '--evsms-success', 'Ikona potwierdzenia po wysłaniu.' ),
		);
	}

	/**
	 * Gotowe zestawy kolorów: klucz => [etykieta, kolory]. Pierwszy jest domyślny; puste wartości = kolory motywu.
	 */
	public static function color_schemes() {
		$light = array(
			'color_button_text' => '#ffffff',
			'color_text'        => '#1f2937',
			'color_input_bg'    => '#ffffff',
			'color_error'       => '#c62828',
			'color_success'     => '#1a7f37',
		);

		// Ciemne tła: jasny tekst, ciemny tekst na jasnym przycisku
		$dark = function ( $accent, $bg, $input_bg, $border, $text = '#f9fafb' ) {
			return array(
				'color_accent'      => $accent,
				'color_button_text' => $bg,
				'color_text'        => $text,
				'color_bg'          => $bg,
				'color_input_bg'    => $input_bg,
				'color_border'      => $border,
				'color_error'       => '#f87171',
				'color_success'     => '#22c55e',
			);
		};

		return array(
			// Jasne
			'blue'       => array( 'Niebieski', array_merge( $light, array( 'color_accent' => '#2563eb', 'color_bg' => '#f5f8ff', 'color_border' => '#d4dcef' ) ) ),
			'green'      => array( 'Zielony', array_merge( $light, array( 'color_accent' => '#15803d', 'color_bg' => '#f3faf5', 'color_border' => '#cfe3d6' ) ) ),
			'red'        => array( 'Promocja', array_merge( $light, array( 'color_accent' => '#dc2626', 'color_bg' => '#fff5f5', 'color_border' => '#f3d0d0' ) ) ),
			'orange'     => array( 'Pomarańczowy', array_merge( $light, array( 'color_accent' => '#c2410c', 'color_bg' => '#fff7f0', 'color_border' => '#f5d9c4' ) ) ),
			'violet'     => array( 'Fioletowy', array_merge( $light, array( 'color_accent' => '#7c3aed', 'color_bg' => '#f8f5ff', 'color_border' => '#e0d4fb' ) ) ),
			'teal'       => array( 'Morski', array_merge( $light, array( 'color_accent' => '#0f766e', 'color_bg' => '#f0faf9', 'color_border' => '#c5e6e2' ) ) ),
			'pink'       => array( 'Róż / beauty', array_merge( $light, array( 'color_accent' => '#be185d', 'color_bg' => '#fff5f9', 'color_border' => '#f5cfe0', 'color_text' => '#3b1a2a' ) ) ),
			'olive'      => array( 'Natura / eko', array_merge( $light, array( 'color_accent' => '#4d7c0f', 'color_bg' => '#f7fbef', 'color_border' => '#dbe8c3', 'color_text' => '#1f2a15' ) ) ),
			'terracotta' => array( 'Terakota (ciepły)', array_merge( $light, array( 'color_accent' => '#b4532a', 'color_bg' => '#fdf6ec', 'color_border' => '#efdcc4', 'color_text' => '#3d2b1f', 'color_input_bg' => '#fffdf9' ) ) ),
			'mono'       => array( 'Minimalistyczny', array_merge( $light, array( 'color_accent' => '#111827', 'color_bg' => '#ffffff', 'color_border' => '#e5e7eb', 'color_text' => '#111827' ) ) ),
			// Ciemne
			'dark'       => array( 'Ciemny', $dark( '#facc15', '#111827', '#1f2937', '#374151' ) ),
			'gold'       => array( 'Czarno-złoty', $dark( '#d4a24c', '#1c1917', '#292524', '#44403c', '#fafaf9' ) ),
			'navy'       => array( 'Granatowy nocny', $dark( '#38bdf8', '#0f1e3d', '#172a52', '#2a3f6b', '#e8eefc' ) ),
			'neon'       => array( 'Neon', $dark( '#a3e635', '#0b0b14', '#18181b', '#27272a', '#f4f4f5' ) ),
			// Bez własnych kolorów
			'theme'      => array( 'Kolory motywu', array_fill_keys( array_keys( self::colors() ), '' ) ),
		);
	}

	/**
	 * Wbudowane ikony nagłówka (SVG 24×24, obrys w kolorze akcentu): klucz => [etykieta, ścieżki SVG].
	 */
	public static function icons() {
		return array(
			'ticket'  => array( 'Kupon', '<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M9 9.5h.01M15 14.5h.01M15.5 9 8.5 15"/>' ),
			'percent' => array( 'Procent', '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>' ),
			'tag'     => array( 'Metka', '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r="1.5"/>' ),
			'gift'    => array( 'Prezent', '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>' ),
			'sms'     => array( 'Wiadomość', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/>' ),
			'phone'   => array( 'Telefon', '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>' ),
			'bag'     => array( 'Zakupy', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>' ),
			'star'    => array( 'Gwiazdka', '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2-6.2 3.2L7 14.2 2 9.3l6.9-1z"/>' ),
			'heart'   => array( 'Serce', '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>' ),
			'bolt'    => array( 'Błyskawica', '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>' ),
		);
	}

	/**
	 * HTML ikony nagłówka formularza ('' = bez ikony).
	 *
	 * @param string|null $icon Klucz ikony, 'custom' lub 'none'; null = z ustawień.
	 */
	public static function icon_html( $icon = null ) {
		$icon  = null === $icon ? (string) self::get( 'icon' ) : $icon;
		$icons = self::icons();

		if ( 'custom' === $icon ) {
			$url = (string) self::get( 'icon_url' );
			return '' !== $url ? '<img src="' . esc_url( $url ) . '" alt="" width="26" height="26">' : '';
		}
		if ( ! isset( $icons[ $icon ] ) ) {
			return '';
		}

		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icons[ $icon ][1] . '</svg>';
	}

	/**
	 * Deklaracje zmiennych CSS z ustawień wyglądu (do .evsms{...}).
	 */
	public static function css_vars() {
		$css = '';
		foreach ( self::colors() as $key => $def ) {
			$value = sanitize_hex_color( (string) self::get( $key ) );
			if ( $value ) {
				$css .= $def[1] . ':' . $value . ';';
			}
		}

		foreach ( self::radii() as $key => $def ) {
			$value = self::get( $key );
			if ( '' !== (string) $value ) {
				$css .= $def[1] . ':' . absint( $value ) . 'px;';
			}
		}

		return $css;
	}

	/**
	 * Zaokrąglenia: klucz => [etykieta, zmienna CSS, wartość domyślna w px].
	 */
	public static function radii() {
		return array(
			'radius_card' => array( 'Zaokrąglenie ramki', '--evsms-card-radius', 16 ),
			'radius'      => array( 'Zaokrąglenie pól i przycisku', '--evsms-radius', 12 ),
		);
	}

	/**
	 * Wartości domyślne.
	 */
	public static function defaults() {
		$defaults = array(
			'token'            => '',
			'sender'           => '',
			'test_mode'        => 1,
			'normalize'        => 1,
			'message'          => '{site}: Twoj kod rabatowy to {code}. Voucher nr {voucher}. Podaj kod przy zamowieniu.',
			'code_mode'        => 'unique',
			'static_code'      => '',
			'code_prefix'      => 'RABAT-',
			'voucher_prefix'   => 'EV-',
			'notify_email'     => get_option( 'admin_email' ),
			'email_subject'    => '[{site}] Pobrano voucher nr {voucher}',
			'one_per_phone'    => 1,
			'blocked_presets'  => array_keys( self::prefix_presets() ),
			'blocked_prefixes' => '',
			'max_per_ip_hour'  => 3,
			'consent_text'     => 'Wyrażam zgodę na przetwarzanie mojego numeru telefonu w celu wysłania kuponu rabatowego.',
			'radius'           => '12',
			'radius_card'      => '16',
			'icon'             => 'ticket',
			'icon_url'         => '',
		);

		foreach ( self::messages() as $key => $def ) {
			$defaults[ $key ] = $def[2];
		}

		// Kolory domyślne = pierwszy zestaw (Niebieski)
		$schemes  = self::color_schemes();
		$defaults = array_merge( $defaults, reset( $schemes )[1] );

		return $defaults;
	}

	/**
	 * Pobiera pojedyncze ustawienie.
	 */
	public static function get( $key ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return $settings[ $key ] ?? null;
	}

	/**
	 * Komunikat z podstawionymi znacznikami, np. msg( 'err_phone_blocked', array( '{prefix}' => '70' ) ).
	 */
	public static function msg( $key, $vars = array() ) {
		return strtr( (string) self::get( $key ), $vars );
	}

	/**
	 * Token SMSAPI; stała EVSMS_SMSAPI_TOKEN w wp-config.php ma pierwszeństwo.
	 */
	public static function token() {
		if ( defined( 'EVSMS_SMSAPI_TOKEN' ) && EVSMS_SMSAPI_TOKEN ) {
			return (string) EVSMS_SMSAPI_TOKEN;
		}
		return (string) self::get( 'token' );
	}

	/**
	 * Zabronione początki numeru krajowego (bez 48): zaznaczone znane zakresy + własne, np. ['70', '80', '703'].
	 */
	public static function blocked_prefixes() {
		$prefixes = array();
		$presets  = self::prefix_presets();

		foreach ( (array) self::get( 'blocked_presets' ) as $key ) {
			if ( isset( $presets[ $key ] ) ) {
				$prefixes = array_merge( $prefixes, $presets[ $key ][1] );
			}
		}

		$prefixes = array_merge( $prefixes, self::parse_prefixes( (string) self::get( 'blocked_prefixes' ) ) );

		return array_values( array_unique( $prefixes ) );
	}

	private static function parse_prefixes( $value ) {
		$prefixes = preg_split( '/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_filter( $prefixes, function ( $p ) {
			return (bool) preg_match( '/^\d{1,8}$/', $p );
		} ) ) );
	}

	/**
	 * Dodaje stronę menu.
	 */
	public static function add_menu_page() {
		add_options_page(
			'EVILLAGE SMS Kupon',
			'SMS Kupon',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Link "Ustawienia" na liście wtyczek.
	 */
	public static function add_settings_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Ustawienia</a>' );
		return $links;
	}

	/**
	 * Zasoby panelu – tylko na stronie ustawień wtyczki.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_media(); // wybór własnej ikony z biblioteki mediów
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'evsms-form' );
		wp_enqueue_style( 'evsms-admin', EVSMS_PLUGIN_URL . 'assets/admin.css', array( 'evsms-form' ), EVSMS_VERSION );
		wp_enqueue_script( 'evsms-admin', EVSMS_PLUGIN_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), EVSMS_VERSION, true );

		$icons = array();
		foreach ( array_keys( self::icons() ) as $key ) {
			$icons[ $key ] = self::icon_html( $key );
		}

		wp_localize_script( 'evsms-admin', 'evsmsAdmin', array(
			'site'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'icons'   => $icons,
			'schemes' => wp_list_pluck( self::color_schemes(), 1 ),
			'palette' => array_values( array_unique( array_filter( wp_list_pluck( wp_list_pluck( self::color_schemes(), 1 ), 'color_accent' ) ) ) ),
		) );
	}

	/**
	 * Ostrzeżenie o trybie testowym (strona ustawień i lista wtyczek).
	 */
	public static function test_mode_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'settings_page_' . self::PAGE, 'plugins' ), true ) ) {
			return;
		}
		if ( ! self::get( 'test_mode' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>SMS Kupon działa w trybie testowym</strong> – SMS-y nie są realnie wysyłane. <a href="%s">Wyłącz tryb testowy</a>, gdy konfiguracja jest gotowa.</p></div>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) )
		);
	}

	/**
	 * Rejestruje ustawienia.
	 */
	public static function register_settings() {
		register_setting(
			'evsms_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanityzacja ustawień.
	 */
	public static function sanitize_settings( $input ) {
		$input    = (array) $input;
		$current  = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$defaults = self::defaults();
		$out      = array();

		// Puste pole tokenu = zostaw dotychczasowy (token nie jest wyświetlany w formularzu)
		$token        = trim( (string) ( $input['token'] ?? '' ) );
		$out['token'] = '' !== $token ? sanitize_text_field( $token ) : $current['token'];

		$out['sender']           = substr( sanitize_text_field( $input['sender'] ?? '' ), 0, 11 );
		$out['test_mode']        = empty( $input['test_mode'] ) ? 0 : 1;
		$out['normalize']        = empty( $input['normalize'] ) ? 0 : 1;
		$out['message']          = sanitize_textarea_field( $input['message'] ?? '' );
		$out['code_mode']        = ( 'static' === ( $input['code_mode'] ?? '' ) ) ? 'static' : 'unique';
		$out['static_code']      = sanitize_text_field( $input['static_code'] ?? '' );
		$out['code_prefix']      = sanitize_text_field( $input['code_prefix'] ?? '' );
		$out['voucher_prefix']   = sanitize_text_field( $input['voucher_prefix'] ?? '' );
		$out['email_subject']    = sanitize_text_field( $input['email_subject'] ?? '' );
		$out['one_per_phone']    = empty( $input['one_per_phone'] ) ? 0 : 1;
		$out['blocked_presets']  = array_values( array_intersect( array_keys( self::prefix_presets() ), (array) ( $input['blocked_presets'] ?? array() ) ) );
		$out['blocked_prefixes'] = implode( ', ', self::parse_prefixes( (string) ( $input['blocked_prefixes'] ?? '' ) ) );

		foreach ( array_keys( self::colors() ) as $key ) {
			$out[ $key ] = (string) sanitize_hex_color( $input[ $key ] ?? '' );
		}
		foreach ( array_keys( self::radii() ) as $key ) {
			$radius      = trim( (string) ( $input[ $key ] ?? '' ) );
			$out[ $key ] = '' === $radius ? '' : (string) min( 40, absint( $radius ) );
		}

		$icon            = (string) ( $input['icon'] ?? '' );
		$out['icon_url'] = esc_url_raw( (string) ( $input['icon_url'] ?? '' ) );
		$out['icon']     = isset( self::icons()[ $icon ] ) || in_array( $icon, array( 'none', 'custom' ), true ) ? $icon : $defaults['icon'];
		if ( 'custom' === $out['icon'] && '' === $out['icon_url'] ) {
			add_settings_error( self::OPTION, 'evsms_icon_url', 'Wybrano własną ikonę, ale nie wskazano obrazka – przywrócono ikonę „Kupon”.', 'warning' );
			$out['icon'] = $defaults['icon'];
		}
		$out['max_per_ip_hour']  = max( 0, absint( $input['max_per_ip_hour'] ?? 0 ) );
		$out['consent_text']     = wp_kses_post( $input['consent_text'] ?? '' );

		// Dopuszczamy kilka adresów rozdzielonych przecinkami
		$emails              = array_filter( array_map( 'sanitize_email', explode( ',', (string) ( $input['notify_email'] ?? '' ) ) ), 'is_email' );
		$out['notify_email'] = implode( ', ', $emails );

		// Puste komunikaty wracają do treści domyślnej, żeby klient nigdy nie zobaczył pustego błędu
		foreach ( array_keys( self::messages() ) as $key ) {
			$value       = sanitize_text_field( $input[ $key ] ?? '' );
			$out[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		foreach ( array( 'message', 'email_subject' ) as $key ) {
			if ( '' === $out[ $key ] ) {
				$out[ $key ] = $defaults[ $key ];
			}
		}
		if ( 'static' === $out['code_mode'] && '' === $out['static_code'] ) {
			add_settings_error( self::OPTION, 'evsms_static_code', 'Tryb „stały kod” wymaga podania kodu kuponu.' );
		}

		return $out;
	}

	/**
	 * Renderuje stronę ustawień.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s    = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$name = self::OPTION;
		$tabs = array(
			'sms'      => 'SMS',
			'coupon'   => 'Kupon i ochrona',
			'messages' => 'Komunikaty',
			'look'     => 'Wygląd',
			'notify'   => 'Powiadomienia',
			'vouchers' => 'Vouchery',
		);
		?>
		<div class="wrap evsms-admin">
			<h1>EVILLAGE SMS Kupon</h1>
			<p>Formularz wstawisz blokiem <strong>„SMS Kupon”</strong> w edytorze albo shortcodem <code>[evillage_smsform]</code>
				(atrybuty: <code>title</code>, <code>subtitle</code>, <code>button</code>, <code>color="#e11d48"</code>).</p>

			<nav class="nav-tab-wrapper evsms-tabs" role="tablist">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="#evsms-tab-<?php echo esc_attr( $id ); ?>" class="nav-tab" role="tab" data-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'evsms_settings_group' ); ?>

				<section class="evsms-panel" id="evsms-tab-sms" data-panel="sms">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="evsms_token">Token OAuth SMSAPI</label></th>
							<td>
								<?php if ( defined( 'EVSMS_SMSAPI_TOKEN' ) && EVSMS_SMSAPI_TOKEN ) : ?>
									<p>Token zdefiniowany stałą <code>EVSMS_SMSAPI_TOKEN</code> w wp-config.php.</p>
								<?php else : ?>
									<input type="password" id="evsms_token" name="<?php echo esc_attr( $name ); ?>[token]" value="" class="regular-text" autocomplete="new-password"
										placeholder="<?php echo $s['token'] ? esc_attr( '•••••• zapisany (zostaw puste, by nie zmieniać)' ) : ''; ?>">
									<p class="description">Panel SMSAPI → Ustawienia API → Tokeny API (uprawnienie SMS).</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_sender">Pole nadawcy</label></th>
							<td>
								<input type="text" id="evsms_sender" name="<?php echo esc_attr( $name ); ?>[sender]" value="<?php echo esc_attr( $s['sender'] ); ?>" maxlength="11" class="regular-text">
								<p class="description">Nazwa zarejestrowana w SMSAPI (max 11 znaków). Puste = domyślne pole nadawcy konta.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Tryb testowy</th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[test_mode]" value="1" <?php checked( $s['test_mode'], 1 ); ?>>
									Nie wysyłaj realnie SMS-ów (<code>test=1</code> – SMSAPI waliduje żądanie, nie pobiera punktów)</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_message">Treść SMS</label></th>
							<td>
								<textarea id="evsms_message" name="<?php echo esc_attr( $name ); ?>[message]" rows="3" class="large-text"><?php echo esc_textarea( $s['message'] ); ?></textarea>
								<p class="description">Znaczniki: <code>{code}</code> kod kuponu, <code>{voucher}</code> numer vouchera, <code>{site}</code> nazwa strony.</p>
								<div class="evsms-sms-preview" aria-live="polite">
									<div class="evsms-sms-bubble" id="evsms-sms-bubble"></div>
									<p class="evsms-sms-stats" id="evsms-sms-stats"></p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">Polskie znaki</th>
							<td>
								<label><input type="checkbox" id="evsms_normalize" name="<?php echo esc_attr( $name ); ?>[normalize]" value="1" <?php checked( $s['normalize'], 1 ); ?>>
									Zamieniaj na znaki bez ogonków (<code>normalize=1</code>) – SMS mieści 160 zamiast 70 znaków</label>
							</td>
						</tr>
					</table>
				</section>

				<section class="evsms-panel" id="evsms-tab-coupon" data-panel="coupon">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Kod kuponu</th>
							<td>
								<fieldset>
									<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[code_mode]" value="unique" <?php checked( $s['code_mode'], 'unique' ); ?>> Unikalny, generowany dla każdego numeru</label><br>
									<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[code_mode]" value="static" <?php checked( $s['code_mode'], 'static' ); ?>> Stały kod dla wszystkich</label>
								</fieldset>
							</td>
						</tr>
						<tr data-code-mode="unique">
							<th scope="row"><label for="evsms_code_prefix">Prefiks kodu unikalnego</label></th>
							<td>
								<input type="text" id="evsms_code_prefix" name="<?php echo esc_attr( $name ); ?>[code_prefix]" value="<?php echo esc_attr( $s['code_prefix'] ); ?>" class="regular-text">
								<p class="description">Kod = prefiks + 8 znaków bez mylących 0/O i 1/I, np. <code><?php echo esc_html( $s['code_prefix'] ); ?>K7PX2MQA</code>.</p>
							</td>
						</tr>
						<tr data-code-mode="static">
							<th scope="row"><label for="evsms_static_code">Stały kod</label></th>
							<td><input type="text" id="evsms_static_code" name="<?php echo esc_attr( $name ); ?>[static_code]" value="<?php echo esc_attr( $s['static_code'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_voucher_prefix">Prefiks numeru vouchera</label></th>
							<td>
								<input type="text" id="evsms_voucher_prefix" name="<?php echo esc_attr( $name ); ?>[voucher_prefix]" value="<?php echo esc_attr( $s['voucher_prefix'] ); ?>" class="regular-text">
								<p class="description">Numer = prefiks + kolejny numer z bazy, np. <code><?php echo esc_html( $s['voucher_prefix'] ); ?>000123</code>.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Jeden kupon na numer</th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[one_per_phone]" value="1" <?php checked( $s['one_per_phone'], 1 ); ?>> Blokuj ponowne pobranie na ten sam numer telefonu</label></td>
						</tr>
						<tr>
							<th scope="row">Zabronione numery</th>
							<td>
								<fieldset>
									<?php foreach ( self::prefix_presets() as $key => $preset ) : ?>
										<label class="evsms-preset">
											<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[blocked_presets][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['blocked_presets'], true ) ); ?>>
											<?php echo esc_html( $preset[0] ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">Numery komórkowe (45, 50, 51, 53, 57, 60, 66, 69, 72, 73, 78, 79, 88) nie są blokowane przez żadną z grup.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_blocked">Własne zabronione początki</label></th>
							<td>
								<input type="text" id="evsms_blocked" name="<?php echo esc_attr( $name ); ?>[blocked_prefixes]" value="<?php echo esc_attr( $s['blocked_prefixes'] ); ?>" class="regular-text" placeholder="np. 703, 601234">
								<p class="description">Początki 9-cyfrowego numeru (bez +48), rozdzielone przecinkami – dodawane do zaznaczonych grup.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_max_ip">Limit prób z IP / godz.</label></th>
							<td>
								<input type="number" min="0" id="evsms_max_ip" name="<?php echo esc_attr( $name ); ?>[max_per_ip_hour]" value="<?php echo esc_attr( $s['max_per_ip_hour'] ); ?>" class="small-text">
								<p class="description">0 = bez limitu (niezalecane – każdy SMS kosztuje).</p>
							</td>
						</tr>
					</table>
				</section>

				<section class="evsms-panel" id="evsms-tab-messages" data-panel="messages">
					<p class="description">Teksty widoczne dla klienta. Puste pole przywraca treść domyślną (pokazaną w podpowiedzi).</p>
					<?php
					$group = '';
					foreach ( self::messages() as $key => $def ) :
						if ( $group !== $def[0] ) :
							if ( '' !== $group ) {
								echo '</table>';
							}
							$group = $def[0];
							?>
							<h2 class="evsms-group-title"><?php echo esc_html( $group ); ?></h2>
							<table class="form-table" role="presentation">
							<?php if ( 'Formularz' === $group ) : ?>
								<tr>
									<th scope="row"><label for="evsms_consent">Treść zgody</label></th>
									<td>
										<textarea id="evsms_consent" name="<?php echo esc_attr( $name ); ?>[consent_text]" rows="2" class="large-text"><?php echo esc_textarea( $s['consent_text'] ); ?></textarea>
										<p class="description">Puste = formularz bez checkboxa zgody. Dozwolone linki HTML (np. do polityki prywatności).</p>
									</td>
								</tr>
							<?php endif; ?>
						<?php endif; ?>
						<tr>
							<th scope="row"><label for="evsms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[1] ); ?></label></th>
							<td>
								<input type="text" id="evsms_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>"
									value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( $def[2] ); ?>" class="large-text">
								<?php if ( '' !== $def[3] ) : ?>
									<p class="description"><?php echo esc_html( $def[3] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</table>
				</section>

				<section class="evsms-panel" id="evsms-tab-look" data-panel="look">
					<div class="evsms-look">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">Gotowe zestawy</th>
								<td>
									<div class="evsms-schemes">
										<?php foreach ( self::color_schemes() as $key => $scheme ) : ?>
											<button type="button" class="button evsms-scheme" data-scheme="<?php echo esc_attr( $key ); ?>">
												<span class="evsms-scheme-swatch" aria-hidden="true"
													style="<?php echo esc_attr( 'background:' . ( $scheme[1]['color_bg'] ?: '#fff' ) . ';border-color:' . ( $scheme[1]['color_border'] ?: '#c3c4c7' ) ); ?>">
													<span style="<?php echo esc_attr( 'background:' . ( $scheme[1]['color_accent'] ?: 'repeating-linear-gradient(45deg,#c3c4c7 0 3px,#fff 3px 6px)' ) ); ?>"></span>
												</span>
												<?php echo esc_html( $scheme[0] ); ?>
											</button>
										<?php endforeach; ?>
									</div>
									<p class="description">Zestaw wypełnia pola poniżej – każdy kolor możesz potem zmienić. Zmiany zapisz przyciskiem „Zapisz zmiany”.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Ikona</th>
								<td>
									<fieldset class="evsms-icons">
										<legend class="screen-reader-text">Ikona nagłówka formularza</legend>
										<?php foreach ( self::icons() as $key => $icon ) : ?>
											<label class="evsms-icon-choice" title="<?php echo esc_attr( $icon[0] ); ?>">
												<input type="radio" name="<?php echo esc_attr( $name ); ?>[icon]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['icon'], $key ); ?>>
												<span class="evsms-icon-tile"><?php echo self::icon_html( $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- statyczny SVG ?></span>
												<span class="evsms-icon-name"><?php echo esc_html( $icon[0] ); ?></span>
											</label>
										<?php endforeach; ?>
										<label class="evsms-icon-choice" title="Własny obrazek">
											<input type="radio" name="<?php echo esc_attr( $name ); ?>[icon]" value="custom" <?php checked( $s['icon'], 'custom' ); ?>>
											<span class="evsms-icon-tile evsms-icon-tile--custom">
												<?php if ( $s['icon_url'] ) : ?>
													<img src="<?php echo esc_url( $s['icon_url'] ); ?>" alt="" width="22" height="22">
												<?php else : ?>
													<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
												<?php endif; ?>
											</span>
											<span class="evsms-icon-name">Własna</span>
										</label>
										<label class="evsms-icon-choice" title="Bez ikony">
											<input type="radio" name="<?php echo esc_attr( $name ); ?>[icon]" value="none" <?php checked( $s['icon'], 'none' ); ?>>
											<span class="evsms-icon-tile"><span class="dashicons dashicons-hidden" aria-hidden="true"></span></span>
											<span class="evsms-icon-name">Brak</span>
										</label>
									</fieldset>
									<div class="evsms-icon-custom" data-icon-custom>
										<input type="url" id="evsms_icon_url" name="<?php echo esc_attr( $name ); ?>[icon_url]" value="<?php echo esc_attr( $s['icon_url'] ); ?>" class="regular-text" placeholder="https://…/ikona.svg">
										<button type="button" class="button" id="evsms-icon-pick">Wybierz z biblioteki</button>
										<p class="description">Najlepiej kwadratowy SVG lub PNG (min. 52×52 px). Wyświetlany w kółku o rozmiarze 44 px.</p>
									</div>
								</td>
							</tr>
							<?php foreach ( self::colors() as $key => $def ) : ?>
								<tr>
									<th scope="row"><label for="evsms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
									<td>
										<input type="text" id="evsms_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>"
											value="<?php echo esc_attr( $s[ $key ] ); ?>" class="evsms-color" data-var="<?php echo esc_attr( $def[1] ); ?>">
										<?php if ( '' !== $def[2] ) : ?>
											<p class="description"><?php echo esc_html( $def[2] ); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php foreach ( self::radii() as $key => $def ) : ?>
								<?php $value = '' !== (string) $s[ $key ] ? (int) $s[ $key ] : $def[2]; ?>
								<tr>
									<th scope="row"><label for="evsms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
									<td>
										<div class="evsms-range">
											<input type="range" min="0" max="40" step="1" id="evsms_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>"
												value="<?php echo esc_attr( $value ); ?>" class="evsms-radius" data-var="<?php echo esc_attr( $def[1] ); ?>">
											<output for="evsms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?> px</output>
										</div>
										<?php if ( 'radius_card' === $key ) : ?>
											<p class="description">0 = ostre rogi ramki formularza.</p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>

						<div class="evsms-look-preview">
							<p class="evsms-look-label">Podgląd na żywo</p>
							<?php echo EVSMS_Form::markup( array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapowane w markup() ?>
							<p class="description">Puste pole koloru = kolor z motywu (Blocksy). Na stronie formularz dziedziczy krój pisma motywu.</p>
						</div>
					</div>
				</section>

				<section class="evsms-panel" id="evsms-tab-notify" data-panel="notify">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="evsms_notify_email">E-mail powiadomień</label></th>
							<td>
								<input type="text" id="evsms_notify_email" name="<?php echo esc_attr( $name ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text">
								<p class="description">Po wysłaniu kuponu trafi tu informacja o numerze vouchera. Kilka adresów rozdziel przecinkami.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="evsms_email_subject">Temat e-maila</label></th>
							<td>
								<input type="text" id="evsms_email_subject" name="<?php echo esc_attr( $name ); ?>[email_subject]" value="<?php echo esc_attr( $s['email_subject'] ); ?>" class="large-text">
								<p class="description">Znaczniki: <code>{voucher}</code>, <code>{code}</code>, <code>{site}</code>.</p>
							</td>
						</tr>
					</table>
				</section>

				<div class="evsms-submit-row"><?php submit_button(); ?></div>
			</form>

			<section class="evsms-panel" id="evsms-tab-vouchers" data-panel="vouchers">
				<?php self::render_vouchers_table(); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Podsumowanie i tabela ostatnich voucherów.
	 */
	private static function render_vouchers_table() {
		$counts = EVSMS_Vouchers::count_by_status();
		$labels = array(
			EVSMS_Vouchers::STATUS_SENT    => 'Wysłane',
			EVSMS_Vouchers::STATUS_FAILED  => 'Błąd',
			EVSMS_Vouchers::STATUS_PENDING => 'W toku',
		);
		?>
		<ul class="evsms-stats">
			<?php foreach ( $labels as $status => $label ) : ?>
				<li class="evsms-stat evsms-stat--<?php echo esc_attr( $status ); ?>">
					<strong><?php echo esc_html( number_format_i18n( $counts[ $status ] ?? 0 ) ); ?></strong>
					<span><?php echo esc_html( $label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		$rows = EVSMS_Vouchers::latest( 50 );
		if ( ! $rows ) {
			echo '<p>Brak voucherów – pojawią się tu po pierwszym wysłaniu formularza.</p>';
			return;
		}
		?>
		<table class="widefat striped evsms-vouchers">
			<thead>
				<tr><th>Numer</th><th>Data</th><th>Telefon</th><th>Kod</th><th>Status</th><th>ID SMS / błąd</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( EVSMS_Vouchers::format_number( $row->id ) ); ?></strong></td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><a href="<?php echo esc_url( 'tel:+' . $row->phone ); ?>">+<?php echo esc_html( $row->phone ); ?></a></td>
						<td><code><?php echo esc_html( $row->code ); ?></code></td>
						<td><span class="evsms-badge evsms-badge--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $labels[ $row->status ] ?? $row->status ); ?></span></td>
						<td><?php echo esc_html( EVSMS_Vouchers::STATUS_FAILED === $row->status ? (string) $row->error : $row->sms_id ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
