<?php
/**
 * Frontend REST calls vs registered routes (PHP CLI, WordPress stubs): php tests/rest-routes-test.php
 *
 * Every `CoinsnapBIF.restUrl + '<path>'` call in assets/js/frontend.js must hit a route that
 * CoinsnapBIF_Rest_Routes::register() registers with that HTTP method. The payment window used to
 * POST to verify-payment/<invoice>, which was never registered, so every visitor got a 404.
 */
namespace {
	if ( PHP_SAPI !== 'cli' ) { exit; }
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['routes'] = array();
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][] = array( $ns, $route, $args ); return true; }
	function __( $s, $d = null ) { return $s; }
}
namespace {
	require dirname( __DIR__ ) . '/src/class-coinsnapbif-constants.php';
	require dirname( __DIR__ ) . '/src/Rest/class-coinsnapbif-rest-routes.php';
	\CoinsnapBIF\Rest\CoinsnapBIF_Rest_Routes::register();

	$fail = 0;
	$ok   = function ( $c, $m ) use ( &$fail ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; $fail += $c ? 0 : 1; };

	// Matches a request like WP_REST_Server::match_request_to_handler() does.
	$registered = function ( $path, $method ) {
		foreach ( $GLOBALS['routes'] as $r ) {
			list( $ns, $route, $args ) = $r;
			$full = '/' . trim( $ns, '/' ) . $route;
			if ( preg_match( '@^' . $full . '$@i', $path ) && in_array( $method, array_map( 'trim', explode( ',', $args['methods'] ) ), true ) ) {
				return true;
			}
		}
		return false;
	};

	$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/frontend.js' );
	// restUrl + 'path' [+ encodeURIComponent(id)] ... method: 'GET|POST'
	preg_match_all( "#CoinsnapBIF\.restUrl\s*\+\s*'([^']*)'(\s*\+\s*encodeURIComponent)?.*?method:\s*'(\w+)'#s", $js, $calls, PREG_SET_ORDER );
	$ok( count( $calls ) >= 2, 'found the REST calls in frontend.js (' . count( $calls ) . ')' );
	foreach ( $calls as $c ) {
		$path   = '/' . \CoinsnapBIF\CoinsnapBIF_Constants::REST_NAMESPACE . '/' . $c[1] . ( ! empty( $c[2] ) ? 'abc123DEF' : '' );
		$method = strtoupper( $c[3] );
		$ok( $registered( $path, $method ), "$method $path is a registered route" );
	}

	// The status poll is what confirms a payment: it must stay registered as GET.
	$ok( $registered( '/coinsnapbif/v1/status/abc123DEF', 'GET' ), 'GET status/<invoice> is registered' );
	$ok( false === strpos( $js, 'verify-payment' ), 'frontend.js does not call verify-payment' );

	echo $fail ? "\n$fail failed\n" : "\nall passed\n";
	exit( $fail ? 1 : 0 );
}
