<?php
/**
 * REST API routes.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Rest;

use CoinsnapBIF\CoinsnapBIF_Constants;
use CoinsnapBIF\Services\CoinsnapBIF_Services_Payment_Service;
use CoinsnapBIF\Services\CoinsnapBIF_Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API routes registration and handling.
 */
class CoinsnapBIF_Rest_Routes {
	/**
	 * Register REST API routes.
	 */
	public static function register(): void {
		// Payment creation endpoint
		register_rest_route(
			CoinsnapBIF_Constants::REST_NAMESPACE,
			'/payment/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_payment' ),
				'permission_callback' => '__return_true', // public: visitors pay without an account; currency comes from the form, the amount is recorded server-side
				'args'                => array(
					'form_id' => array(
						'required' => true,
						'type'     => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Payment status check endpoint
		register_rest_route(
			CoinsnapBIF_Constants::REST_NAMESPACE,
			CoinsnapBIF_Constants::REST_ROUTE_STATUS,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'check_payment_status' ),
				'permission_callback' => '__return_true', // public: returns only paid yes/no for one invoice
				'args'                => array(
					'invoice' => array(
						'required' => true,
						'type'     => 'string',
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $value );
						},
					),
				),
			)
		);

		// CoinSnap webhook endpoint
		register_rest_route(
			CoinsnapBIF_Constants::REST_NAMESPACE,
			CoinsnapBIF_Constants::REST_ROUTE_WEBHOOK_COINSNAP,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_coinsnap_webhook' ),
				'permission_callback' => array( CoinsnapBIF_Security::class, 'check_coinsnap_webhook' ),
			)
		);

		// BTCPay webhook endpoint
		register_rest_route(
			CoinsnapBIF_Constants::REST_NAMESPACE,
			CoinsnapBIF_Constants::REST_ROUTE_WEBHOOK_BTCPAY,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_btcpay_webhook' ),
				'permission_callback' => array( CoinsnapBIF_Security::class, 'check_btcpay_webhook' ),
			)
		);
	}

	/**
	 * Create payment endpoint.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function create_payment( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify nonce
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'coinsnap-bitcoin-invoice-form' ),
			), 403 );
		}

		$form_id = $request->get_param( 'form_id' );
		$form_data = $request->get_params();

		// Remove nonce and form_id from form data
		unset( $form_data['_wpnonce'] );
		unset( $form_data['form_id'] );

		$result = CoinsnapBIF_Services_Payment_Service::create_invoice( $form_id, $form_data );

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	/**
	 * Check payment status endpoint.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function check_payment_status( \WP_REST_Request $request ): \WP_REST_Response {
		$invoice_id = $request->get_param( 'invoice' );
		$result = CoinsnapBIF_Services_Payment_Service::check_payment_status( $invoice_id );

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	/**
	 * Handle CoinSnap webhook. The signature was checked by the permission callback.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function handle_coinsnap_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( null, CoinsnapBIF_Services_Payment_Service::handle_webhook( 'coinsnap', $request->get_body() ) );
	}

	/**
	 * Handle BTCPay webhook. The signature was checked by the permission callback.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function handle_btcpay_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( null, CoinsnapBIF_Services_Payment_Service::handle_webhook( 'btcpay', $request->get_body() ) );
	}
}
