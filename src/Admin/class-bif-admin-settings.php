<?php
/**
 * Admin settings.
 *
 * Delegates all settings registration, sanitization, and rendering to
 * CoinsnapCore\Admin\SettingsPage so we get the modern card-based UI
 * consistent with other Coinsnap plugins.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Admin;

use CoinsnapCore\Admin\SettingsPage;
use BitcoinInvoiceForm\Util\BIF_Util_Provider_Factory as ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages payment-gateway settings for the Invoice Form plugin.
 */
class BIF_Admin_Settings {

	/**
	 * Register hooks (settings registration delegated to coinsnap-core).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', function () {
			SettingsPage::register_for( ProviderFactory::make_instance() );
		} );
	}

	/**
	 * Return settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return SettingsPage::get_settings_for( ProviderFactory::make_instance() );
	}

	/**
	 * Render the admin settings page (delegates to coinsnap-core card UI).
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'Coinsnap-Bitcoin-Invoice-form' ) );
		}
		SettingsPage::render_page_for( ProviderFactory::make_instance() );
	}
}
