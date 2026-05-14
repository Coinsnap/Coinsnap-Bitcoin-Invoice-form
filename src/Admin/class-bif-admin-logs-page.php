<?php
/**
 * Admin logs page.
 *
 * Delegates rendering to CoinsnapCore\Admin\LogsPage for a consistent UI.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Admin;

use CoinsnapCore\Admin\LogsPage;
use CoinsnapCore\Admin\SettingsPage;
use CoinsnapCore\Util\Logger;
use BitcoinInvoiceForm\Util\BIF_Util_Provider_Factory as ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin logs page for the Invoice Form plugin.
 */
class BIF_Admin_Logs_Page {

	/**
	 * Register the admin page (no-op: registered via admin menu).
	 *
	 * @return void
	 */
	public static function register(): void {}

	/**
	 * Render the logs page via coinsnap-core.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		$inst     = ProviderFactory::make_instance();
		$settings = SettingsPage::get_settings_for( $inst );
		$logger   = new Logger(
			$inst->get( 'log_dir_name' ),
			$inst->get( 'log_file_name' ),
			$settings['log_level'] ?? 'error'
		);
		LogsPage::render_page_for( $inst, $logger );
	}
}
