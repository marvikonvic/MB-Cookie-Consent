<?php
/**
 * Main plugin coordinator.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

final class MBCC_Plugin {
	/** @var MBCC_Plugin|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/**
	 * Get the singleton.
	 *
	 * @return MBCC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add defaults on first activation without overwriting saved settings.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( MBCC_Settings::OPTION_NAME, false ) ) {
			add_option( MBCC_Settings::OPTION_NAME, MBCC_Settings::defaults(), '', false );
		}
	}

	/**
	 * Register hooks and services.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		$settings = new MBCC_Settings();
		$settings->register_hooks();

		$options = MBCC_Settings::get();
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		$blocker = new MBCC_Blocker( $options );
		$blocker->register_hooks();

		$frontend = new MBCC_Frontend( $options );
		$frontend->register_hooks();
	}

	/**
	 * Load translations for source/admin strings.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mb-cookie-consent', false, dirname( plugin_basename( MBCC_FILE ) ) . '/languages' );
	}
}
