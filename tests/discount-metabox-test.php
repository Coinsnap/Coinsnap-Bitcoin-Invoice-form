<?php
/**
 * Form edit page, "Bitcoin Discount" box (PHP CLI, WordPress stubs): php tests/discount-metabox-test.php
 *
 * - The saved discount type must be the selected option. A hard-coded `selected` on "Percentage"
 *   made every re-saved form switch a fixed discount (e.g. 5 EUR off) to a percentage (5 % off).
 * - Forms saved before the label fields existed must render without "Undefined array key" warnings.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
	$GLOBALS['meta'] = array();
	function __( $s, $d = null ) { return $s; }
	function esc_html__( $s, $d = null ) { return $s; }
	function esc_attr__( $s, $d = null ) { return $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_html_e( $s, $d = null ) { echo $s; }
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
	function checked( $a, $b, $echo = true ) { return (string) $a === (string) $b ? " checked='checked'" : ''; }
	function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? " selected='selected'" : ''; }
	function wp_nonce_field( ...$a ) { return ''; }
	function get_option( $k, $d = false ) { return $d; }
	function add_action( ...$a ) {}
	function add_filter( ...$a ) {}
}
namespace CoinsnapBIF { class CoinsnapBIF_Constants { const CPT_INVOICE_FORM = 'coinsnapbif'; const SHORTCODE_INVOICE_FORM = 'coinsnap_invoice_form'; } }
namespace {
	require dirname( __DIR__ ) . '/src/CPT/class-coinsnapbif-cpt-invoice-form-post-type.php';
	$warnings = array();
	set_error_handler( function ( $no, $str ) use ( &$warnings ) { $warnings[] = $str; return true; } );

	$render = function ( $id ) {
		ob_start();
		\CoinsnapBIF\CPT\CoinsnapBIF_CPT_Invoice_Form_Post_Type::render_fields_metabox( new WP_Post( $id ) );
		return ob_get_clean();
	};
	// Options of the discount type select that the browser submits as selected (the last one with "selected" wins).
	$chosen = function ( $html ) {
		preg_match( '#<select name="coinsnapbif_fields\[discount_type\]".*?</select>#s', $html, $m );
		preg_match_all( '#<option([^>]*)value="(\w+)"([^>]*)>#', $m[0] ?? '', $o, PREG_SET_ORDER );
		$sel = array();
		foreach ( $o as $opt ) {
			if ( preg_match( '/\bselected\b/', $opt[1] . ' ' . $opt[3] ) ) {
				$sel[] = $opt[2];
			}
		}
		return $sel;
	};

	$fail = 0;
	$ok   = function ( $c, $m ) use ( &$fail ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; $fail += $c ? 0 : 1; };

	// A form saved with a fixed discount, before the label fields were added.
	$GLOBALS['meta'][10]['_coinsnapbif_fields'] = array( 'discount_enabled' => '1', 'discount_type' => 'fixed', 'discount_value' => '5' );
	$html = $render( 10 );
	$ok( array( 'fixed' ) === $chosen( $html ), 'saved fixed discount: only "Fixed amount" is selected (got ' . implode( ',', $chosen( $html ) ) . ')' );
	$ok( array() === $warnings, 'form without label fields renders without warnings' . ( $warnings ? ' (' . implode( '; ', array_unique( $warnings ) ) . ')' : '' ) );

	$warnings = array();
	$GLOBALS['meta'][11]['_coinsnapbif_fields'] = array( 'discount_enabled' => '1', 'discount_type' => 'percent', 'discount_value' => '10' );
	$ok( array( 'percent' ) === $chosen( $render( 11 ) ), 'saved percentage discount: "Percentage" is selected' );

	$html = $render( 12 ); // new form, nothing saved
	$ok( array( 'percent' ) === $chosen( $html ), 'new form: "Percentage" is the default' );
	$ok( array() === $warnings, 'new form renders without warnings' . ( $warnings ? ' (' . implode( '; ', array_unique( $warnings ) ) . ')' : '' ) );

	echo $fail ? "$fail failed\n" : "all passed\n";
	exit( $fail ? 1 : 0 );
}
