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
