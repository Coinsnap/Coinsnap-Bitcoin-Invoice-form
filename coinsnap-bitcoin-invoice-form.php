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
 * Tested up to:        6.9
 * Requires at least:   6.2
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

if(!defined('COINSNAPBIF_REFERRAL_CODE' ) ) { define( 'COINSNAPBIF_REFERRAL_CODE', 'D85536' );}
if(!defined('COINSNAPBIF_VERSION' ) ) { define( 'COINSNAPBIF_VERSION', '1.0.0' );}
if(!defined('COINSNAPBIF_PHP_VERSION' ) ) { define( 'COINSNAPBIF_PHP_VERSION', '7.4' );}
if(!defined('COINSNAP_CURRENCIES')){define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}

if(!defined('COINSNAPBIF_PLUGIN_FILE')) { define( 'COINSNAPBIF_PLUGIN_FILE', __FILE__ ); }
if(!defined('COINSNAPBIF_PLUGIN_DIR' )) { define( 'COINSNAPBIF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); }
if(!defined('COINSNAPBIF_PLUGIN_URL' )) { define( 'COINSNAPBIF_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); }
/**
 * Register a PSR-4 autoloader for this plugin namespace with WordPress class name mapping.
 */
spl_autoload_register(
	function ( $autoload_class ) {
		$prefix = 'CoinsnapBIF\\';
		if ( strpos( $autoload_class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $autoload_class, strlen( $prefix ) );

		// Map WordPress-style class names to WordPress-style filenames.
		$class_mapping = array(
			// Main classes.
			'CoinsnapBIF_Plugin'                                  => 'class-coinsnapbif-plugin',
			'CoinsnapBIF_Constants'                               => 'class-coinsnapbif-constants',

			// Admin classes.
			'Admin\\CoinsnapBIF_Admin_Logs_Page'                  => 'Admin/class-coinsnapbif-admin-logs-page',
			'Admin\\CoinsnapBIF_Admin_Settings'                   => 'Admin/class-coinsnapbif-admin-settings',
			'Admin\\CoinsnapBIF_Admin_Transactions_Page'          => 'Admin/class-coinsnapbif-admin-transactions-page',

			// CPT classes.
			'CPT\\CoinsnapBIF_CPT_Invoice_Form_Post_Type'         => 'CPT/class-coinsnapbif-cpt-invoice-form-post-type',

			// Database classes.
			'Database\\Installer'                         => 'Database/class-installer',

			// Rest classes.
			'Rest\\CoinsnapBIF_Rest_Routes'                       => 'Rest/class-coinsnapbif-rest-routes',

			// Services classes.
			'Services\\CoinsnapBIF_Services_Payment_Service'      => 'Services/class-coinsnapbif-services-payment-service',

			// Shortcode classes.
			'Shortcode\\CoinsnapBIF_Shortcode_Invoice_Form_Shortcode' => 'Shortcode/class-coinsnapbif-shortcode-invoice-form-shortcode',

			// Util classes.
			'Util\\CoinsnapBIF_Logger'                            => 'Util/class-coinsnapbif-logger',
			'Util\\CoinsnapBIF_Log_Levels'                        => 'Util/class-coinsnapbif-log-levels',
			'Util\\CoinsnapBIF_Util_Provider_Factory'             => 'Util/class-coinsnapbif-util-provider-factory',

			// Payment Provider classes.
			'Providers\\Payment\\BTCPayProvider'          => 'Providers/Payment/class-btcpayprovider',
			'Providers\\Payment\\CoinsnapProvider'        => 'Providers/Payment/class-coinsnapprovider',
			'Providers\\Payment\\PaymentProviderInterface' => 'Providers/Payment/class-paymentproviderinterface',
		);

		// Check if this is a WordPress-style class name.
		if ( isset( $class_mapping[ $relative ] ) ) {
			$file = COINSNAPBIF_PLUGIN_DIR . 'src/' . $class_mapping[ $relative ] . '.php';
		} else {
			// Default PSR-4 mapping.
			$file = COINSNAPBIF_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
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
		if ( ! class_exists( 'CoinsnapBIF\\Database\\Installer' ) ) {
			require_once COINSNAPBIF_PLUGIN_DIR . 'src/Database/class-installer.php';
		}
		\CoinsnapBIF\Database\Installer::activate();
	}
);

/**
 * Bootstrap plugin after all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'CoinsnapBIF\\CoinsnapBIF_Plugin' ) ) {
			require_once COINSNAPBIF_PLUGIN_DIR . 'src/class-coinsnapbif-plugin.php';
		}
		\CoinsnapBIF\CoinsnapBIF_Plugin::instance()->boot();
	}
);
