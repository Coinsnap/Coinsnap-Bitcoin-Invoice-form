<?php
/**
 * Admin settings.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings registration and rendering.
 */
class CoinsnapBIF_Admin_Settings {
	public const OPTION_KEY = 'bif_settings';
        public const WEBHOOK_KEY = 'coinsnapbif_webhook';

	/** Register hooks to initialize settings. */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}
        
        /**
	 * Get merged settings with defaults.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings(): array {
		$defaults = array(
			'payment_provider'        => 'coinsnap',
			'coinsnap_store_id'       => '',
			'coinsnap_api_key'        => '',
			'coinsnap_api_base'       => 'https://app.coinsnap.io',
			'coinsnap_webhook_secret' => '',
			'btcpay_host'             => '',
			'btcpay_api_key'          => '',
			'btcpay_store_id'         => '',
			'btcpay_webhook_secret'   => '',
			'log_level'               => 'error',
			'disable_webhook_verification' => false,
			'ngrok_url'               => '',
			'bitcoin_discount_enabled' => false,
			'bitcoin_discount_type'    => 'percentage',
			'bitcoin_discount_value'   => 0.0,
		);
		//$defaults = apply_filters( 'bif_default_settings', $defaults );
		$opts     = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		return array_merge( $defaults, $opts );
	}

	/** Register settings, sections, and fields. */
	public static function register_settings(): void {
		register_setting(
			'coinsnapbif_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		add_settings_section(
			'coinsnapbif_general',
			__( 'General', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				echo '<p>' . esc_html__( 'Configure payment gateway settings.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
			},
			'coinsnapbif-settings'
		);

		add_settings_field(
			'payment_provider',
			__( 'Bitcoin Payment Gateway', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[payment_provider]">';
				foreach ( array(
					'coinsnap' => 'Coinsnap',
					'btcpay'   => 'BTCPay',
				) as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $k, $s['payment_provider'], false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'coinsnapbif-settings',
			'coinsnapbif_general'
		);


		add_settings_field(
			'coinsnap_store_id',
			__( 'Coinsnap Store ID', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_store_id]" value="' . esc_attr( $s['coinsnap_store_id'] ) . '" />';
			},
			'coinsnapbif-settings',
			'coinsnapbif_coinsnap'
		);
		add_settings_section( 'coinsnapbif_coinsnap', __( 'Coinsnap', 'coinsnap-bitcoin-invoice-form' ), function () {
			echo '<p class="description">' . esc_html__( 'Configure Coinsnap settings. These fields are only relevant when Coinsnap is selected as the payment provider.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}, 'coinsnapbif-settings' );
		add_settings_field(
			'coinsnap_api_key',
			__( 'Coinsnap API Key', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_api_key]" value="' . esc_attr( $s['coinsnap_api_key'] ) . '" />';
			},
			'coinsnapbif-settings',
			'coinsnapbif_coinsnap'
		);

//		add_settings_field(
//			'coinsnap_api_base',
//			__( 'Coinsnap API Base URL', 'coinsnap-bitcoin-invoice-form' ),
//			function () {
//				$s = self::get_settings();
//				echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_api_base]" value="' . esc_attr( $s['coinsnap_api_base'] ) . '" />';
//			},
//			'coinsnapbif-settings',
//			'coinsnapbif_coinsnap'
//		);
//		add_settings_field(
//			'coinsnap_webhook_secret',
//			__( 'Coinsnap Webhook Secret', 'coinsnap-bitcoin-invoice-form' ),
//			function () {
//				$s = self::get_settings();
//				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_webhook_secret]" value="' . esc_attr( $s['coinsnap_webhook_secret'] ) . '" />';
//			},
//			'coinsnapbif-settings',
//			'coinsnapbif_coinsnap'
//		);

		add_settings_section( 'coinsnapbif_btcpay', __( 'BTCPay Server', 'coinsnap-bitcoin-invoice-form' ), function () {
			echo '<p class="description">' . esc_html__( 'Configure BTCPay Server settings. These fields are only relevant when BTCPay Server is selected as the payment provider.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}, 'coinsnapbif-settings' );
		add_settings_field(
			'btcpay_host',
			__( 'BTCPay Server Host', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="url" id="coinsnapbif_btcpay_url" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_host]" value="' . esc_attr( $s['btcpay_host'] ) . '" /><br/><button class="button btcpay-apikey-link" type="button" id="coinsnapbif_btcpay_wizard_button" target="_blank">'. esc_html__('Generate API key','coinsnap-bitcoin-invoice-form') .'</button>';
			},
			'coinsnapbif-settings',
			'coinsnapbif_btcpay'
		);
		add_settings_field(
			'btcpay_api_key',
			__( 'BTCPay API Key', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_api_key]" value="' . esc_attr( $s['btcpay_api_key'] ) . '" />';
			},
			'coinsnapbif-settings',
			'coinsnapbif_btcpay'
		);
		add_settings_field(
			'btcpay_store_id',
			__( 'BTCPay Store ID', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_store_id]" value="' . esc_attr( $s['btcpay_store_id'] ) . '" />';
			},
			'coinsnapbif-settings',
			'coinsnapbif_btcpay'
		);
//		add_settings_field(
//			'btcpay_webhook_secret',
//			__( 'BTCPay Webhook Secret', 'coinsnap-bitcoin-invoice-form' ),
//			function () {
//				$s = self::get_settings();
//				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_webhook_secret]" value="' . esc_attr( $s['btcpay_webhook_secret'] ) . '" />';
//			},
//			'coinsnapbif-settings',
//			'coinsnapbif_btcpay'
//		);

		add_settings_section( 'coinsnapbif_advanced', __( 'Advanced', 'coinsnap-bitcoin-invoice-form' ), function () {
			echo '<p class="description">' . esc_html__( 'Advanced configuration options.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}, 'coinsnapbif-settings' );
		add_settings_field(
			'ngrok_url',
			__( 'Webhook Override URL (ngrok)', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[ngrok_url]" value="' . esc_attr( $s['ngrok_url'] ) . '" placeholder="https://xxxx.ngrok.io" />';
				echo '<p class="description">' . esc_html__( 'Local/dev sites only: enter your public ngrok URL so Coinsnap can reach the webhook endpoint. Leave empty on production.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
			},
			'coinsnapbif-settings',
			'coinsnapbif_advanced'
		);
		add_settings_field(
			'log_level',
			__( 'Log Level', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[log_level]">';
				foreach ( array(
					'error'   => __( 'Error', 'coinsnap-bitcoin-invoice-form' ),
					'warning' => __( 'Warning', 'coinsnap-bitcoin-invoice-form' ),
					'info'    => __( 'Info', 'coinsnap-bitcoin-invoice-form' ),
					'debug'   => __( 'Debug', 'coinsnap-bitcoin-invoice-form' ),
				) as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $k, $s['log_level'], false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'coinsnapbif-settings',
			'coinsnapbif_advanced'
		);/*
		add_settings_field(
			'disable_webhook_verification',
			__( 'Disable Webhook Verification', 'coinsnap-bitcoin-invoice-form' ),
			function () {
				$s = self::get_settings();
				echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[disable_webhook_verification]" value="1" ' . checked( $s['disable_webhook_verification'], true, false ) . ' /> ' . esc_html__( 'Disable webhook signature verification (not recommended)', 'coinsnap-bitcoin-invoice-form' ) . '</label>';
			},
			'coinsnapbif-settings',
			'coinsnapbif_advanced'
		);*/
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized input.
	 */
	public static function sanitize( array $input ): array {
		$sanitized = array();

		// Sanitize text fields
		$text_fields = array(
			'payment_provider',
			'coinsnap_api_key',
			'coinsnap_store_id',
			'coinsnap_api_base',
			'coinsnap_webhook_secret',
			'btcpay_host',
			'btcpay_api_key',
			'btcpay_store_id',
			'btcpay_webhook_secret',
			'log_level',
			'ngrok_url',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		// Sanitize boolean fields
		$sanitized['disable_webhook_verification'] = isset( $input['disable_webhook_verification'] ) && $input['disable_webhook_verification'];
		$sanitized['bitcoin_discount_enabled']     = isset( $input['bitcoin_discount_enabled'] ) && $input['bitcoin_discount_enabled'];

		// Sanitize discount type (only allow known values).
		$discount_type = $input['bitcoin_discount_type'] ?? 'percentage';
		$sanitized['bitcoin_discount_type'] = in_array( $discount_type, array( 'percentage', 'fixed' ), true ) ? $discount_type : 'percentage';

		// Sanitize discount value (must be non-negative number).
		$discount_value = isset( $input['bitcoin_discount_value'] ) ? floatval( $input['bitcoin_discount_value'] ) : 0.0;
		$sanitized['bitcoin_discount_value'] = max( 0.0, $discount_value );

		return $sanitized;
	}

	/**
	 * Render the settings page using the vendor csc-* card UI.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'coinsnap-bitcoin-invoice-form' ) );
		}

		$s          = self::get_settings();
		$option_key = self::OPTION_KEY;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
		?>
		<div class="wrap csc-admin csc-settings-page">
			<?php if ( $saved ) : ?>
			<div class="csc-toast" id="csc-save-toast">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
				<?php esc_html_e( 'Settings saved successfully', 'coinsnap-bitcoin-invoice-form' ); ?>
			</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'coinsnapbif_settings_group' ); ?>

				<!-- Page Header with Connection Badge -->
				<div class="csc-settings-header">
					<div class="csc-settings-header-left">
						<h1><?php esc_html_e( 'Bitcoin Invoice Form Settings', 'coinsnap-bitcoin-invoice-form' ); ?></h1>
						<p class="csc-settings-subtitle"><?php esc_html_e( 'Connect your payment provider to start accepting Bitcoin payments.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
					</div>
					<div class="csc-connection-badge" id="csc-connection-badge">
						<span class="csc-status-dot"></span>
						<span class="csc-connection-text"><?php esc_html_e( 'Checking...', 'coinsnap-bitcoin-invoice-form' ); ?></span>
					</div>
				</div>

				<!-- Hidden div for legacy AJAX connection check -->
				<div class="coinsnapConnectionStatus" style="display:none;"></div>

				<!-- Payment Provider Card -->
				<div class="csc-card">
					<div class="csc-card-header">
						<h2><?php esc_html_e( 'Payment Gateway', 'coinsnap-bitcoin-invoice-form' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Choose your Bitcoin payment provider and enter your credentials.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
					</div>
					<div class="csc-card-body">

						<!-- Provider Toggle -->
						<div class="csc-provider-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Payment provider', 'coinsnap-bitcoin-invoice-form' ); ?>">
							<input type="radio"
								name="<?php echo esc_attr( $option_key ); ?>[payment_provider]"
								id="csc-provider-coinsnap"
								value="coinsnap"
								<?php checked( $s['payment_provider'], 'coinsnap' ); ?>
							/>
							<label for="csc-provider-coinsnap">
								<span class="csc-provider-icon">
									<img src="<?php echo esc_url( COINSNAP_CORE_PLUGIN_URL . 'assets/img/coinsnap-icon.svg' ); ?>" alt="" />
								</span>
								Coinsnap
							</label>
							<input type="radio"
								name="<?php echo esc_attr( $option_key ); ?>[payment_provider]"
								id="csc-provider-btcpay"
								value="btcpay"
								<?php checked( $s['payment_provider'], 'btcpay' ); ?>
							/>
							<label for="csc-provider-btcpay">
								<span class="csc-provider-icon">
									<img src="<?php echo esc_url( COINSNAP_CORE_PLUGIN_URL . 'assets/img/btcpay-icon.svg' ); ?>" alt="" />
								</span>
								BTCPay Server
							</label>
						</div>

						<!-- Coinsnap Fields -->
						<div class="csc-provider-panel" id="csc-panel-coinsnap" data-provider="coinsnap">
							<div class="csc-field-row">
								<label for="csc-coinsnap-store-id"><?php esc_html_e( 'Store ID', 'coinsnap-bitcoin-invoice-form' ); ?></label>
								<input type="text"
									id="csc-coinsnap-store-id"
									name="<?php echo esc_attr( $option_key ); ?>[coinsnap_store_id]"
									value="<?php echo esc_attr( $s['coinsnap_store_id'] ); ?>"
									placeholder="<?php esc_attr_e( 'Enter your Coinsnap Store ID', 'coinsnap-bitcoin-invoice-form' ); ?>"
								/>
								<p class="csc-field-description"><?php esc_html_e( 'Find this in your Coinsnap dashboard under Store Settings.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
							</div>
							<div class="csc-field-row">
								<label for="csc-coinsnap-api-key"><?php esc_html_e( 'API Key', 'coinsnap-bitcoin-invoice-form' ); ?></label>
								<input type="text"
									id="csc-coinsnap-api-key"
									name="<?php echo esc_attr( $option_key ); ?>[coinsnap_api_key]"
									value="<?php echo esc_attr( $s['coinsnap_api_key'] ); ?>"
									placeholder="<?php esc_attr_e( 'Enter your Coinsnap API Key', 'coinsnap-bitcoin-invoice-form' ); ?>"
								/>
								<p class="csc-field-description"><?php esc_html_e( 'Your API key from the Coinsnap dashboard.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
							</div>
						</div>

						<!-- BTCPay Fields -->
						<div class="csc-provider-panel" id="csc-panel-btcpay" data-provider="btcpay">
							<div class="csc-field-row">
								<label for="coinsnapbif_btcpay_url"><?php esc_html_e( 'Server URL', 'coinsnap-bitcoin-invoice-form' ); ?></label>
								<div class="csc-generate-key-wrapper">
									<input type="url"
										id="coinsnapbif_btcpay_url"
										name="<?php echo esc_attr( $option_key ); ?>[btcpay_host]"
										value="<?php echo esc_attr( $s['btcpay_host'] ); ?>"
										placeholder="https://your-btcpay-instance.com"
									/>
									<button type="button" class="csc-btn-generate" id="coinsnapbif_btcpay_wizard_button">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
										<?php esc_html_e( 'Generate API Key', 'coinsnap-bitcoin-invoice-form' ); ?>
									</button>
								</div>
								<p class="csc-field-description">
									<?php if ( ! is_ssl() && empty( $s['ngrok_url'] ) ) : ?>
									<?php esc_html_e( 'Enter your BTCPay Server URL, then click "Generate API Key". BTCPay opens in a popup — approve the permissions, confirm the browser prompt, and the API key will be filled in automatically.', 'coinsnap-bitcoin-invoice-form' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Enter your BTCPay Server URL, then click "Generate API Key" to authorize automatically.', 'coinsnap-bitcoin-invoice-form' ); ?>
									<?php endif; ?>
								</p>
							</div>
							<div class="csc-field-row">
								<label for="csc-btcpay-api-key"><?php esc_html_e( 'API Key', 'coinsnap-bitcoin-invoice-form' ); ?></label>
								<input type="text"
									id="csc-btcpay-api-key"
									name="<?php echo esc_attr( $option_key ); ?>[btcpay_api_key]"
									value="<?php echo esc_attr( $s['btcpay_api_key'] ); ?>"
									placeholder="<?php esc_attr_e( 'Auto-filled after authorization', 'coinsnap-bitcoin-invoice-form' ); ?>"
								/>
								<p class="csc-field-description"><?php esc_html_e( 'This field is populated automatically when you use the Generate API Key flow above.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
							</div>
							<div class="csc-field-row">
								<label for="csc-btcpay-store-id"><?php esc_html_e( 'Store ID', 'coinsnap-bitcoin-invoice-form' ); ?></label>
								<input type="text"
									id="csc-btcpay-store-id"
									name="<?php echo esc_attr( $option_key ); ?>[btcpay_store_id]"
									value="<?php echo esc_attr( $s['btcpay_store_id'] ); ?>"
									placeholder="<?php esc_attr_e( 'Auto-filled after authorization', 'coinsnap-bitcoin-invoice-form' ); ?>"
								/>
							</div>
						</div>

					</div>
				</div>

				<!-- Bitcoin Discount Card -->
				<div class="csc-card">
					<div class="csc-card-header">
						<h2><?php esc_html_e( 'Bitcoin Discount', 'coinsnap-bitcoin-invoice-form' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Offer a discount to customers who pay with Bitcoin. Applied globally to all invoice forms.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
					</div>
					<div class="csc-card-body">
						<div class="csc-field-row">
							<label class="csc-toggle-label">
								<input type="checkbox"
									id="csc-discount-enabled"
									name="<?php echo esc_attr( $option_key ); ?>[bitcoin_discount_enabled]"
									value="1"
									<?php checked( $s['bitcoin_discount_enabled'], true ); ?>
								/>
								<?php esc_html_e( 'Enable Bitcoin discount', 'coinsnap-bitcoin-invoice-form' ); ?>
							</label>
						</div>
						<div class="csc-field-row">
							<label for="csc-discount-type"><?php esc_html_e( 'Discount Type', 'coinsnap-bitcoin-invoice-form' ); ?></label>
							<select id="csc-discount-type" name="<?php echo esc_attr( $option_key ); ?>[bitcoin_discount_type]">
								<option value="percentage" <?php selected( $s['bitcoin_discount_type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage (%)', 'coinsnap-bitcoin-invoice-form' ); ?></option>
								<option value="fixed" <?php selected( $s['bitcoin_discount_type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'coinsnap-bitcoin-invoice-form' ); ?></option>
							</select>
						</div>
						<div class="csc-field-row">
							<label for="csc-discount-value"><?php esc_html_e( 'Discount Value', 'coinsnap-bitcoin-invoice-form' ); ?></label>
							<input type="number"
								id="csc-discount-value"
								name="<?php echo esc_attr( $option_key ); ?>[bitcoin_discount_value]"
								value="<?php echo esc_attr( $s['bitcoin_discount_value'] ); ?>"
								min="0"
								step="0.01"
								placeholder="0.00"
							/>
							<p class="csc-field-description"><?php esc_html_e( 'Enter a percentage (e.g. 5 for 5%) or a fixed amount in the invoice currency.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Advanced Settings Card -->
				<div id="csc-advanced" class="csc-card csc-card--compact">
					<div class="csc-card-header">
						<h2><?php esc_html_e( 'Advanced', 'coinsnap-bitcoin-invoice-form' ); ?></h2>
					</div>
					<div class="csc-card-body">
						<div class="csc-field-row">
							<label for="csc-log-level"><?php esc_html_e( 'Log Level', 'coinsnap-bitcoin-invoice-form' ); ?></label>
							<select id="csc-log-level" name="<?php echo esc_attr( $option_key ); ?>[log_level]">
								<?php
								$levels = array(
									'error'   => __( 'Error', 'coinsnap-bitcoin-invoice-form' ),
									'warning' => __( 'Warning', 'coinsnap-bitcoin-invoice-form' ),
									'info'    => __( 'Info', 'coinsnap-bitcoin-invoice-form' ),
									'debug'   => __( 'Debug', 'coinsnap-bitcoin-invoice-form' ),
								);
								foreach ( $levels as $k => $label ) {
									echo '<option value="' . esc_attr( $k ) . '" ' . selected( $k, $s['log_level'], false ) . '>' . esc_html( $label ) . '</option>';
								}
								?>
							</select>
							<p class="csc-field-description"><?php esc_html_e( 'Set the verbosity of plugin logging. Use "Debug" only for troubleshooting.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Sticky Save Bar -->
				<div class="csc-save-bar">
					<p class="csc-save-hint">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
						<?php esc_html_e( 'Changes are applied after saving', 'coinsnap-bitcoin-invoice-form' ); ?>
					</p>
					<?php submit_button( __( 'Save Changes', 'coinsnap-bitcoin-invoice-form' ), 'primary', 'submit', false ); ?>
				</div>

			</form>
		</div>
		<?php
	}
}
