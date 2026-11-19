<?php
/**
 * Invoice metadata for an invoice payment.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the metadata keys sent with every invoice, next to the plugin's own snake_case keys
 * (form_id and transaction_id are what the webhook matches on; they are not built here).
 * Plain values (no HTML escaping); empty values are left out.
 */
class CoinsnapBIF_Invoice_Metadata {

	public const PLATFORM   = 'bitcoin-invoice-form';
	public const MAX_LENGTH = 500;

	/**
	 * Metadata for one payment.
	 *
	 * @param array $args transaction_id, invoice_number, form_id, form_title, item_desc, name, email, company,
	 *                    message, discount_total, page_url, version, language.
	 * @return array
	 */
	public static function for_payment( array $args ): array {
		$meta = array();
		// orderId: the transaction ID, unique and shown first on Bitcoin Invoices → Transactions.
		self::put( $meta, 'orderId', (string) ( $args['transaction_id'] ?? '' ) );
		// orderNumber: the merchant's invoice number that is being paid.
		self::put( $meta, 'orderNumber', (string) ( $args['invoice_number'] ?? '' ) );
		self::put( $meta, 'formId', (string) ( $args['form_id'] ?? '' ) );
		self::put( $meta, 'formTitle', (string) ( $args['form_title'] ?? '' ) );
		$desc = trim( (string) ( $args['item_desc'] ?? '' ) );
		if ( '' === $desc && '' !== trim( (string) ( $args['invoice_number'] ?? '' ) ) ) {
			$desc = 'Invoice ' . trim( (string) $args['invoice_number'] );
		}
		self::put( $meta, 'itemDesc', '' !== $desc ? $desc : (string) ( $args['form_title'] ?? '' ) );
		self::put( $meta, 'platform', self::PLATFORM );
		self::put( $meta, 'pluginVersion', (string) ( $args['version'] ?? '' ) );
		self::put( $meta, 'pageUrl', (string) ( $args['page_url'] ?? '' ) );
		self::put( $meta, 'language', (string) ( $args['language'] ?? '' ) );
		$discount = (float) ( $args['discount_total'] ?? 0 );
		if ( $discount > 0 ) {
			$meta['discountTotal'] = number_format( $discount, 2, '.', '' );
		}

		// Personal data. The plugin has no "send customer data" setting, so it is always sent.
		self::put( $meta, 'customerName', (string) ( $args['name'] ?? '' ) );
		self::put( $meta, 'buyerName', (string) ( $args['name'] ?? '' ) );
		self::put( $meta, 'buyerEmail', (string) ( $args['email'] ?? '' ) );
		self::put( $meta, 'buyerCompany', (string) ( $args['company'] ?? '' ) );
		self::put( $meta, 'buyerMessage', (string) ( $args['message'] ?? '' ) );
		return $meta;
	}

	/**
	 * Adds $value under $key unless it is empty, cut to MAX_LENGTH characters.
	 *
	 * @param array  $meta  Metadata.
	 * @param string $key   Key.
	 * @param string $value Value.
	 */
	private static function put( array &$meta, string $key, string $value ): void {
		$value = trim( $value );
		if ( '' === $value ) {
			return;
		}
		if ( function_exists( 'mb_substr' ) ) {
			$value = mb_strlen( $value, 'UTF-8' ) > self::MAX_LENGTH ? mb_substr( $value, 0, self::MAX_LENGTH, 'UTF-8' ) : $value;
		} elseif ( strlen( $value ) > self::MAX_LENGTH ) {
			$value = substr( $value, 0, self::MAX_LENGTH );
		}
		$meta[ $key ] = $value;
	}
}
