<?php
/**
 * Coinsnap provider.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Providers\Payment;

use BitcoinInvoiceForm\Admin\BIF_Admin_Settings as Settings;
use BitcoinInvoiceForm\Util\BIF_Logger;
use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CoinSnap payment provider implementation.
 */
class CoinsnapProvider implements PaymentProviderInterface {
	/**
	 * Create a payment invoice.
	 *
	 * @param int    $form_id        Form ID.
	 * @param int    $amount         Amount.
	 * @param string $currency       Currency code.
	 * @param array  $invoice_data   Invoice data.
	 * @return array { invoice_id: string, payment_url: string, expires_at?: int }
	 */
	public function create_invoice( int $form_id, int $amount, string $currency, array $invoice_data ): array {
		$settings = Settings::get_settings();
		$api_key  = $settings['coinsnap_api_key'];
		$store_id = $settings['coinsnap_store_id'];
		$api_base = rtrim( $settings['coinsnap_api_base'] ? $settings['coinsnap_api_base'] : BIF_Constants::COINSNAP_DEFAULT_API_BASE, '/' );
		if ( ! $api_key || ! $store_id ) {
			BIF_Logger::error(
				'Coinsnap invoice creation failed: Missing API key or store ID',
				array(
					'has_api_key'  => ! empty( $api_key ),
					'has_store_id' => ! empty( $store_id ),
					'form_id'      => $form_id,
				)
			);
			return array();
		}
		$endpoints = array(
			$api_base . sprintf( BIF_Constants::COINSNAP_INVOICES_ENDPOINT_V1, rawurlencode( $store_id ) ),
			$api_base . sprintf( BIF_Constants::COINSNAP_INVOICES_ENDPOINT_ALT, rawurlencode( $store_id ) ),
		);
		// Convert amount from cents back to currency units for CoinSnap API
		$amount_in_currency = $amount / 100;
		
		// Validate currency code
		$supported_currencies = array( 'USD', 'EUR', 'CAD', 'JPY', 'GBP', 'CHF', 'BTC', 'SATS' );
		if ( ! in_array( $currency, $supported_currencies, true ) ) {
			BIF_Logger::error( 'Unsupported currency for CoinSnap', array(
				'currency' => $currency,
				'form_id'  => $form_id,
			) );
			return array();
		}
		
		$payload   = array(
			'amount'     => $amount_in_currency,
			'currency'   => $currency,
			'buyerEmail' => isset( $invoice_data['email'] ) ? (string) $invoice_data['email'] : '',
			'metadata'   => array(
				'form_id' => $form_id,
				'email'   => (string) ( $invoice_data['email'] ?? '' ),
			),
			'checkout'   => array(
				'defaultPaymentMethod' => 'LightningNetwork',
			),
		);
		$payload   = apply_filters( 'wpbn_coinsnap_invoice_payload', $payload, $form_id, $invoice_data );
		$args      = array(
			'method'  => 'POST',
			'headers' => array(
				// New API header per latest docs; keep Authorization for backward compatibility.
				'X-Api-Key'     => $api_key,
				'Authorization' => 'token ' . $api_key,
				'Content-Type'  => 'application/json',
				'accept'        => 'application/json',
			),
			'timeout' => 20,
			'body'    => wp_json_encode( $payload ),
		);
		$args      = apply_filters( 'wpbn_coinsnap_request_args', $args, $form_id );

		BIF_Logger::debug(
			'Coinsnap invoice creation request',
			array(
				'form_id'   => $form_id,
				'amount'    => $amount,
				'currency'  => $currency,
				'email'     => $invoice_data['email'] ?? 'unknown',
				'endpoints' => $endpoints,
			)
		);

		foreach ( $endpoints as $url ) {
			$res = wp_remote_request( $url, $args );
			/** Action: on Coinsnap response (raw) */
			do_action( 'wpbn_coinsnap_response', $res, $form_id );
			if ( is_wp_error( $res ) ) {
				BIF_Logger::warning(
					'Coinsnap invoice creation failed: HTTP request error',
					array(
						'url'     => $url,
						'form_id' => $form_id,
						'error'   => $res->get_error_message(),
					)
				);
				continue;
			}
			$code = wp_remote_retrieve_response_code( $res );
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
				$invoice_id  = isset( $body['id'] ) ? (string) $body['id'] : '';
				$payment_url = isset( $body['checkoutLink'] ) ? (string) $body['checkoutLink'] : '';
				if ( $invoice_id && $payment_url ) {
					BIF_Logger::info(
						'Coinsnap invoice created successfully',
						array(
							'invoice_id' => $invoice_id,
							'form_id'    => $form_id,
							'amount'     => $amount,
							'currency'   => $currency,
						)
					);
					return array(
						'invoice_id'  => $invoice_id,
						'payment_url' => $payment_url,
					);
				}
			} else {
				BIF_Logger::error(
					'Coinsnap invoice creation failed: Invalid response',
					array(
						'url'           => $url,
						'form_id'       => $form_id,
						'status_code'   => $code,
						'response_body' => $body,
					)
				);
			}
		}

		BIF_Logger::error(
			'Coinsnap invoice creation failed: All endpoints failed',
			array(
				'form_id'  => $form_id,
				'amount'   => $amount,
				'currency' => $currency,
			)
		);
		return array();
	}

	/**
	 * Validate webhook or callback.
	 *
	 * @param array $request Parsed request body.
	 * @return array { invoice_id: string, paid: bool, metadata: array }
	 */
	public function handle_webhook( array $request ): array {
		do_action( 'wpbn_coinsnap_webhook_received', $request );
		$invoice_id = isset( $request['invoiceId'] ) ? (string) $request['invoiceId'] : '';
		$type       = isset( $request['type'] ) ? (string) $request['type'] : '';
		$paid       = in_array( $type, array( 'InvoiceSettled', 'PaymentReceived', 'InvoicePaid', 'Settled' ), true );
		return array(
			'invoice_id' => $invoice_id,
			'paid'       => $paid,
			'metadata'   => $request,
		);
	}

	/**
	 * Check invoice payment status with Coinsnap API.
	 *
	 * @param string $invoice_id Invoice ID to check.
	 * @return array { invoice_id: string, paid: bool, status: string, metadata: array }
	 */
	public function check_invoice_status( string $invoice_id ): array {
		$settings = Settings::get_settings();
		$api_key  = $settings['coinsnap_api_key'];
		$store_id = $settings['coinsnap_store_id'];
		$api_base = rtrim( $settings['coinsnap_api_base'] ? $settings['coinsnap_api_base'] : BIF_Constants::COINSNAP_DEFAULT_API_BASE, '/' );
		
		if ( ! $api_key || ! $store_id ) {
			BIF_Logger::error(
				'Coinsnap invoice status check failed: Missing API key or store ID',
				array(
					'invoice_id' => $invoice_id,
					'has_api_key'  => ! empty( $api_key ),
					'has_store_id' => ! empty( $store_id ),
				)
			);
			return array(
				'invoice_id' => $invoice_id,
				'paid'       => false,
				'status'     => 'error',
				'metadata'   => array( 'error' => 'Missing API credentials' ),
			);
		}

		$endpoints = array(
			$api_base . sprintf( '/api/v1/stores/%s/invoices/%s', rawurlencode( $store_id ), rawurlencode( $invoice_id ) ),
			$api_base . sprintf( '/api/stores/%s/invoices/%s', rawurlencode( $store_id ), rawurlencode( $invoice_id ) ),
		);

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'X-Api-Key'     => $api_key,
				'Authorization' => 'token ' . $api_key,
				'Content-Type'  => 'application/json',
				'accept'        => 'application/json',
			),
			'timeout' => 20,
		);

		BIF_Logger::debug(
			'Coinsnap invoice status check request',
			array(
				'invoice_id' => $invoice_id,
				'endpoints'  => $endpoints,
			)
		);

		foreach ( $endpoints as $url ) {
			$res = wp_remote_request( $url, $args );
			do_action( 'wpbn_coinsnap_response', $res, 0 );
			
			if ( is_wp_error( $res ) ) {
				BIF_Logger::warning(
					'Coinsnap invoice status check failed: HTTP request error',
					array(
						'url'        => $url,
						'invoice_id' => $invoice_id,
						'error'      => $res->get_error_message(),
					)
				);
				continue;
			}
			
			$code = wp_remote_retrieve_response_code( $res );
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			
			if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
				$status = isset( $body['status'] ) ? (string) $body['status'] : 'unknown';
				$paid   = in_array( $status, array( 'Settled', 'Paid', 'Complete' ), true );
				
				BIF_Logger::info(
					'Coinsnap invoice status retrieved',
					array(
						'invoice_id' => $invoice_id,
						'status'     => $status,
						'paid'       => $paid,
					)
				);
				
				return array(
					'invoice_id' => $invoice_id,
					'paid'       => $paid,
					'status'     => $status,
					'metadata'   => $body,
				);
			} else {
				BIF_Logger::error(
					'Coinsnap invoice status check failed: Invalid response',
					array(
						'url'           => $url,
						'invoice_id'    => $invoice_id,
						'status_code'   => $code,
						'response_body' => $body,
					)
				);
			}
		}

		BIF_Logger::error(
			'Coinsnap invoice status check failed: All endpoints failed',
			array(
				'invoice_id' => $invoice_id,
			)
		);
		
		return array(
			'invoice_id' => $invoice_id,
			'paid'       => false,
			'status'     => 'error',
			'metadata'   => array( 'error' => 'All API endpoints failed' ),
		);
	}

	/**
	 * Verify webhook HMAC signature.
	 *
	 * @return bool True if signature valid.
	 */
	public static function verify_signature(): bool {
		$settings = Settings::get_settings();
		$secret   = (string) $settings['coinsnap_webhook_secret'];
		if ( ! $secret ) {
			return false;
		}
		$headers = array(
			isset( $_SERVER['HTTP_BTCPAY_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_BTCPAY_SIGNATURE'] ) ) : '',
			isset( $_SERVER['HTTP_X_COINSNAP_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_COINSNAP_SIGNATURE'] ) ) : '',
			isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '',
		);
		$payload = file_get_contents( 'php://input' );
		if ( ! $payload ) {
			$payload = '';
		}
		if ( ! $payload ) {
			return false;
		}
		$raw         = hash_hmac( 'sha256', $payload, $secret );
		$with_prefix = 'sha256=' . $raw;
		foreach ( $headers as $sig ) {
			if ( ! $sig ) {
				continue;
			}
			if ( hash_equals( $raw, $sig ) || hash_equals( $with_prefix, $sig ) ) {
				return true;
			}
		}
		return false;
	}
}
