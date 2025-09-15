<?php
/**
 * DB installer.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace BitcoinInvoiceForm\Database;

use BitcoinInvoiceForm\BIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database table installer and utilities.
 */
class Installer {
	/**
	 * Get invoices table name with prefix.
	 *
	 * @param \wpdb|null $wpdb_param Optional wpdb instance.
	 * @return string Table name.
	 */
	public static function table_name( $wpdb_param = null ): string {
		global $wpdb;
		$db = $wpdb_param ? $wpdb_param : $wpdb;
		return $db->prefix . \BitcoinInvoiceForm\BIF_Constants::INVOICES_TABLE_SUFFIX;
	}

	/**
	 * Back-compat alias for camelCase method.
	 *
	 * @deprecated 0.2.0 Use table_name() instead.
	 *
	 * @param \wpdb|null $wpdb_param Optional wpdb instance.
	 * @return string Table name.
	 *
     * @phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	 */
	public static function tableName( $wpdb_param = null ): string { // phpcs:ignore Squiz.NamingConventions.ValidFunctionName.NotCamelCaps
		return self::table_name( $wpdb_param );
	}

	/**
	 * Activation callback to create/update DB schema.
	 */
	public static function activate(): void {
		global $wpdb;
		$table           = self::tableName( $wpdb );
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            transaction_id VARCHAR(190) NOT NULL,
            invoice_number VARCHAR(190) NULL,
            customer_name VARCHAR(190) NOT NULL,
            customer_email VARCHAR(190) NOT NULL,
            customer_company VARCHAR(190) NULL,
            amount BIGINT NOT NULL,
            currency VARCHAR(10) NOT NULL,
            description TEXT NULL,
            payment_provider VARCHAR(50) NOT NULL,
            payment_invoice_id VARCHAR(190) NOT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            payment_url TEXT NULL,
            ip VARCHAR(64) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY transaction_id (transaction_id),
            KEY invoice_number (invoice_number),
            KEY customer_email (customer_email),
            KEY payment_status (payment_status),
            KEY payment_provider (payment_provider)
        ) $charset_collate;";

		\dbDelta( $sql );
	}
}
