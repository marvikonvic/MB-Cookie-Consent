<?php
/** Standalone regression tests for default-rule upgrades; no WordPress DB access. */

define( 'ABSPATH', __DIR__ );

$mbcc_test_options = array();
$mbcc_test_admin   = true;
$mbcc_test_writes  = 0;

function get_option( $name, $default = false ) {
	global $mbcc_test_options;
	return array_key_exists( $name, $mbcc_test_options ) ? $mbcc_test_options[ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	global $mbcc_test_options, $mbcc_test_writes;
	$changed                      = ! array_key_exists( $name, $mbcc_test_options ) || $mbcc_test_options[ $name ] !== $value;
	$mbcc_test_options[ $name ]   = $value;
	++$mbcc_test_writes;
	return $changed;
}

function current_user_can( $capability ) {
	global $mbcc_test_admin;
	return $mbcc_test_admin && 'manage_options' === $capability;
}

function mbcc_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-mbcc-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-mbcc-plugin.php';

$defaults = MBCC_Settings::defaults();
mbcc_test_assert( false !== strpos( $defaults['script_handles'], "google_gtagjs|analytics" ), 'Google Tag handle default is missing.' );
mbcc_test_assert( false !== strpos( $defaults['script_handles'], "googlesitekit-events-provider-content-events|analytics" ), 'Site Kit handle default is missing.' );
mbcc_test_assert( false !== strpos( $defaults['script_patterns'], "googletagmanager.com/gtag/js|analytics" ), 'Generic Google Tag URL default is missing.' );
mbcc_test_assert( false !== strpos( $defaults['script_patterns'], "google-site-kit/dist/assets/js/googlesitekit-events-provider-content-events-|analytics" ), 'Site Kit URL default is missing.' );
mbcc_test_assert( strpos( $defaults['script_patterns'], 'gtag/js?id=AW-|marketing' ) < strpos( $defaults['script_patterns'], 'gtag/js|analytics' ), 'Google Ads rule must precede the generic Google Tag rule.' );

$mbcc_test_options['mbcc_settings'] = array(
	'script_handles'  => "custom-handle|preferences\ngoogle_gtagjs|marketing",
	'script_patterns' => "custom.example/script.js|preferences\ngoogletagmanager.com/gtag/js|marketing",
	'consent_version' => 'custom',
);

MBCC_Plugin::migrate_default_rules();
$migrated = $mbcc_test_options['mbcc_settings'];
mbcc_test_assert( false !== strpos( $migrated['script_handles'], 'custom-handle|preferences' ), 'Custom handle was removed.' );
mbcc_test_assert( false !== strpos( $migrated['script_handles'], 'google_gtagjs|marketing' ), 'Custom Google Tag category was overwritten.' );
mbcc_test_assert( false === strpos( $migrated['script_handles'], 'google_gtagjs|analytics' ), 'Duplicate Google Tag handle was added.' );
mbcc_test_assert( false !== strpos( $migrated['script_handles'], 'googlesitekit-events-provider-content-events|analytics' ), 'Missing Site Kit handle was not added.' );
mbcc_test_assert( false !== strpos( $migrated['script_patterns'], 'google-site-kit/dist/assets/js/googlesitekit-events-provider-content-events-|analytics' ), 'Missing Site Kit URL was not added.' );
mbcc_test_assert( 'custom' === $migrated['consent_version'], 'Unrelated settings were changed.' );
mbcc_test_assert( '1.0.5' === $mbcc_test_options['mbcc_rules_version'], 'Rules migration version was not recorded.' );

$after_first_run = $mbcc_test_options;
$writes          = $mbcc_test_writes;
MBCC_Plugin::migrate_default_rules();
mbcc_test_assert( $after_first_run === $mbcc_test_options && $writes === $mbcc_test_writes, 'Migration is not idempotent.' );

$mbcc_test_admin = false;
unset( $mbcc_test_options['mbcc_rules_version'] );
$before_denied = $mbcc_test_options;
MBCC_Plugin::migrate_default_rules();
mbcc_test_assert( $before_denied === $mbcc_test_options, 'A non-administrator triggered the migration.' );

echo "PASS: defaults, precedence, preservation, migration and idempotency.\n";
