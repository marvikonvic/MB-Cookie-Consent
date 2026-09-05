<?php
/**
 * Main plugin coordinator.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

final class MBCC_Plugin {
	const RULES_VERSION_OPTION = 'mbcc_rules_version';
	const RULES_VERSION        = '1.0.7';

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
			add_option( MBCC_Settings::OPTION_NAME, MBCC_Settings::defaults(), '', true );
			update_option( self::RULES_VERSION_OPTION, self::RULES_VERSION, false );
		} else {
			wp_set_option_autoload_values( array( MBCC_Settings::OPTION_NAME => true ) );
		}
	}

	/**
	 * Add new stock defaults once without replacing site-specific settings.
	 * Existing rules win even when their category differs from the new default.
	 *
	 * @return void
	 */
	public static function migrate_default_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_version = (string) get_option( self::RULES_VERSION_OPTION, '0' );
		if ( version_compare( $current_version, self::RULES_VERSION, '>=' ) ) {
			return;
		}

		$settings = get_option( MBCC_Settings::OPTION_NAME, false );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$required_rules = array(
			'script_handles'  => array(
				'google_gtagjs|analytics',
				'googlesitekit-events-provider-content-events|analytics',
			),
			'script_patterns' => array(
				'googletagmanager.com/gtag/js|analytics',
				'google-site-kit/dist/assets/js/googlesitekit-events-provider-content-events-|analytics',
			),
		);

		$changed = false;
		foreach ( $required_rules as $key => $rules ) {
			$original         = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			$settings[ $key ] = self::append_missing_rules( $original, $rules );
			$changed          = $changed || $settings[ $key ] !== $original;
		}

		$upgraded_settings = MBCC_Settings::replace_legacy_default_texts( $settings );
		$changed           = $changed || $upgraded_settings !== $settings;
		$settings          = $upgraded_settings;

		if ( $changed && ! update_option( MBCC_Settings::OPTION_NAME, $settings, true ) ) {
			return;
		}

		update_option( self::RULES_VERSION_OPTION, self::RULES_VERSION, false );
	}

	/**
	 * Append rules whose value is not already present in any category.
	 *
	 * @param string            $stored Existing newline-delimited rules.
	 * @param array<int,string> $required Rules to add.
	 * @return string
	 */
	private static function append_missing_rules( $stored, $required ) {
		$lines  = preg_split( '/\r\n|\r|\n/', $stored );
		$lines  = is_array( $lines ) ? $lines : array();
		$values = array();
		$missing = array();

		foreach ( $lines as $line ) {
			$parts = explode( '|', trim( $line ), 2 );
			$value = strtolower( trim( $parts[0] ) );
			if ( '' !== $value ) {
				$values[ $value ] = true;
			}
		}

		foreach ( $required as $rule ) {
			$parts = explode( '|', $rule, 2 );
			$value = strtolower( trim( $parts[0] ) );
			if ( isset( $values[ $value ] ) ) {
				continue;
			}

			$missing[]        = $rule;
			$values[ $value ] = true;
		}

		if ( empty( $missing ) ) {
			return $stored;
		}

		$separator = '' === $stored || preg_match( '/(?:\r\n|\r|\n)$/', $stored ) ? '' : "\n";
		return $stored . $separator . implode( "\n", $missing );
	}

	/**
	 * Upgrade existing installations without replacing their settings.
	 * Uses the cached alloptions list, so no repeated writes are required.
	 *
	 * @return void
	 */
	public static function ensure_settings_autoload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$autoloaded = wp_load_alloptions();
		if ( isset( $autoloaded[ MBCC_Settings::OPTION_NAME ] ) ) {
			return;
		}
		if ( false !== get_option( MBCC_Settings::OPTION_NAME, false ) ) {
			wp_set_option_autoload_values( array( MBCC_Settings::OPTION_NAME => true ) );
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
		add_action( 'admin_init', array( __CLASS__, 'ensure_settings_autoload' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_default_rules' ) );

		$settings = new MBCC_Settings();
		$settings->register_hooks();

		$scanner = new MBCC_Scanner();
		$scanner->register_hooks();

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
