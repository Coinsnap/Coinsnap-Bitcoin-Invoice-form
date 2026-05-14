<?php
/**
 * Factory to resolve payment provider implementations.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Util;

use BitcoinInvoiceForm\BIF_Constants;
use CoinsnapCore\PluginInstance;
use CoinsnapCore\Interfaces\PaymentProviderInterface;
use CoinsnapCore\Util\ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a configured PluginInstance and resolves the active payment provider.
 */
class BIF_Util_Provider_Factory {

	/**
	 * Build the shared PluginInstance for this plugin.
	 *
	 * @return PluginInstance
	 */
	public static function make_instance(): PluginInstance {
		return new PluginInstance(
			array(
				'plugin_name'              => 'Bitcoin Invoice Form',
				'option_key'               => BIF_Constants::OPTION_KEY,
				'webhook_key'              => BIF_Constants::WEBHOOK_KEY,
				'rest_namespace'           => BIF_Constants::REST_NAMESPACE,
				'referral_code'            => COINSNAP_BITCOIN_INVOICE_FORM_REFERRAL_CODE,
				'text_domain'              => 'coinsnap-bitcoin-invoice-form',
				'plugin_url'               => COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_URL,
				'plugin_dir'               => COINSNAP_BITCOIN_INVOICE_FORM_PLUGIN_DIR,
				'table_suffix'             => BIF_Constants::INVOICES_TABLE_SUFFIX,
				'source_column'            => 'form_id',
				'log_dir_name'             => 'bif-logs',
				'log_file_name'            => 'bif.log',
				'menu_slug'                => BIF_Constants::MENU_SLUG,
				'plugin_icon_url'          => COINSNAP_CORE_PLUGIN_URL . 'assets/img/coinsnap-icon.svg',
				'btcpay_callback_endpoint' => 'bif-btcpay-callback',
				'btcpay_app_name'          => 'BitcoinInvoiceForm',
				'help_links'               => array(
					array( 'url' => 'https://coinsnap.io/en/coinsnap-documentation/', 'label' => 'Documentation' ),
					array( 'url' => 'https://coinsnap.io/en/support/', 'label' => 'Support' ),
					array( 'url' => 'https://app.coinsnap.io', 'label' => 'Coinsnap Dashboard' ),
				),
			)
		);
	}

	/**
	 * Resolve payment provider for a given form.
	 *
	 * Reads the per-form provider override from post meta (if set), then
	 * delegates to the shared vendor ProviderFactory.
	 *
	 * @param int|string $form_id Form ID.
	 * @return PaymentProviderInterface Provider instance.
	 */
	public static function payment_for_form( $form_id ): PaymentProviderInterface {
		$form_id = (int) $form_id;
		$payment  = $form_id > 0 ? get_post_meta( $form_id, '_bif_payment', true ) : array();
		$override = is_array( $payment ) && ! empty( $payment['provider_override'] ) ? (string) $payment['provider_override'] : '';

		return ProviderFactory::create( self::make_instance(), $override );
	}

	/**
	 * Resolve the active payment provider (no per-form override).
	 *
	 * @param string $override Optional explicit provider key ('coinsnap'|'btcpay').
	 * @return PaymentProviderInterface
	 */
	public static function create( string $override = '' ): PaymentProviderInterface {
		return ProviderFactory::create( self::make_instance(), $override );
	}
}
