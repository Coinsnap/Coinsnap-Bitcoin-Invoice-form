<?php
/**
 * Factory to resolve payment provider implementations.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Util;

use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as Settings;
use CoinsnapBIF\Providers\Payment\PaymentProviderInterface;
use CoinsnapBIF\Providers\Payment\CoinsnapProvider;
use CoinsnapBIF\Providers\Payment\BTCPayProvider;

/**
 * Factory to resolve payment provider implementations.
 */
class CoinsnapBIF_Util_Provider_Factory {
	/**
	 * Resolve payment provider for a given form.
	 *
	 * Accepts either an integer or a numeric string for the form ID and
	 * safely casts it to an integer to avoid strict type errors when the
	 * ID originates from database results.
	 *
	 * @param int|string $form_id Form ID.
	 * @return PaymentProviderInterface Provider instance.
	 */
	public static function payment_for_form( $form_id ): PaymentProviderInterface {
		// Ensure we always work with an integer ID.
		$form_id = (int) $form_id;

		$settings = Settings::get_settings();
		$payment  = get_post_meta( $form_id, '_bif_payment', true );
		$override = is_array( $payment ) && ! empty( $payment['provider_override'] ) ? $payment['provider_override'] : '';
		$key      = $override ? $override : $settings['payment_provider'];
		switch ( $key ) {
			case 'btcpay':
				return new BTCPayProvider();
			case 'coinsnap':
			default:
				return new CoinsnapProvider();
		}
	}
}
