<?php
/** Isolated persistence, failure-reporting and authorization regression tests. */
define( 'ABSPATH', __DIR__ );
define( 'MBCC_VERSION', 'test' );
define( 'HOUR_IN_SECONDS', 3600 );
class MBCC_Settings {
	const OPTION_NAME = 'mbcc_settings';
	public static function get() { return array(); }
}
$store = array();
$job = array();
$deny_write = false;
$allowed = true;
$nonce_ok = true;
function current_user_can( $cap ) { global $allowed; return $allowed; }
function sanitize_key( $v ) { return $v; }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function esc_url_raw( $v ) { return $v; }
function wp_parse_url( $v, $part = -1 ) { return parse_url( $v, $part ); }
function esc_html__( $v, $domain ) { return $v; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return esc_html( $v ); }
function wp_nonce_field( $v ) {}
function __( $v, $domain ) { return $v; }
function check_admin_referer( $v ) { global $nonce_ok; if ( ! $nonce_ok ) { throw new RuntimeException( 'nonce' ); } }
function check_ajax_referer( $v, $key ) { check_admin_referer( $v ); }
function get_option( $key, $default = array() ) { global $store; return isset( $store[$key] ) ? $store[$key] : $default; }
function update_option( $key, $value, $autoload = null ) { global $store, $deny_write, $deny_inventory; if ( ( 'mbcc_settings' === $key && $deny_write ) || ( 'mbcc_cookie_inventory' === $key && ! empty( $deny_inventory ) ) ) { return false; } $store[$key] = $value; return true; }
function wp_die( $v ) { throw new RuntimeException( $v ); }
function admin_url( $v ) { return $v; }
function wp_safe_redirect( $v ) { throw new RuntimeException( 'redirect' ); }
function get_current_user_id() { return 1; }
function get_transient( $key ) { global $job; return $job; }
function set_transient( $key, $value, $duration ) { global $job; $job = $value; }
function delete_transient( $key ) { global $job; $job = null; }
function home_url( $v ) { return 'https://example.com' . $v; }
function wp_safe_remote_get( $url, $options ) { return false !== strpos( $url, 'failed' ) ? 'error' : array(); }
function is_wp_error( $v ) { return 'error' === $v; }
function wp_remote_retrieve_response_code( $v ) { return 200; }
function wp_remote_retrieve_body( $v ) { global $fixture_html; return isset( $fixture_html ) ? $fixture_html : '<html></html>'; }
function wp_remote_retrieve_header( $v, $name ) { return ''; }
function wp_send_json_success( $v ) { throw new RuntimeException( 'success' ); }
function wp_send_json_error( $v, $status ) { throw new RuntimeException( 'denied' ); }
require dirname( __DIR__ ) . '/includes/class-mbcc-blocker.php';
require dirname( __DIR__ ) . '/includes/class-mbcc-scanner.php';
function verify_state( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function invoke_scanner( $method ) { try { ( new MBCC_Scanner() )->$method(); } catch ( RuntimeException $e ) { return $e->getMessage(); } return ''; }
$initial = array( 'mbcc_settings' => array( 'script_patterns' => '' ), 'mbcc_scan_results' => array( 'sample' => array( 'type' => 'script', 'value' => 'example.com/a.js' ) ) );
$_POST = array( 'item_id' => 'sample', 'category' => 'analytics' );
$store = $initial;
$deny_write = true;
verify_state( false !== strpos( invoke_scanner( 'add_rule' ), 'retained' ), 'Failure must be reported' );
verify_state( $initial === $store, 'Failed write must preserve finding and settings' );
$deny_write = false;
$allowed = false;
invoke_scanner( 'add_rule' );
verify_state( $initial === $store, 'Unauthorized action must not mutate data' );
$allowed = true;
$nonce_ok = false;
invoke_scanner( 'add_rule' );
verify_state( $initial === $store, 'Invalid nonce must not mutate data' );
$nonce_ok = true;
verify_state( 'redirect' === invoke_scanner( 'add_rule' ), 'Successful write returns to admin' );
verify_state( 'example.com/a.js|analytics' === $store['mbcc_settings']['script_patterns'] && array() === $store['mbcc_scan_results'], 'Successful write adds rule and removes finding' );
foreach ( array( array( 'https://example.com/ok', 'https://example.com/failed' ), array( 'https://example.com/failed' ) ) as $urls ) {
	$job = array( 'urls' => $urls, 'offset' => 0, 'errors' => 0, 'failed_urls' => array() );
	verify_state( 'success' === invoke_scanner( 'scan_batch' ), 'Batch completes' );
	$summary = $store['mbcc_scan_summary'];
	verify_state( count( $urls ) === $summary['total'] && 1 === $summary['errors'], 'Failure counts must persist' );
	verify_state( array( 'https://example.com/failed' ) === $summary['failed_urls'], 'Failed URLs must be visible after reload' );
}
ob_start();
( new MBCC_Scanner() )->render_page();
$rendered = ob_get_clean();
verify_state( false !== strpos( $rendered, '0 successful, 1 failed, 1 total URLs.' ), 'All-failed summary visible after page load' );
verify_state( false !== strpos( $rendered, 'https://example.com/failed' ), 'Failed URL displayed' );
echo "PASS: scanner writes, authorization and partial/all-failed summaries\n";

foreach ( array( 'cookie', 'script', 'iframe' ) as $type ) {
	$store = array( 'mbcc_scan_results' => array( 'pipe' => array( 'type' => $type, 'value' => 'bad|name' ) ) );
	$_POST = array( 'item_id' => 'pipe', 'category' => 'analytics' );
	$before = $store;
	verify_state( false !== strpos( invoke_scanner( 'add_rule' ), 'retained' ), 'Delimiter must report error' );
	verify_state( $before === $store, 'Delimiter rejection retains finding and settings' );
}
require dirname( __DIR__ ) . '/includes/class-mbcc-cookies.php';
$store = array();
for ( $i = 0; $i < MBCC_Cookies::MAX_RECORDS; ++$i ) {
	MBCC_Cookies::record( array( 'value' => 'existing_' . $i ) );
}
$fixture_html = '<script src="https://example.com/first.js"></script><script>document.cookie="new_cookie=value";</script><iframe src="https://example.com/last"></iframe>';
$job = array( 'urls' => array( 'https://example.com/ok' ), 'offset' => 0, 'errors' => 0, 'failed_urls' => array() );
verify_state( 'success' === invoke_scanner( 'scan_batch' ), 'Full inventory does not abort batch' );
verify_state( 0 === $store['mbcc_scan_summary']['errors'], 'Inventory capacity is not a URL failure' );
verify_state( ! empty( $store['mbcc_scan_summary']['warnings']['inventory_full'] ), 'Capacity warning persists' );
verify_state( 3 === count( $store['mbcc_scan_results'] ), 'Keep findings before and after the new cookie' );
verify_state( 1000 === count( $store[MBCC_Cookies::OPTION_NAME] ), 'Inventory remains bounded' );
echo "PASS: delimiter rejection and full-inventory partial persistence\n";
$store = array();
$deny_inventory = true;
$job = array( 'urls' => array( 'https://example.com/ok' ), 'offset' => 0, 'errors' => 0, 'failed_urls' => array() );
invoke_scanner( 'scan_batch' );
verify_state( ! empty( $store['mbcc_scan_summary']['warnings']['inventory_write_failed'] ), 'Write failure warning persists separately' );
verify_state( 0 === $store['mbcc_scan_summary']['errors'] && 3 === count( $store['mbcc_scan_results'] ), 'Write failure keeps page findings' );
echo "PASS: inventory write failure is not a failed URL\n";
