<?php
/**
 * Settings storage and admin page.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

class MBCC_Settings {
	const OPTION_NAME = 'mbcc_settings';

	/**
	 * Default frontend text in both supported languages.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function default_texts() {
		return array(
			'en' => array(
				'title'              => 'Your privacy choices',
				'message'            => 'We use necessary cookies to make this site work. With your permission, we also use optional cookies for preferences, analytics and marketing.',
				'accept_all'         => 'Accept all',
				'reject_optional'    => 'Reject optional',
				'settings'           => 'Cookie settings',
				'save'               => 'Save choices',
				'close'              => 'Close',
				'necessary'          => 'Necessary',
				'necessary_desc'     => 'Enabled by default for security and basic site functions. You can disable this category, but parts of the site may stop working.',
				'preferences'        => 'Preferences',
				'preferences_desc'   => 'Remember choices that change how the site behaves or looks.',
				'analytics'          => 'Analytics',
				'analytics_desc'     => 'Help us understand how visitors use the site.',
				'marketing'          => 'Marketing',
				'marketing_desc'     => 'Used for advertising, measurement and external media.',
				'always_active'      => 'Always active',
				'privacy_policy'     => 'Privacy policy',
				'blocked_content'    => 'External content is blocked until you allow its category.',
				'allow_content'      => 'Review cookie settings',
				'reopen'             => 'Change cookie settings',
			),
			'sr' => array(
				'title'              => 'Vaš izbor privatnosti',
				'message'            => 'Koristimo neophodne kolačiće da bi sajt radio. Uz vašu dozvolu koristimo i opcione kolačiće za podešavanja, analitiku i marketing.',
				'accept_all'         => 'Prihvati sve',
				'reject_optional'    => 'Odbij opcione',
				'settings'           => 'Podešavanja kolačića',
				'save'               => 'Sačuvaj izbor',
				'close'              => 'Zatvori',
				'necessary'          => 'Neophodni',
				'necessary_desc'     => 'Podrazumevano su uključeni zbog bezbednosti i osnovnih funkcija sajta. Možete ih isključiti, ali delovi sajta mogu prestati da rade.',
				'preferences'        => 'Podešavanja',
				'preferences_desc'   => 'Pamte izbore koji menjaju način rada ili izgled sajta.',
				'analytics'          => 'Analitika',
				'analytics_desc'     => 'Pomažu nam da razumemo kako posetioci koriste sajt.',
				'marketing'          => 'Marketing',
				'marketing_desc'     => 'Koriste se za oglašavanje, merenje i prikaz spoljnog sadržaja.',
				'always_active'      => 'Uvek aktivni',
				'privacy_policy'     => 'Politika privatnosti',
				'blocked_content'    => 'Spoljni sadržaj je blokiran dok ne dozvolite njegovu kategoriju.',
				'allow_content'      => 'Pregledaj podešavanja kolačića',
				'reopen'             => 'Promeni podešavanja kolačića',
			),
		);
	}

	/**
	 * Plugin defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$texts = self::default_texts();

		return array(
			'enabled'                 => 1,
			'language_mode'           => 'auto',
			'banner_layout'           => 'bar',
			'position'                => 'bottom',
			'floating_position'       => 'left',
			'accent_color'            => '#1769aa',
			'consent_version'         => '1.0',
			'duration_days'           => 180,
			'google_consent_mode'     => 1,
			'auto_blocking'           => 1,
			'allow_disable_necessary' => 1,
			'delete_data_on_uninstall'=> 0,
			'privacy_url_en'          => '',
			'privacy_url_sr'          => '',
			'script_handles'          => "google-analytics|analytics\nmonsterinsights-frontend|analytics\ngoogle-tag-manager|marketing\ngtm4wp-gtm|marketing\nfacebook-pixel|marketing",
			'script_patterns'         => "googletagmanager.com/gtag/js?id=G-|analytics\ngoogle-analytics.com|analytics\nclarity.ms|analytics\nstatic.hotjar.com|analytics\ngoogletagmanager.com/gtm.js|marketing\ngoogletagmanager.com/gtag/js?id=AW-|marketing\nconnect.facebook.net|marketing\ntiktok.com/i18n/pixel|marketing\nsnap.licdn.com|marketing",
			'iframe_patterns'         => "youtube.com/embed|marketing\nyoutube-nocookie.com/embed|marketing\nplayer.vimeo.com/video|marketing\ngoogle.com/maps|marketing\nmaps.google.com|marketing",
			'cookie_patterns'         => "_ga*|analytics\n_gid|analytics\n_gat*|analytics\n_clck|analytics\n_clsk|analytics\n_hj*|analytics\n_fbp|marketing\n_gcl_*|marketing\nIDE|marketing\ntest_cookie|marketing",
			'text_en'                 => $texts['en'],
			'text_sr'                 => $texts['sr'],
		);
	}

	/**
	 * Get merged settings, including new defaults after upgrades.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$options = wp_parse_args( $saved, self::defaults() );
		$texts   = self::default_texts();
		$options['text_en'] = wp_parse_args( isset( $saved['text_en'] ) && is_array( $saved['text_en'] ) ? $saved['text_en'] : array(), $texts['en'] );
		$options['text_sr'] = wp_parse_args( isset( $saved['text_sr'] ) && is_array( $saved['text_sr'] ) ? $saved['text_sr'] : array(), $texts['sr'] );

		return apply_filters( 'mbcc_settings', $options );
	}

	/** Register admin hooks. */
	public function register_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MBCC_FILE ), array( $this, 'action_links' ) );
	}

	/** Register the option with validation. */
	public function register_setting() {
		register_setting(
			'mbcc_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/** Add Settings submenu. */
	public function add_page() {
		add_options_page(
			__( 'MB Cookie Consent', 'mb-cookie-consent' ),
			__( 'MB Cookie Consent', 'mb-cookie-consent' ),
			'manage_options',
			'mb-cookie-consent',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a direct settings link.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=mb-cookie-consent' ) ) . '">' . esc_html__( 'Settings', 'mb-cookie-consent' ) . '</a>' );
		return $links;
	}

	/**
	 * Validate the full settings array.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		foreach ( array( 'enabled', 'google_consent_mode', 'auto_blocking', 'allow_disable_necessary', 'delete_data_on_uninstall' ) as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$output['language_mode']     = in_array( isset( $input['language_mode'] ) ? $input['language_mode'] : '', array( 'auto', 'sr', 'en' ), true ) ? $input['language_mode'] : 'auto';
		$output['banner_layout']     = in_array( isset( $input['banner_layout'] ) ? $input['banner_layout'] : '', array( 'bar', 'card_left', 'card_right', 'card_center' ), true ) ? $input['banner_layout'] : 'bar';
		$output['position']          = in_array( isset( $input['position'] ) ? $input['position'] : '', array( 'top', 'bottom' ), true ) ? $input['position'] : 'bottom';
		$output['floating_position'] = in_array( isset( $input['floating_position'] ) ? $input['floating_position'] : '', array( 'left', 'right' ), true ) ? $input['floating_position'] : 'left';
		$output['accent_color']      = sanitize_hex_color( isset( $input['accent_color'] ) ? $input['accent_color'] : '' );
		$output['accent_color']      = $output['accent_color'] ? $output['accent_color'] : $defaults['accent_color'];
		$output['duration_days']     = min( 3650, max( 1, absint( isset( $input['duration_days'] ) ? $input['duration_days'] : $defaults['duration_days'] ) ) );

		$version = sanitize_text_field( isset( $input['consent_version'] ) ? $input['consent_version'] : '' );
		$output['consent_version'] = preg_match( '/^[A-Za-z0-9._-]{1,32}$/', $version ) ? $version : $defaults['consent_version'];
		$output['privacy_url_en']  = esc_url_raw( isset( $input['privacy_url_en'] ) ? $input['privacy_url_en'] : '' );
		$output['privacy_url_sr']  = esc_url_raw( isset( $input['privacy_url_sr'] ) ? $input['privacy_url_sr'] : '' );

		foreach ( array( 'script_handles', 'script_patterns', 'iframe_patterns', 'cookie_patterns' ) as $key ) {
			$output[ $key ] = sanitize_textarea_field( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}

		$text_defaults = self::default_texts();
		foreach ( array( 'en', 'sr' ) as $language ) {
			$input_key  = 'text_' . $language;
			$output_key = 'text_' . $language;
			$submitted  = isset( $input[ $input_key ] ) && is_array( $input[ $input_key ] ) ? $input[ $input_key ] : array();
			foreach ( $text_defaults[ $language ] as $key => $default ) {
				$value = isset( $submitted[ $key ] ) ? $submitted[ $key ] : $default;
				$output[ $output_key ][ $key ] = 'message' === $key || false !== strpos( $key, '_desc' ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			}
		}

		return $output;
	}

	/** Render the admin settings screen. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = self::get();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'MB Cookie Consent', 'mb-cookie-consent' ); ?></h1>
			<p><?php echo esc_html__( 'Bilingual consent banner and cache-safe blocking for standard WordPress frontends, classic themes and Blogsy.', 'mb-cookie-consent' ); ?></p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'mbcc_settings_group' ); ?>
				<h2><?php echo esc_html__( 'General', 'mb-cookie-consent' ); ?></h2>
				<table class="form-table" role="presentation"><tbody>
				<?php $this->checkbox_row( 'enabled', __( 'Enable consent banner', 'mb-cookie-consent' ), $options ); ?>
				<tr><th scope="row"><label for="mbcc-language-mode"><?php echo esc_html__( 'Frontend language', 'mb-cookie-consent' ); ?></label></th><td>
					<select id="mbcc-language-mode" name="mbcc_settings[language_mode]">
						<option value="auto" <?php selected( $options['language_mode'], 'auto' ); ?>><?php echo esc_html__( 'Automatic (WordPress, Polylang or WPML)', 'mb-cookie-consent' ); ?></option>
						<option value="sr" <?php selected( $options['language_mode'], 'sr' ); ?>><?php echo esc_html__( 'Serbian Latin', 'mb-cookie-consent' ); ?></option>
						<option value="en" <?php selected( $options['language_mode'], 'en' ); ?>><?php echo esc_html__( 'English', 'mb-cookie-consent' ); ?></option>
					</select>
				</td></tr>
				<?php $this->select_row( 'banner_layout', __( 'Banner layout', 'mb-cookie-consent' ), $options, array( 'bar' => __( 'Full-width bar', 'mb-cookie-consent' ), 'card_left' => __( 'Floating card — left', 'mb-cookie-consent' ), 'card_right' => __( 'Floating card — right', 'mb-cookie-consent' ), 'card_center' => __( 'Floating card — centre', 'mb-cookie-consent' ) ) ); ?>
				<?php $this->select_row( 'position', __( 'Banner position', 'mb-cookie-consent' ), $options, array( 'bottom' => __( 'Bottom', 'mb-cookie-consent' ), 'top' => __( 'Top', 'mb-cookie-consent' ) ) ); ?>
				<?php $this->select_row( 'floating_position', __( 'Settings button side', 'mb-cookie-consent' ), $options, array( 'left' => __( 'Left', 'mb-cookie-consent' ), 'right' => __( 'Right', 'mb-cookie-consent' ) ) ); ?>
				<tr><th scope="row"><label for="mbcc-accent"><?php echo esc_html__( 'Accent colour', 'mb-cookie-consent' ); ?></label></th><td><input id="mbcc-accent" type="color" name="mbcc_settings[accent_color]" value="<?php echo esc_attr( $options['accent_color'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="mbcc-version"><?php echo esc_html__( 'Consent version', 'mb-cookie-consent' ); ?></label></th><td><input id="mbcc-version" class="regular-text" name="mbcc_settings[consent_version]" value="<?php echo esc_attr( $options['consent_version'] ); ?>"><p class="description"><?php echo esc_html__( 'Change this value to ask every visitor for consent again.', 'mb-cookie-consent' ); ?></p></td></tr>
				<tr><th scope="row"><label for="mbcc-days"><?php echo esc_html__( 'Consent lifetime (days)', 'mb-cookie-consent' ); ?></label></th><td><input id="mbcc-days" type="number" min="1" max="3650" name="mbcc_settings[duration_days]" value="<?php echo esc_attr( $options['duration_days'] ); ?>"></td></tr>
				<?php $this->checkbox_row( 'google_consent_mode', __( 'Google Consent Mode v2', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->checkbox_row( 'allow_disable_necessary', __( 'Allow visitors to disable the necessary category', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->checkbox_row( 'delete_data_on_uninstall', __( 'Delete settings when the plugin is uninstalled', 'mb-cookie-consent' ), $options ); ?>
				</tbody></table>

				<h2><?php echo esc_html__( 'Privacy policy', 'mb-cookie-consent' ); ?></h2>
				<table class="form-table" role="presentation"><tbody>
				<?php $this->text_row( 'privacy_url_sr', __( 'Serbian URL', 'mb-cookie-consent' ), $options, 'url' ); ?>
				<?php $this->text_row( 'privacy_url_en', __( 'English URL', 'mb-cookie-consent' ), $options, 'url' ); ?>
				</tbody></table>

				<h2><?php echo esc_html__( 'Blocking rules', 'mb-cookie-consent' ); ?></h2>
				<p><?php echo esc_html__( 'Use one rule per line in the format value|category. Allowed categories: necessary, preferences, analytics, marketing.', 'mb-cookie-consent' ); ?></p>
				<table class="form-table" role="presentation"><tbody>
				<?php $this->checkbox_row( 'auto_blocking', __( 'Enable URL-based script and iframe blocking', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->textarea_row( 'script_handles', __( 'WordPress script handles', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->textarea_row( 'script_patterns', __( 'Script URL patterns', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->textarea_row( 'iframe_patterns', __( 'Iframe URL patterns', 'mb-cookie-consent' ), $options ); ?>
				<?php $this->textarea_row( 'cookie_patterns', __( 'Cookies to remove after rejection', 'mb-cookie-consent' ), $options ); ?>
				</tbody></table>

				<?php $this->language_fields( 'sr', __( 'Serbian Latin frontend text', 'mb-cookie-consent' ), $options['text_sr'] ); ?>
				<?php $this->language_fields( 'en', __( 'English frontend text', 'mb-cookie-consent' ), $options['text_en'] ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/** Render checkbox row. */
	private function checkbox_row( $key, $label, $options ) {
		?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="mbcc_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $options[ $key ] ) ); ?>> <?php echo esc_html__( 'Enabled', 'mb-cookie-consent' ); ?></label></td></tr>
		<?php
	}

	/** Render select row. */
	private function select_row( $key, $label, $options, $choices ) {
		?>
		<tr><th scope="row"><label for="mbcc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><select id="mbcc-<?php echo esc_attr( $key ); ?>" name="mbcc_settings[<?php echo esc_attr( $key ); ?>]">
		<?php foreach ( $choices as $value => $choice_label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $options[ $key ], $value ); ?>><?php echo esc_html( $choice_label ); ?></option>
		<?php endforeach; ?>
		</select></td></tr>
		<?php
	}

	/** Render input row. */
	private function text_row( $key, $label, $options, $type = 'text' ) {
		?>
		<tr><th scope="row"><label for="mbcc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="mbcc-<?php echo esc_attr( $key ); ?>" class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="mbcc_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $options[ $key ] ); ?>"></td></tr>
		<?php
	}

	/** Render textarea row. */
	private function textarea_row( $key, $label, $options ) {
		?>
		<tr><th scope="row"><label for="mbcc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><textarea id="mbcc-<?php echo esc_attr( $key ); ?>" class="large-text code" rows="6" name="mbcc_settings[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $options[ $key ] ); ?></textarea></td></tr>
		<?php
	}

	/** Render all localized text fields. */
	private function language_fields( $language, $heading, $texts ) {
		?>
		<h2><?php echo esc_html( $heading ); ?></h2>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach ( $texts as $key => $value ) : ?>
			<tr><th scope="row"><label for="mbcc-<?php echo esc_attr( $language . '-' . $key ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></label></th><td>
			<?php if ( 'message' === $key || false !== strpos( $key, '_desc' ) ) : ?>
				<textarea id="mbcc-<?php echo esc_attr( $language . '-' . $key ); ?>" class="large-text" rows="3" name="mbcc_settings[text_<?php echo esc_attr( $language ); ?>][<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input id="mbcc-<?php echo esc_attr( $language . '-' . $key ); ?>" class="large-text" name="mbcc_settings[text_<?php echo esc_attr( $language ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
			<?php endif; ?>
			</td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}
}
