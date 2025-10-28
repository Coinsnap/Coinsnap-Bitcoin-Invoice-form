<?php
/**
 * Invoice form shortcode.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Shortcode;

use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice form shortcode implementation.
 */
class BIF_Shortcode_Invoice_Form_Shortcode {
	/**
	 * Register the shortcode.
	 */
	public static function register(): void {
		// Register the main shortcode name
		add_shortcode( BIF_Constants::SHORTCODE_INVOICE_FORM, array( __CLASS__, 'render' ) );

		// Register the old shortcode name for backward compatibility
		add_shortcode( 'bif_invoice_form', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the invoice form shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Rendered form HTML.
	 */
	public static function render( array $atts, string $content = '' ): string {
		$atts = shortcode_atts( array(
			'id'    => 0,
			'class' => '',
			'style' => '',
		), $atts, BIF_Constants::SHORTCODE_INVOICE_FORM );

		$form_id = intval( $atts['id'] );
		if ( ! $form_id ) {
			return '<p>' . esc_html__( 'Invalid form ID.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}

		$form = get_post( $form_id );
		$valid_post_types = array( BIF_Constants::CPT_INVOICE_FORM, 'coinsnap_invoice_form' );
		if ( ! $form || ! in_array( $form->post_type, $valid_post_types, true ) ) {
			return '<p>' . esc_html__( 'Form not found.', 'coinsnap-bitcoin-invoice-form' ) . '</p>';
		}

		// Get form configuration
		$fields = get_post_meta( $form_id, '_bif_fields', true );
		$payment = get_post_meta( $form_id, '_bif_payment', true );
		$redirect = get_post_meta( $form_id, '_bif_redirect', true );

		// Set defaults - only if fields array is empty
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			$fields = array(
				'name_enabled'        => '1',
				'name_required'       => '1',
				'name_label'          => __( 'Name', 'coinsnap-bitcoin-invoice-form' ),
				'name_order'          => '10',
				'email_enabled'       => '1',
				'email_required'      => '1',
				'email_label'         => __( 'Email', 'coinsnap-bitcoin-invoice-form' ),
				'email_order'         => '20',
				'company_enabled'     => '0',
				'company_required'    => '0',
				'company_label'       => __( 'Company', 'coinsnap-bitcoin-invoice-form' ),
				'company_order'       => '30',
				'invoice_number_enabled' => '1',
				'invoice_number_required' => '1',
				'invoice_number_label' => __( 'Invoice Number', 'coinsnap-bitcoin-invoice-form' ),
				'invoice_number_order' => '40',
				'amount_enabled'      => '1',
				'amount_required'     => '1',
				'amount_label'        => __( 'Amount', 'coinsnap-bitcoin-invoice-form' ),
				'amount_order'        => '50',
				'currency_enabled'    => '1',
				'currency_required'   => '1',
				'currency_label'      => __( 'Currency', 'coinsnap-bitcoin-invoice-form' ),
				'currency_order'      => '45',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Description/Notes', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '60',
				'button_text'         => __( 'Pay with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
				'discount_enabled'    => '0',
				'discount_type'       => 'fixed',
				'discount_value'      => '0',
				'discount_notice'     => '',
			);
		} else {
			// Merge with defaults to ensure all keys exist
			$defaults = array(
				'name_enabled'        => '1',
				'name_required'       => '1',
				'name_label'          => __( 'Name', 'coinsnap-bitcoin-invoice-form' ),
				'name_order'          => '10',
				'email_enabled'       => '1',
				'email_required'      => '1',
				'email_label'         => __( 'Email', 'coinsnap-bitcoin-invoice-form' ),
				'email_order'         => '20',
				'company_enabled'     => '0',
				'company_required'    => '0',
				'company_label'       => __( 'Company', 'coinsnap-bitcoin-invoice-form' ),
				'company_order'       => '30',
				'invoice_number_enabled' => '1',
				'invoice_number_required' => '1',
				'invoice_number_label' => __( 'Invoice Number', 'coinsnap-bitcoin-invoice-form' ),
				'invoice_number_order' => '40',
				'amount_enabled'      => '1',
				'amount_required'     => '1',
				'amount_label'        => __( 'Amount', 'coinsnap-bitcoin-invoice-form' ),
				'amount_order'        => '50',
				'currency_enabled'    => '1',
				'currency_required'   => '1',
				'currency_label'      => __( 'Currency', 'coinsnap-bitcoin-invoice-form' ),
				'currency_order'      => '45',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Description/Notes', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '60',
				'button_text'         => __( 'Pay with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
				'discount_enabled'    => '0',
				'discount_type'       => 'fixed',
				'discount_value'      => '0',
				'discount_notice'     => '',
			);
			$fields = wp_parse_args( $fields, $defaults );
		}

		$payment = wp_parse_args( $payment, array(
			'amount'     => '',
			'currency'   => '',
			'description' => '',
		) );

		$redirect = wp_parse_args( $redirect, array(
			'success_page' => '',
			'error_page'   => '',
			'thank_you_message' => __( 'Thank you! Your payment has been processed successfully.', 'coinsnap-bitcoin-invoice-form' ),
		) );

		// Determine default currency for the form UI: use per-form payment setting, fallback to USD
		$current_currency = ! empty( $payment['currency'] ) ? (string) $payment['currency'] : 'USD';
		// Pass to renderer via internal key
		$fields['_currency_default'] = $current_currency;

		// Build form HTML
		$form_classes = array( 'bif-form', 'bif-form-' . $form_id );
		if ( ! empty( $atts['class'] ) ) {
			$form_classes[] = $atts['class'];
		}

		ob_start();
		?>
		<form class="<?php echo esc_attr( implode( ' ', $form_classes ) ); ?>"<?php if ( ! empty( $atts['style'] ) ) { echo ' style="' . esc_attr( $atts['style'] ) . '"'; } ?> data-form-id="<?php echo esc_attr( $form_id ); ?>" data-currency="<?php echo esc_attr( $current_currency ); ?>">
			<?php wp_nonce_field( 'bif_invoice_form_' . $form_id, 'bif_form_nonce' ); ?>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />

			<div class="bif-form-fields">
				<?php
				// Render enabled fields in order
				$field_order = array();
				foreach ( array( 'name', 'email', 'company', 'invoice_number', 'currency', 'amount', 'description' ) as $field ) {
					$enabled = $fields[ $field . '_enabled' ] ?? '0';
					if ( '1' === $enabled || 'on' === $enabled || true === $enabled ) {
						$order = intval( $fields[ $field . '_order' ] ?? '10' );
						$field_order[ $order ] = $field;
					}
				}
				ksort( $field_order );

				foreach ( $field_order as $field ) {
					self::render_field( $field, $fields );
				}
				?>
			</div>

			<?php
			$disc_enabled_val = $fields['discount_enabled'] ?? '0';
			$disc_enabled = ( '1' === $disc_enabled_val || 'on' === $disc_enabled_val || true === $disc_enabled_val );
			$disc_value = isset( $fields['discount_value'] ) ? floatval( $fields['discount_value'] ) : 0.0;
			if ( $disc_enabled && $disc_value > 0 ) {
				$custom_notice = trim( (string) ( $fields['discount_notice'] ?? '' ) );
				if ( '' !== $custom_notice ) {
					$msg = $custom_notice;
				} else {
					$disc_type = $fields['discount_type'] ?? 'fixed';
					$val_str = rtrim( rtrim( number_format( $disc_value, 2, '.', '' ), '0' ), '.' );
					if ( 'percent' === $disc_type ) {
						/* translators: %s is the discount percentage value (without the percent sign). */
						$msg = sprintf( __( 'Good news! A discount of %s%% will be applied to the amount at checkout.', 'coinsnap-bitcoin-invoice-form' ), $val_str );
					} else {
						/* translators: %s is the fixed discount amount in the selected currency. */
						$msg = sprintf( __( 'Good news! A fixed discount of %s will be applied in the selected currency.', 'coinsnap-bitcoin-invoice-form' ), $val_str );
					}
				}
				echo '<div class="bif-discount-notice" style="margin:10px 0 0;padding:10px;border:1px dashed #39a; background:#f3fbff; color:#124; border-radius:4px; font-size:14px;">' . esc_html( $msg ) . '</div>';
			}
			?>

			<div class="bif-form-actions">
				<button type="submit" class="bif-button">
					<?php echo esc_html( $fields['button_text'] ); ?>
				</button>
			</div>

			<div class="bif-form-messages" style="display: none;">
				<div class="bif-message bif-message-success"></div>
				<div class="bif-message bif-message-error"></div>
			</div>
		</form>


		<?php
		return ob_get_clean();
	}

	/**
	 * Render a form field.
	 *
	 * @param string $field  Field name.
	 * @param array  $fields Field configuration.
	 */
	private static function render_field( string $field, array $fields ): void {
		$enabled_value = $fields[ $field . '_enabled' ] ?? '0';
		$enabled = '1' === $enabled_value || 'on' === $enabled_value || true === $enabled_value;
		$required_value = $fields[ $field . '_required' ] ?? '0';
		$required = '1' === $required_value || 'on' === $required_value || true === $required_value;
		$label = $fields[ $field . '_label' ] ?? ucfirst( str_replace( '_', ' ', $field ) );

		if ( ! $enabled ) {
			return;
		}

		$field_id = 'bif_' . $field;
		$field_name = 'bif_' . $field;

		echo '<div class="bif-field bif-field-' . esc_attr( $field ) . '">';
		echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="required">*</span>';
		}
		echo '</label>';

			switch ( $field ) {
				case 'description':
					echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . ' rows="4"></textarea>';
					break;
				case 'amount':
					$disc_enabled_val = $fields['discount_enabled'] ?? '0';
					$disc_enabled = ( '1' === $disc_enabled_val || 'on' === $disc_enabled_val || true === $disc_enabled_val );
					$disc_type = $fields['discount_type'] ?? 'fixed';
					$disc_value = isset( $fields['discount_value'] ) ? floatval( $fields['discount_value'] ) : 0.0;
					$step_attr = ' step="0.01"';
					echo '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . $step_attr . ' min="0"' . ( $disc_enabled && $disc_value > 0 ? ' data-discount-enabled="1" data-discount-type="' . esc_attr( $disc_type ) . '" data-discount-value="' . esc_attr( (string) $disc_value ) . '"' : '' ) . ' />';
					if ( $disc_enabled && $disc_value > 0 ) {
						// Render a readonly final amount field next to the amount
						$final_id = $field_id . '_final';
						echo '<div class="bif-final-amount" style="margin-top:6px; display:flex; gap:8px; align-items:center;">';
						echo '<label for="' . esc_attr( $final_id ) . '" style="margin:0; font-size:12px; color:#555;">' . esc_html__( 'Final amount (after discount)', 'coinsnap-bitcoin-invoice-form' ) . '</label>';
						echo '<input type="text" id="' . esc_attr( $final_id ) . '" class="bif-amount-final" value="" readonly style="max-width:160px; background:#f8f8f8;" aria-readonly="true" />';
						echo '</div>';
					}
					break;
				case 'currency':
					$selected_currency = $fields['_currency_default'] ?? 'USD';
					// Display the currency as read-only to the user; do not allow changing it on the frontend.
					// Include a hidden input for compatibility, but backend enforces form-configured currency regardless.
					echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $selected_currency ) . '" />';
					echo '<input type="text" id="' . esc_attr( $field_id ) . '_display" value="' . esc_attr( $selected_currency ) . '" readonly disabled style="background:#f8f8f8; color:#333; max-width:120px;" aria-readonly="true" />';
					break;
				case 'email':
					echo '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . ' />';
					break;
				default:
					echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . ' />';
					break;
			}

		echo '</div>';
	}
}
