<?php
/**
 * Plugin Name:       MB Cookie Consent
 * Description:       Bilingual, privacy-first cookie consent and script blocking for WordPress.
 * Version:           1.0.7
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            MB
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mb-cookie-consent
 * Domain Path:       /languages
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

define( 'MBCC_VERSION', '1.0.7' );
define( 'MBCC_FILE', __FILE__ );
define( 'MBCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MBCC_URL', plugin_dir_url( __FILE__ ) );

require_once MBCC_PATH . 'includes/class-mbcc-settings.php';
require_once MBCC_PATH . 'includes/class-mbcc-blocker.php';
require_once MBCC_PATH . 'includes/class-mbcc-frontend.php';
require_once MBCC_PATH . 'includes/class-mbcc-plugin.php';

register_activation_hook( __FILE__, array( 'MBCC_Plugin', 'activate' ) );

MBCC_Plugin::instance()->boot();
