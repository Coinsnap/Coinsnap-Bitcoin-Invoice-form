<?php
/**
 * Payment service for handling invoice payments.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Services;

use BitcoinInvoiceForm\Database\Installer;
use BitcoinInvoiceForm\Util\BIF_Logger;
use BitcoinInvoiceForm\Util\BIF_Util_Provider_Factory;
use BitcoinInvoiceForm\Admin\BIF_Admin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment service for handling invoice payments.
 */
class BIF_Services_Payment_Service {
	/**
	 * Create a new invoice payment.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Form data.
	 * @return array Response data.
	 */
	public static function create_invoice( int $form_id, array $data ): array {
		global $wpdb;

		try {
			// Validate form exists
			$form = get_post( $form_id );
			$valid_post_types = array( 'bif_invoice_form', 'coinsnap_invoice_form' );
			if ( ! $form || ! in_array( $form->post_type, $valid_post_types, true ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid form ID.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			// Get form configuration
			$fields = get_post_meta( $form_id, '_bif_fields', true );
			$payment_config = get_post_meta( $form_id, '_bif_payment', true );
			$email_config = get_post_meta( $form_id, '_bif_email', true );

			// Set defaults
			$fields = wp_parse_args( $fields, array() );
			$payment_config = wp_parse_args( $payment_config, array(
				'amount'     => '',
				'currency'   => 'USD',
				'description' => '',
			) );

			// Calculate amount
			$amount = 0;
			if ( ! empty( $data['bif_amount'] ) ) {
				$amount = floatval( $data['bif_amount'] );
			} elseif ( ! empty( $payment_config['amount'] ) ) {
				$amount = floatval( $payment_config['amount'] );
			} else {
				$settings = BIF_Admin_Settings::get_settings();
				$amount = floatval( $settings['default_amount'] );
			}

			if ( $amount <= 0 ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid amount.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			// Apply discount if configured on the form
			$discount_enabled = $fields['discount_enabled'] ?? '0';
			if ( '1' === $discount_enabled || 'on' === $discount_enabled ) {
				$discount_type  = $fields['discount_type'] ?? 'fixed';
				$discount_value = isset( $fields['discount_value'] ) ? floatval( $fields['discount_value'] ) : 0.0;
				if ( $discount_value > 0 ) {
					if ( 'percent' === $discount_type ) {
						$amount = $amount - ( $amount * ( $discount_value / 100 ) );
					} else { // fixed
						$amount = $amount - $discount_value;
					}
					// Ensure amount doesn't go below zero
					if ( $amount < 0 ) {
						$amount = 0;
					}
				}
			}

			// Convert amount to smallest currency unit (e.g., cents for USD)
			$amount_cents = intval( round( $amount * 100 ) );

			// Get currency - prioritize user selection over form default
			$currency = 'USD'; // Default fallback

			// First check if user selected a currency
			if ( ! empty( $data['bif_currency'] ) ) {
				$currency = sanitize_text_field( $data['bif_currency'] );
			} elseif ( ! empty( $payment_config['currency'] ) ) {
				// Fall back to form's default currency
				$currency = $payment_config['currency'];
			} else {
				// Fall back to global settings
				$settings = BIF_Admin_Settings::get_settings();
				$currency = $settings['default_currency'] ?? 'USD';
			}

			// Generate transaction ID
			$transaction_id = 'bif_' . time() . '_' . wp_generate_password( 8, false );

			// Prepare invoice data
			$invoice_data = array(
				'name'            => sanitize_text_field( $data['bif_name'] ?? '' ),
				'email'           => sanitize_email( $data['bif_email'] ?? '' ),
				'company'         => sanitize_text_field( $data['bif_company'] ?? '' ),
				'invoice_number'  => sanitize_text_field( $data['bif_invoice_number'] ?? '' ),
				'description'     => sanitize_textarea_field( $data['bif_description'] ?? '' ),
			);

			// Create payment provider
			$payment_provider = BIF_Util_Provider_Factory::payment_for_form( $form_id );


			// Create invoice with payment provider
			$payment_result = $payment_provider->create_invoice( $form_id, $amount_cents, $currency, $invoice_data );

			if ( empty( $payment_result ) || empty( $payment_result['invoice_id'] ) ) {
				BIF_Logger::error( 'Failed to create payment invoice', array(
					'form_id' => $form_id,
					'amount'  => $amount,
					'currency' => $currency,
				) );
				return array(
					'success' => false,
					'message' => __( 'Failed to create payment invoice.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			// Save transaction to database
			$table_name = Installer::table_name();

			// Get and sanitize user agent
			$user_agent = '';
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert via $wpdb is appropriate here to persist transaction data.
			$insert_result = $wpdb->insert(
				$table_name,
				array(
					'form_id'            => $form_id,
					'transaction_id'     => $transaction_id,
					'invoice_number'     => $invoice_data['invoice_number'],
					'customer_name'      => $invoice_data['name'],
					'customer_email'     => $invoice_data['email'],
					'customer_company'   => $invoice_data['company'],
					'amount'             => $amount,
					'currency'           => $currency,
					'description'        => $invoice_data['description'],
					'payment_provider'   => $payment_config['provider_override'] ?? $settings['payment_provider'],
					'payment_invoice_id' => $payment_result['invoice_id'],
					'payment_url'        => $payment_result['payment_url'] ?? '',
					'payment_status'     => 'unpaid',
					'ip'                 => self::get_client_ip(),
					'user_agent'         => $user_agent,
					'created_at'         => current_time( 'mysql' ),
					'updated_at'         => current_time( 'mysql' ),
				),
				array(
					'%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
				)
			);

			if ( false === $insert_result ) {
				BIF_Logger::error( 'Failed to save transaction to database', array(
					'form_id' => $form_id,
					'transaction_id' => $transaction_id,
					'wpdb_error' => $wpdb->last_error,
				) );
				return array(
					'success' => false,
					'message' => __( 'Failed to save transaction.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			BIF_Logger::info( 'Invoice created successfully', array(
				'form_id' => $form_id,
				'transaction_id' => $transaction_id,
				'invoice_id' => $payment_result['invoice_id'],
				'amount' => $amount,
				'currency' => $currency,
			) );

			// Get redirect configuration
			$redirect_config = get_post_meta( $form_id, '_bif_redirect', true );
			$redirect_config = wp_parse_args( $redirect_config, array(
				'success_page' => '',
				'error_page'   => '',
				'thank_you_message' => __( 'Thank you! Your payment has been processed successfully.', 'coinsnap-bitcoin-invoice-form' ),
			) );

			return array(
				'success' => true,
				'data'    => array(
					'transaction_id' => $transaction_id,
					'invoice_id'     => $payment_result['invoice_id'],
					'payment_url'    => $payment_result['payment_url'],
					'amount'         => $amount,
					'currency'       => $currency,
					'description'    => $invoice_data['description'],
					'success_page'   => $redirect_config['success_page'],
					'thank_you_message' => $redirect_config['thank_you_message'],
				),
			);

		} catch ( \Exception $e ) {
			BIF_Logger::error( 'Exception in create_invoice', array(
				'form_id' => $form_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );
			return array(
				'success' => false,
				'message' => __( 'An error occurred while creating the invoice.', 'coinsnap-bitcoin-invoice-form' ),
			);
		}
	}

	/**
	 * Handle payment webhook.
	 *
	 * @param string $provider Payment provider name.
	 * @param array  $data     Webhook data.
	 * @return array Response data.
	 */
	public static function handle_webhook( string $provider, array $data ): array {
		global $wpdb;

		try {
			// Create payment provider
			$payment_provider = BIF_Util_Provider_Factory::payment_for_form( 0 );
			if ( $provider === 'btcpay' ) {
				$payment_provider = new \BitcoinInvoiceForm\Providers\Payment\BTCPayProvider();
			} else {
				$payment_provider = new \BitcoinInvoiceForm\Providers\Payment\CoinsnapProvider();
			}

			// Handle webhook
			$webhook_result = $payment_provider->handle_webhook( $data );

			if ( empty( $webhook_result ) || empty( $webhook_result['invoice_id'] ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid webhook data.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			$invoice_id = $webhook_result['invoice_id'];
			$paid = $webhook_result['paid'] ?? false;

			// Update transaction status
			$table_name = Installer::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Update via $wpdb is appropriate here to set status.
			$update_result = $wpdb->update(
				$table_name,
				array(
					'payment_status' => $paid ? 'paid' : 'failed',
					'updated_at'     => current_time( 'mysql' ),
				),
				array(
					'payment_invoice_id' => $invoice_id,
				),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $update_result ) {
				BIF_Logger::error( 'Failed to update transaction status', array(
					'invoice_id' => $invoice_id,
					'paid' => $paid,
					'wpdb_error' => $wpdb->last_error,
				) );
			}

			// If payment is successful, send email notification
			if ( $paid ) {
				self::send_payment_notification( $invoice_id );
			}

			BIF_Logger::info( 'Webhook processed successfully', array(
				'provider' => $provider,
				'invoice_id' => $invoice_id,
				'paid' => $paid,
			) );

			return array(
				'success' => true,
				'message' => __( 'Webhook processed successfully.', 'coinsnap-bitcoin-invoice-form' ),
			);

		} catch ( \Exception $e ) {
			BIF_Logger::error( 'Exception in handle_webhook', array(
				'provider' => $provider,
				'error'    => $e->getMessage(),
				'trace'    => $e->getTraceAsString(),
			) );
			return array(
				'success' => false,
				'message' => __( 'An error occurred while processing the webhook.', 'coinsnap-bitcoin-invoice-form' ),
			);
		}
	}

	/**
	 * Check payment status.
	 *
	 * @param string $invoice_id Invoice ID.
	 * @return array Response data.
	 */
	public static function check_payment_status( string $invoice_id ): array {
		global $wpdb;

		try {
			// Get transaction from database
			$table_name = Installer::table_name();

			// Properly prepare query inline
			$transaction = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE payment_invoice_id = %s",
					$invoice_id
				)
			);


			if ( ! $transaction ) {
				return array(
					'success' => false,
					'message' => __( 'Transaction not found.', 'coinsnap-bitcoin-invoice-form' ),
				);
			}

			// If already paid, return status
			if ( 'paid' === $transaction->payment_status ) {
				return array(
					'success' => true,
					'data'    => array(
						'invoice_id' => $invoice_id,
						'paid'       => true,
						'status'     => 'paid',
					),
				);
			}

			// Check with payment provider
			$payment_provider = BIF_Util_Provider_Factory::payment_for_form( $transaction->form_id );
			$status_result = $payment_provider->check_invoice_status( $invoice_id );

			if ( ! empty( $status_result ) && $status_result['paid'] ) {
				// Update status in database
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Update via $wpdb is appropriate here after status check.
				$wpdb->update(
					$table_name,
					array(
						'payment_status' => 'paid',
						'updated_at'     => current_time( 'mysql' ),
					),
					array(
						'payment_invoice_id' => $invoice_id,
					),
					array( '%s', '%s' ),
					array( '%s' )
				);

				// Send email notification
				self::send_payment_notification( $invoice_id );
			}

			return array(
				'success' => true,
				'data'    => array(
					'invoice_id' => $invoice_id,
					'paid'       => $status_result['paid'] ?? false,
					'status'     => $status_result['status'] ?? 'unknown',
				),
			);

		} catch ( \Exception $e ) {
			BIF_Logger::error( 'Exception in check_payment_status', array(
				'invoice_id' => $invoice_id,
				'error'      => $e->getMessage(),
				'trace'      => $e->getTraceAsString(),
			) );
			return array(
				'success' => false,
				'message' => __( 'An error occurred while checking payment status.', 'coinsnap-bitcoin-invoice-form' ),
			);
		}
	}

	/**
	 * Send payment notification email.
	 *
	 * @param string $invoice_id Invoice ID.
	 */
	private static function send_payment_notification( string $invoice_id ): void {
		global $wpdb;

		// Get transaction details
		$table_name = Installer::table_name();

		// Properly prepare query inline
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE payment_invoice_id = %s",
				$invoice_id
			)
		);


		if ( ! $transaction ) {
			return;
		}

		// Get form email configuration
		$email_config = get_post_meta( $transaction->form_id, '_bif_email', true );
		$email_config = wp_parse_args( $email_config, array(
			'admin_email'     => get_option( 'admin_email' ),
			'email_subject'   => __( 'New Invoice Payment Received', 'coinsnap-bitcoin-invoice-form' ),
			'email_template'  => __( 'A new invoice payment has been received:

Invoice Number: {invoice_number}
Customer: {customer_name}
Email: {customer_email}
Amount: {amount} {currency}
Payment Status: {payment_status}

Payment Details:
Transaction ID: {transaction_id}
Payment Provider: {payment_provider}

Description: {description}', 'coinsnap-bitcoin-invoice-form' ),
		) );

		// Replace placeholders in email template
		$placeholders = array(
			'{invoice_number}'  => $transaction->invoice_number,
			'{customer_name}'   => $transaction->customer_name,
			'{customer_email}'  => $transaction->customer_email,
			'{amount}'          => $transaction->amount,
			'{currency}'        => $transaction->currency,
			'{payment_status}'  => ucfirst( $transaction->payment_status ),
			'{transaction_id}'  => $transaction->transaction_id,
			'{payment_provider}' => ucfirst( $transaction->payment_provider ),
			'{description}'     => $transaction->description,
		);

		$email_subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $email_config['email_subject'] );
		$email_message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $email_config['email_template'] );

		// Send email
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		wp_mail( $email_config['admin_email'], $email_subject, $email_message, $headers );

		BIF_Logger::info( 'Payment notification email sent', array(
			'invoice_id' => $invoice_id,
			'admin_email' => $email_config['admin_email'],
		) );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				// Unslash and sanitize the server variable
				$ip_string = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				foreach ( explode( ',', $ip_string ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		// Return sanitized REMOTE_ADDR or fallback
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '0.0.0.0';
	}
}
