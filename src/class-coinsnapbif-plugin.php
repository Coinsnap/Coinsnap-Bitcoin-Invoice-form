<?php
/**
 * Plugin core bootstrap.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF;

use CoinsnapBIF\CPT\CoinsnapBIF_CPT_Invoice_Form_Post_Type as InvoiceFormPostType;
use CoinsnapBIF\Shortcode\CoinsnapBIF_Shortcode_Invoice_Form_Shortcode as InvoiceFormShortcode;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as AdminSettings;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Transactions_Page as TransactionsPage;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Logs_Page as LogsPage;
use CoinsnapBIF\Rest\CoinsnapBIF_Rest_Routes as RestRoutes;
use CoinsnapBIF\Util\CoinsnapBIF_Logger;
use CoinsnapBIF\CoinsnapBIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 */
class CoinsnapBIF_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CoinsnapBIF_Plugin|null
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return CoinsnapBIF_Plugin
	 */
	public static function instance(): CoinsnapBIF_Plugin {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks on load.
	 */
	public function boot(): void {
		// Initialize logger first.
		CoinsnapBIF_Logger::init();

		add_action( 'init', array( InvoiceFormPostType::class, 'register' ) );
		add_action( 'init', array( InvoiceFormShortcode::class, 'register' ) );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		AdminSettings::register();
		TransactionsPage::register();
		LogsPage::register();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Register admin menus and submenus.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Bitcoin Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Bitcoin Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-transactions',
			array( TransactionsPage::class, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		// Add explicit submenu for transactions (same as main page).
		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Transactions', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Transactions', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-transactions',
			array( TransactionsPage::class, 'render_page' )
		);

		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Settings', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Settings', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-settings',
			array( AdminSettings::class, 'render_page' )
		);

		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Logs', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Logs', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-logs',
			array( LogsPage::class, 'render_page' )
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		RestRoutes::register();
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend(): void {
		wp_register_style( 'coinsnapbif-frontend', COINSNAPBIF_PLUGIN_URL . 'assets/css/frontend.css', array(), COINSNAPBIF_VERSION );
		wp_register_script( 'coinsnapbif-frontend', COINSNAPBIF_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), COINSNAPBIF_VERSION, true );

		wp_localize_script(
			'coinsnapbif-frontend',
			'CoinsnapBIF',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'coinsnapbif-frontend' );
		wp_enqueue_script( 'coinsnapbif-frontend' );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin( string $hook ): void {
		// Only enqueue on our plugin pages
		if ( strpos( $hook, 'coinsnapbif-' ) === false && strpos( $hook, 'coinsnapbif_invoice_form' ) === false ) {
			return;
		}

		wp_register_style( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/css/admin.css', array(), COINSNAPBIF_VERSION );
		wp_register_script( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAPBIF_VERSION, true );

		// Localize script with REST API data
		wp_localize_script(
			'coinsnapbif-admin',
			'CoinsnapBIFRestUrl',
			array(
				'restUrl' => esc_url_raw( get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'coinsnapbif-admin' );
		wp_enqueue_script( 'coinsnapbif-admin' );
	}
}
