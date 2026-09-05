<?php
/** Standalone regression tests; run with php tests/blocker-regression.php. */
define( 'ABSPATH', __DIR__ );
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
require dirname( __DIR__ ) . '/includes/class-mbcc-blocker.php';
function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}
$blocker = new MBCC_Blocker(
	array(
		'auto_blocking'   => 1,
		'script_handles'  => "mbcc-frontend|marketing\npll_cookie_script|preferences",
		'script_patterns' => "pll_cookie_script|preferences\nmb-cookie-consent|marketing",
		'iframe_patterns' => '',
	)
);
foreach ( array( 'mbcc-frontend-js', 'mbcc-frontend-js-extra', 'mbcc-frontend-js-before', 'mbcc-frontend-js-after' ) as $id ) {
	$tag = '<script id="' . $id . '">var rule="pll_cookie_script";</script>';
	$html = '<html><body>' . $tag . '</body></html>';
	check( $html === $blocker->filter_html( $html ), "Own script must remain executable: $id" );
}
$tag = '<script id="mbcc-frontend-js" src="/wp-content/plugins/mb-cookie-consent/assets/js/frontend.js"></script>';
check( $tag === $blocker->filter_script_loader_tag( $tag, 'mbcc-frontend' ), 'Own handle must not be blocked' );
check( '<html>' . $tag . '</html>' === $blocker->filter_html( '<html>' . $tag . '</html>' ), 'Own asset must survive URL rules' );
foreach ( array( 'before', 'after' ) as $placement ) {
	$tag = '<script id="pll_cookie_script-js-' . $placement . '">document.cookie="pll_language=srp";</script>';
	$html = $blocker->filter_html( '<html>' . $tag . '</html>' );
	check( false !== strpos( $html, 'type="text/plain"' ), 'Polylang inline code must remain inert' );
	check( false !== strpos( $html, 'data-mbcc-category="preferences"' ), 'Polylang category must be preserved' );
	check( $html === $blocker->filter_html( $html ), 'Filtering must be idempotent' );
}
// An ID mentioned inside another script is not an exemption for that script.
$html = $blocker->filter_html( '<html><script>var tag=\'<script id="mbcc-frontend-js-extra">\';var rule="pll_cookie_script";</script></html>' );
check( false !== strpos( $html, 'data-mbcc-blocked="1"' ), 'Only the actual opening tag ID may be exempt' );
echo "PASS: own runtime/config protection and Polylang inline blocking\n";

$edge = new MBCC_Blocker( array( 'auto_blocking' => 1, 'script_handles' => 'demo|analytics', 'script_patterns' => "example.com|analytics\nx.com|analytics", 'iframe_patterns' => 'example.com|marketing' ) );
$cases = array(
	'unquoted module' => array( '<script type=module src="https://example.com/a.js"></script>', 'https://example.com/a.js', true ),
	'unquoted source' => array( '<script src=https://example.com/a.js></script>', 'https://example.com/a.js', false ),
	'quoted greater-than' => array( '<script src="https://x.com/x?a=1>2" data-mbcc-category="analytics">/* untouched */</script>', 'https://x.com/x?a=1>2', false ),
	'single quotes' => array( "<script type='module' src='https://example.com/a.js?v=1>2'></script>", 'https://example.com/a.js?v=1>2', true ),
	'duplicate attributes' => array( '<script type=module type="text/javascript" src=https://example.com/a.js src="https://example.com/b.js"></script>', 'https://example.com/a.js', true ),
);
foreach ( $cases as $label => $case ) {
	foreach ( array( 'html', 'handle' ) as $route ) {
		$input = 'html' === $route ? '<html>' . $case[0] . '</html>' : $case[0];
		$result = 'html' === $route ? $edge->filter_html( $input ) : $edge->filter_script_loader_tag( $input, 'demo' );
		check( 1 === preg_match_all( '/\stype\s*=/i', $result ), "$label/$route: exactly one type" );
		check( false !== strpos( $result, 'type="text/plain"' ), "$label/$route: inert type" );
		check( 0 === preg_match( '/\ssrc\s*=/i', $result ), "$label/$route: no executable src" );
		check( false !== strpos( $result, 'data-mbcc-src="' . esc_attr( $case[1] ) . '"' ), "$label/$route: source preserved" );
		if ( $case[2] ) {
			check( false !== strpos( $result, 'data-mbcc-type="module"' ), "$label/$route: module type preserved" );
		}
		$again = 'html' === $route ? $edge->filter_html( $result ) : $edge->filter_script_loader_tag( $result, 'demo' );
		check( $result === $again, "$label/$route: idempotent" );
	}
}
$input = '<html><script data-note=" type=module src=fake" src=https://example.com/a.js></script></html>';
$result = $edge->filter_html( $input );
check( false !== strpos( $result, 'data-note=" type=module src=fake"' ), 'Attribute-like strings inside values remain unchanged' );
check( false === strpos( $result, 'data-mbcc-type="module"' ), 'Attribute-like strings are not real attributes' );
foreach ( array( 'src=https://example.com/embed', 'src="https://example.com/embed?v=1>2"' ) as $source ) {
	$result = $edge->filter_html( '<html><iframe ' . $source . '></iframe></html>' );
	check( false !== strpos( $result, 'data-mbcc-src=' ) && 0 === preg_match( '/\ssrc\s*=/', $result ), 'Iframe source is inert' );
	check( $result === $edge->filter_html( $result ), 'Iframe filtering is idempotent' );
}
$input = '<!DOCTYPE html><html><!-- <script src=https://example.com/a.js></script> --><div title="<script src=https://example.com/a.js>">ok</div></html>';
check( $input === $edge->filter_html( $input ), 'Comments and attribute contents remain byte-for-byte unchanged' );
foreach ( array( 'var x="<\/script>";', 'var x="</script";', 'var x="</script>";' ) as $body ) {
	$input = '<html><script data-mbcc-category="analytics">' . $body . '</script><script src=https://example.com/next.js></script></html>';
	$result = $edge->filter_html( $input );
	check( 2 === substr_count( $result, 'type="text/plain"' ), 'Closing-tag fixture preserves following script processing' );
	check( false !== strpos( $result, $body ), 'Script body is not reserialized' );
	check( $result === $edge->filter_html( $result ), 'Closing-tag fixture is idempotent' );
}
echo "PASS: attribute forms, module metadata, quoted >, duplicate attributes and idempotency\n";
