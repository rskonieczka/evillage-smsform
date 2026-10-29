<?php
/**
 * Formularz (shortcode [evillage_smsform] i blok „SMS Kupon”) + obsługa AJAX:
 * kupon SMS-em, potem e-mail z numerem vouchera.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Form {

	const NONCE_ACTION = 'evsms_request';

	/**
	 * Inicjalizacja.
	 */
	public static function init() {
		add_shortcode( 'evillage_smsform', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );

		add_action( 'wp_ajax_evsms_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_evsms_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_evsms_request', array( __CLASS__, 'ajax_request' ) );
		add_action( 'wp_ajax_nopriv_evsms_request', array( __CLASS__, 'ajax_request' ) );
		add_action( 'wp_ajax_evsms_conv', array( __CLASS__, 'ajax_conversion' ) );
		add_action( 'wp_ajax_nopriv_evsms_conv', array( __CLASS__, 'ajax_conversion' ) );
	}

	/**
	 * Rejestruje zasoby (front ładuje je tylko tam, gdzie jest formularz; styl służy też edytorowi i podglądowi w panelu).
	 */
	public static function register_assets() {
		wp_register_style( 'evsms-form', EVSMS_PLUGIN_URL . 'assets/smsform.css', array(), EVSMS_VERSION );

		// Kolory i zaokrąglenie ustawione w panelu
		$vars = EVSMS_Settings::css_vars();
		if ( '' !== $vars ) {
			wp_add_inline_style( 'evsms-form', '.evsms{' . $vars . '}' );
		}

		wp_register_script( 'evsms-form', EVSMS_PLUGIN_URL . 'assets/smsform.js', array(), EVSMS_VERSION, true );
		wp_localize_script( 'evsms-form', 'evsmsData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'blockedPrefixes' => EVSMS_Settings::blocked_prefixes(),
			'deviceDays' => (int) EVSMS_Settings::get( 'device_coupons' ) > 0 ? (int) EVSMS_Settings::get( 'device_days' ) : 0,
			'tracking' => self::tracking_config(),
			'gift' => array(
				'enabled' => (bool) EVSMS_Settings::get( 'gift_enabled' ),
				'shareText' => EVSMS_Settings::msg( 'gift_share_text' ),
				'copied' => EVSMS_Settings::msg( 'gift_copied' ),
			),
			'messages' => array(
				'phoneEmpty' => EVSMS_Settings::msg( 'err_phone_empty' ),
				'phoneLength' => EVSMS_Settings::msg( 'err_phone_length' ),
				'phoneBlocked' => EVSMS_Settings::msg( 'err_phone_blocked' ),
				'consent' => EVSMS_Settings::msg( 'err_consent' ),
				'deviceWait' => EVSMS_Settings::msg( 'err_device_wait' ),
				'generic' => EVSMS_Settings::msg( 'err_generic' ),
			),
		) );
	}

	/**
	 * Konfiguracja śledzenia konwersji dla skryptu (puste send_to = tylko dataLayer / GA4).
	 */
	private static function tracking_config() {
		return array(
			'sendTo' => (string) EVSMS_Settings::get( 'conv_send_to' ),
			'value' => (float) EVSMS_Settings::get( 'conv_value' ),
			'currency' => (string) EVSMS_Settings::get( 'conv_currency' ),
			'ga4Event' => (string) EVSMS_Settings::get( 'conv_ga4_event' ),
			'enhanced' => (bool) EVSMS_Settings::get( 'conv_enhanced' ),
		);
	}

	/**
	 * Blok „SMS Kupon” (renderowany po stronie serwera tym samym kodem co shortcode).
	 */
	public static function register_block() {
		wp_register_script(
			'evsms-block',
			EVSMS_PLUGIN_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			EVSMS_VERSION,
			true
		);

		register_block_type( 'evillage/smsform', array(
			'api_version' => 3,
			'title' => 'SMS Kupon',
			'category' => 'widgets',
			'editor_script' => 'evsms-block',
			'editor_style' => 'evsms-form',
			'render_callback' => array( __CLASS__, 'render_block' ),
			'supports' => array(
				'html' => false,
				'align' => array( 'left', 'center', 'right', 'wide', 'full' ),
				'spacing' => array( 'margin' => true ),
			),
			'attributes' => array(
				'textAlign' => array( 'type' => 'string', 'default' => '' ),
				'title' => array( 'type' => 'string', 'default' => '' ),
				'subtitle' => array( 'type' => 'string', 'default' => '' ),
				'button' => array( 'type' => 'string', 'default' => '' ),
				'color' => array( 'type' => 'string', 'default' => '' ),
			),
		) );
	}

	/**
	 * Blok: formularz w opakowaniu z klasami Gutenberga (wyrównanie bloku i tekstu, marginesy).
	 */
	public static function render_block( $atts ) {
		$align = sanitize_html_class( $atts['textAlign'] ?? '' );
		unset( $atts['textAlign'], $atts['align'], $atts['style'] );

		return sprintf(
			'<div %s>%s</div>',
			get_block_wrapper_attributes( $align ? array( 'class' => 'has-text-align-' . $align ) : array() ),
			self::render_shortcode( $atts )
		);
	}

	/**
	 * Shortcode / blok: ładuje zasoby i zwraca formularz.
	 */
	public static function render_shortcode( $atts ) {
		wp_enqueue_style( 'evsms-form' );
		wp_enqueue_script( 'evsms-form' );

		return self::markup( (array) $atts );
	}

	/**
	 * HTML formularza. Puste atrybuty = teksty z panelu (zakładka Komunikaty).
	 * $preview: podgląd w panelu – <div> zamiast <form>, bo stoi wewnątrz formularza ustawień.
	 */
	public static function markup( $atts = array(), $preview = false ) {
		$tag = $preview ? 'div' : 'form';

		// Podgląd: pola bez name/required – inaczej walidacja HTML5 blokuje zapis ustawień, a dane trafiałyby do options.php
		$field = function ( $name ) use ( $preview ) {
			return $preview ? '' : ' name="' . $name . '" required';
		};

		$atts = array_merge(
			array( 'title' => '', 'subtitle' => '', 'button' => '', 'color' => '' ),
			array_filter( array_map( 'strval', $atts ), 'strlen' )
		);

		$title = '' !== $atts['title'] ? $atts['title'] : EVSMS_Settings::msg( 'form_title' );
		$subtitle = '' !== $atts['subtitle'] ? $atts['subtitle'] : EVSMS_Settings::msg( 'form_subtitle' );
		$button = '' !== $atts['button'] ? $atts['button'] : EVSMS_Settings::msg( 'form_button' );
		$consent = (string) EVSMS_Settings::get( 'consent_text' );
		$uid = wp_unique_id( 'evsms-' );
		$color = sanitize_hex_color( $atts['color'] );
		$icon = EVSMS_Settings::icon_html();

		ob_start();
		?>
		<div class="evsms" <?php echo $color ? ' style="' . esc_attr( '--evsms-accent:' . $color ) . '"' : ''; ?><?php echo EVSMS_Settings::get( 'share_url' ) ? ' data-share-url="' . esc_url( EVSMS_Settings::get( 'share_url' ) ) . '"' : ''; ?>>
			<<?php echo $tag; ?> class="evsms-form"<?php echo $preview ? '' : ' novalidate'; ?>
				aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"> <div class="evsms-head">
					<?php // W podglądzie span zostaje zawsze, żeby panel mógł podmieniać ikonę na żywo ?>
					<?php if ( '' !== $icon || $preview ) : ?>
						<span class="evsms-icon" aria-hidden="true" <?php echo '' === $icon ? ' hidden' : ''; ?>><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput -- statyczny SVG / esc_url ?></span>
					<?php endif; ?>
					<div>
						<p class="evsms-title" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $title ); ?></p>
						<?php if ( '' !== $subtitle ) : ?>
							<p class="evsms-subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="evsms-field" data-field="phone">
					<label class="evsms-label"
						for="<?php echo esc_attr( $uid ); ?>-phone"><?php echo esc_html( EVSMS_Settings::msg( 'form_label' ) ); ?></label>
					<div class="evsms-row">
						<div class="evsms-input">
							<span class="evsms-prefix" aria-hidden="true">+48</span>
							<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone" <?php echo $field( 'phone' ); ?>
								class="evsms-phone" inputmode="numeric" autocomplete="tel-national" placeholder="600 100 200"
								maxlength="20" aria-describedby="<?php echo esc_attr( $uid ); ?>-phone-error">
						</div>
						<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="evsms-submit"
							data-busy="<?php echo esc_attr( EVSMS_Settings::msg( 'form_button_busy' ) ); ?>">
							<span class="evsms-submit-label"><?php echo esc_html( $button ); ?></span>
							<span class="evsms-spinner" aria-hidden="true"></span>
						</button>
					</div>
					<p class="evsms-error" id="<?php echo esc_attr( $uid ); ?>-phone-error"></p>
				</div>
				<?php if ( '' !== $consent ) : ?>
					<div class="evsms-field" data-field="consent">
						<label class="evsms-consent">
							<input type="checkbox" <?php echo $field( 'consent' ); ?> value="1"
								aria-describedby="<?php echo esc_attr( $uid ); ?>-consent-error">
							<span><?php echo wp_kses_post( $consent ); ?></span>
						</label>
						<p class="evsms-error" id="<?php echo esc_attr( $uid ); ?>-consent-error"></p>
					</div>
				<?php endif; ?>
				<?php if ( ! $preview ) : ?>
					<!-- Honeypot: pole niewidoczne dla ludzi -->
					<div class="evsms-hp" aria-hidden="true">
						<label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
					</div>
				<?php endif; ?>
				<p class="evsms-msg" role="alert"></p>
			</<?php echo $tag; ?>> <div class="evsms-success" tabindex="-1" hidden>
				<span class="evsms-success-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5"
						stroke-linecap="round" stroke-linejoin="round">
						<path d="M20 6 9 17l-5-5" />
					</svg>
				</span>
				<p class="evsms-success-title"></p>
				<p class="evsms-success-meta"></p>
			</div>
			<?php if ( ! $preview ) : ?>
				<div class="evsms-claimed" tabindex="-1" hidden>
					<span class="evsms-claimed-icon"
						aria-hidden="true"><?php echo EVSMS_Settings::icon_html( 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statyczny SVG ?></span>
					<p class="evsms-claimed-title"><?php echo esc_html( EVSMS_Settings::msg( 'err_device_claimed' ) ); ?></p>
				</div>
				<div class="evsms-gift" hidden>
					<p class="evsms-gift-text"><?php echo esc_html( EVSMS_Settings::msg( 'gift_text' ) ); ?></p>
					<button type="button" class="evsms-gift-btn">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="18" cy="5" r="3" />
							<circle cx="6" cy="12" r="3" />
							<circle cx="18" cy="19" r="3" />
							<path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4" />
						</svg>
						<?php echo esc_html( EVSMS_Settings::msg( 'gift_button' ) ); ?>
					</button>
					<p class="evsms-gift-status" role="status"></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Zwraca świeży nonce – formularz bywa serwowany z cache strony (LiteSpeed),
	 * więc nonce osadzony w HTML mógłby być już przeterminowany.
	 */
	public static function ajax_nonce() {
		nocache_headers();

		// Przy okazji wydajemy identyfikator urządzenia (ciasteczko) – bez niego formularz nie zostanie przyjęty
		if ( ! EVSMS_Device::ensure() ) {
			// Za dużo nowych urządzeń z tej sieci (kasowanie ciasteczek / incognito)
			self::fail( EVSMS_Settings::msg( 'err_rate_limit' ), 429, '', array( 'code' => 'ip_limit' ) );
		}

		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE_ACTION ) ) );
	}

	/**
	 * Obsługa wysłania formularza.
	 */
	public static function ajax_request() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			self::fail( EVSMS_Settings::msg( 'err_session' ), 403 );
		}

		// Bot wypełnił honeypot – udajemy sukces, nic nie wysyłamy
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => EVSMS_Settings::msg( 'success_message' ) ) );
		}

		$device = EVSMS_Device::current_hash();
		if ( '' === $device ) {
			self::fail( EVSMS_Settings::msg( 'err_cookies' ), 400, '', array( 'code' => 'cookies' ) );
		}

		// Jedno żądanie naraz z urządzenia – równoległe kliknięcia/skrypty nie ominą limitów
		$lock = 'evsms_d_' . substr( $device, 0, 40 );
		if ( ! self::acquire_lock( $lock ) ) {
			self::fail( EVSMS_Settings::msg( 'err_busy' ), 409 );
		}

		try {
			$result = self::process( $device );
		} finally {
			self::release_lock( $lock );
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		}
		wp_send_json_error( $result['data'], $result['status'] );
	}

	/**
	 * Walidacja, limity (urządzenie → IP → limit globalny w issue_voucher) i wysyłka.
	 *
	 * @return array{success:bool, data:array, status?:int}
	 */
	private static function process( $device ) {
		$gift = (bool) EVSMS_Settings::get( 'gift_enabled' );

		if ( EVSMS_Device::is_claimed( $device ) ) {
			return self::error( EVSMS_Settings::msg( 'err_device_claimed' ), 403, '', array( 'code' => 'device_claimed', 'gift' => $gift ) );
		}

		$raw = (string) wp_unslash( $_POST['phone'] ?? '' );
		if ( '' === trim( $raw ) ) {
			return self::error( EVSMS_Settings::msg( 'err_phone_empty' ), 400, 'phone' );
		}

		$phone = self::normalize_phone( $raw );
		if ( ! $phone ) {
			return self::error( EVSMS_Settings::msg( 'err_phone_length' ), 400, 'phone' );
		}

		$blocked = self::blocked_prefix( $phone );
		if ( null !== $blocked ) {
			return self::error( EVSMS_Settings::msg( 'err_phone_blocked', array( '{prefix}' => $blocked ) ), 400, 'phone' );
		}

		if ( '' !== (string) EVSMS_Settings::get( 'consent_text' ) && empty( $_POST['consent'] ) ) {
			return self::error( EVSMS_Settings::msg( 'err_consent' ), 400, 'consent' );
		}

		// Flood z urządzenia: odstęp między próbami i limit prób na godzinę (pod lockiem urządzenia)
		$flood = EVSMS_Device::check_and_record( $device );
		if ( $flood && 'cooldown' === $flood['code'] ) {
			return self::error( EVSMS_Settings::msg( 'err_device_wait', array( '{seconds}' => $flood['retry_after'] ) ), 429, '', $flood );
		}
		if ( $flood ) {
			return self::error( EVSMS_Settings::msg( 'err_device_limit' ), 429, '', $flood );
		}

		$ip_hash = EVSMS_Device::ip_hash();

		// Blokada na IP – bez niej równoległe żądania odczytałyby ten sam stan licznika
		$ip_lock = 'evsms_ip_' . substr( $ip_hash, 0, 32 );
		if ( ! self::acquire_lock( $ip_lock ) ) {
			return self::error( EVSMS_Settings::msg( 'err_busy' ), 409 );
		}
		$allowed = self::check_rate_limit( $ip_hash );
		self::release_lock( $ip_lock );

		if ( ! $allowed ) {
			return self::error( EVSMS_Settings::msg( 'err_rate_limit' ), 429 );
		}

		// Blokada per numer – chroni przed równoległymi żądaniami na ten sam numer z różnych urządzeń
		$lock = 'evsms_' . $phone;
		if ( ! self::acquire_lock( $lock ) ) {
			return self::error( EVSMS_Settings::msg( 'err_busy' ), 409 );
		}

		try {
			$result = self::issue_voucher( $phone, $ip_hash, $device, self::request_source() );
		} finally {
			self::release_lock( $lock );
		}

		if ( ! $result['success'] ) {
			// Awaria po stronie SMSAPI nie powinna zużywać cooldownu ani limitu prób użytkownika
			if ( ! empty( $result['refund'] ) ) {
				EVSMS_Device::refund( $device );
			}
			$extra = ! empty( $result['duplicate'] ) ? array( 'gift' => $gift ) : array();
			return self::error( $result['message'], $result['status'] ?? 400, $result['field'] ?? '', $extra );
		}

		$data = array(
			'message' => EVSMS_Settings::msg( 'success_message' ),
			// Ukryty duplikat (brak voucher_id): nie obiecujemy SMS-a, którego nie wysłano
			'meta' => EVSMS_Settings::msg(
				empty( $result['voucher_id'] ) ? 'success_meta_repeat' : 'success_meta',
				array( '{phone}' => self::mask_phone( $phone ) )
			),
			'gift' => $gift,
			'claimedDays' => (int) EVSMS_Settings::get( 'device_days' ),
		);

		// Konwersja tylko, gdy SMS faktycznie wysłano (nie przy ukrytym duplikacie, nie w trybie testowym)
		if ( ! empty( $result['voucher_id'] ) && ! EVSMS_Settings::get( 'test_mode' ) ) {
			$data['conversion'] = array(
				'id' => $result['voucher_id'],
				'transactionId' => $result['voucher'],
				'token' => EVSMS_Vouchers::conv_token( $result['voucher_id'] ),
			);
		}

		return array( 'success' => true, 'data' => $data );
	}

	/**
	 * Źródło zgłoszenia przekazane przez skrypt: strona, gclid i UTM (zsanityzowane, przycięte do długości kolumn).
	 */
	private static function request_source() {
		$get = function ( $key, $max ) {
			return substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ), 0, $max );
		};

		return array(
			'page' => $get( 'src_page', 255 ),
			'gclid' => preg_replace( '/[^A-Za-z0-9_\-]/', '', $get( 'src_gclid', 255 ) ),
			'utm_source' => $get( 'src_utm_source', 100 ),
			'utm_medium' => $get( 'src_utm_medium', 100 ),
			'utm_campaign' => $get( 'src_utm_campaign', 150 ),
		);
	}

	/**
	 * Zapisuje sygnał przekazania zdarzenia do warstwy tagowania (podpis chroni przed zgadywaniem ID).
	 */
	public static function ajax_conversion() {
		nocache_headers();

		$id = absint( $_POST['id'] ?? 0 );
		$token = (string) wp_unslash( $_POST['token'] ?? '' );

		if ( ! $id || ! hash_equals( EVSMS_Vouchers::conv_token( $id ), $token ) ) {
			wp_send_json_error( null, 403 );
		}

		EVSMS_Vouchers::mark_converted( $id );
		wp_send_json_success();
	}

	/**
	 * Wynik błędu dla process(); $extra trafia do JSON (np. code, retry_after, gift).
	 */
	private static function error( $message, $status, $field = '', $extra = array() ) {
		return array(
			'success' => false,
			'status' => $status,
			'data' => array_merge( array( 'message' => $message, 'field' => $field ), $extra ),
		);
	}

	/**
	 * Rezerwuje numer vouchera, wysyła SMS, a po sukcesie – e-mail.
	 *
	 * @return array{success:bool, message?:string, field?:string, status?:int, duplicate?:bool}
	 */
	private static function issue_voucher( $phone, $ip_hash, $device, $source = array() ) {
		if ( EVSMS_Settings::get( 'one_per_phone' ) && EVSMS_Vouchers::phone_has_voucher( $phone ) ) {
			// Ten sam ekran co przy sukcesie – nie zdradzamy, kto już brał udział w promocji
			if ( EVSMS_Settings::get( 'hide_duplicate' ) ) {
				return array( 'success' => true );
			}
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_duplicate' ), 'field' => 'phone', 'duplicate' => true );
		}

		// Limit globalny: sprawdzenie i rezerwacja numeru pod jedną blokadą, żeby równoległe żądania go nie przekroczyły
		if ( ! self::acquire_lock( 'evsms_quota' ) ) {
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_busy' ), 'status' => 409 );
		}

		$quota = self::quota_exceeded();
		$code = self::generate_code();
		$id = $quota ? false : EVSMS_Vouchers::create( $phone, $code, $ip_hash, $device, $source );
		self::release_lock( 'evsms_quota' );

		if ( $quota ) {
			self::send_quota_alert( $quota );
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_quota' ), 'status' => 429 );
		}
		if ( ! $id ) {
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_generic' ) );
		}

		$number = EVSMS_Vouchers::format_number( $id );
		$message = EVSMS_Settings::msg( 'message', self::placeholders( $number, $code ) );

		$sms = EVSMS_Smsapi::send( $phone, $message, self::sms_idx( $id ) );
		if ( ! $sms['success'] ) {
			EVSMS_Vouchers::mark_failed( $id, $sms['error'] );
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_sms_failed' ), 'field' => 'phone', 'refund' => true );
		}

		EVSMS_Vouchers::mark_sent( $id, $sms['sms_id'] );
		self::send_notification( $number, $phone, $code );

		/**
		 * Kupon wysłany SMS-em.
		 *
		 * @param int    $id     Autonumer vouchera.
		 * @param string $number Sformatowany numer vouchera.
		 * @param string $phone  Numer telefonu (48XXXXXXXXX).
		 * @param string $code   Kod kuponu.
		 */
		do_action( 'evsms_voucher_sent', $id, $number, $phone, $code );

		return array( 'success' => true, 'voucher_id' => $id, 'voucher' => $number );
	}

	/**
	 * Przekroczony limit globalny: 'hour' / 'day' albo '' gdy można wysyłać.
	 * Liczy zarezerwowane numery z pominięciem nieudanych wysyłek.
	 */
	private static function quota_exceeded() {
		$limits = array(
			'hour' => array( (int) EVSMS_Settings::get( 'max_per_hour' ), HOUR_IN_SECONDS ),
			'day' => array( (int) EVSMS_Settings::get( 'max_per_day' ), DAY_IN_SECONDS ),
		);

		foreach ( $limits as $window => $limit ) {
			if ( $limit[0] > 0 && EVSMS_Vouchers::count_since( $limit[1] ) >= $limit[0] ) {
				return $window;
			}
		}

		return '';
	}

	/**
	 * Jeden alert e-mail na okno limitu (godzina / doba).
	 */
	private static function send_quota_alert( $window ) {
		$to = (string) EVSMS_Settings::get( 'notify_email' );
		$key = 'evsms_quota_alert_' . $window;
		if ( '' === $to || get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 'day' === $window ? DAY_IN_SECONDS : HOUR_IN_SECONDS );

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		wp_mail(
			$to,
			sprintf( '[%s] SMS Kupon: osiągnięto limit wysyłek', $site ),
			sprintf(
				"Formularz SMS Kupon osiągnął limit %s i odrzuca kolejne zgłoszenia.\n\nJeśli to nie jest zwykły ruch, sprawdź rejestr voucherów:\n%s\n\nLimity zmienisz w zakładce Kupon i ochrona.",
				'hour' === $window ? 'godzinowy' : 'dobowy',
				admin_url( 'options-general.php?page=' . EVSMS_Settings::PAGE . '#evsms-tab-vouchers' )
			)
		);
	}

	/**
	 * Identyfikator wysyłki dla SMSAPI (check_idx) – ponowienie na zapasowym endpoincie nie zdubluje SMS-a.
	 */
	private static function sms_idx( $id ) {
		return 'evsms-' . substr( md5( home_url() ), 0, 8 ) . '-' . $id;
	}

	/**
	 * Znaczniki wspólne dla SMS-a i e-maila.
	 */
	private static function placeholders( $number, $code, $phone = '' ) {
		return array(
			'{code}' => $code,
			'{voucher}' => $number,
			'{site}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{phone}' => '' !== (string) $phone ? '+' . $phone : '',
			'{date}' => current_time( 'd.m.Y H:i:s' ),
		);
	}

	/**
	 * E-mail (HTML) do administratora z numerem pobranego vouchera.
	 */
	private static function send_notification( $number, $phone, $code ) {
		$to = (string) EVSMS_Settings::get( 'notify_email' );
		if ( '' === $to ) {
			return;
		}

		$subject = EVSMS_Settings::msg( 'email_subject', self::placeholders( $number, $code, $phone ) );
		$rows = array(
			'Numer vouchera' => '<strong style="font-size:18px">' . esc_html( $number ) . '</strong>',
			'Kod kuponu' => '<code style="font-size:15px">' . esc_html( $code ) . '</code>',
			'Telefon' => '<a href="' . esc_url( 'tel:+' . $phone ) . '">+' . esc_html( $phone ) . '</a>',
			'Data' => esc_html( current_time( 'd.m.Y H:i:s' ) ),
		);

		ob_start();
		?>
		<div
			style="background:#f4f5f7;padding:24px 12px;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1f2937">
			<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e5e7eb">
				<p style="margin:0 0 4px;font-size:13px;color:#6b7280"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				<h1 style="margin:0 0 20px;font-size:20px;line-height:1.3">Pobrano voucher rabatowy</h1>
				<?php if ( EVSMS_Settings::get( 'test_mode' ) ) : ?>
					<p style="margin:0 0 16px;padding:10px 12px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:14px">
						Tryb testowy SMSAPI – SMS nie został realnie dostarczony. </p>
				<?php endif; ?>
				<table role="presentation" style="width:100%;border-collapse:collapse;font-size:15px">
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr>
							<td style="padding:10px 0;border-top:1px solid #f0f1f3;color:#6b7280;width:40%">
								<?php echo esc_html( $label ); ?></td>
							<td style="padding:10px 0;border-top:1px solid #f0f1f3">
								<?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput -- escapowane wyżej ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div> <?php
		$body = ob_get_clean();

		if ( ! wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			error_log( 'evillage-smsform: nie udało się wysłać e-maila o voucherze ' . $number );
		}
	}

	/**
	 * Normalizuje numer do formatu 48XXXXXXXXX; false gdy niepoprawny.
	 */
	private static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );

		if ( str_starts_with( $digits, '0048' ) ) {
			$digits = substr( $digits, 2 );
		}
		if ( 9 === strlen( $digits ) ) {
			$digits = '48' . $digits;
		}

		return preg_match( '/^48\d{9}$/', $digits ) ? $digits : false;
	}

	/**
	 * Zwraca zabroniony prefiks pasujący do numeru (np. "70" dla 48700...) albo null.
	 */
	private static function blocked_prefix( $phone ) {
		$national = substr( $phone, 2 );
		foreach ( EVSMS_Settings::blocked_prefixes() as $prefix ) {
			if ( str_starts_with( $national, $prefix ) ) {
				return $prefix;
			}
		}
		return null;
	}

	/**
	 * Maskuje numer do komunikatu sukcesu: 48600100200 => +48 600 *** 200.
	 */
	private static function mask_phone( $phone ) {
		return '+48 ' . substr( $phone, 2, 3 ) . ' *** ' . substr( $phone, 8, 3 );
	}

	/**
	 * Kod kuponu: stały albo unikalny (bez mylących znaków 0/O, 1/I).
	 */
	private static function generate_code() {
		if ( 'static' === EVSMS_Settings::get( 'code_mode' ) && '' !== (string) EVSMS_Settings::get( 'static_code' ) ) {
			return (string) EVSMS_Settings::get( 'static_code' );
		}

		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$code = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$code .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
		}

		return EVSMS_Settings::get( 'code_prefix' ) . $code;
	}

	/**
	 * Limit prób na IP w stałym oknie godzinowym.
	 */
	private static function check_rate_limit( $ip_hash ) {
		$max = (int) EVSMS_Settings::get( 'max_per_ip_hour' );
		if ( $max <= 0 ) {
			return true;
		}

		$key = 'evsms_rl_' . substr( $ip_hash, 0, 32 );
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

	/**
	 * Blokada MySQL (GET_LOCK) – działa między procesami PHP.
	 */
	private static function acquire_lock( $name ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) );
	}

	private static function release_lock( $name ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	/**
	 * Zwraca błąd JSON i kończy żądanie. $field wskazuje pole formularza, przy którym pokazać błąd.
	 */
	private static function fail( $message, $status = 400, $field = '', $extra = array() ) {
		wp_send_json_error( array_merge( array( 'message' => $message, 'field' => $field ), $extra ), $status );
	}
}
