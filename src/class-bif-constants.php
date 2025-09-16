<?php
/**
 * Constants container.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm;

/**
 * Shared constants for endpoints and namespaces.
 */
class BIF_Constants {
	/** REST namespace for the plugin. */
	public const REST_NAMESPACE              = 'bif/v1';
	public const REST_ROUTE_PAYMENT_COINSNAP = '/payment/coinsnap';
	public const REST_ROUTE_PAYMENT_BTCPAY   = '/payment/btcpay';
	public const REST_ROUTE_STATUS           = '/status/(?P<invoice>[^/]+)';
	public const REST_ROUTE_WEBHOOK_COINSNAP = '/webhook/coinsnap';
	public const REST_ROUTE_WEBHOOK_BTCPAY   = '/webhook/btcpay';

	/** CoinSnap endpoints (relative to API base). */
	public const COINSNAP_DEFAULT_API_BASE      = 'https://api.coinsnap.io';
	public const COINSNAP_INVOICES_ENDPOINT_V1  = '/api/v1/stores/%s/invoices';
	public const COINSNAP_INVOICES_ENDPOINT_ALT = '/api/stores/%s/invoices';

	/** CoinSnap API header names. */
	public const COINSNAP_HEADER_API_KEY = 'X-Api-Key';

	/** BTCPay endpoint (relative to host). */
	public const BTCPAY_INVOICES_ENDPOINT = '/api/v1/stores/%s/invoices';

	/** DB table suffixes. */
	public const INVOICES_TABLE_SUFFIX = 'bif_invoices';

	/** Custom Post Type. */
	public const CPT_INVOICE_FORM = 'bif_invoice_form';

	/** Shortcode. */
	public const SHORTCODE_INVOICE_FORM = 'coinsnap_invoice_form';
}
