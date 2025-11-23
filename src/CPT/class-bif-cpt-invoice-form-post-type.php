<?php
/**
 * Custom Post Type for invoice forms and its meta UI.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\CPT;

use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Post Type for invoice forms and its meta UI.
 */
class BIF_CPT_Invoice_Form_Post_Type {
	public const POST_TYPE = BIF_Constants::CPT_INVOICE_FORM;

	/** Register CPT and metabox hooks. */
	public static function register(): void {
		$labels = array(
			'name'               => __( 'Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			'singular_name'      => __( 'Invoice Form', 'coinsnap-bitcoin-invoice-form' ),
			'add_new'            => __( 'Add New', 'coinsnap-bitcoin-invoice-form' ),
			'add_new_item'       => __( 'Add New Invoice Form', 'coinsnap-bitcoin-invoice-form' ),
			'edit_item'          => __( 'Edit Invoice Form', 'coinsnap-bitcoin-invoice-form' ),
			'new_item'           => __( 'New Invoice Form', 'coinsnap-bitcoin-invoice-form' ),
			'all_items'          => __( 'Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			'view_item'          => __( 'View Invoice Form', 'coinsnap-bitcoin-invoice-form' ),
			'search_items'       => __( 'Search Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			'not_found'          => __( 'No forms found', 'coinsnap-bitcoin-invoice-form' ),
			'not_found_in_trash' => __( 'No forms found in Trash', 'coinsnap-bitcoin-invoice-form' ),
			'menu_name'          => __( 'Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => $labels,
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => 'bif-transactions',
				'menu_position' => 57,
				'menu_icon'     => 'dashicons-money-alt',
				'supports'      => array( 'title' ),
				'has_archive'   => false,
				'show_in_rest'  => false,
			)
		);

		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/** Register meta boxes for the CPT. */
	public static function register_metaboxes(): void {
		add_meta_box( 'bif_fields', __( 'Invoice Fields', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_fields_metabox' ), self::POST_TYPE, 'normal' );
		add_meta_box( 'bif_payment', __( 'Payment Configuration', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_payment_metabox' ), self::POST_TYPE, 'side' );
		add_meta_box( 'bif_email', __( 'Admin Email Settings', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_email_metabox' ), self::POST_TYPE, 'normal' );
		add_meta_box( 'bif_email_customer', __( 'Customer Email Settings', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_customer_email_metabox' ), self::POST_TYPE, 'normal' );
		add_meta_box( 'bif_redirect', __( 'Redirect Settings', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_redirect_metabox' ), self::POST_TYPE, 'side' );
		add_meta_box( 'bif_shortcode', __( 'Shortcode', 'coinsnap-bitcoin-invoice-form' ), array( __CLASS__, 'render_shortcode_metabox' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Render the fields metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_fields_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bif_save_form_' . $post->ID, 'bif_form_nonce' );

		$defaults = array(
			'invoice_recipient_enabled'  => '1',
			'invoice_recipient_required' => '1',
			'invoice_recipient_label'    => __( 'Invoice Recipient', 'coinsnap-bitcoin-invoice-form' ),
			'invoice_recipient_order'    => '10',
			'invoice_number_enabled' => '1',
			'invoice_number_required' => '1',
			'invoice_number_label' => __( 'Invoice Number', 'coinsnap-bitcoin-invoice-form' ),
			'invoice_number_order' => '20',
			'amount_enabled'      => '1',
			'amount_required'     => '1',
			'amount_label'        => __( 'Amount', 'coinsnap-bitcoin-invoice-form' ),
			'amount_order'        => '30',
			'currency_enabled'    => '1',
			'currency_required'   => '1',
			'currency_label'      => __( 'Currency', 'coinsnap-bitcoin-invoice-form' ),
			'currency_order'      => '40',
			'email_enabled'       => '1',
			'email_required'      => '0',
			'email_label'         => __( 'Email', 'coinsnap-bitcoin-invoice-form' ),
			'email_order'         => '50',
			'company_enabled'     => '0',
			'company_required'    => '0',
			'company_label'       => __( 'Company', 'coinsnap-bitcoin-invoice-form' ),
			'company_order'       => '60',
			'description_enabled' => '1',
			'description_required' => '0',
			'description_label'   => __( 'Your message to the Invoice receiver', 'coinsnap-bitcoin-invoice-form' ),
			'description_order'   => '70',
			'button_text'         => __( 'Pay Invoice with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
			'discount_enabled'    => '1',
			'discount_type'       => 'percentage',
			'discount_value'      => '10',
			'discount_notice'     => '',
		);

		$values = get_post_meta( $post->ID, '_bif_fields', true );
		$values = wp_parse_args( $values, $defaults );

		// Backward compatibility: map legacy 'name_*' settings to 'invoice_recipient_*' if present in saved values
		if ( isset( $values['name_enabled'] ) && ! isset( $values['invoice_recipient_enabled'] ) ) {
			$values['invoice_recipient_enabled'] = $values['name_enabled'];
		}
		if ( isset( $values['name_required'] ) && ! isset( $values['invoice_recipient_required'] ) ) {
			$values['invoice_recipient_required'] = $values['name_required'];
		}
		if ( isset( $values['name_label'] ) && ! isset( $values['invoice_recipient_label'] ) ) {
			$values['invoice_recipient_label'] = ( $values['name_label'] === __( 'Name', 'coinsnap-bitcoin-invoice-form' ) ) ? __( 'Invoice Recipient', 'coinsnap-bitcoin-invoice-form' ) : $values['name_label'];
		}
		if ( isset( $values['name_order'] ) && ! isset( $values['invoice_recipient_order'] ) ) {
			$values['invoice_recipient_order'] = $values['name_order'];
		}

		// Build a placeholder for discount notice using the same default text as on the frontend
		$discount_placeholder = '';
		$disc_type  = $values['discount_type'] ?? 'fixed';
		$disc_value = isset( $values['discount_value'] ) ? floatval( $values['discount_value'] ) : 0.0;
		if ( $disc_value > 0 ) {
			$payment = get_post_meta( $post->ID, '_bif_payment', true );
			$payment = wp_parse_args( $payment, array(
				'currency' => 'USD',
			) );
			$current_currency = (string) ( $payment['currency'] ?? 'USD' );
			$val_str = rtrim( rtrim( number_format( $disc_value, 2, '.', '' ), '0' ), '.' );
			if ( 'percent' === $disc_type ) {
				/* translators: %s is the discount percentage value (without the percent sign). */
				$discount_placeholder = sprintf( __( 'Good news! A discount of %s%% will be applied to the amount at checkout.', 'coinsnap-bitcoin-invoice-form' ), $val_str );
			} else {
                            $discount_placeholder = sprintf(
                                /* translators: 1: fixed discount amount; 2: currency code. */
                                 __( 'Good news! A fixed discount of %1$s %2$s will be applied in the selected currency.', 'coinsnap-bitcoin-invoice-form' ), $val_str, $current_currency );
			}
		} else {
			$discount_placeholder = __( 'Good news! A Bitcoin discount will be applied at checkout.', 'coinsnap-bitcoin-invoice-form' );
		}

		echo '<div class="bif-fields-config">';

		// Name field
		self::render_toggle_row( 'invoice_recipient', $values );

		// Invoice Number field
		self::render_toggle_row( 'invoice_number', $values );

		// Amount field
		self::render_toggle_row( 'amount', $values );

		// Currency field
		self::render_toggle_row( 'currency', $values );

		// Email field
		self::render_toggle_row( 'email', $values );

		// Company field
		self::render_toggle_row( 'company', $values );

		// Description field
		self::render_toggle_row( 'description', $values );

		// Button text
		echo '<div class="bif-button-text-config" style="margin-bottom:20px;padding:15px;border:1px solid #ddd;border-radius:4px;background:#fafafa;">';
		echo '<h4 style="margin:0 0 10px 0;font-weight:bold;">' . esc_html__( 'Submit Button', 'coinsnap-bitcoin-invoice-form' ) . '</h4>';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<label for="button_text" style="margin:0;font-weight:500;white-space:nowrap;">' . esc_html__( 'Button Text', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<input type="text" id="button_text" name="bif_fields[button_text]" value="' . esc_attr( $values['button_text'] ) . '" style="flex:1;min-width:200px;padding:6px 10px;border:1px solid #ccc;border-radius:3px;" placeholder="' . esc_attr__( 'Pay with Bitcoin', 'coinsnap-bitcoin-invoice-form' ) . '" />';
		echo '</div>';
		echo '<p class="description" style="margin:8px 0 0 0;color:#666;font-size:13px;">' . esc_html__( 'Customize the text displayed on the submit button.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		echo '</div>';

		// Discount settings
		echo '<fieldset class="bif-discount-config" style="border:1px solid #ddd;padding:15px;margin:15px 0;border-radius:4px;background:#fafafa;">';
		echo '<legend style="font-weight:bold;padding:0 8px;background:#fff;border-radius:3px;">' . esc_html__( 'Bitcoin Discount', 'coinsnap-bitcoin-invoice-form' ) . '</legend>';
		echo '<div class="bif-field-options" style="display:flex;flex-wrap:wrap;gap:15px;align-items:center;margin-top:10px;">';
		echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:5px;">';
		echo '<input type="checkbox" name="bif_fields[discount_enabled]" value="1" ' . checked( '1', $values['discount_enabled'] ?? '0', false ) . ' id="discount_enabled" />';
		echo '<label for="discount_enabled" style="margin:0;font-weight:500;">' . esc_html__( 'Enable Bitcoin Discount', 'coinsnap-bitcoin-invoice-form' ) . '</label>';
		echo '</div>';
		echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:8px;">';
		echo '<label for="discount_type" style="margin:0;font-weight:500;white-space:nowrap;">' . esc_html__( 'Type', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<select name="bif_fields[discount_type]" id="discount_type" style="min-width:120px;padding:4px 8px;border:1px solid #ccc;border-radius:3px;">';
		echo '<option value="fixed" ' . selected( 'fixed', $values['discount_type'] ?? 'fixed', false ) . '>' . esc_html__( 'Fixed amount', 'coinsnap-bitcoin-invoice-form' ) . '</option>';
		echo '<option selected value="percent" ' . selected( 'percent', $values['discount_type'] ?? 'fixed', false ) . '>' . esc_html__( 'Percentage', 'coinsnap-bitcoin-invoice-form' ) . '</option>';
		echo '</select>';
		echo '</div>';
		echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:8px;">';
		echo '<label for="discount_value" style="margin:0;font-weight:500;white-space:nowrap;">' . esc_html__( 'Amount', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<input type="number" step="0.01" min="0" name="bif_fields[discount_value]" value="' . esc_attr( $values['discount_value'] ?? '0' ) . '" id="discount_value" style="width:120px;padding:4px 8px;border:1px solid #ccc;border-radius:3px;" />';
		echo '</div>';
		echo '</div>';
		echo '<div class="bif-option-group" style="display:flex;flex-direction:column;gap:6px;margin-top:12px;">';
		echo '<label for="discount_notice" style="margin:0;font-weight:500;">' . esc_html__( 'Customer-facing discount notice (optional)', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<textarea id="discount_notice" name="bif_fields[discount_notice]" style="width:100%;min-height:60px;padding:6px 8px;border:1px solid #ccc;border-radius:3px;" placeholder="' . esc_attr( $discount_placeholder ) . '">' . esc_textarea( $values['discount_notice'] ?? '' ) . '</textarea>';
		echo '<p class="description" style="margin:0;color:#666;font-size:13px;">' . esc_html__( 'Shown on the form when discount is enabled. Leave empty to use the automatic default message.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		echo '</div>';
		echo '<p class="description" style="margin:8px 0 0 0;color:#666;font-size:13px;">' . esc_html__( 'If enabled, the discount will be applied to the invoice amount before creating the payment. Use percentage for relative discounts (e.g., 10%) or fixed amount for absolute discounts (e.g., 5.00).', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		echo '</fieldset>';

		echo '</div>';
	}

	/**
	 * Render a toggle row for a field.
	 *
	 * @param string $field_name Field name.
	 * @param array  $values     Field values.
	 */
	private static function render_toggle_row( string $field_name, array $values ): void {
		$enabled_key    = $field_name . '_enabled';
		$required_key   = $field_name . '_required';
		$label_key      = $field_name . '_label';
		$order_key      = $field_name . '_order';

		$enabled  = $values[ $enabled_key ] ?? '0';
		$required = $values[ $required_key ] ?? '0';
		$label    = $values[ $label_key ] ?? ucfirst( str_replace( '_', ' ', $field_name ) );
		$order    = $values[ $order_key ] ?? '10';

		// Core fields that are always required and don't need enabled/required checkboxes
		$core_required_fields = array( 'invoice_recipient', 'invoice_number', 'amount', 'currency');

		echo '<fieldset class="bif-field-config" style="border:1px solid #ddd;padding:15px;margin:15px 0;border-radius:4px;background:#fafafa;">';
		$legend_text = ( 'description' === $field_name )
			? __( 'Message to the Invoice receiver', 'coinsnap-bitcoin-invoice-form' )
			: ucwords( str_replace( '_', ' ', $field_name ) );
		echo '<legend style="font-weight:bold;padding:0 8px;background:#fff;border-radius:3px;">' . esc_html( $legend_text ) . '</legend>';

		echo '<div class="bif-field-options" style="display:flex;flex-wrap:wrap;gap:15px;align-items:center;margin-top:10px;">';

		// Only show enabled checkbox for non-core fields
		if ( ! in_array( $field_name, $core_required_fields, true ) ) {
			echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:5px;">';
			echo '<input type="checkbox" name="bif_fields[' . esc_attr( $enabled_key ) . ']" value="1" ' . checked( '1', $enabled, false ) . ' id="' . esc_attr( $enabled_key ) . '" />';
			echo '<label for="' . esc_attr( $enabled_key ) . '" style="margin:0;font-weight:500;">' . esc_html__( 'Enabled', 'coinsnap-bitcoin-invoice-form' ) . '</label>';
			echo '</div>';

			echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:5px;">';
			echo '<input type="checkbox" name="bif_fields[' . esc_attr( $required_key ) . ']" value="1" ' . checked( '1', $required, false ) . ' id="' . esc_attr( $required_key ) . '" />';
			echo '<label for="' . esc_attr( $required_key ) . '" style="margin:0;font-weight:500;">' . esc_html__( 'Required', 'coinsnap-bitcoin-invoice-form' ) . '</label>';
			echo '</div>';
		}

		echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:8px;">';
		echo '<label for="' . esc_attr( $label_key ) . '" style="margin:0;font-weight:500;white-space:nowrap;">' . esc_html__( 'Label', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<input type="text" name="bif_fields[' . esc_attr( $label_key ) . ']" value="' . esc_attr( $label ) . '" id="' . esc_attr( $label_key ) . '" style="min-width:150px;padding:4px 8px;border:1px solid #ccc;border-radius:3px;" />';
		echo '</div>';

		echo '<div class="bif-option-group" style="display:flex;align-items:center;gap:8px;">';
		echo '<label for="' . esc_attr( $order_key ) . '" style="margin:0;font-weight:500;white-space:nowrap;">' . esc_html__( 'Order', 'coinsnap-bitcoin-invoice-form' ) . ':</label>';
		echo '<input type="number" name="bif_fields[' . esc_attr( $order_key ) . ']" value="' . esc_attr( $order ) . '" id="' . esc_attr( $order_key ) . '" style="width:80px;padding:4px 8px;border:1px solid #ccc;border-radius:3px;" min="0" max="999" />';
		echo '</div>';

		echo '</div>';
		echo '</fieldset>';
	}

	/**
	 * Render the payment metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_payment_metabox( \WP_Post $post ): void {
		$defaults = array(
			'provider_override' => '',
			'amount'           => '',
			'currency'         => 'USD',
			'description'      => '',
		);

		$values = get_post_meta( $post->ID, '_bif_payment', true );
		$values = wp_parse_args( $values, $defaults );

		echo '<div class="bif-payment-config">';
		echo '<p><label for="provider_override">' . esc_html__( 'Payment Gateway Override', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<select id="provider_override" name="bif_payment[provider_override]" style="width:100%;">';
		echo '<option value="">' . esc_html__( 'Use Default', 'coinsnap-bitcoin-invoice-form' ) . '</option>';
		echo '<option value="coinsnap" ' . selected( $values['provider_override'], 'coinsnap', false ) . '>' . esc_html__( 'CoinSnap', 'coinsnap-bitcoin-invoice-form' ) . '</option>';
		echo '<option value="btcpay" ' . selected( $values['provider_override'], 'btcpay', false ) . '>' . esc_html__( 'BTCPay Server', 'coinsnap-bitcoin-invoice-form' ) . '</option>';
		echo '</select>';

		echo '<p><label for="amount">' . esc_html__( 'Default Amount', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="number" id="amount" name="bif_payment[amount]" value="' . esc_attr( $values['amount'] ) . '" style="width:100%;" step="0.01" min="0" />';

		echo '<p><label for="currency">' . esc_html__( 'Currency (per form)', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<select id="currency" name="bif_payment[currency]" style="width:100%;">';
		echo '<option value="USD" ' . selected( $values['currency'], 'USD', false ) . '>USD</option>';
		echo '<option value="EUR" ' . selected( $values['currency'], 'EUR', false ) . '>EUR</option>';
		echo '<option value="CHF" ' . selected( $values['currency'], 'CHF', false ) . '>CHF</option>';
		echo '<option value="JPY" ' . selected( $values['currency'], 'JPY', false ) . '>JPY</option>';
		echo '<option value="SATS" ' . selected( $values['currency'], 'SATS', false ) . '>SATS</option>';
		echo '</select>';

		echo '<p><label for="description">' . esc_html__( 'Default Description', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<textarea id="description" name="bif_payment[description]" style="width:100%;height:60px;">' . esc_textarea( $values['description'] ) . '</textarea>';

		echo '</div>';
	}

	/**
	 * Render the email metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_email_metabox( \WP_Post $post ): void {
		$defaults = array(
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
		);

		$values = get_post_meta( $post->ID, '_bif_email', true );
		$values = wp_parse_args( $values, $defaults );

		echo '<div class="bif-email-config">';
		echo '<p><label for="admin_email">' . esc_html__( 'Admin Email', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="email" id="admin_email" name="bif_email[admin_email]" value="' . esc_attr( $values['admin_email'] ) . '" style="width:100%;" />';

		echo '<p><label for="email_subject">' . esc_html__( 'Email Subject (to Admin)', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="text" id="email_subject" name="bif_email[email_subject]" value="' . esc_attr( $values['email_subject'] ) . '" style="width:100%;" />';

		echo '<p><label for="email_template">' . esc_html__( 'Email Template (to Admin)', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<textarea id="email_template" name="bif_email[email_template]" style="width:100%;height:200px;">' . esc_textarea( $values['email_template'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Available placeholders: {invoice_number}, {customer_name}, {customer_email}, {amount}, {currency}, {payment_status}, {transaction_id}, {payment_provider}, {description}', 'coinsnap-bitcoin-invoice-form' ) . '</p>';

		echo '</div>';
	}

	/**
	 * Render the customer email metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_customer_email_metabox( \WP_Post $post ): void {
		$defaults = array(
			'customer_email_enabled' => '1',
			'customer_email_subject' => __( 'Your payment receipt for invoice {invoice_number}', 'coinsnap-bitcoin-invoice-form' ),
			'customer_email_template' => __( "Hello {customer_name},\n\nThank you for your payment. Here are the details of your receipt:\n\nInvoice Number: {invoice_number}\nAmount Paid: {amount} {currency}\nPayment Status: {payment_status}\n\nDescription: {description}\n\nTransaction ID: {transaction_id}\nPayment Provider: {payment_provider}\n\nIf you have any questions, reply to this email.\n\nBest regards,\n{site_name}", 'coinsnap-bitcoin-invoice-form' ),
		);

		$values = get_post_meta( $post->ID, '_bif_email_customer', true );
		$values = wp_parse_args( $values, $defaults );

		echo '<div class="bif-email-config">';
		echo '<p><label for="customer_email_enabled">' . esc_html__( 'Send email to customer', 'coinsnap-bitcoin-invoice-form' ) . ':</label> ';
		$checked = ( '1' === (string) ( $values['customer_email_enabled'] ?? '0' ) || 'on' === (string) ( $values['customer_email_enabled'] ?? '' ) ) ? 'checked' : '';
		echo '<input type="checkbox" id="customer_email_enabled" name="bif_email_customer[customer_email_enabled]" value="1" ' . esc_attr($checked) . ' />';
		echo '</p>';

		echo '<p><label for="customer_email_subject">' . esc_html__( 'Customer Email Subject', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="text" id="customer_email_subject" name="bif_email_customer[customer_email_subject]" value="' . esc_attr( $values['customer_email_subject'] ) . '" style="width:100%;" />';

		echo '<p><label for="customer_email_template">' . esc_html__( 'Customer Email Template', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<textarea id="customer_email_template" name="bif_email_customer[customer_email_template]" style="width:100%;height:200px;">' . esc_textarea( $values['customer_email_template'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Available placeholders: {invoice_number}, {customer_name}, {customer_email}, {amount}, {currency}, {payment_status}, {transaction_id}, {payment_provider}, {description}, {site_name}', 'coinsnap-bitcoin-invoice-form' ) . '</p>';

		echo '</div>';
	}

	/**
	 * Render the redirect metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_redirect_metabox( \WP_Post $post ): void {
		$defaults = array(
			'success_page' => '',
			'error_page'   => '',
			'thank_you_message' => esc_html__( 'Thank you! Your payment has been processed successfully.', 'coinsnap-bitcoin-invoice-form' ),
		);

		$values = get_post_meta( $post->ID, '_bif_redirect', true );
		$values = wp_parse_args( $values, $defaults );

		echo '<div class="bif-redirect-config">';
		echo '<p><label for="success_page">' . esc_html__( 'Success Page URL', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="url" id="success_page" name="bif_redirect[success_page]" value="' . esc_attr( $values['success_page'] ) . '" style="width:100%;" placeholder="https://example.com/thank-you" />';

		echo '<p><label for="error_page">' . esc_html__( 'Error Page URL', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<input type="url" id="error_page" name="bif_redirect[error_page]" value="' . esc_attr( $values['error_page'] ) . '" style="width:100%;" placeholder="https://example.com/payment-error" />';

		echo '<p><label for="thank_you_message">' . esc_html__( 'Thank You Message', 'coinsnap-bitcoin-invoice-form' ) . ':</label></p>';
		echo '<textarea id="thank_you_message" name="bif_redirect[thank_you_message]" style="width:100%;height:80px;">' . esc_textarea( $values['thank_you_message'] ) . '</textarea>';

		echo '</div>';
	}

	/**
	 * Render the shortcode metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_shortcode_metabox( \WP_Post $post ): void {
		$shortcode = '[' . BIF_Constants::SHORTCODE_INVOICE_FORM . ' id="' . $post->ID . '"]';
		echo '<div class="bif-shortcode-config">';
		echo '<p><strong>' . esc_html__( 'Shortcode:', 'coinsnap-bitcoin-invoice-form' ) . '</strong></p>';
		echo '<input type="text" value="' . esc_attr( $shortcode ) . '" readonly style="width:100%;font-family:monospace;background:#f1f1f1;" onclick="this.select();" />';
		echo '<p class="description">' . esc_html__( 'Copy this shortcode and paste it into any page or post to display this invoice form.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Save meta data for the form.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bif_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bif_form_nonce'] ) ), 'bif_save_form_' . $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save fields
		if ( isset( $_POST['bif_fields'] ) ) {
			$fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['bif_fields'] ) );

			// Core fields that are always required
			$core_required_fields = array( 'invoice_recipient', 'invoice_number', 'amount', 'currency' );

			// Ensure checkbox values are properly set (unchecked checkboxes don't send values)
			$field_names = array( 'invoice_recipient', 'email', 'company', 'invoice_number', 'amount', 'currency', 'description' );
			foreach ( $field_names as $field_name ) {
				$enabled_key = $field_name . '_enabled';
				$required_key = $field_name . '_required';

				// Set to '0' if not present (unchecked checkbox)
				if ( ! isset( $fields[ $enabled_key ] ) ) {
					$fields[ $enabled_key ] = '0';
				}
				if ( ! isset( $fields[ $required_key ] ) ) {
					$fields[ $required_key ] = '0';
				}

				// Force core fields to always be enabled and required
				if ( in_array( $field_name, $core_required_fields, true ) ) {
					$fields[ $enabled_key ] = '1';
					$fields[ $required_key ] = '1';
				}
			}

			// Handle discount settings
			if ( ! isset( $fields['discount_enabled'] ) ) {
				$fields['discount_enabled'] = '0';
			}
			$fields['discount_type'] = in_array( $fields['discount_type'] ?? 'fixed', array( 'fixed', 'percent' ), true ) ? $fields['discount_type'] : 'fixed';
			$fields['discount_value'] = isset( $fields['discount_value'] ) ? (string) max( 0, floatval( $fields['discount_value'] ) ) : '0';

			update_post_meta( $post_id, '_bif_fields', $fields );
		}

		// Save payment settings
		if ( isset( $_POST['bif_payment'] ) ) {
			$payment = array_map( 'sanitize_text_field', wp_unslash( $_POST['bif_payment'] ) );
			update_post_meta( $post_id, '_bif_payment', $payment );
		}

		// Save admin email settings
		if ( isset( $_POST['bif_email'] ) ) {
			$email = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['bif_email'] ) );
			update_post_meta( $post_id, '_bif_email', $email );
		}

		// Save customer email settings
		if ( isset( $_POST['bif_email_customer'] ) ) {
			$customer_email = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['bif_email_customer'] ) );
			if ( ! isset( $customer_email['customer_email_enabled'] ) ) {
				$customer_email['customer_email_enabled'] = '0';
			}
			update_post_meta( $post_id, '_bif_email_customer', $customer_email );
		}

		// Save redirect settings
		if ( isset( $_POST['bif_redirect'] ) ) {
			$redirect = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['bif_redirect'] ) );
			update_post_meta( $post_id, '_bif_redirect', $redirect );
		}
	}
}
