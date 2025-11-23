<?php
/**
 * Admin transactions page.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Admin;

use BitcoinInvoiceForm\Database\Installer;
use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin transactions page for viewing and managing invoice transactions.
 */
class BIF_Admin_Transactions_Page {
	/**
	 * Register the admin page.
	 */
	public static function register(): void {
		// Page is registered in main plugin class
	}

	/**
	 * Render the transactions page.
	 */
	public static function render_page(): void {
            global $wpdb;

            $table_name = Installer::table_name();
            $per_page   = 20;

            // Handle pagination with nonce verification for filter state
            $current_page = 1;
            if (filter_input(INPUT_GET,'paged',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) {
        
            // If filters are active, require nonce. Otherwise allow pagination without nonce.
            $has_filters = 
                filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) !== null || filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ||                          filter_input(INPUT_GET,'date_from',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null || filter_input(INPUT_GET,'date_to',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null; 

            if ( !$has_filters || ( filter_input(INPUT_GET,'_wpnonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null && wp_verify_nonce( sanitize_text_field( filter_input(INPUT_GET,'_wpnonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS)), 'bif_transactions_filter' ))){
		$current_page = max( 1, intval( filter_input(INPUT_GET,'paged',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ));
            }
        }

	$offset = ( $current_page - 1 ) * $per_page;

	// Handle filters with nonce verification
	$filters_enabled = 
            filter_input(INPUT_GET,'_wpnonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null && 
            wp_verify_nonce( sanitize_text_field( filter_input(INPUT_GET,'_wpnonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ), 'bif_transactions_filter' );

	$where_conditions = array( '1 = %d' );
	$where_values     = array( 1 );

		if ( $filters_enabled ) {
			if (filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) !== null && filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) !== '') {
				$where_conditions[] = 'form_id = %d';
				$where_values[]     = intval( filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) );
			}

			if (filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null && filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== '') {
				$where_conditions[] = 'payment_status = %s';
				$where_values[]     = sanitize_text_field( wp_unslash(filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS)) );
			}

			if ( ! empty( filter_input(INPUT_GET,'date_from',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) {
				$where_conditions[] = 'created_at >= %s';
				$where_values[]     = sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'date_from',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) . ' 00:00:00';
			}

			if ( ! empty( filter_input(INPUT_GET,'date_to',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) {
				$where_conditions[] = 'created_at <= %s';
				$where_values[]     = sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'date_to',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) . ' 23:59:59';
			}
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Get total count - query uses a dynamic table name and dynamic WHERE built with placeholders.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is from Installer::table_name(); WHERE clause contains only placeholder fragments, values are passed to prepare; direct query is acceptable within admin listing.
		$total_items = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}",$where_values)
		);

		// Get transactions - prepared with LIMIT/OFFSET and dynamic WHERE with placeholders
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is from Installer::table_name(); WHERE clause contains only placeholder fragments; values are passed to prepare; direct query is acceptable within admin listing.
		$transactions = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",$query_values)
		);

		// Get forms for filter dropdown
		$forms = get_posts( array(
                    'post_type'      => BIF_Constants::CPT_INVOICE_FORM,
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
		) );

		?>
      <div class="wrap">
        <h1><?php esc_html_e( 'Invoice Transactions', 'coinsnap-bitcoin-invoice-form' ); ?></h1>

        <!-- Filters -->
        <div class="tablenav top">
          <form method="get" action="">
            <input type="hidden" name="page" value="bif-transactions" />
			  <?php wp_nonce_field( 'bif_transactions_filter' ); ?>

            <div class="alignleft actions">
              <select name="form_id">
                <option value=""><?php esc_html_e( 'All Forms', 'coinsnap-bitcoin-invoice-form' ); ?></option>
				  <?php foreach ( $forms as $form ) : ?>
                    <option value="<?php echo esc_attr( $form->ID ); ?>" <?php selected( filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) !== null ? intval( filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT) ) : '', $form->ID ); ?>>
						<?php echo esc_html( $form->post_title ); ?>
                    </option>
				  <?php endforeach; ?>
              </select>

              <select name="payment_status">
                <option value=""><?php esc_html_e( 'All Statuses', 'coinsnap-bitcoin-invoice-form' ); ?></option>
                <option value="unpaid" <?php selected( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '', 'unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'coinsnap-bitcoin-invoice-form' ); ?></option>
                <option value="paid" <?php selected( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '', 'paid' ); ?>><?php esc_html_e( 'Paid', 'coinsnap-bitcoin-invoice-form' ); ?></option>
                <option value="failed" <?php selected( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '', 'failed' ); ?>><?php esc_html_e( 'Failed', 'coinsnap-bitcoin-invoice-form' ); ?></option>
                <option value="refunded" <?php selected( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'payment_status',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '', 'refunded' ); ?>><?php esc_html_e( 'Refunded', 'coinsnap-bitcoin-invoice-form' ); ?></option>
              </select>

              <input type="date" name="date_from" value="<?php echo esc_attr( filter_input(INPUT_GET,'date_from',FILTER_SANITIZE_FULL_SPECIAL_CHARS)!== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'date_from',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'coinsnap-bitcoin-invoice-form' ); ?>" />
              <input type="date" name="date_to" value="<?php echo esc_attr( filter_input(INPUT_GET,'date_to',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null ? sanitize_text_field( wp_unslash( filter_input(INPUT_GET,'date_to',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'coinsnap-bitcoin-invoice-form' ); ?>" />

              <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'coinsnap-bitcoin-invoice-form' ); ?>" />
            </div>
          </form>
        </div>

        <!-- Transactions Table -->
        <table class="wp-list-table widefat fixed striped">
          <thead>
          <tr>
            <th><?php esc_html_e( 'Transaction ID', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Form', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Invoice Number', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Customer', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Amount', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Status', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Date', 'coinsnap-bitcoin-invoice-form' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'coinsnap-bitcoin-invoice-form' ); ?></th>
          </tr>
          </thead>
          <tbody>
		  <?php if ( empty( $transactions ) ) : ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 20px;">
				  <?php esc_html_e( 'No transactions found.', 'coinsnap-bitcoin-invoice-form' ); ?>
              </td>
            </tr>
		  <?php else : ?>
			  <?php foreach ( $transactions as $transaction ) : ?>
              <tr>
                <td>
                  <strong><?php echo esc_html( $transaction->transaction_id ); ?></strong>
                </td>
                <td>
					<?php
					$form = get_post( $transaction->form_id );
					echo $form ? esc_html( $form->post_title ) : esc_html__( 'Unknown Form', 'coinsnap-bitcoin-invoice-form' );
					?>
                </td>
                <td>
					<?php echo esc_html( $transaction->invoice_number ?: '-' ); ?>
                </td>
                <td>
                  <strong><?php echo esc_html( $transaction->customer_name ); ?></strong><br>
                  <small><?php echo esc_html( $transaction->customer_email ); ?></small>
					<?php if ( $transaction->customer_company ) : ?>
                      <br><small><?php echo esc_html( $transaction->customer_company ); ?></small>
					<?php endif; ?>
                </td>
                <td>
					<?php echo esc_html( $transaction->amount ); ?> <?php echo esc_html( $transaction->currency ); ?>
                </td>
                <td>
									<span class="bif-status bif-status-<?php echo esc_attr( $transaction->payment_status ); ?>">
										<?php echo esc_html( ucfirst( $transaction->payment_status ) ); ?>
									</span>
                </td>
                <td>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $transaction->created_at ) ) ); ?>
                </td>
                <td>
					<?php if ( $transaction->payment_url ) : ?>
                      <a href="<?php echo esc_url( $transaction->payment_url ); ?>" target="_blank" class="button button-small">
						  <?php esc_html_e( 'View Payment', 'coinsnap-bitcoin-invoice-form' ); ?>
                      </a>
					<?php endif; ?>
                </td>
              </tr>
			  <?php endforeach; ?>
		  <?php endif; ?>
          </tbody>
        </table>

        <!-- Pagination -->
		  <?php if ( $total_items > $per_page ) : ?>
            <div class="tablenav bottom">
              <div class="tablenav-pages">
				  <?php
				  $total_pages = ceil( $total_items / $per_page );
				  $pagination_args = array(
					  'base'    => add_query_arg( 'paged', '%#%' ),
					  'format'  => '',
					  'current' => $current_page,
					  'total'   => $total_pages,
				  );
				  echo wp_kses_post( paginate_links( $pagination_args ) );
				  ?>
              </div>
            </div>
		  <?php endif; ?>
      </div>
		<?php
	}
}
