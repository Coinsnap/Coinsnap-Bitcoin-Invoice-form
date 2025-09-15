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
use BitcoinInvoiceForm\Util\BIF_Logger;
use BitcoinInvoiceForm\BIF_Constants;

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
	 */
	public function boot(): void {
		// Initialize logger first.
		BIF_Logger::init();

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
			__( 'Bitcoin Invoice Forms', 'bif' ),
			__( 'Bitcoin Invoice Forms', 'bif' ),
			'manage_options',
			'bif-transactions',
			array( TransactionsPage::class, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		// Add explicit submenu for transactions (same as main page).
		add_submenu_page(
			'bif-transactions',
			__( 'Transactions', 'bif' ),
			__( 'Transactions', 'bif' ),
			'manage_options',
			'bif-transactions',
			array( TransactionsPage::class, 'render_page' )
		);

		add_submenu_page(
			'bif-transactions',
			__( 'Invoice Forms', 'bif' ),
			__( 'Invoice Forms', 'bif' ),
			'manage_options',
			'edit.php?post_type=' . BIF_Constants::CPT_INVOICE_FORM
		);

		add_submenu_page(
			'bif-transactions',
			__( 'Settings', 'bif' ),
			__( 'Settings', 'bif' ),
			'manage_options',
			'bif-settings',
			array( AdminSettings::class, 'render_page' )
		);

		add_submenu_page(
			'bif-transactions',
			__( 'Logs', 'bif' ),
			__( 'Logs', 'bif' ),
			'manage_options',
			'bif-logs',
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
		wp_register_style( 'bif-frontend', BIF_PLUGIN_URL . 'assets/css/frontend.css', array(), BIF_VERSION );
		wp_register_script( 'bif-frontend', BIF_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), BIF_VERSION, true );

		wp_localize_script(
			'bif-frontend',
			'BIF',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( get_rest_url( null, BIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'bif-frontend' );
		wp_enqueue_script( 'bif-frontend' );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin( string $hook ): void {
		// Only enqueue on our plugin pages
		if ( strpos( $hook, 'bif-' ) === false && strpos( $hook, 'bif_invoice_form' ) === false ) {
			return;
		}

		wp_register_style( 'bif-admin', BIF_PLUGIN_URL . 'assets/css/admin.css', array(), BIF_VERSION );
		wp_register_script( 'bif-admin', BIF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BIF_VERSION, true );

		// Localize script with REST API data
		wp_localize_script(
			'bif-admin',
			'bifRestUrl',
			array(
				'restUrl' => esc_url_raw( get_rest_url( null, BIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'bif-admin' );
		wp_enqueue_script( 'bif-admin' );
	}
}
