<?php
/**
 * Admin transactions page.
 *
 * Delegates rendering to CoinsnapCore\Admin\TransactionsPage for a consistent UI.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Admin;

use CoinsnapCore\Admin\TransactionsPage;
use BitcoinInvoiceForm\Util\BIF_Util_Provider_Factory as ProviderFactory;
use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin transactions page for the Invoice Form plugin.
 */
class BIF_Admin_Transactions_Page {

	/**
	 * Register hooks (label filter for source column).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'coinsnap_core_transaction_sources', array( __CLASS__, 'provide_invoice_form_sources' ), 10, 2 );
		add_filter( 'coinsnap_core_transaction_source_label', array( __CLASS__, 'provide_invoice_form_label' ), 10, 3 );
	}

	/**
	 * Provide invoice form list for the source dropdown filter.
	 *
	 * @param array                       $sources  Existing sources.
	 * @param \CoinsnapCore\PluginInstance $instance Plugin instance.
	 * @return array
	 */
	public static function provide_invoice_form_sources( array $sources, $instance ): array {
		if ( $instance->option_key() !== BIF_Constants::OPTION_KEY ) {
			return $sources;
		}

		$forms = get_posts( array(
			'post_type'      => BIF_Constants::CPT_INVOICE_FORM,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $forms as $form ) {
			$sources[] = array(
				'id'    => $form->ID,
				'title' => $form->post_title,
			);
		}

		return $sources;
	}

	/**
	 * Provide a human-readable label for a source_id (form title).
	 *
	 * @param string                      $label      Current label.
	 * @param mixed                       $source_val Source ID value.
	 * @param \CoinsnapCore\PluginInstance $instance   Plugin instance.
	 * @return string
	 */
	public static function provide_invoice_form_label( string $label, $source_val, $instance ): string {
		if ( $instance->option_key() !== BIF_Constants::OPTION_KEY ) {
			return $label;
		}

		$post = get_post( (int) $source_val );
		if ( $post ) {
			return $post->post_title;
		}

		return $label;
	}

	/**
	 * Render the transactions page via coinsnap-core.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		TransactionsPage::render_page_for( ProviderFactory::make_instance() );
	}
}
