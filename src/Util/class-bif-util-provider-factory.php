<?php
/**
 * Factory to resolve payment provider implementations.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Util;

use BitcoinInvoiceForm\Admin\BIF_Admin_Settings as Settings;
use BitcoinInvoiceForm\Providers\Payment\PaymentProviderInterface;
use BitcoinInvoiceForm\Providers\Payment\CoinsnapProvider;
use BitcoinInvoiceForm\Providers\Payment\BTCPayProvider;

/**
 * Factory to resolve payment provider implementations.
 */
class BIF_Util_Provider_Factory {
	/**
	 * Resolve payment provider for a given form.
	 *
	 * @param int $form_id Form ID.
	 * @return PaymentProviderInterface Provider instance.
	 */
	public static function payment_for_form( int $form_id ): PaymentProviderInterface {
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
