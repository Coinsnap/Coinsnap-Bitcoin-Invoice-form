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
			'btcpay_host'             => '',
			'btcpay_api_key'          => '',
			'btcpay_store_id'         => '',
			'log_level'               => 'error',
			'disable_webhook_verification' => false,
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
				self::secret_field( 'coinsnap_api_key' );
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
				self::secret_field( 'btcpay_api_key' );
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

		add_settings_section( 'coinsnapbif_advanced', __( 'Advanced', 'coinsnap-bitcoin-invoice-form' ), function () {
			echo '<p class="description">' . esc_html__( 'Advanced configuration options.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}, 'coinsnapbif-settings' );
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
	 * API key field: never shows the stored key, only its last 4 characters.
	 * Saving with the field empty keeps the stored key.
	 *
	 * @param string $field Field name.
	 */
	private static function secret_field( string $field ): void {
		$hint = \CoinsnapBIF\Services\CoinsnapBIF_Security::secret_hint( (string) self::get_settings()[ $field ] );
		printf(
			'<input type="password" class="regular-text" name="%s" value="" autocomplete="new-password" placeholder="%s" />',
			esc_attr( self::OPTION_KEY . '[' . $field . ']' ),
			esc_attr( '' !== $hint ? $hint : __( 'Paste your API key', 'coinsnap-bitcoin-invoice-form' ) )
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * Called for the settings form (options.php) and for programmatic updates of the
	 * option. Only the settings form applies the "empty key keeps the stored key" rule.
	 *
	 * @param mixed $input Raw input.
	 * @return array Sanitized input.
	 */
	public static function sanitize( $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$sanitized = array();

		// Sanitize text fields. Webhook secrets are not settings: the plugin creates them.
		$text_fields = array(
			'payment_provider',
			'coinsnap_api_key',
			'coinsnap_store_id',
			'coinsnap_api_base',
			'btcpay_host',
			'btcpay_api_key',
			'btcpay_store_id',
			'log_level',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = trim( sanitize_text_field( (string) $input[ $field ] ) );
			}
		}
		if ( isset( $sanitized['payment_provider'] ) && 'btcpay' !== $sanitized['payment_provider'] ) {
			$sanitized['payment_provider'] = 'coinsnap';
		}
		if ( isset( $sanitized['btcpay_host'] ) ) {
			$sanitized['btcpay_host'] = rtrim( esc_url_raw( $sanitized['btcpay_host'] ), '/' );
		}

		// Sanitize boolean fields
		$sanitized['disable_webhook_verification'] = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php has checked the nonce.
		$from_form = isset( $_POST['option_page'] ) && 'coinsnapbif_settings_group' === $_POST['option_page'];
		if ( ! $from_form ) {
			return array_merge( self::stored(), $sanitized );
		}
		return self::apply_form_rules( self::stored(), $sanitized );
	}

	/**
	 * Stored option, without defaults.
	 */
	private static function stored(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		return is_array( $opts ) ? $opts : array();
	}

	/**
	 * Rules for the settings form: empty key fields keep the stored key; a new BTCPay
	 * URL with an empty key drops the stored key (6.14); a pasted BTCPay key is accepted
	 * only if BTCPay says it is limited to one store with the needed permissions.
	 *
	 * @param array $stored    Stored settings.
	 * @param array $submitted Sanitized submitted settings.
	 * @return array Settings to save.
	 */
	public static function apply_form_rules( array $stored, array $submitted ): array {
		$out = array_merge( $stored, $submitted );

		if ( '' === ( $submitted['coinsnap_api_key'] ?? '' ) ) {
			$out['coinsnap_api_key'] = (string) ( $stored['coinsnap_api_key'] ?? '' );
		}

		$old_host = rtrim( (string) ( $stored['btcpay_host'] ?? '' ), '/' );
		$new_host = (string) ( $out['btcpay_host'] ?? '' );
		$new_key  = (string) ( $submitted['btcpay_api_key'] ?? '' );

		if ( '' === $new_key || ( $new_key === (string) ( $stored['btcpay_api_key'] ?? '' ) && $new_host === $old_host ) ) {
			$out['btcpay_api_key'] = ( $new_host === $old_host ) ? (string) ( $stored['btcpay_api_key'] ?? '' ) : '';
			return $out;
		}

		$permissions = \CoinsnapBIF\Services\CoinsnapBIF_Security::btcpay_key_permissions( $new_host, $new_key );
		$store       = null === $permissions ? '' : \CoinsnapBIF\Services\CoinsnapBIF_Security::btcpay_key_store( $permissions );
		$wanted      = (string) ( $out['btcpay_store_id'] ?? '' );
		if ( '' === $wanted ) {
			$wanted = $store;
		}
		if ( null === $permissions || $store !== $wanted || ! \CoinsnapBIF\Services\CoinsnapBIF_Security::btcpay_key_is_acceptable( $permissions, $wanted ) ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'coinsnapbif_btcpay_key',
					__( 'The BTCPay API key was not saved: it must work on this BTCPay Server and be limited to one store with permission to create and view invoices and manage webhooks.', 'coinsnap-bitcoin-invoice-form' )
				);
			}
			// Keep the old key only if it still belongs to the same server and store.
			$same = $new_host === $old_host && (string) ( $out['btcpay_store_id'] ?? '' ) === (string) ( $stored['btcpay_store_id'] ?? '' );
			$out['btcpay_api_key']  = $same ? (string) ( $stored['btcpay_api_key'] ?? '' ) : '';
			return $out;
		}
		$out['btcpay_api_key']  = $new_key;
		$out['btcpay_store_id'] = $wanted;
		return $out;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		echo '<div class="wrap bif-admin">';
		echo '<h1>' . esc_html__( 'Bitcoin Invoice Form Settings', 'coinsnap-bitcoin-invoice-form' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'coinsnapbif_settings_group' );
                
                echo '<div class="coinsnapConnectionStatus"></div>';

		// Render general settings section
		echo '<div id="bif-general-settings">';
		self::render_section( 'coinsnapbif_general' );
		echo '</div>';

		// Wrap payment provider settings in containers
		echo '<div id="bif-payment-provider-settings">';
		echo '<div id="bif-coinsnap-settings-wrapper" class="provider-settings">';
		self::render_section( 'coinsnapbif_coinsnap' );
		echo '</div>';

		echo '<div id="bif-btcpay-settings-wrapper" class="provider-settings">';
		self::render_section( 'coinsnapbif_btcpay' );
		echo '</div>';
		echo '</div>';

		// Advanced Settings section
		echo '<div id="bif-advanced-settings">';
		self::render_section( 'coinsnapbif_advanced' );
		echo '</div>';

		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render a specific settings section manually.
	 *
	 * @param string $section_id The ID of the section to render.
	 */
	private static function render_section( string $section_id ): void {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections['coinsnapbif-settings'][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections['coinsnapbif-settings'][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
		}
		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! empty( $wp_settings_fields['coinsnapbif-settings'][ $section_id ] ) ) {
			echo '<table class="form-table">';
			do_settings_fields( 'coinsnapbif-settings', $section_id );
			echo '</table>';
		}
	}
}
