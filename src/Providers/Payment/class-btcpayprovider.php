<?php
/**
 * BTCPay provider.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Providers\Payment;

use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as Settings;
use CoinsnapBIF\CoinsnapBIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BTCPay Server payment provider implementation.
 */
class BTCPayProvider implements PaymentProviderInterface {
	
    /**
     * Get store data from BTCPay server
     *
     * @param void.
     * @return array().
     */
    public function get_store(): array {
        //  BTCPay server connection credentials check
        
        $s = Settings::get_settings();
        $host = rtrim( (string) $s['btcpay_host'], '/' );
	$api_key = (string) $s['btcpay_api_key'];
	$store   = (string) $s['btcpay_store_id'];
	
        if ( ! $host || ! $api_key || ! $store ) {
            return array('error' => true,'message'=>__('Connection credentials error','coinsnap-bitcoin-invoice-form'));
        }
        
        $url = $host . sprintf( CoinsnapBIF_Constants::BTCPAY_STORE_ENDPOINT, rawurlencode( $store ) );
                
        $args = array(
            'method'  => 'GET',
            'headers' => array(
                'Authorization' => 'token ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 20
        );
		
        $res = wp_remote_request( $url, $args );
                
        if ( is_wp_error( $res ) ) {
            return array('error' => true,'message'=>__('Store data error','coinsnap-bitcoin-invoice-form'));
        }
                
        $code = wp_remote_retrieve_response_code( $res );
        $result = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code >= 200 && $code < 300 && is_array($result) ) {
            return ['code' => $code, 'result' => $result];
        }
        else {
            return array('error' => true,'message'=>__('BTCPay server request error','coinsnap-bitcoin-invoice-form'),'code' => $code, 'result' => $result);
        }
    }    
    
        /**
	 * Create a payment invoice.
	 *
	 * @param int    $form_id         Form ID.
	 * @param int    $amount          Amount.
	 * @param string $currency        Currency code.
	 * @param array  $invoice_data   Invoice data.
	 * @return array { invoice_id: string, payment_url: string, expires_at?: int }
	 */
	public function create_invoice( int $form_id, int $amount, string $currency, array $invoice_data ): array {
		$s       = Settings::get_settings();
		$host    = rtrim( (string) $s['btcpay_host'], '/' );
		$api_key = (string) $s['btcpay_api_key'];
		$store   = (string) $s['btcpay_store_id'];
		if ( ! $host || ! $api_key || ! $store ) {
			return array();
		}
		$url     = $host . sprintf( CoinsnapBIF_Constants::BTCPAY_INVOICES_ENDPOINT, rawurlencode( $store ) );
		// Convert from minor units to BTCPay expected units.
		// Our service stores amounts in minor units (e.g., cents for fiat, centisats for SATS).
		// BTCPay expects major units for fiat (e.g., USD) and whole sats for SATS.
		$api_amount = $amount / 100;
		$payload = array(
			'amount'   => (string) $api_amount,
			'currency' => $currency,
			'metadata' => array_merge(
				array(
					'form_id' => $form_id,
					'transaction_id' => (string) ( $invoice_data['transaction_id'] ?? '' ),
					'email'   => (string) $invoice_data['email'],
					'name'    => (string) ( $invoice_data['name'] ?? '' ),
					'company' => (string) ( $invoice_data['company'] ?? '' ),
					'invoice_number' => (string) ( $invoice_data['invoice_number'] ?? '' ),
					'description' => (string) ( $invoice_data['description'] ?? '' ),
					'wp_version' => (string) ( $invoice_data['wp_version'] ?? '' ),
					'plugin_version' => (string) ( $invoice_data['plugin_version'] ?? '' ),
				),
				// orderId, orderNumber, form, buyer and message (CoinsnapBIF_Invoice_Metadata).
				is_array( $invoice_data['metadata'] ?? null ) ? $invoice_data['metadata'] : array()
			),
		);
		$args    = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'token ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 20,
			'body'    => wp_json_encode( $payload ),
		);
		//$args    = apply_filters( 'wpbn_btcpay_request_args', $args, $form_id );
		$res     = wp_remote_request( $url, $args );
		do_action( 'wpbn_btcpay_response', $res, $form_id );
		if ( is_wp_error( $res ) ) {
			return array();
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
			$invoice_id  = isset( $body['id'] ) ? (string) $body['id'] : '';
			$payment_url = isset( $body['checkoutLink'] ) ? (string) $body['checkoutLink'] : '';
			return $invoice_id && $payment_url ? array(
				'invoice_id'  => $invoice_id,
				'payment_url' => $payment_url,
			) : array();
		}
		return array();
	}

}
