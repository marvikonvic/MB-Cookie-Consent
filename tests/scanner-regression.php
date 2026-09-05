<?php
/** Standalone regression tests for manual scanner extraction and suggestions. */

define( 'ABSPATH', __DIR__ );

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function mbcc_scanner_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-mbcc-blocker.php';
require_once dirname( __DIR__ ) . '/includes/class-mbcc-scanner.php';

$html = <<<'HTML'
<html><head>
<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>
<script>document.cookie = "custom_cookie=value; path=/";</script>
<script src="data:text/javascript,alert(1)"></script>
</head><body><iframe src="https://www.youtube.com/embed/example?autoplay=0"></iframe></body></html>
HTML;

$items = MBCC_Scanner::extract_items(
	$html,
	array(
		'_ga=GA1.1.123; Path=/; SameSite=Lax',
		'server_session=secret; Path=/; HttpOnly, preference_cookie=yes; Path=/',
	)
);
$indexed = array();
foreach ( $items as $item ) {
	$indexed[ $item['type'] . '|' . $item['value'] ] = true;
}

mbcc_scanner_assert( isset( $indexed['cookie|_ga'] ), 'Set-Cookie name was not extracted.' );
mbcc_scanner_assert( isset( $indexed['cookie|server_session'] ), 'HttpOnly Set-Cookie name was not extracted.' );
mbcc_scanner_assert( isset( $indexed['cookie|preference_cookie'] ), 'Second Set-Cookie name was not extracted.' );
mbcc_scanner_assert( isset( $indexed['cookie|custom_cookie'] ), 'Inline document.cookie name was not extracted.' );
mbcc_scanner_assert( isset( $indexed['script|www.googletagmanager.com/gtag/js'] ), 'Script URL was not normalized.' );
mbcc_scanner_assert( isset( $indexed['iframe|www.youtube.com/embed/example'] ), 'Iframe URL was not normalized.' );
mbcc_scanner_assert( ! isset( $indexed['script|data:text/javascript,alert(1)'] ), 'Data URL should be ignored.' );
mbcc_scanner_assert( 'analytics' === MBCC_Scanner::suggest_category( 'cookie', '_ga_TEST' ), 'Analytics suggestion failed.' );
mbcc_scanner_assert( 'marketing' === MBCC_Scanner::suggest_category( 'script', 'connect.facebook.net/en_US/fbevents.js' ), 'Marketing suggestion failed.' );
mbcc_scanner_assert( 'preferences' === MBCC_Scanner::suggest_category( 'cookie', 'pll_language' ), 'Preferences suggestion failed.' );
mbcc_scanner_assert( 'necessary' === MBCC_Scanner::suggest_category( 'cookie', 'mbcc_consent' ), 'Necessary suggestion failed.' );
mbcc_scanner_assert( '' === MBCC_Scanner::suggest_category( 'cookie', 'unknown_cookie' ), 'Unknown item should require manual classification.' );

echo "PASS: cookie names, resources, normalization and conservative category suggestions.\n";
foreach ( array(
 '<script src=https://example.com/a.js></script>',
 '<script title="1>2" src="https://example.com/a.js"></script>',
 '<script data-mbcc-src="https://example.com/a.js" type="text/plain"></script>'
) as $fixture ) {
 $found = MBCC_Scanner::extract_items( $fixture );
 mbcc_scanner_assert( count( $found ) === 1 && 'example.com/a.js' === $found[0]['value'], 'Static resource must be discovered.' );
}
foreach ( array(
 '<!-- <script src="https://example.com/a.js"></script> -->',
 '<script data-src="https://example.com/a.js"></script>',
 '<textarea><script src="https://example.com/a.js"></script></textarea>',
 '<style>/* <script src="https://example.com/a.js"></script> */</style>',
 '<div title=\'<script src="https://example.com/a.js"></script>\'></div>'
) as $fixture ) {
 mbcc_scanner_assert( array() === MBCC_Scanner::extract_items( $fixture ), 'Non-resource text must be ignored.' );
}
mbcc_scanner_assert( 'example.com:8080/a.js' === MBCC_Scanner::normalize_resource( 'http://example.com:8080/a.js' ), 'Port must survive normalization.' );
echo "PASS: scanner contexts, attribute forms and URL ports\n";
