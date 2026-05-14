<?php
/**
 * Plugin core bootstrap.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm;

use BitcoinInvoiceForm\CPT\BIF_CPT_Invoice_Form_Post_Type as InvoiceFormPostType;
use BitcoinInvoiceForm\Shortcode\BIF_Shortcode_Invoice_Form_Shortcode as InvoiceFormShortcode;
use BitcoinInvoiceForm\Admin\BIF_Admin_Settings as AdminSettings;
use BitcoinInvoiceForm\Admin\BIF_Admin_Transactions_Page as TransactionsPage;
use BitcoinInvoiceForm\Admin\BIF_Admin_Logs_Page as LogsPage;
use BitcoinInvoiceForm\Rest\BIF_Rest_Routes as RestRoutes;
use BitcoinInvoiceForm\Util\BIF_Util_Provider_Factory as ProviderFactory;
use BitcoinInvoiceForm\BIF_Constants;
use CoinsnapCore\Admin\AjaxHandlers as CoreAjaxHandlers;
use CoinsnapCore\Admin\SettingsPage;
use CoinsnapCore\Auth\BTCPayAuthorizer;
use CoinsnapCore\Util\ProviderFactory as CoreProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 */
class BIF_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BIF_Plugin|null
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return BIF_Plugin
	 */
	public static function instance(): BIF_Plugin {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks on load.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( InvoiceFormPostType::class, 'register' ) );
		add_action( 'init', array( InvoiceFormShortcode::class, 'register' ) );
		add_action( 'rest_api_init', array( RestRoutes::class, 'register' ) );
		AdminSettings::register();
		TransactionsPage::register();

		// Register BTCPay OAuth callback handler.
		BTCPayAuthorizer::register_callback( ProviderFactory::make_instance() );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );

			// Auto-register webhook when settings are saved.
			add_action( 'update_option_' . BIF_Constants::OPTION_KEY, array( $this, 'maybe_register_webhook_on_save' ), 10, 2 );

			// AJAX handlers for the settings page.
			add_action( 'wp_ajax_bif_connection_handler', array( $this, 'handle_connection_check' ) );
			add_action( 'wp_ajax_bif_btcpay_apiurl_handler', array( $this, 'handle_btcpay_url' ) );
			add_action( 'wp_ajax_bif_reregister_webhook', array( $this, 'handle_reregister_webhook' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
	}

	/**
	 * Register admin menus and submenus.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Bitcoin Invoice Forms', 'Coinsnap-Bitcoin-Invoice-form' ),
			__( 'Bitcoin Invoice Forms', 'Coinsnap-Bitcoin-Invoice-form' ),
			'manage_options',
			BIF_Constants::MENU_SLUG,
			array( TransactionsPage::class, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		add_submenu_page(
			BIF_Constants::MENU_SLUG,
			__( 'Transactions', 'Coinsnap-Bitcoin-Invoice-form' ),
			__( 'Transactions', 'Coinsnap-Bitcoin-Invoice-form' ),
			'manage_options',
			BIF_Constants::MENU_SLUG,
			array( TransactionsPage::class, 'render_page' )
		);

		add_submenu_page(
			BIF_Constants::MENU_SLUG,
			__( 'Settings', 'Coinsnap-Bitcoin-Invoice-form' ),
			__( 'Settings', 'Coinsnap-Bitcoin-Invoice-form' ),
			'manage_options',
			BIF_Constants::MENU_SLUG . '-settings',
			array( AdminSettings::class, 'render_page' )
		);

		add_submenu_page(
			BIF_Constants::MENU_SLUG,
			__( 'Logs', 'Coinsnap-Bitcoin-Invoice-form' ),
			__( 'Logs', 'Coinsnap-Bitcoin-Invoice-form' ),
			'manage_options',
			BIF_Constants::MENU_SLUG . '-logs',
			array( LogsPage::class, 'render_page' )
		);
	}

	/**
	 * Enqueue coinsnap-core admin CSS/JS on all plugin admin pages.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts(): void {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$page = is_string( $page ) ? $page : '';

		// Load on any page belonging to this plugin.
		if ( false === strpos( $page, BIF_Constants::MENU_SLUG ) ) {
			return;
		}

		$inst = ProviderFactory::make_instance();

		wp_register_style( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/css/admin.css', array(), COINSNAP_CORE_VERSION );
		wp_register_script( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAP_CORE_VERSION, true );

		wp_localize_script( 'coinsnap-core-admin', 'CoinsnapCoreAdmin', array(
			'option_key'        => $inst->option_key(),
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'coinsnap-ajax-nonce' ),
			'connection_action' => 'bif_connection_handler',
			'btcpay_action'     => 'bif_btcpay_apiurl_handler',
			'webhook_action'    => 'bif_reregister_webhook',
		) );

		wp_enqueue_style( 'coinsnap-core-admin' );
		wp_enqueue_script( 'coinsnap-core-admin' );
	}

	/**
	 * Enqueue frontend assets for the invoice form shortcode.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		wp_register_style( 'coinsnap-bitcoin-invoice-form-frontend', COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_URL . 'assets/css/frontend.css', array(), COINSNAP_BITCOIN_INVOICE_FORM_VERSION );
		wp_register_script( 'coinsnap-bitcoin-invoice-form-frontend', COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), COINSNAP_BITCOIN_INVOICE_FORM_VERSION, true );

		wp_localize_script(
			'coinsnap-bitcoin-invoice-form-frontend',
			'BIF',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( get_rest_url( null, BIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'coinsnap-bitcoin-invoice-form-frontend' );
		wp_enqueue_script( 'coinsnap-bitcoin-invoice-form-frontend' );
	}

	/**
	 * Show a setup notice if payment credentials are not configured.
	 *
	 * @return void
	 */
	public function maybe_show_setup_notice(): void {
		SettingsPage::maybe_show_setup_notice( ProviderFactory::make_instance() );
	}

	/**
	 * AJAX: check payment provider connection.
	 *
	 * @return void
	 */
	public function handle_connection_check(): void {
		CoreAjaxHandlers::handle_connection_check( ProviderFactory::make_instance() );
	}

	/**
	 * AJAX: return BTCPay authorization URL.
	 *
	 * @return void
	 */
	public function handle_btcpay_url(): void {
		CoreAjaxHandlers::handle_btcpay_url( ProviderFactory::make_instance() );
	}

	/**
	 * AJAX: clear and re-register the payment provider webhook.
	 *
	 * @return void
	 */
	public function handle_reregister_webhook(): void {
		$nonce = filter_input( INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! wp_verify_nonce( $nonce, 'coinsnap-ajax-nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$inst          = ProviderFactory::make_instance();
		$settings      = SettingsPage::get_settings_for( $inst );
		$provider_name = $settings['payment_provider'] ?? 'coinsnap';

		try {
			$provider = CoreProviderFactory::create( $inst );

			$provider->check_webhook();
			delete_option( $inst->webhook_key() );

			$result = $provider->register_webhook();

			if ( ! isset( $result['error'] ) && isset( $result['result'] ) ) {
				$stored                   = array();
				$stored[ $provider_name ] = array(
					'id'     => $result['result']['id'],
					'secret' => $result['result']['secret'],
					'url'    => $result['result']['url'],
				);
				update_option( $inst->webhook_key(), $stored );
				wp_send_json_success( array(
					'message' => 'Webhook registered successfully',
					'url'     => $result['result']['url'],
					'id'      => $result['result']['id'],
				) );
			} else {
				wp_send_json_error( $result['message'] ?? 'Registration failed' );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Auto-register webhook when plugin settings are saved.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function maybe_register_webhook_on_save( $old_value, $new_value ): void {
		if ( ! is_array( $new_value ) ) {
			return;
		}

		$provider_name = $new_value['payment_provider'] ?? 'coinsnap';

		if ( 'btcpay' === $provider_name ) {
			$has_creds = ! empty( $new_value['btcpay_api_key'] )
				&& ! empty( $new_value['btcpay_store_id'] )
				&& ! empty( $new_value['btcpay_host'] );
		} else {
			$has_creds = ! empty( $new_value['coinsnap_api_key'] )
				&& ! empty( $new_value['coinsnap_store_id'] );
		}

		if ( ! $has_creds ) {
			return;
		}

		try {
			$inst     = ProviderFactory::make_instance();
			$provider = CoreProviderFactory::create( $inst );

			if ( $provider->check_webhook() ) {
				return;
			}

			$result = $provider->register_webhook();

			if ( ! isset( $result['error'] ) && isset( $result['result'] ) ) {
				$stored                   = array();
				$stored[ $provider_name ] = array(
					'id'     => $result['result']['id'],
					'secret' => $result['result']['secret'],
					'url'    => $result['result']['url'],
				);
				update_option( $inst->webhook_key(), $stored );
			}
		} catch ( \Exception $e ) {
			// Silently fail — user can manually re-register via button.
		}
	}
}
