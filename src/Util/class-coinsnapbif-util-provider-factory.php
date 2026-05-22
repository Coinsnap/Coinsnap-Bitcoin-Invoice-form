<?php
/**
 * Factory to resolve payment provider implementations.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Util;

use CoinsnapCore\PluginInstance;
use CoinsnapCore\Interfaces\PaymentProviderInterface;
use CoinsnapCore\Util\ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory to resolve payment provider implementations via the shared vendor library.
 */
class CoinsnapBIF_Util_Provider_Factory {

	/**
	 * Build a PluginInstance configured for the Bitcoin Invoice Form plugin.
	 *
	 * @return PluginInstance
	 */
	public static function make_instance(): PluginInstance {
		return new PluginInstance(
			array(
				'plugin_name'              => 'Bitcoin Invoice Form',
				'option_key'               => 'bif_settings',
				'webhook_key'              => 'coinsnapbif_webhook',
				'rest_namespace'           => 'coinsnapbif/v1',
				'referral_code'            => COINSNAPBIF_REFERRAL_CODE,
				'text_domain'              => 'coinsnap-bitcoin-invoice-form',
				'plugin_url'               => COINSNAPBIF_PLUGIN_URL,
				'plugin_dir'               => COINSNAPBIF_PLUGIN_DIR,
				'table_suffix'             => 'coinsnapbif_transactions',
				'source_column'            => 'form_id',
				'log_dir_name'             => 'coinsnapbif-logs',
				'log_file_name'            => 'coinsnapbif.log',
				'menu_slug'                => 'coinsnapbif-settings',
				'btcpay_callback_endpoint' => 'coinsnapBIF-settings-callback',
				'btcpay_app_name'          => 'CoinsnapBIF',
				'plugin_icon_url'          => COINSNAP_CORE_PLUGIN_URL . 'assets/img/coinsnap-icon.svg',
				'help_links'               => array(
					array( 'url' => 'https://coinsnap.io/en/coinsnap-documentation/', 'label' => 'Documentation' ),
					array( 'url' => 'https://coinsnap.io/en/support/', 'label' => 'Support' ),
					array( 'url' => 'https://app.coinsnap.io', 'label' => 'Coinsnap Dashboard' ),
				),
			)
		);
	}

	/**
	 * Create a payment provider using the core vendor factory.
	 *
	 * @param string $override Optional explicit provider key ('coinsnap'|'btcpay').
	 * @return PaymentProviderInterface
	 */
	public static function create( string $override = '' ): PaymentProviderInterface {
		return ProviderFactory::create( self::make_instance(), $override );
	}

	/**
	 * Resolve payment provider for a given form, respecting per-form provider overrides.
	 *
	 * Accepts either an integer or a numeric string for the form ID and
	 * safely casts it to an integer to avoid strict type errors when the
	 * ID originates from database results.
	 *
	 * @param int|string $form_id Form ID.
	 * @return PaymentProviderInterface Provider instance.
	 */
	public static function payment_for_form( $form_id = 0 ): PaymentProviderInterface {
		$form_id  = (int) $form_id;
		$override = '';

		if ( $form_id > 0 ) {
			$payment  = get_post_meta( $form_id, '_coinsnapbif_payment', true );
			$override = ( is_array( $payment ) && ! empty( $payment['provider_override'] ) )
				? $payment['provider_override']
				: '';
		}

		return ProviderFactory::create( self::make_instance(), $override );
	}
}
