<?php
/**
 * Optional data cleanup.
 *
 * @package MBCookieConsent
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mbcc_settings = get_option( 'mbcc_settings', array() );
if ( is_array( $mbcc_settings ) && ! empty( $mbcc_settings['delete_data_on_uninstall'] ) ) {
	delete_option( 'mbcc_settings' );
}
