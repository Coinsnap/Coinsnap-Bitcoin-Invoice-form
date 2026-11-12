<?php
/**
 * Admin logs page.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Admin;

use CoinsnapBIF\Util\CoinsnapBIF_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin logs page for viewing plugin logs.
 */
class CoinsnapBIF_Admin_Logs_Page {
	/**
	 * Register the admin page.
	 */
	public static function register(): void {
		// Page is registered in main plugin class
	}

	/**
	 * Render the logs page.
	 */
	public static function render_page(): void {
		$log_file_path = CoinsnapBIF_Logger::get_log_file_path();
		$log_file_size = CoinsnapBIF_Logger::get_log_file_size();
		$recent_entries = CoinsnapBIF_Logger::get_recent_entries( 100 );

		// Handle log clearing
		if ( isset( $_POST['clear_logs'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bif_clear_logs' ) ) {
			CoinsnapBIF_Logger::clear_log();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared successfully.', 'coinsnap-bitcoin-invoice-form' ) . '</p></div>';
			$recent_entries = array();
			$log_file_size = 0;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plugin Logs', 'coinsnap-bitcoin-invoice-form' ); ?></h1>

			<div class="bif-logs-info">
				<p>
					<strong><?php esc_html_e( 'Log File:', 'coinsnap-bitcoin-invoice-form' ); ?></strong>
					<code><?php echo esc_html( $log_file_path ); ?></code>
				</p>
				<p>
					<strong><?php esc_html_e( 'File Size:', 'coinsnap-bitcoin-invoice-form' ); ?></strong>
					<?php echo esc_html( size_format( $log_file_size ) ); ?>
				</p>
			</div>

			<div class="bif-logs-actions">
				<form method="post" style="display: inline;">
					<?php wp_nonce_field( 'bif_clear_logs' ); ?>
					<input type="submit" name="clear_logs" class="button" value="<?php esc_attr_e( 'Clear Logs', 'coinsnap-bitcoin-invoice-form' ); ?>"
						   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs?', 'coinsnap-bitcoin-invoice-form' ); ?>');" />
				</form>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bif-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Log Settings', 'coinsnap-bitcoin-invoice-form' ); ?>
				</a>
			</div>

			<div class="bif-logs-content">
				<?php if ( empty( $recent_entries ) ) : ?>
					<p><?php esc_html_e( 'No log entries found.', 'coinsnap-bitcoin-invoice-form' ); ?></p>
				<?php else : ?>
					<div class="bif-log-entries">
						<?php foreach ( $recent_entries as $entry ) : ?>
							<?php
							// Parse log entry
							$parts = explode( '] ', $entry, 3 );
							if ( count( $parts ) >= 3 ) {
								$timestamp = trim( $parts[0], '[' );
								$level = trim( $parts[1], ':' );
								$message = $parts[2];

								// Determine log level class
								$level_class = 'bif-log-' . strtolower( $level );
							} else {
								$timestamp = '';
								$level = '';
								$message = $entry;
								$level_class = 'bif-log-unknown';
							}
							?>
							<div class="bif-log-entry <?php echo esc_attr( $level_class ); ?>">
								<div class="bif-log-header">
									<span class="bif-log-timestamp"><?php echo esc_html( $timestamp ); ?></span>
									<span class="bif-log-level"><?php echo esc_html( $level ); ?></span>
								</div>
								<div class="bif-log-message"><?php echo esc_html( $message ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
