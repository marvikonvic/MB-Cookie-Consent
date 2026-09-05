<?php
/** Standalone inventory, editing and authorization regressions. */
define( 'ABSPATH', __DIR__ );
$store = array( 'mbcc_settings' => array( 'cookie_patterns' => "_ga*|analytics\npll_language|preferences", 'script_patterns' => 'example.com/tag.js|analytics', 'custom_text' => 'Keep me' ) );
$allowed = true;
$nonce = true;
$fail = '';
class MBCC_Settings {
	const OPTION_NAME = 'mbcc_settings';
	public static function get() { return get_option( self::OPTION_NAME ); }
}
function get_option( $key, $default = array() ) { global $store; return isset( $store[$key] ) ? $store[$key] : $default; }
function update_option( $key, $v, $autoload = null ) { global $store, $fail; if ( $key === $fail ) { return false; } $changed = ! isset( $store[$key] ) || $store[$key] !== $v; $store[$key] = $v; return $changed; }
function current_user_can( $cap ) { global $allowed; return $allowed; }
function check_admin_referer( $action ) { global $nonce; if ( ! $nonce ) { throw new RuntimeException('nonce'); } }
function wp_die( $v ) { throw new RuntimeException($v); }
function wp_safe_redirect( $v ) { throw new RuntimeException('redirect'); }
function admin_url( $v ) { return $v; }
function sanitize_text_field( $v ) { return strip_tags($v); }
function sanitize_key( $v ) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($v)); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode($v); }
function esc_url_raw( $v ) { return $v; }
function wp_parse_url( $v, $part = -1 ) { return parse_url($v, $part); }
function __( $v, $d ) { return $v; }
function esc_html__( $v, $d ) { return $v; }
require dirname(__DIR__) . '/includes/class-mbcc-blocker.php';
require dirname(__DIR__) . '/includes/class-mbcc-scanner.php';
require dirname(__DIR__) . '/includes/class-mbcc-cookies.php';
function check_cookie( $v, $message ) { if (!$v) { throw new RuntimeException($message); } }
function post_cookie( $name, $category, $extra = array() ) {
	$_POST = array_merge(array('cookie_name' => $name, 'category' => $category, 'revision' => MBCC_Cookies::revision(MBCC_Settings::get(), get_option(MBCC_Cookies::OPTION_NAME))), $extra);
	try { (new MBCC_Cookies())->save(); } catch (RuntimeException $e) { return $e->getMessage(); }
}
$original = $store;
$allowed = false;
post_cookie('pll_language', 'marketing');
check_cookie($store === $original, 'Unauthorized edit must not write');
$allowed = true; $nonce = false;
post_cookie('pll_language', 'marketing');
check_cookie($store === $original, 'Invalid nonce must not write');
$nonce = true;
post_cookie('pll_language', 'marketing', array('revision' => 'stale'));
check_cookie($store === $original, 'Stale form must not write');
$fail = 'mbcc_settings';
post_cookie('pll_language', 'marketing');
check_cookie($store === $original, 'Failed rule write must preserve inventory');
$fail = '';
check_cookie('redirect' === post_cookie('pll_language', 'marketing'), 'Edit redirects after saving');
check_cookie(false !== strpos($store['mbcc_settings']['cookie_patterns'], 'pll_language|marketing'), 'Existing rule category updated');
check_cookie(1 === substr_count($store['mbcc_settings']['cookie_patterns'], 'pll_language|'), 'No duplicate rule');
check_cookie('Keep me' === $store['mbcc_settings']['custom_text'], 'Unrelated settings preserved');
check_cookie('redirect' === post_cookie('_ga_TEST', 'preferences'), 'Wildcard edit accepted');
check_cookie(false !== strpos($store['mbcc_settings']['cookie_patterns'], '_ga*|preferences'), 'Covering wildcard updated');
check_cookie(false === strpos($store['mbcc_settings']['cookie_patterns'], '_ga_TEST|'), 'No shadow exact rule');
$before = $store['mbcc_settings'];
check_cookie('redirect' === post_cookie('server_session', 'necessary', array('httponly' => '1', 'domain' => 'example.com')), 'Manual HttpOnly record');
check_cookie($before === $store['mbcc_settings'], 'HttpOnly must not write removal rules');
$row = $store[MBCC_Cookies::OPTION_NAME][sha1('server_session')];
check_cookie($row['server'] && $row['httponly'], 'HttpOnly implies server control');
check_cookie(MBCC_Cookies::record(array('value' => 'server_session', 'server' => true)), 'Rescan stored');
check_cookie('necessary' === $store[MBCC_Cookies::OPTION_NAME][sha1('server_session')]['category'], 'Rescan preserves confirmed category');
check_cookie('redirect' === post_cookie('server_session', 'marketing'), 'Server category may be edited');
check_cookie($before === $store['mbcc_settings'], 'Server edit remains informational');
check_cookie('redirect' === post_cookie('unknown_cookie', ''), 'Unclassified manual cookie accepted');
$rows = MBCC_Cookies::inventory(MBCC_Settings::get());
check_cookie('' === $rows[sha1('unknown_cookie')]['category'], 'Unknown has no automatic category');
check_cookie('necessary' === $rows[sha1('mbcc_consent')]['category'], 'Consent record visible');
$before = $store;
post_cookie('mbcc_consent', 'marketing'); post_cookie('bad|name', 'analytics'); post_cookie('valid', 'invalid');
check_cookie($before === $store, 'Protected cookie and malformed rules rejected');
$data = MBCC_Scanner::extract_items('<script>document.cookie="server_session=never-store-this";</script>', 'server_session=secret; Expires=Wed, 09 Jun 2027 10:18:14 GMT; Domain=.example.com; HttpOnly, next_cookie=secret2; Path=/');
check_cookie(2 === count($data) && $data[0]['httponly'] && $data[0]['server'], 'Combined headers and inline duplicate preserve flags');
check_cookie('.example.com' === $data[0]['domain'], 'Header domain preserved');
check_cookie(false === strpos(json_encode($data), 'secret') && false === strpos(json_encode($data), 'never-store-this'), 'No cookie values stored');
$store['mbcc_settings']['cookie_patterns'] .= "\n_ga_TEST|analytics";
$before = $store;
check_cookie(false !== strpos(post_cookie('_ga_TEST', 'marketing'), 'Overlapping'), 'Conflicting wildcard requires review');
check_cookie($before === $store, 'Overlap rejection preserves settings');
check_cookie('x|marketing' === MBCC_Cookies::replace_rule("x|analytics\nx|necessary", 'x', 'marketing'), 'Exact duplicates collapsed');
echo "PASS: inventory, category edits, wildcard scope, server records, metadata, rescan, authorization and write failures\n";
