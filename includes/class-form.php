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
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'blockedPrefixes' => EVSMS_Settings::blocked_prefixes(),
			'messages'        => array(
				'phoneEmpty'   => EVSMS_Settings::msg( 'err_phone_empty' ),
				'phoneLength'  => EVSMS_Settings::msg( 'err_phone_length' ),
				'phoneBlocked' => EVSMS_Settings::msg( 'err_phone_blocked' ),
				'consent'      => EVSMS_Settings::msg( 'err_consent' ),
				'generic'      => EVSMS_Settings::msg( 'err_generic' ),
			),
		) );
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
			'api_version'     => 3,
			'title'           => 'SMS Kupon',
			'category'        => 'widgets',
			'editor_script'   => 'evsms-block',
			'editor_style'    => 'evsms-form',
			'render_callback' => array( __CLASS__, 'render_shortcode' ),
			'attributes'      => array(
				'title'    => array( 'type' => 'string', 'default' => '' ),
				'subtitle' => array( 'type' => 'string', 'default' => '' ),
				'button'   => array( 'type' => 'string', 'default' => '' ),
				'color'    => array( 'type' => 'string', 'default' => '' ),
			),
		) );
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

		$title    = '' !== $atts['title'] ? $atts['title'] : EVSMS_Settings::msg( 'form_title' );
		$subtitle = '' !== $atts['subtitle'] ? $atts['subtitle'] : EVSMS_Settings::msg( 'form_subtitle' );
		$button   = '' !== $atts['button'] ? $atts['button'] : EVSMS_Settings::msg( 'form_button' );
		$consent  = (string) EVSMS_Settings::get( 'consent_text' );
		$uid      = wp_unique_id( 'evsms-' );
		$color    = sanitize_hex_color( $atts['color'] );
		$icon     = EVSMS_Settings::icon_html();

		ob_start();
		?>
		<div class="evsms"<?php echo $color ? ' style="' . esc_attr( '--evsms-accent:' . $color ) . '"' : ''; ?>>
			<<?php echo $tag; ?> class="evsms-form"<?php echo $preview ? '' : ' novalidate'; ?> aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
				<div class="evsms-head">
					<?php // W podglądzie span zostaje zawsze, żeby panel mógł podmieniać ikonę na żywo ?>
					<?php if ( '' !== $icon || $preview ) : ?>
						<span class="evsms-icon" aria-hidden="true"<?php echo '' === $icon ? ' hidden' : ''; ?>><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput -- statyczny SVG / esc_url ?></span>
					<?php endif; ?>
					<div>
						<p class="evsms-title" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $title ); ?></p>
						<?php if ( '' !== $subtitle ) : ?>
							<p class="evsms-subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="evsms-field" data-field="phone">
					<label class="evsms-label" for="<?php echo esc_attr( $uid ); ?>-phone"><?php echo esc_html( EVSMS_Settings::msg( 'form_label' ) ); ?></label>
					<div class="evsms-row">
						<div class="evsms-input">
							<span class="evsms-prefix" aria-hidden="true">+48</span>
							<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone"<?php echo $field( 'phone' ); ?> class="evsms-phone"
								inputmode="numeric" autocomplete="tel-national" placeholder="600 100 200" maxlength="20"
								aria-describedby="<?php echo esc_attr( $uid ); ?>-phone-error">
						</div>
						<button type="<?php echo $preview ? 'button' : 'submit'; ?>" class="evsms-submit" data-busy="<?php echo esc_attr( EVSMS_Settings::msg( 'form_button_busy' ) ); ?>">
							<span class="evsms-submit-label"><?php echo esc_html( $button ); ?></span>
							<span class="evsms-spinner" aria-hidden="true"></span>
						</button>
					</div>
					<p class="evsms-error" id="<?php echo esc_attr( $uid ); ?>-phone-error"></p>
				</div>

				<?php if ( '' !== $consent ) : ?>
					<div class="evsms-field" data-field="consent">
						<label class="evsms-consent">
							<input type="checkbox"<?php echo $field( 'consent' ); ?> value="1" aria-describedby="<?php echo esc_attr( $uid ); ?>-consent-error">
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
			</<?php echo $tag; ?>>

			<div class="evsms-success" tabindex="-1" hidden>
				<span class="evsms-success-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</span>
				<p class="evsms-success-title"></p>
				<p class="evsms-success-meta"></p>
			</div>
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

		$raw = (string) wp_unslash( $_POST['phone'] ?? '' );
		if ( '' === trim( $raw ) ) {
			self::fail( EVSMS_Settings::msg( 'err_phone_empty' ), 400, 'phone' );
		}

		$phone = self::normalize_phone( $raw );
		if ( ! $phone ) {
			self::fail( EVSMS_Settings::msg( 'err_phone_length' ), 400, 'phone' );
		}

		$blocked = self::blocked_prefix( $phone );
		if ( null !== $blocked ) {
			self::fail( EVSMS_Settings::msg( 'err_phone_blocked', array( '{prefix}' => $blocked ) ), 400, 'phone' );
		}

		if ( '' !== (string) EVSMS_Settings::get( 'consent_text' ) && empty( $_POST['consent'] ) ) {
			self::fail( EVSMS_Settings::msg( 'err_consent' ), 400, 'consent' );
		}

		$ip_hash = hash_hmac( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), wp_salt( 'nonce' ) );
		if ( ! self::check_rate_limit( $ip_hash ) ) {
			self::fail( EVSMS_Settings::msg( 'err_rate_limit' ), 429 );
		}

		// Blokada per numer – chroni przed dwoma równoległymi żądaniami na ten sam numer
		$lock = 'evsms_' . $phone;
		if ( ! self::acquire_lock( $lock ) ) {
			self::fail( EVSMS_Settings::msg( 'err_busy' ), 409 );
		}

		try {
			$result = self::issue_voucher( $phone, $ip_hash );
		} finally {
			self::release_lock( $lock );
		}

		if ( ! $result['success'] ) {
			self::fail( $result['message'], 400, $result['field'] ?? '' );
		}

		wp_send_json_success( array(
			'message' => EVSMS_Settings::msg( 'success_message' ),
			'meta'    => EVSMS_Settings::msg( 'success_meta', array( '{phone}' => self::mask_phone( $phone ) ) ),
		) );
	}

	/**
	 * Rezerwuje numer vouchera, wysyła SMS, a po sukcesie – e-mail.
	 *
	 * @return array{success:bool, message?:string, field?:string}
	 */
	private static function issue_voucher( $phone, $ip_hash ) {
		if ( EVSMS_Settings::get( 'one_per_phone' ) && EVSMS_Vouchers::phone_has_voucher( $phone ) ) {
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_duplicate' ), 'field' => 'phone' );
		}

		$code = self::generate_code();
		$id   = EVSMS_Vouchers::create( $phone, $code, $ip_hash );
		if ( ! $id ) {
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_generic' ) );
		}

		$number  = EVSMS_Vouchers::format_number( $id );
		$message = EVSMS_Settings::msg( 'message', self::placeholders( $number, $code ) );

		$sms = EVSMS_Smsapi::send( $phone, $message );
		if ( ! $sms['success'] ) {
			EVSMS_Vouchers::mark_failed( $id, $sms['error'] );
			return array( 'success' => false, 'message' => EVSMS_Settings::msg( 'err_sms_failed' ), 'field' => 'phone' );
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

		return array( 'success' => true );
	}

	/**
	 * Znaczniki wspólne dla SMS-a i e-maila.
	 */
	private static function placeholders( $number, $code ) {
		return array(
			'{code}'    => $code,
			'{voucher}' => $number,
			'{site}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
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

		$subject = EVSMS_Settings::msg( 'email_subject', self::placeholders( $number, $code ) );
		$rows    = array(
			'Numer vouchera' => '<strong style="font-size:18px">' . esc_html( $number ) . '</strong>',
			'Kod kuponu'     => '<code style="font-size:15px">' . esc_html( $code ) . '</code>',
			'Telefon'        => '<a href="' . esc_url( 'tel:+' . $phone ) . '">+' . esc_html( $phone ) . '</a>',
			'Data'           => esc_html( current_time( 'Y-m-d H:i:s' ) ),
		);

		ob_start();
		?>
		<div style="background:#f4f5f7;padding:24px 12px;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1f2937">
			<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e5e7eb">
				<p style="margin:0 0 4px;font-size:13px;color:#6b7280"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				<h1 style="margin:0 0 20px;font-size:20px;line-height:1.3">Pobrano voucher rabatowy</h1>
				<?php if ( EVSMS_Settings::get( 'test_mode' ) ) : ?>
					<p style="margin:0 0 16px;padding:10px 12px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:14px">
						Tryb testowy SMSAPI – SMS nie został realnie dostarczony.
					</p>
				<?php endif; ?>
				<table role="presentation" style="width:100%;border-collapse:collapse;font-size:15px">
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr>
							<td style="padding:10px 0;border-top:1px solid #f0f1f3;color:#6b7280;width:40%"><?php echo esc_html( $label ); ?></td>
							<td style="padding:10px 0;border-top:1px solid #f0f1f3"><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput -- escapowane wyżej ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p style="margin:24px 0 0">
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . EVSMS_Settings::PAGE . '#evsms-tab-vouchers' ) ); ?>"
						style="display:inline-block;padding:10px 18px;border-radius:8px;background:#1f2937;color:#fff;text-decoration:none;font-weight:600">Zobacz rejestr voucherów</a>
				</p>
			</div>
		</div>
		<?php
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
		$code     = '';
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

		$key   = 'evsms_rl_' . substr( $ip_hash, 0, 32 );
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
	private static function fail( $message, $status = 400, $field = '' ) {
		wp_send_json_error( array( 'message' => $message, 'field' => $field ), $status );
	}
}
