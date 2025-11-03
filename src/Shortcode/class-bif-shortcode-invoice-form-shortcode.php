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
				'currency_label'      => __( 'Currency Selection', 'coinsnap-bitcoin-invoice-form' ),
				'currency_order'      => '40',
				'email_enabled'       => '1',
				'email_required'      => '1',
				'email_label'         => __( 'Email', 'coinsnap-bitcoin-invoice-form' ),
				'email_order'         => '50',
				'company_enabled'     => '0',
				'company_required'    => '0',
				'company_label'       => __( 'Company', 'coinsnap-bitcoin-invoice-form' ),
				'company_order'       => '60',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Message', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '70',
				'button_text'         => __( 'Pay Invoice with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
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
				'currency_label'      => __( 'Currency Selection', 'coinsnap-bitcoin-invoice-form' ),
				'currency_order'      => '40',
				'email_enabled'       => '1',
				'email_required'      => '1',
				'email_label'         => __( 'Email', 'coinsnap-bitcoin-invoice-form' ),
				'email_order'         => '50',
				'company_enabled'     => '0',
				'company_required'    => '0',
				'company_label'       => __( 'Company', 'coinsnap-bitcoin-invoice-form' ),
				'company_order'       => '60',
				'description_enabled' => '1',
				'description_required' => '1',
				'description_label'   => __( 'Message', 'coinsnap-bitcoin-invoice-form' ),
				'description_order'   => '70',
				'button_text'         => __( 'Pay Invoice with Bitcoin', 'coinsnap-bitcoin-invoice-form' ),
				'discount_enabled'    => '0',
				'discount_type'       => 'fixed',
				'discount_value'      => '0',
				'discount_notice'     => '',
			);
			$fields = wp_parse_args( $fields, $defaults );

			// Normalize legacy labels and order if they match old defaults (preserve user customizations)
			// Update labels only when they equal the previous defaults
			if ( isset( $fields['name_label'] ) && $fields['name_label'] === __( 'Invoice Recipient', 'coinsnap-bitcoin-invoice-form' ) ) {
				$fields['name_label'] = __( 'Invoice Recipient', 'coinsnap-bitcoin-invoice-form' );
			}
			if ( isset( $fields['amount_label'] ) && $fields['amount_label'] === __( 'Invoice Amount', 'coinsnap-bitcoin-invoice-form' ) ) {
				$fields['amount_label'] = __( 'Invoice Amount', 'coinsnap-bitcoin-invoice-form' );
			}
			if ( isset( $fields['description_label'] ) && $fields['description_label'] === __( 'Message to the Invoice recipient', 'coinsnap-bitcoin-invoice-form' ) ) {
				$fields['description_label'] = __( 'Message to the invoice recipient', 'coinsnap-bitcoin-invoice-form' );
			}

			// Update orders only when they equal known old default values
			$orders_map = array(
				'invoice_number_order' => array( 40 ),
				'amount_order'         => array( 50 ),
				'currency_order'       => array( 45, 55 ),
				'email_order'          => array( 20 ),
				'company_order'        => array( 30 ),
				'description_order'    => array( 60 ),
			);
			if ( isset( $fields['invoice_number_order'] ) && in_array( intval( $fields['invoice_number_order'] ), $orders_map['invoice_number_order'], true ) ) {
				$fields['invoice_number_order'] = '20';
			}
			if ( isset( $fields['amount_order'] ) && in_array( intval( $fields['amount_order'] ), $orders_map['amount_order'], true ) ) {
				$fields['amount_order'] = '30';
			}
			if ( isset( $fields['currency_order'] ) && in_array( intval( $fields['currency_order'] ), $orders_map['currency_order'], true ) ) {
				$fields['currency_order'] = '40';
			}
			if ( isset( $fields['email_order'] ) && in_array( intval( $fields['email_order'] ), $orders_map['email_order'], true ) ) {
				$fields['email_order'] = '50';
			}
			if ( isset( $fields['company_order'] ) && in_array( intval( $fields['company_order'] ), $orders_map['company_order'], true ) ) {
				$fields['company_order'] = '60';
			}
			if ( isset( $fields['description_order'] ) && in_array( intval( $fields['description_order'] ), $orders_map['description_order'], true ) ) {
				$fields['description_order'] = '70';
			}
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
		<form class="<?php echo esc_attr( implode( ' ', $form_classes ) ); ?>"<?php if ( ! empty( $atts['style'] ) ) { echo ' style="' . esc_attr( $atts['style'] ) . '"'; } ?> data-form-id="<?php echo esc_attr( $form_id ); ?>" data-currency="<?php echo esc_attr( $current_currency ); ?>"<?php
			$disc_enabled_val = $fields['discount_enabled'] ?? '0';
			$disc_enabled = ( '1' === $disc_enabled_val || 'on' === $disc_enabled_val || true === $disc_enabled_val );
			$disc_type = $fields['discount_type'] ?? 'fixed';
			$disc_value = isset( $fields['discount_value'] ) ? floatval( $fields['discount_value'] ) : 0.0;
			if ( $disc_enabled && $disc_value > 0 ) {
				echo ' data-discount-enabled="1" data-discount-type="' . esc_attr( $disc_type ) . '" data-discount-value="' . esc_attr( (string) $disc_value ) . '"';
			}
		?>>
			<?php wp_nonce_field( 'bif_invoice_form_' . $form_id, 'bif_form_nonce' ); ?>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>" />

			<?php if ( $disc_enabled && $disc_value > 0 ) : ?>
				<?php
					$val_str = rtrim( rtrim( number_format( $disc_value, 2, '.', '' ), '0' ), '.' );
					$badge = ( 'percent' === $disc_type )
						? sprintf( __( 'Bitcoin Discount: %s%%', 'coinsnap-bitcoin-invoice-form' ), $val_str )
						: sprintf( __( 'Bitcoin Discount: %s %s', 'coinsnap-bitcoin-invoice-form' ), $val_str, esc_html( $current_currency ));
				?>
				<div class="bif-discount-badge" aria-live="polite"><?php echo esc_html( $badge ); ?></div>
			<?php endif; ?>

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

			<?php if ( $disc_enabled && $disc_value > 0 ) : ?>
				<div class="bif-discount-totals" role="status" aria-live="polite">
					<div class="bif-totals-row">
						<span class="bif-totals-label"><?php esc_html_e( 'Original', 'coinsnap-bitcoin-invoice-form' ); ?></span>
						<span class="bif-totals-original" data-value="0">—</span>
					</div>
					<div class="bif-totals-row">
						<span class="bif-totals-label"><?php esc_html_e( 'Discount', 'coinsnap-bitcoin-invoice-form' ); ?></span>
						<span class="bif-totals-discount" data-value="0">—</span>
					</div>
					<div class="bif-totals-row bif-totals-final-row">
						<span class="bif-totals-label"><?php esc_html_e( 'You pay', 'coinsnap-bitcoin-invoice-form' ); ?></span>
						<span class="bif-totals-final" data-value="0">—</span>
					</div>
				</div>
			<?php endif; ?>

			<?php
			$custom_notice = trim( (string) ( $fields['discount_notice'] ?? '' ) );
			if ( $disc_enabled ) {
				if ( '' !== $custom_notice ) {
					$msg = $custom_notice;
				} else {
					if ( $disc_value > 0 ) {
						$val_str = rtrim( rtrim( number_format( $disc_value, 2, '.', '' ), '0' ), '.' );
						if ( 'percent' === $disc_type ) {
							/* translators: %s is the discount percentage value (without the percent sign). */
							$msg = sprintf( __( 'Good news! A discount of %s%% will be applied to the amount at checkout.', 'coinsnap-bitcoin-invoice-form' ), $val_str );
						} else {
							/* translators: 1: fixed discount amount; 2: currency code. */
							$msg = sprintf( __( 'Good news! A fixed discount of %s %s will be applied in the selected currency.', 'coinsnap-bitcoin-invoice-form' ), $val_str, $current_currency );
						}
					} else {
						$msg = __( 'Good news! A Bitcoin discount will be applied at checkout.', 'coinsnap-bitcoin-invoice-form' );
					}
				}
				echo '<div class="bif-discount-notice">' . esc_html( $msg ) . '</div>';
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

		<?php if ( $disc_enabled && $disc_value > 0 ) : ?>
			<script>
			(function(){
				var form = document.querySelector('.bif-form-<?php echo esc_js( $form_id ); ?>');
				if(!form) return;
				var amountInput = form.querySelector('#bif_amount');
				var originalEl = form.querySelector('.bif-totals-original');
				var discountEl = form.querySelector('.bif-totals-discount');
				var finalEl = form.querySelector('.bif-totals-final');
				var currency = form.getAttribute('data-currency') || '';
				var type = form.getAttribute('data-discount-type') || 'fixed';
				var value = parseFloat(form.getAttribute('data-discount-value') || '0') || 0;
				function fmt(n){
					if(isNaN(n)) return '—';
					return currency + ' ' + (Math.round(n * 100) / 100).toFixed(2);
				}
				function update(){
					var amt = parseFloat(amountInput && amountInput.value ? amountInput.value : '0');
					if(isNaN(amt)) amt = 0;
					var disc = 0;
					if(type === 'percent'){
						disc = amt * (value/100);
					}else{
						disc = Math.min(value, amt);
					}
					var finalVal = Math.max(0, amt - disc);
					if(originalEl) originalEl.textContent = fmt(amt);
					if(discountEl) discountEl.textContent = '-' + fmt(disc);
					if(finalEl) finalEl.textContent = fmt(finalVal);
				}
				if(amountInput){ amountInput.addEventListener('input', update); }
				update();
			})();
			</script>
		<?php endif; ?>


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

		// Core fields are always required on the frontend
		$core_required_fields = array( 'name', 'invoice_number', 'amount', 'currency' );
		if ( in_array( $field, $core_required_fields, true ) ) {
			$required = true;
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
					break;
				case 'currency':
					$selected_currency = $fields['_currency_default'] ?? 'USD';
					// Display the currency as read-only to the user; do not allow changing it on the frontend.
					// Include a hidden input for compatibility, but backend enforces form-configured currency regardless.
					echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $selected_currency ) . '"' . ( $required ? ' required' : '' ) . ' />';
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
