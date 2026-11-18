<?php
/**
 * Coinsnap provider.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Providers\Payment;

use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as Settings;
use CoinsnapBIF\Util\CoinsnapBIF_Logger;
use CoinsnapBIF\CoinsnapBIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CoinSnap payment provider implementation.
 */
class CoinsnapProvider implements PaymentProviderInterface {
	
    /**
     * Get store data from Coinsnap server
     *
     * @param void.
     * @return array().
     */
    public function get_store(): array {
        //  Coinsnap server connection credentials check
        
        $settings = Settings::get_settings();
        $api_base = rtrim( $settings['coinsnap_api_base'] ? $settings['coinsnap_api_base'] : CoinsnapBIF_Constants::COINSNAP_DEFAULT_API_BASE, '/' );
	$api_key = (string) $settings['coinsnap_api_key'];
	$store   = (string) $settings['coinsnap_store_id'];
        
	
        if ( ! $api_base || ! $api_key || ! $store ) {
            return array('error' => true,'message'=>__('Connection credentials error','coinsnap-bitcoin-invoice-form'));
        }
        
        $url = $api_base . sprintf( CoinsnapBIF_Constants::COINSNAP_STORE_ENDPOINT_V1, rawurlencode( $store ) );
                
        $args = array(
            'method'  => 'GET',
            'headers' => array(
                'X-Api-Key'     => $api_key,
				'Authorization' => 'token ' . $api_key,
				'Content-Type'  => 'application/json',
				'accept'        => 'application/json',
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
            return array('error' => true,'message'=>__('Coinsnap server request error','coinsnap-bitcoin-invoice-form'),'code' => $code, 'result' => $result);
        }
    }
    
    
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
		$api_base = rtrim( $settings['coinsnap_api_base'] ? $settings['coinsnap_api_base'] : CoinsnapBIF_Constants::COINSNAP_DEFAULT_API_BASE, '/' );
		if ( ! $api_key || ! $store_id ) {
			CoinsnapBIF_Logger::error(
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
			$api_base . sprintf( CoinsnapBIF_Constants::COINSNAP_INVOICES_ENDPOINT_V1, rawurlencode( $store_id ) ),
			$api_base . sprintf( CoinsnapBIF_Constants::COINSNAP_INVOICES_ENDPOINT_ALT, rawurlencode( $store_id ) ),
		);
		// Convert amount from cents back to currency units for CoinSnap API
		$amount_in_currency = $amount / 100;

		// Validate currency code
		$supported_currencies = COINSNAP_CURRENCIES;
		if ( ! in_array( $currency, $supported_currencies, true ) ) {
			CoinsnapBIF_Logger::error( 'Unsupported currency for CoinSnap', array(
				'currency' => $currency,
				'form_id'  => $form_id,
			) );
			return array();
		}

		$payload   = array(
			'amount'     => $amount_in_currency,
			'currency'   => $currency,
			'referralCode' => 'D2PVBL',
			'buyerEmail' => isset( $invoice_data['email'] ) ? (string) $invoice_data['email'] : '',
			'metadata'   => array(
				'form_id' => $form_id,
				'transaction_id' => (string) ( $invoice_data['transaction_id'] ?? '' ),
				'email'   => (string) ( $invoice_data['email'] ?? '' ),
				'name'    => (string) ( $invoice_data['name'] ?? '' ),
				'company' => (string) ( $invoice_data['company'] ?? '' ),
				'invoice_number' => (string) ( $invoice_data['invoice_number'] ?? '' ),
				'description' => (string) ( $invoice_data['description'] ?? '' ),
				'wp_version' => (string) ( $invoice_data['wp_version'] ?? '' ),
				'plugin_version' => (string) ( $invoice_data['plugin_version'] ?? '' ),
			),
			'checkout'   => array(
				'defaultPaymentMethod' => 'LightningNetwork',
			),
		);
		//$payload   = apply_filters( 'wpbn_coinsnap_invoice_payload', $payload, $form_id, $invoice_data );
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
		//$args      = apply_filters( 'coinsnapbif_coinsnap_request_args', $args, $form_id );

		CoinsnapBIF_Logger::debug(
			'Coinsnap invoice creation request',
			array(
				'form_id'   => $form_id,
				'amount'    => $amount,
				'currency'  => $currency,
			)
		);

		foreach ( $endpoints as $url ) {
			$res = wp_remote_request( $url, $args );
			/** Action: on Coinsnap response (raw) */
			do_action( 'wpbn_coinsnap_response', $res, $form_id );
			if ( is_wp_error( $res ) ) {
				CoinsnapBIF_Logger::warning(
					'Coinsnap invoice creation failed: HTTP request error',
					array(
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
					CoinsnapBIF_Logger::info(
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
				CoinsnapBIF_Logger::error(
					'Coinsnap invoice creation failed: Invalid response',
					array(
						'form_id'     => $form_id,
						'status_code' => $code,
					)
				);
			}
		}

		CoinsnapBIF_Logger::error(
			'Coinsnap invoice creation failed: All endpoints failed',
			array(
				'form_id'  => $form_id,
				'amount'   => $amount,
				'currency' => $currency,
			)
		);
		return array();
	}

}
