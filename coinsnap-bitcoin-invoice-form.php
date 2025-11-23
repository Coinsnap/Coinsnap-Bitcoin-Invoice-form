<?php
/*
 * Plugin Name:        Bitcoin Invoice Form
 * Plugin URI:         https://coinsnap.io/coinsnap-bitcoin-invoice-form/
 * Description:        Generate and embed customizable Bitcoin Invoice Forms on your website. Customers can complete and pay invoices directly on your site with payment processing via Coinsnap or BTCPay Server.
 * Version:            1.0.0
 * Author:             Coinsnap
 * Author URI:         https://coinsnap.io/
 * Text Domain:        coinsnap-bitcoin-invoice-form
 * Domain Path:         /languages
 * Tested up to:        6.8
 * Requires at least:   5.8
 * Requires PHP:        7.4
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:             true
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_REFERRAL_CODE' ) ) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_REFERRAL_CODE', 'D85536' );}
if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_VERSION' ) ) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_VERSION', '1.0.0' );}
if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_PHP_VERSION' ) ) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_PHP_VERSION', '7.4' );}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}

if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_FILE')) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_FILE', __FILE__ ); }
if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR' )) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); }
if(!defined('COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_URL' )) { define( 'COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); }
/**
 * Register a PSR-4 autoloader for this plugin namespace with WordPress class name mapping.
 */
spl_autoload_register(
	function ( $autoload_class ) {
		$prefix = 'BitcoinInvoiceForm\\';
		if ( strpos( $autoload_class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $autoload_class, strlen( $prefix ) );

		// Map WordPress-style class names to WordPress-style filenames.
		$class_mapping = array(
			// Main classes.
			'BIF_Plugin'                                  => 'class-bif-plugin',
			'BIF_Constants'                               => 'class-bif-constants',

			// Admin classes.
			'Admin\\BIF_Admin_Logs_Page'                  => 'Admin/class-bif-admin-logs-page',
			'Admin\\BIF_Admin_Settings'                   => 'Admin/class-bif-admin-settings',
			'Admin\\BIF_Admin_Transactions_Page'          => 'Admin/class-bif-admin-transactions-page',

			// CPT classes.
			'CPT\\BIF_CPT_Invoice_Form_Post_Type'         => 'CPT/class-bif-cpt-invoice-form-post-type',

			// Database classes.
			'Database\\Installer'                         => 'Database/class-installer',

			// Rest classes.
			'Rest\\BIF_Rest_Routes'                       => 'Rest/class-bif-rest-routes',

			// Services classes.
			'Services\\BIF_Services_Payment_Service'      => 'Services/class-bif-services-payment-service',

			// Shortcode classes.
			'Shortcode\\BIF_Shortcode_Invoice_Form_Shortcode' => 'Shortcode/class-bif-shortcode-invoice-form-shortcode',

			// Util classes.
			'Util\\BIF_Logger'                            => 'Util/class-bif-logger',
			'Util\\BIF_Log_Levels'                        => 'Util/class-bif-log-levels',
			'Util\\BIF_Util_Provider_Factory'             => 'Util/class-bif-util-provider-factory',

			// Payment Provider classes.
			'Providers\\Payment\\BTCPayProvider'          => 'Providers/Payment/class-btcpayprovider',
			'Providers\\Payment\\CoinsnapProvider'        => 'Providers/Payment/class-coinsnapprovider',
			'Providers\\Payment\\PaymentProviderInterface' => 'Providers/Payment/class-paymentproviderinterface',
		);

		// Check if this is a WordPress-style class name.
		if ( isset( $class_mapping[ $relative ] ) ) {
			$file = COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR . 'src/' . $class_mapping[ $relative ] . '.php';
		} else {
			// Default PSR-4 mapping.
			$file = COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Activation: create or update required database tables.
 */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'BitcoinInvoiceForm\\Database\\Installer' ) ) {
			require_once COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR . 'src/Database/class-installer.php';
		}
		\BitcoinInvoiceForm\Database\Installer::activate();
	}
);

/**
 * Bootstrap plugin after all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'BitcoinInvoiceForm\\BIF_Plugin' ) ) {
			require_once COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR . 'src/class-bif-plugin.php';
		}
		\BitcoinInvoiceForm\BIF_Plugin::instance()->boot();
	}
);
