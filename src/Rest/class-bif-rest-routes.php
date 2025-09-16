<?php
/**
 * REST API routes.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Rest;

use BitcoinInvoiceForm\BIF_Constants;
use BitcoinInvoiceForm\Services\BIF_Services_Payment_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API routes registration and handling.
 */
class BIF_Rest_Routes {
	/**
	 * Register REST API routes.
	 */
	public static function register(): void {
		// Payment creation endpoint
		register_rest_route(
			BIF_Constants::REST_NAMESPACE,
			'/payment/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_payment' ),
				'permission_callback' => '__return_true',
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
			BIF_Constants::REST_NAMESPACE,
			BIF_Constants::REST_ROUTE_STATUS,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'check_payment_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'invoice' => array(
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// CoinSnap webhook endpoint
		register_rest_route(
			BIF_Constants::REST_NAMESPACE,
			BIF_Constants::REST_ROUTE_WEBHOOK_COINSNAP,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_coinsnap_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// BTCPay webhook endpoint
		register_rest_route(
			BIF_Constants::REST_NAMESPACE,
			BIF_Constants::REST_ROUTE_WEBHOOK_BTCPAY,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_btcpay_webhook' ),
				'permission_callback' => '__return_true',
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
				'message' => __( 'Invalid nonce.', 'bif' ),
			), 403 );
		}

		$form_id = $request->get_param( 'form_id' );
		$form_data = $request->get_params();

		// Remove nonce and form_id from form data
		unset( $form_data['_wpnonce'] );
		unset( $form_data['form_id'] );

		$result = BIF_Services_Payment_Service::create_invoice( $form_id, $form_data );

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
		$result = BIF_Services_Payment_Service::check_payment_status( $invoice_id );

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	/**
	 * Handle CoinSnap webhook.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function handle_coinsnap_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();
		$result = BIF_Services_Payment_Service::handle_webhook( 'coinsnap', $data );

		// Always return 200 to acknowledge webhook receipt
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle BTCPay webhook.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public static function handle_btcpay_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();
		$result = BIF_Services_Payment_Service::handle_webhook( 'btcpay', $data );

		// Always return 200 to acknowledge webhook receipt
		return new \WP_REST_Response( $result, 200 );
	}
}
