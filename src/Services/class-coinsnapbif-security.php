<?php
/**
 * Payment security helpers.
 *
 * Follows the Coinsnap Plugin Security Guide: secrets stay on the server, an
 * invoice counts as paid only after the Coinsnap or BTCPay API confirms it,
 * and each invoice is acted on once.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF\Services;

use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as Settings;
use CoinsnapBIF\CoinsnapBIF_Constants;
use CoinsnapBIF\Util\CoinsnapBIF_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side checks for webhooks, invoices and the BTCPay connect flow.
 */
class CoinsnapBIF_Security {

	/** Webhook records, one per provider: [provider => [id, secret, url, store_id]]. */
	public const WEBHOOK_OPTION = 'coinsnapbif_webhook';

	/** One-time state for the BTCPay connect wizard. */
	public const STATE_KEY = 'coinsnapbif_btcpay_state';

	/** Permissions the plugin asks BTCPay for (store-scoped, nothing else). */
	public const BTCPAY_PERMISSIONS = array(
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewinvoices',
		'btcpay.store.canviewstoresettings',
		'btcpay.store.webhooks.canmodifywebhooks',
	);

	/** Permissions a BTCPay key must have to be accepted. */
	public const BTCPAY_REQUIRED = array(
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewinvoices',
		'btcpay.store.webhooks.canmodifywebhooks',
	);

	/* ---------------------------------------------------------------------
	 * Configuration
	 * ------------------------------------------------------------------- */

	/**
	 * Connection settings of one provider.
	 *
	 * @param string $provider 'coinsnap' or 'btcpay'.
	 * @return array{provider:string,url:string,store_id:string,api_key:string}
	 */
	public static function config( string $provider ): array {
		$s = Settings::get_settings();
		if ( 'btcpay' === $provider ) {
			return array(
				'provider' => 'btcpay',
				'url'      => rtrim( (string) $s['btcpay_host'], '/' ),
				'store_id' => (string) $s['btcpay_store_id'],
				'api_key'  => (string) $s['btcpay_api_key'],
			);
		}
		$base = (string) $s['coinsnap_api_base'];
		return array(
			'provider' => 'coinsnap',
			'url'      => rtrim( '' !== $base ? $base : CoinsnapBIF_Constants::COINSNAP_DEFAULT_API_BASE, '/' ),
			'store_id' => (string) $s['coinsnap_store_id'],
			'api_key'  => (string) $s['coinsnap_api_key'],
		);
	}

	/**
	 * Whether URL, store ID and API key are set.
	 *
	 * @param array $cfg Provider config.
	 */
	public static function is_configured( array $cfg ): bool {
		return '' !== $cfg['url'] && '' !== $cfg['store_id'] && '' !== $cfg['api_key'];
	}

	/**
	 * The webhook URL comes from the saved site address, never from the request (6.3).
	 *
	 * @param string $provider Provider.
	 */
	public static function webhook_url( string $provider ): string {
		$route = 'btcpay' === $provider ? CoinsnapBIF_Constants::REST_ROUTE_WEBHOOK_BTCPAY : CoinsnapBIF_Constants::REST_ROUTE_WEBHOOK_COINSNAP;
		return get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . $route );
	}

	/* ---------------------------------------------------------------------
	 * Webhook records: own option name, per provider and store (1, 6.4)
	 * ------------------------------------------------------------------- */

	/**
	 * Stored webhook record of a provider, or [].
	 *
	 * @param string $provider Provider.
	 */
	private static function record( string $provider ): array {
		$all = get_option( self::WEBHOOK_OPTION, array() );
		return ( is_array( $all ) && isset( $all[ $provider ] ) && is_array( $all[ $provider ] ) ) ? $all[ $provider ] : array();
	}

	/**
	 * Secret used to check this provider's webhooks. Empty means every webhook is rejected.
	 * Records saved before this version have no store ID; their secret is still used
	 * until the merchant reconnects, but a record for another store is not.
	 *
	 * @param string $provider Provider.
	 */
	public static function get_secret( string $provider ): string {
		$rec    = self::record( $provider );
		$secret = isset( $rec['secret'] ) && is_string( $rec['secret'] ) ? $rec['secret'] : '';
		if ( '' === $secret ) {
			return '';
		}
		if ( isset( $rec['store_id'] ) && (string) $rec['store_id'] !== self::config( $provider )['store_id'] ) {
			return '';
		}
		return $secret;
	}

	/**
	 * Whether this provider has a webhook registered by this version for the current store and URL.
	 *
	 * @param string $provider Provider.
	 */
	public static function is_connected( string $provider ): bool {
		$rec = self::record( $provider );
		return '' !== self::get_secret( $provider )
			&& isset( $rec['store_id'], $rec['url'] )
			&& (string) $rec['url'] === self::webhook_url( $provider );
	}

	/**
	 * Save a webhook record (not autoloaded).
	 *
	 * @param array  $cfg       Provider config.
	 * @param string $secret    Secret.
	 * @param string $webhookId Webhook ID.
	 */
	private static function save_record( array $cfg, string $secret, string $webhookId ): void {
		$all = get_option( self::WEBHOOK_OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[ $cfg['provider'] ] = array(
			'id'       => $webhookId,
			'secret'   => $secret,
			'url'      => self::webhook_url( $cfg['provider'] ),
			'store_id' => $cfg['store_id'],
		);
		update_option( self::WEBHOOK_OPTION, $all, false );
	}

	/* ---------------------------------------------------------------------
	 * API calls
	 * ------------------------------------------------------------------- */

	/**
	 * Request headers for a provider.
	 *
	 * @param array $cfg Provider config.
	 */
	private static function headers( array $cfg ): array {
		$h = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( 'btcpay' === $cfg['provider'] ) {
			$h['Authorization'] = 'token ' . $cfg['api_key'];
		} else {
			$h['x-api-key'] = $cfg['api_key'];
		}
		return $h;
	}

	/**
	 * Call the store API.
	 *
	 * @param array      $cfg    Provider config.
	 * @param string     $method HTTP method.
	 * @param string     $path   Path below /api/v1/stores/{storeId}.
	 * @param array|null $body   JSON body.
	 * @return array{code:int, body:mixed} code 0 = network error
	 */
	private static function api( array $cfg, string $method, string $path, ?array $body = null ): array {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => self::headers( $cfg ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $cfg['url'] . '/api/v1/stores/' . rawurlencode( $cfg['store_id'] ) . $path, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'code' => 0,
				'body' => null,
			);
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( (string) wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * Fetch an invoice. Returns the invoice, null if it doesn't exist, or throws
	 * so the webhook replies 503 and the provider retries.
	 *
	 * @param string $provider  Provider.
	 * @param string $invoiceId Invoice ID.
	 * @throws \RuntimeException When the API can't be reached.
	 */
	public static function fetch_invoice( string $provider, string $invoiceId ): ?array {
		$cfg = self::config( $provider );
		if ( ! self::is_configured( $cfg ) ) {
			throw new \RuntimeException( 'Not configured' );
		}
		$r = self::api( $cfg, 'GET', '/invoices/' . rawurlencode( $invoiceId ) );
		if ( 0 === $r['code'] || $r['code'] >= 500 ) {
			throw new \RuntimeException( 'API unavailable' );
		}
		if ( 200 !== $r['code'] ) {
			return null; // Coinsnap 400 / BTCPay 404: unknown invoice, e.g. a dashboard test webhook.
		}
		return is_array( $r['body'] ) ? $r['body'] : null;
	}

	/* ---------------------------------------------------------------------
	 * Webhook registration, on the server only (1, 6.5)
	 * ------------------------------------------------------------------- */

	/**
	 * Create or update this plugin's webhook with a new secret.
	 * Callers check capability and nonce first. Never deletes webhooks, and an API
	 * error is never treated as "no webhook".
	 *
	 * @param string $provider Provider.
	 */
	public static function register_webhook( string $provider ): bool {
		$cfg = self::config( $provider );
		if ( ! self::is_configured( $cfg ) ) {
			return false;
		}
		$url = self::webhook_url( $provider );

		$list = self::api( $cfg, 'GET', '/webhooks' );
		if ( 200 !== $list['code'] || ! is_array( $list['body'] ) ) {
			self::log( 'info', 'webhook list failed' );
			return false;
		}
		$existingId = '';
		foreach ( $list['body'] as $wh ) {
			if ( is_array( $wh ) && (string) ( $wh['url'] ?? '' ) === $url ) {
				$existingId = (string) ( $wh['id'] ?? '' );
				break;
			}
		}

		$secret = bin2hex( random_bytes( 32 ) );
		if ( 'btcpay' === $provider ) {
			$body = array(
				'url'                 => $url,
				'enabled'             => true,
				'automaticRedelivery' => true,
				'authorizedEvents'    => array(
					'everything'     => false,
					'specificEvents' => array( 'InvoiceSettled', 'InvoiceExpired', 'InvoiceInvalid', 'InvoiceProcessing' ),
				),
				'secret'              => $secret,
			);
		} else {
			$body = array(
				'url'    => $url,
				'events' => array( 'Settled', 'Expired', 'Processing' ),
				'secret' => $secret,
			);
		}

		$r = ( '' !== $existingId )
			? self::api( $cfg, 'PUT', '/webhooks/' . rawurlencode( $existingId ), $body )
			: self::api( $cfg, 'POST', '/webhooks', $body );

		if ( $r['code'] < 200 || $r['code'] >= 300 ) {
			self::log( 'info', 'webhook register failed' );
			return false;
		}
		$res = is_array( $r['body'] ) ? $r['body'] : array();
		// The provider's answer is the secret it signs with; ours if it echoes none.
		if ( isset( $res['secret'] ) && is_string( $res['secret'] ) && '' !== $res['secret'] ) {
			$secret = $res['secret'];
		}
		$id = '' !== $existingId ? $existingId : (string) ( $res['id'] ?? '' );
		self::save_record( $cfg, $secret, $id );
		return true;
	}

	/**
	 * Register webhooks for every configured provider (forms can override the default one).
	 */
	public static function register_all_webhooks(): bool {
		$ok = true;
		foreach ( array( 'coinsnap', 'btcpay' ) as $provider ) {
			if ( self::is_configured( self::config( $provider ) ) ) {
				$ok = self::register_webhook( $provider ) && $ok;
			}
		}
		return $ok;
	}

	/* ---------------------------------------------------------------------
	 * Webhook signature (handler step 1)
	 * ------------------------------------------------------------------- */

	/**
	 * Coinsnap: X-Coinsnap-Signature: t=<unix>,v1=HMAC-SHA256(t.body). Not older than 5 minutes.
	 *
	 * @param string   $header  Header value.
	 * @param string   $rawBody Raw body.
	 * @param string   $secret  Secret.
	 * @param int|null $now     Current time (tests).
	 * @return string '' if valid, otherwise the log reason.
	 */
	public static function coinsnap_signature_error( string $header, string $rawBody, string $secret, ?int $now = null ): string {
		parse_str( str_replace( ',', '&', $header ), $p );
		$t        = (int) ( $p['t'] ?? 0 );
		$v1       = is_string( $p['v1'] ?? null ) ? $p['v1'] : '';
		$expected = hash_hmac( 'sha256', $t . '.' . $rawBody, $secret );
		if ( '' === $secret || $t <= 0 || ! hash_equals( $expected, $v1 ) ) {
			return 'bad signature';
		}
		if ( abs( ( $now ?? time() ) - $t ) > 300 ) {
			return 'stale timestamp';
		}
		return '';
	}

	/**
	 * BTCPay: BTCPay-Sig: sha256=HMAC-SHA256(body), compared with the prefix.
	 *
	 * @param string $header  Header value.
	 * @param string $rawBody Raw body.
	 * @param string $secret  Secret.
	 */
	public static function btcpay_signature_ok( string $header, string $rawBody, string $secret ): bool {
		$expected = 'sha256=' . hash_hmac( 'sha256', $rawBody, $secret );
		return '' !== $secret && hash_equals( $expected, $header );
	}

	/**
	 * Permission callback for the Coinsnap webhook route.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function check_coinsnap_webhook( \WP_REST_Request $request ): bool {
		return self::check_once(
			$request,
			function () use ( $request ) {
				$reason = self::coinsnap_signature_error(
					(string) $request->get_header( 'X-Coinsnap-Signature' ),
					$request->get_body(),
					self::get_secret( 'coinsnap' )
				);
				if ( '' !== $reason ) {
					self::log( 'security', $reason );
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * Permission callback for the BTCPay webhook route.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function check_btcpay_webhook( \WP_REST_Request $request ): bool {
		return self::check_once(
			$request,
			function () use ( $request ) {
				if ( ! self::btcpay_signature_ok( (string) $request->get_header( 'BTCPay-Sig' ), $request->get_body(), self::get_secret( 'btcpay' ) ) ) {
					self::log( 'security', 'bad signature' );
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * WordPress may call a permission callback more than once per request; check and log once.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param callable         $check   Check.
	 */
	private static function check_once( \WP_REST_Request $request, callable $check ): bool {
		static $results = array();
		$key = spl_object_id( $request );
		// The request is kept with its result, so its object ID can't be reused by another request.
		if ( ! isset( $results[ $key ] ) || $results[ $key ][0] !== $request ) {
			$results[ $key ] = array( $request, (bool) $check() );
		}
		return $results[ $key ][1];
	}

	/* ---------------------------------------------------------------------
	 * When is an invoice paid (3)
	 * ------------------------------------------------------------------- */

	/**
	 * Whole cents from a decimal number or string, rounded half up. Null if not a number.
	 *
	 * @param mixed $value Value.
	 */
	public static function to_cents( $value ): ?int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = number_format( (float) $value, 2, '.', '' );
		}
		if ( ! is_string( $value ) || ! preg_match( '/^(\d+)(?:\.(\d+))?$/', trim( $value ), $m ) ) {
			return null;
		}
		$frac  = str_pad( $m[2] ?? '', 3, '0' );
		$cents = (int) $m[1] * 100 + (int) substr( $frac, 0, 2 );
		return ( (int) $frac[2] >= 5 ) ? $cents + 1 : $cents;
	}

	/**
	 * Paid only if Settled, not marked by hand (BTCPay), the same currency and the
	 * amount the plugin recorded when it created the invoice.
	 *
	 * @param array  $invoice  Invoice from the API.
	 * @param mixed  $expected Recorded amount in the invoice currency.
	 * @param string $currency Recorded currency.
	 * @param bool   $isBtcpay BTCPay invoice.
	 */
	public static function invoice_pays( array $invoice, $expected, string $currency, bool $isBtcpay ): bool {
		$expectedCents = self::to_cents( $expected );
		if ( null === $expectedCents || $expectedCents <= 0 || 'Settled' !== ( $invoice['status'] ?? '' ) ) {
			return false;
		}
		if ( $isBtcpay && 'Marked' === ( $invoice['additionalStatus'] ?? '' ) ) {
			return false; // Settled by hand.
		}
		if ( '' === $currency || strtoupper( (string) ( $invoice['currency'] ?? '' ) ) !== strtoupper( $currency ) ) {
			return false;
		}
		if ( $isBtcpay || 'SATS' === strtoupper( $currency ) ) {
			$paid = $invoice['amount'] ?? null;
		} else {
			$paid = $invoice['invoiceAmount'] ?? ( $invoice['fiatAmount'] ?? null );
		}
		$paidCents = self::to_cents( $paid );
		return null !== $paidCents && abs( $paidCents - $expectedCents ) <= 1;
	}

	/* ---------------------------------------------------------------------
	 * BTCPay connect callback and key permissions
	 * ------------------------------------------------------------------- */

	/** Called from the admin AJAX handler (capability and nonce checked there). */
	public static function btcpay_start_state(): string {
		$state = bin2hex( random_bytes( 32 ) );
		set_transient( self::STATE_KEY, hash( 'sha256', $state ), 10 * MINUTE_IN_SECONDS );
		return $state;
	}

	/**
	 * One use, even if the check fails.
	 *
	 * @param string $state State from the callback URL.
	 */
	public static function btcpay_consume_state( string $state ): bool {
		$expected = get_transient( self::STATE_KEY );
		delete_transient( self::STATE_KEY );
		return is_string( $expected ) && '' !== $state && hash_equals( $expected, hash( 'sha256', $state ) );
	}

	/**
	 * Ask BTCPay what the key can do; never trust permissions from the POST.
	 *
	 * @param string $btcpayUrl Server URL from settings.
	 * @param string $apiKey    Key.
	 */
	public static function btcpay_key_permissions( string $btcpayUrl, string $apiKey ): ?array {
		if ( '' === $btcpayUrl || '' === $apiKey ) {
			return null;
		}
		$response = wp_remote_get(
			rtrim( $btcpayUrl, '/' ) . '/api/v1/api-keys/current',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'token ' . $apiKey,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$key = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return ( is_array( $key ) && is_array( $key['permissions'] ?? null ) ) ? array_values( $key['permissions'] ) : null;
	}

	/**
	 * The store the key's first permission is limited to, or ''.
	 *
	 * @param array $permissions Permissions.
	 */
	public static function btcpay_key_store( array $permissions ): string {
		$first = explode( ':', (string) ( $permissions[0] ?? '' ), 2 );
		return $first[1] ?? '';
	}

	/**
	 * Every permission is a store permission for this store, and the needed ones are there.
	 *
	 * @param array  $permissions Permissions from api-keys/current.
	 * @param string $storeId     Store.
	 */
	public static function btcpay_key_is_acceptable( array $permissions, string $storeId ): bool {
		if ( array() === $permissions || '' === $storeId ) {
			return false;
		}
		foreach ( $permissions as $permission ) {
			$parts = array_pad( explode( ':', (string) $permission, 2 ), 2, '' );
			if ( 0 !== strpos( $parts[0], 'btcpay.store.' ) || $parts[1] !== $storeId ) {
				return false; // Unrestricted, server or user rights, or not limited to this store.
			}
		}
		foreach ( self::BTCPAY_REQUIRED as $needed ) {
			if ( ! in_array( $needed . ':' . $storeId, $permissions, true ) ) {
				return false;
			}
		}
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Settings display
	 * ------------------------------------------------------------------- */

	/**
	 * Last 4 characters of a stored secret.
	 *
	 * @param string $value Secret.
	 */
	public static function secret_hint( string $value ): string {
		return '' === $value ? '' : '••••••••' . substr( $value, -4 );
	}

	/* ---------------------------------------------------------------------
	 * Logs: a fixed reason and the invoice ID, nothing else (5, 6.13)
	 * ------------------------------------------------------------------- */

	/**
	 * Log a webhook decision.
	 *
	 * @param string $level     'security' (someone may be faking a payment) or 'info'.
	 * @param string $reason    Fixed reason.
	 * @param string $invoiceId Invoice ID from the request.
	 */
	public static function log( string $level, string $reason, string $invoiceId = '' ): void {
		if ( 'security' === $level && ! self::security_log_allowed() ) {
			return;
		}
		$id   = substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $invoiceId ), 0, 64 );
		$line = sprintf( '%s webhook reason=%s invoice=%s', strtoupper( $level ), $reason, '' !== $id ? $id : '-' );
		if ( 'security' === $level ) {
			CoinsnapBIF_Logger::error( $line );
		} else {
			CoinsnapBIF_Logger::info( $line );
		}
	}

	/** At most 10 security lines a minute, then one line saying the rest were dropped. */
	private static function security_log_allowed(): bool {
		$key   = 'coinsnapbif_seclog_' . gmdate( 'YmdHi' );
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, 2 * MINUTE_IN_SECONDS );
		if ( 11 === $count ) {
			CoinsnapBIF_Logger::error( 'SECURITY webhook more rejections this minute, not logged' );
		}
		return $count <= 10;
	}
}
