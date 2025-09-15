<?php
/**
 * Logger class for Bitcoin Invoice Form.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Util;

use BitcoinInvoiceForm\Admin\BIF_Admin_Settings as Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for handling plugin logging.
 */
class BIF_Logger {
	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private static $log_file = '';

	/**
	 * Current log level.
	 *
	 * @var string
	 */
	private static $log_level = '';

	/**
	 * Maximum log file size in bytes (5MB).
	 *
	 * @var int
	 */
	private const MAX_LOG_SIZE = 5 * 1024 * 1024;

	/**
	 * Maximum number of log files to keep.
	 *
	 * @var int
	 */
	private const MAX_LOG_FILES = 5;

	/**
	 * Initialize the logger.
	 */
	public static function init(): void {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/bif-logs';

		// Create log directory if it doesn't exist.
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		self::$log_file  = $log_dir . '/bif.log';
		self::$log_level = self::get_current_log_level();
	}

	/**
	 * Get current log level from settings.
	 *
	 * @return string Current log level.
	 */
	private static function get_current_log_level(): string {
		$settings = Settings::get_settings();
		$level    = $settings['log_level'] ?? 'error';

		return BIF_Log_Levels::is_valid_level( $level ) ? $level : 'error';
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function log( string $level, string $message, array $context = array() ): bool {
		if ( ! self::should_log( $level ) ) {
			return false;
		}

		$log_entry = self::format_log_entry( $level, $message, $context );

		// Write to file.
		$result = self::write_to_file( $log_entry );

		// Also log to WordPress error log if level is error or higher.
		if ( in_array( $level, array( BIF_Log_Levels::ERROR, BIF_Log_Levels::CRITICAL, BIF_Log_Levels::ALERT, BIF_Log_Levels::EMERGENCY ), true ) ) {
			error_log( "[BIF {$level}] {$message}" );
		}

		return $result;
	}

	/**
	 * Check if a message should be logged based on current log level.
	 *
	 * @param string $level Log level to check.
	 * @return bool True if should log, false otherwise.
	 */
	private static function should_log( string $level ): bool {
		$current_level_value = BIF_Log_Levels::get_level_value( self::$log_level );
		$message_level_value = BIF_Log_Levels::get_level_value( $level );

		return $message_level_value <= $current_level_value;
	}

	/**
	 * Format a log entry.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return string Formatted log entry.
	 */
	private static function format_log_entry( string $level, string $message, array $context ): string {
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$context_str = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';

		return sprintf(
			'[%s] %s: %s%s' . PHP_EOL,
			$timestamp,
			strtoupper( $level ),
			$message,
			$context_str
		);
	}

	/**
	 * Write log entry to file.
	 *
	 * @param string $log_entry Formatted log entry.
	 * @return bool True if written successfully, false otherwise.
	 */
	private static function write_to_file( string $log_entry ): bool {
		// Rotate log file if it's too large.
		self::maybe_rotate_log();

		// Use WordPress filesystem API.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			// Fallback to direct file operation if filesystem is not available.
			$result = file_put_contents( self::$log_file, $log_entry, FILE_APPEND | LOCK_EX );
			return false !== $result;
		}

		// Read existing content and append new entry.
		$existing_content = $wp_filesystem->exists( self::$log_file ) ? $wp_filesystem->get_contents( self::$log_file ) : '';
		$new_content      = $existing_content . $log_entry;

		return $wp_filesystem->put_contents( self::$log_file, $new_content, FS_CHMOD_FILE );
	}

	/**
	 * Rotate log file if it exceeds maximum size.
	 */
	private static function maybe_rotate_log(): void {
		// Use WordPress filesystem API.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			// Fallback to direct file operations if filesystem is not available.
			self::maybe_rotate_log_fallback();
			return;
		}

		if ( ! $wp_filesystem->exists( self::$log_file ) ) {
			return;
		}

		$file_size = $wp_filesystem->size( self::$log_file );
		if ( $file_size < self::MAX_LOG_SIZE ) {
			return;
		}

		// Rotate existing log files.
		for ( $i = self::MAX_LOG_FILES - 1; $i > 0; $i-- ) {
			$old_file = self::$log_file . '.' . $i;
			$new_file = self::$log_file . '.' . ( $i + 1 );

			if ( $wp_filesystem->exists( $old_file ) ) {
				if ( self::MAX_LOG_FILES - 1 === $i ) {
					// Delete the oldest log file.
					$wp_filesystem->delete( $old_file );
				} else {
					// Move to next number.
					$wp_filesystem->move( $old_file, $new_file );
				}
			}
		}

		// Move current log to .1.
		$wp_filesystem->move( self::$log_file, self::$log_file . '.1' );
	}

	/**
	 * Fallback log rotation using direct file operations.
	 */
	private static function maybe_rotate_log_fallback(): void {
		if ( ! file_exists( self::$log_file ) ) {
			return;
		}

		$file_size = filesize( self::$log_file );
		if ( $file_size < self::MAX_LOG_SIZE ) {
			return;
		}

		// Rotate existing log files.
		for ( $i = self::MAX_LOG_FILES - 1; $i > 0; $i-- ) {
			$old_file = self::$log_file . '.' . $i;
			$new_file = self::$log_file . '.' . ( $i + 1 );

			if ( file_exists( $old_file ) ) {
				if ( self::MAX_LOG_FILES - 1 === $i ) {
					// Delete the oldest log file.
					unlink( $old_file );
				} else {
					rename( $old_file, $new_file );
				}
			}
		}

		// Move current log to .1.
		rename( self::$log_file, self::$log_file . '.1' );
	}

	/**
	 * Log an emergency message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function emergency( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::EMERGENCY, $message, $context );
	}

	/**
	 * Log an alert message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function alert( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::ALERT, $message, $context );
	}

	/**
	 * Log a critical message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function critical( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::CRITICAL, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function error( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function warning( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::WARNING, $message, $context );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function notice( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::NOTICE, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function info( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True if logged, false otherwise.
	 */
	public static function debug( string $message, array $context = array() ): bool {
		return self::log( BIF_Log_Levels::DEBUG, $message, $context );
	}

	/**
	 * Get log file path.
	 *
	 * @return string Log file path.
	 */
	public static function get_log_file_path(): string {
		return self::$log_file;
	}

	/**
	 * Get log file size in bytes.
	 *
	 * @return int Log file size in bytes.
	 */
	public static function get_log_file_size(): int {
		return file_exists( self::$log_file ) ? filesize( self::$log_file ) : 0;
	}

	/**
	 * Clear log file.
	 *
	 * @return bool True if cleared successfully, false otherwise.
	 */
	public static function clear_log(): bool {
		// Use WordPress filesystem API.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			// Fallback to direct file operation if filesystem is not available.
			if ( ! file_exists( self::$log_file ) ) {
				return true;
			}
			return unlink( self::$log_file );
		}

		if ( ! $wp_filesystem->exists( self::$log_file ) ) {
			return true;
		}

		return $wp_filesystem->delete( self::$log_file );
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $lines Number of lines to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_recent_entries( int $lines = 100 ): array {
		// Use WordPress filesystem API.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			// Fallback to direct file operation if filesystem is not available.
			if ( ! file_exists( self::$log_file ) ) {
				return array();
			}
			$log_content = file_get_contents( self::$log_file );
			if ( false === $log_content ) {
				return array();
			}
		} else {
			if ( ! $wp_filesystem->exists( self::$log_file ) ) {
				return array();
			}
			$log_content = $wp_filesystem->get_contents( self::$log_file );
			if ( false === $log_content ) {
				return array();
			}
		}

		$log_lines = explode( PHP_EOL, $log_content );
		$log_lines = array_filter( $log_lines ); // Remove empty lines.

		return array_slice( $log_lines, -$lines );
	}
}
