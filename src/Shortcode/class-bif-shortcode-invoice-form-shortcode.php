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
				'currency_order'      => '55',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Description/Notes', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '60',
				'button_text'         => __( 'Pay with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
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
				'currency_order'      => '55',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Description/Notes', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '60',
				'button_text'         => __( 'Pay with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
			);
			$fields = wp_parse_args( $fields, $defaults );
		}

		$payment = wp_parse_args( $payment, array(
			'amount'     => '',
			'currency'   => 'USD',
			'description' => '',
		) );

		$redirect = wp_parse_args( $redirect, array(
			'success_page' => '',
			'error_page'   => '',
			'thank_you_message' => __( 'Thank you! Your payment has been processed successfully.', 'coinsnap-bitcoin-invoice-form' ),
		) );

		// Build form HTML
		$form_classes = array( 'bif-form', 'bif-form-' . $form_id );
		if ( ! empty( $atts['class'] ) ) {
			$form_classes[] = $atts['class'];
		}

		ob_start();
		?>
		<form class="<?php echo esc_attr( implode( ' ', $form_classes ) ); ?>"<?php if ( ! empty( $atts['style'] ) ) { echo ' style="' . esc_attr( $atts['style'] ) . '"'; } ?> data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<?php wp_nonce_field( 'bif_invoice_form_' . $form_id, 'bif_form_nonce' ); ?>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />

			<div class="bif-form-fields">
				<?php
				// Render enabled fields in order
				$field_order = array();
				foreach ( array( 'name', 'email', 'company', 'invoice_number', 'amount', 'currency', 'description' ) as $field ) {
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
					echo '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . ' step="0.01" min="0" />';
					break;
				case 'currency':
					echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"' . ( $required ? ' required' : '' ) . '>';
					$currencies = array( 'USD', 'EUR', 'CHF', 'JPY', 'SATS' );
					foreach ( $currencies as $currency ) {
						echo '<option value="' . esc_attr( $currency ) . '">' . esc_html( $currency ) . '</option>';
					}
					echo '</select>';
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
