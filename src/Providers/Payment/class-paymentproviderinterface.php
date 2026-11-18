<?php
/**
 * Payment provider interface.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Providers\Payment;

/**
 * Payment providers create invoices and read store data. Webhooks and invoice
 * checks are handled by CoinsnapBIF_Security.
 */
interface PaymentProviderInterface {
	/**
	 * Create a payment invoice.
	 *
	 * @param int    $form_id        Form ID.
	 * @param int    $amount         Amount.
	 * @param string $currency       Currency code.
	 * @param array  $invoice_data   Invoice data.
	 * @return array { invoice_id: string, payment_url: string, expires_at?: int }
	 */
	public function create_invoice( int $form_id, int $amount, string $currency, array $invoice_data ): array;

	/**
	 * Getting Store data.
	 *
	 * @return array
	 */
	public function get_store(): array;
}
