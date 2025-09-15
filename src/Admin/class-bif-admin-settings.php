<?php
/**
 * Admin settings.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings registration and rendering.
 */
class BIF_Admin_Settings {
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
			'default_amount'          => 0,
			'default_currency'        => 'USD',
			'coinsnap_api_key'        => '',
			'coinsnap_store_id'       => '',
			'coinsnap_api_base'       => 'https://api.coinsnap.io',
			'coinsnap_webhook_secret' => '',
			'btcpay_host'             => '',
			'btcpay_api_key'          => '',
			'btcpay_store_id'         => '',
			'btcpay_webhook_secret'   => '',
			'log_level'               => 'error',
			'disable_webhook_verification' => false,
		);
		$defaults = apply_filters( 'bif_default_settings', $defaults );
		$opts     = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		return array_merge( $defaults, $opts );
	}

	/** Register settings, sections, and fields. */
	public static function register_settings(): void {
		register_setting(
			'bif_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		add_settings_section(
			'bif_general',
			__( 'General', 'bif' ),
			function () {
				echo '<p>' . esc_html__( 'Configure payment gateway settings.', 'bif' ) . '</p>';
			},
			'bif-settings'
		);

		add_settings_field(
			'payment_provider',
			__( 'Default Payment Provider', 'bif' ),
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
			'bif-settings',
			'bif_general'
		);

		add_settings_field(
			'default_amount',
			__( 'Default Amount', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="number" min="0" step="0.01" name="' . esc_attr( self::OPTION_KEY ) . '[default_amount]" value="' . esc_attr( (string) $s['default_amount'] ) . '" />';
			},
			'bif-settings',
			'bif_general'
		);

		add_settings_field(
			'default_currency',
			__( 'Default Currency', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[default_currency]">';
				foreach ( array( 'USD', 'EUR', 'CHF', 'JPY', 'SATS' ) as $cur ) {
					echo '<option value="' . esc_attr( $cur ) . '" ' . selected( $cur, $s['default_currency'], false ) . '>' . esc_html( $cur ) . '</option>';
				}
				echo '</select>';
			},
			'bif-settings',
			'bif_general'
		);

		add_settings_section( 'bif_coinsnap', __( 'Coinsnap', 'bif' ), function () {
			echo '<p class="description">' . esc_html__( 'Configure Coinsnap settings. These fields are only relevant when Coinsnap is selected as the payment provider.', 'bif' ) . '</p>';
		}, 'bif-settings' );
		add_settings_field(
			'coinsnap_api_key',
			__( 'Coinsnap API Key', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_api_key]" value="' . esc_attr( $s['coinsnap_api_key'] ) . '" />';
			},
			'bif-settings',
			'bif_coinsnap'
		);
		add_settings_field(
			'coinsnap_store_id',
			__( 'Coinsnap Store ID', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_store_id]" value="' . esc_attr( $s['coinsnap_store_id'] ) . '" />';
			},
			'bif-settings',
			'bif_coinsnap'
		);
		add_settings_field(
			'coinsnap_api_base',
			__( 'Coinsnap API Base URL', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_api_base]" value="' . esc_attr( $s['coinsnap_api_base'] ) . '" />';
			},
			'bif-settings',
			'bif_coinsnap'
		);
		add_settings_field(
			'coinsnap_webhook_secret',
			__( 'Coinsnap Webhook Secret', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[coinsnap_webhook_secret]" value="' . esc_attr( $s['coinsnap_webhook_secret'] ) . '" />';
			},
			'bif-settings',
			'bif_coinsnap'
		);

		add_settings_section( 'bif_btcpay', __( 'BTCPay Server', 'bif' ), function () {
			echo '<p class="description">' . esc_html__( 'Configure BTCPay Server settings. These fields are only relevant when BTCPay Server is selected as the payment provider.', 'bif' ) . '</p>';
		}, 'bif-settings' );
		add_settings_field(
			'btcpay_host',
			__( 'BTCPay Server Host', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_host]" value="' . esc_attr( $s['btcpay_host'] ) . '" />';
			},
			'bif-settings',
			'bif_btcpay'
		);
		add_settings_field(
			'btcpay_api_key',
			__( 'BTCPay API Key', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_api_key]" value="' . esc_attr( $s['btcpay_api_key'] ) . '" />';
			},
			'bif-settings',
			'bif_btcpay'
		);
		add_settings_field(
			'btcpay_store_id',
			__( 'BTCPay Store ID', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_store_id]" value="' . esc_attr( $s['btcpay_store_id'] ) . '" />';
			},
			'bif-settings',
			'bif_btcpay'
		);
		add_settings_field(
			'btcpay_webhook_secret',
			__( 'BTCPay Webhook Secret', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[btcpay_webhook_secret]" value="' . esc_attr( $s['btcpay_webhook_secret'] ) . '" />';
			},
			'bif-settings',
			'bif_btcpay'
		);

		add_settings_section( 'bif_advanced', __( 'Advanced', 'bif' ), function () {
			echo '<p class="description">' . esc_html__( 'Advanced configuration options.', 'bif' ) . '</p>';
		}, 'bif-settings' );
		add_settings_field(
			'log_level',
			__( 'Log Level', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[log_level]">';
				foreach ( array(
					'error'   => __( 'Error', 'bif' ),
					'warning' => __( 'Warning', 'bif' ),
					'info'    => __( 'Info', 'bif' ),
					'debug'   => __( 'Debug', 'bif' ),
				) as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $k, $s['log_level'], false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'bif-settings',
			'bif_advanced'
		);
		add_settings_field(
			'disable_webhook_verification',
			__( 'Disable Webhook Verification', 'bif' ),
			function () {
				$s = self::get_settings();
				echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[disable_webhook_verification]" value="1" ' . checked( $s['disable_webhook_verification'], true, false ) . ' /> ' . esc_html__( 'Disable webhook signature verification (not recommended)', 'bif' ) . '</label>';
			},
			'bif-settings',
			'bif_advanced'
		);
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
			'default_currency',
			'coinsnap_api_key',
			'coinsnap_store_id',
			'coinsnap_api_base',
			'coinsnap_webhook_secret',
			'btcpay_host',
			'btcpay_api_key',
			'btcpay_store_id',
			'btcpay_webhook_secret',
			'log_level',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		// Sanitize numeric fields
		if ( isset( $input['default_amount'] ) ) {
			$sanitized['default_amount'] = floatval( $input['default_amount'] );
		}

		// Sanitize boolean fields
		$sanitized['disable_webhook_verification'] = isset( $input['disable_webhook_verification'] ) && $input['disable_webhook_verification'];

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		echo '<div class="wrap bif-admin">';
		echo '<h1>' . esc_html__( 'Bitcoin Invoice Form Settings', 'bif' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'bif_settings_group' );
		
		// Render general settings section
		echo '<div id="bif-general-settings">';
		self::render_section( 'bif_general' );
		echo '</div>';
		
		// Wrap payment provider settings in containers
		echo '<div id="bif-payment-provider-settings">';
		echo '<div id="bif-coinsnap-settings-wrapper" class="provider-settings">';
		self::render_section( 'bif_coinsnap' );
		echo '</div>';
		
		echo '<div id="bif-btcpay-settings-wrapper" class="provider-settings">';
		self::render_section( 'bif_btcpay' );
		echo '</div>';
		echo '</div>';
		
		// Advanced Settings section
		echo '<div id="bif-advanced-settings">';
		self::render_section( 'bif_advanced' );
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
		
		if ( ! isset( $wp_settings_sections['bif-settings'][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections['bif-settings'][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
		}
		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! empty( $wp_settings_fields['bif-settings'][ $section_id ] ) ) {
			echo '<table class="form-table">';
			do_settings_fields( 'bif-settings', $section_id );
			echo '</table>';
		}
	}
}