<?php
/**
 * Create-invoice request (PHP CLI, WordPress stubs, fake HTTP API): php tests/invoice-metadata-test.php
 *
 * Runs CoinsnapBIF_Services_Payment_Service::create_invoice() for a Coinsnap and a BTCPay form and
 * checks the request body: orderId = the transaction ID, and the metadata keys. The plugin's own
 * form_id / transaction_id keys (what the webhook matches on) must stay as they were.
 */
namespace {
	if ( PHP_SAPI !== 'cli' ) { exit; }
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COINSNAPBIF_VERSION', '9.9.9' );
	define( 'COINSNAPBIF_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	define( 'COINSNAP_CURRENCIES', array( 'EUR', 'USD', 'SATS', 'BTC' ) );
	$GLOBALS['meta']     = array();
	$GLOBALS['requests'] = array();
	$GLOBALS['settings'] = array();
	class WP_Post { public $ID; public $post_type = 'coinsnapbif'; public $post_title; public function __construct( $id, $title ) { $this->ID = $id; $this->post_title = $title; } }
	class T_Wpdb { public $prefix = 'wp_'; public $last_error = ''; public $rows = array(); public function insert( $t, $row, $f = null ) { $this->rows[] = $row; return 1; } }
	$GLOBALS['wpdb'] = new T_Wpdb();
	function __( $s, $d = null ) { return $s; }
	function get_post( $id ) { return new WP_Post( $id, 'Pay your invoice' ); }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
	function get_option( $k, $d = false ) { return 'bif_settings' === $k ? $GLOBALS['settings'] : $d; }
	function sanitize_text_field( $s ) { return trim( (string) $s ); }
	function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
	function sanitize_email( $s ) { return trim( (string) $s ); }
	function is_email( $s ) { return false !== strpos( (string) $s, '@' ); }
	function get_bloginfo( $k ) { return '6.9'; }
	function wp_generate_password( $n, $special = true ) { return 'abcdefgh'; }
	function current_time( $t ) { return '2026-11-19 10:00:00'; }
	function wp_get_referer() { return 'https://shop.test/pay/'; }
	function determine_locale() { return 'de_DE'; }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function do_action( ...$a ) {}
	function is_wp_error( $v ) { return false; }
	function wp_remote_request( $url, $args ) {
		$GLOBALS['requests'][] = array( 'url' => $url, 'method' => $args['method'], 'body' => $args['body'] );
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => 'inv' . count( $GLOBALS['requests'] ), 'checkoutLink' => 'https://pay.test/i' ) ) );
	}
	function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
	function wp_remote_retrieve_body( $r ) { return $r['body']; }
	spl_autoload_register(
		function ( $c ) {
			$map = array(
				'CoinsnapBIF\\Services\\CoinsnapBIF_Services_Payment_Service' => 'Services/class-coinsnapbif-services-payment-service',
				'CoinsnapBIF\\Services\\CoinsnapBIF_Invoice_Metadata'         => 'Services/class-coinsnapbif-invoice-metadata',
				'CoinsnapBIF\\Util\\CoinsnapBIF_Util_Provider_Factory'        => 'Util/class-coinsnapbif-util-provider-factory',
				'CoinsnapBIF\\Providers\\Payment\\PaymentProviderInterface'   => 'Providers/Payment/class-paymentproviderinterface',
				'CoinsnapBIF\\Providers\\Payment\\CoinsnapProvider'           => 'Providers/Payment/class-coinsnapprovider',
				'CoinsnapBIF\\Providers\\Payment\\BTCPayProvider'             => 'Providers/Payment/class-btcpayprovider',
				'CoinsnapBIF\\CoinsnapBIF_Constants'                          => 'class-coinsnapbif-constants',
			);
			if ( isset( $map[ $c ] ) && file_exists( COINSNAPBIF_PLUGIN_DIR . 'src/' . $map[ $c ] . '.php' ) ) {
				require COINSNAPBIF_PLUGIN_DIR . 'src/' . $map[ $c ] . '.php';
			}
		}
	);
}
namespace CoinsnapBIF\Util { class CoinsnapBIF_Logger { public static function __callStatic( $n, $a ) {} } }
namespace CoinsnapBIF\Admin { class CoinsnapBIF_Admin_Settings { public static function get_settings(): array { return $GLOBALS['settings']; } } }
namespace CoinsnapBIF\Database { class Installer { public static function table_name(): string { return 'wp_coinsnapbif_invoices'; } } }
namespace {
	use CoinsnapBIF\Services\CoinsnapBIF_Services_Payment_Service as Service;

	$fail  = 0;
	$check = function ( $ok, $label ) use ( &$fail ) { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fail++; } };

	$GLOBALS['settings'] = array( 'payment_provider' => 'coinsnap', 'coinsnap_store_id' => 'store1', 'coinsnap_api_key' => 'key', 'coinsnap_api_base' => 'https://coinsnap.test',
		'btcpay_host' => 'https://btcpay.test', 'btcpay_api_key' => 'key', 'btcpay_store_id' => 'store1' );
	$fields = array( 'email_enabled' => '1', 'company_enabled' => '1', 'description_enabled' => '1', 'discount_enabled' => '1', 'discount_type' => 'percent', 'discount_value' => '10' );
	$post   = array( 'bif_amount' => '100', 'bif_invoice_recipient' => 'Ana Buyer', 'bif_invoice_number' => 'INV-2026-001', 'bif_email' => 'ana@example.com',
		'bif_company' => 'ACME d.o.o.', 'bif_description' => 'Thanks for the quick work' );

	foreach ( array( 'coinsnap' => 'https://coinsnap.test/api/v1/stores/store1/invoices', 'btcpay' => 'https://btcpay.test/api/v1/stores/store1/invoices' ) as $provider => $url ) {
		$GLOBALS['requests']    = array();
		$GLOBALS['meta'][5]     = array( '_coinsnapbif_fields' => $fields, '_coinsnapbif_payment' => array( 'currency' => 'EUR', 'provider_override' => $provider, 'description' => '' ) );
		$result                 = Service::create_invoice( 5, $post );
		$check( ! empty( $result['success'] ), "$provider: invoice created and transaction saved" );
		$check( count( $GLOBALS['requests'] ) === 1 && $GLOBALS['requests'][0]['url'] === $url, "$provider: one create-invoice request" );
		if ( ! $GLOBALS['requests'] ) { continue; }
		$body = json_decode( $GLOBALS['requests'][0]['body'], true );
		$m    = $body['metadata'] ?? array();
		$tx   = (string) ( $result['data']['transaction_id'] ?? '' );
		$check( 'coinsnapbif_' === substr( $tx, 0, 12 ), "$provider: transaction ID returned" );
		if ( 'coinsnap' === $provider ) {
			$check( ( $body['orderId'] ?? null ) === $tx, 'coinsnap: top-level orderId is the transaction ID' );
			$check( ( $body['buyerEmail'] ?? null ) === 'ana@example.com', 'coinsnap: top-level buyerEmail' );
		}
		// What the webhook matches on, unchanged.
		$check( ( $m['form_id'] ?? null ) === 5 && ( $m['transaction_id'] ?? null ) === $tx, "$provider: form_id and transaction_id unchanged" );
		foreach ( array( 'email' => 'ana@example.com', 'name' => 'Ana Buyer', 'company' => 'ACME d.o.o.', 'invoice_number' => 'INV-2026-001', 'description' => 'Thanks for the quick work', 'plugin_version' => '9.9.9' ) as $k => $v ) {
			$check( ( $m[ $k ] ?? null ) === $v, "$provider: existing metadata.$k kept" );
		}
		$expected = array(
			'orderId' => $tx, 'orderNumber' => 'INV-2026-001', 'formId' => '5', 'formTitle' => 'Pay your invoice', 'itemDesc' => 'Invoice INV-2026-001',
			'platform' => 'bitcoin-invoice-form', 'pluginVersion' => '9.9.9', 'pageUrl' => 'https://shop.test/pay/', 'language' => 'de_DE', 'discountTotal' => '10.00',
			'customerName' => 'Ana Buyer', 'buyerName' => 'Ana Buyer', 'buyerEmail' => 'ana@example.com', 'buyerCompany' => 'ACME d.o.o.', 'buyerMessage' => 'Thanks for the quick work',
		);
		foreach ( $expected as $k => $v ) {
			$check( ( $m[ $k ] ?? null ) === $v, "$provider: metadata.$k" );
		}
	}

	// The form's payment description, when set, is the checkout description; empty fields are left out.
	$GLOBALS['requests'] = array();
	$GLOBALS['meta'][5]  = array( '_coinsnapbif_fields' => array(), '_coinsnapbif_payment' => array( 'currency' => 'EUR', 'provider_override' => 'coinsnap', 'description' => 'Consulting services' ) );
	Service::create_invoice( 5, array( 'bif_amount' => '50', 'bif_invoice_recipient' => 'Ana Buyer', 'bif_invoice_number' => 'INV-2' ) );
	$m = json_decode( $GLOBALS['requests'][0]['body'] ?? '{}', true )['metadata'] ?? array();
	$check( ( $m['itemDesc'] ?? null ) === 'Consulting services', 'form payment description is itemDesc' );
	$check( ! array_key_exists( 'buyerEmail', $m ) && ! array_key_exists( 'buyerCompany', $m ) && ! array_key_exists( 'discountTotal', $m ), 'empty values are left out' );
	exit( $fail ? 1 : 0 );
}
