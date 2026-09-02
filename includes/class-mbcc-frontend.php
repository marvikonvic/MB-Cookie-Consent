<?php
/**
 * Frontend assets, language selection and accessible markup.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

class MBCC_Frontend {
	/** @var array<string,mixed> */
	private $options;

	/** Constructor. */
	public function __construct( $options ) {
		$this->options = $options;
	}

	/** Register frontend hooks. */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'render_consent_mode' ), 0 );
		add_action( 'wp_footer', array( $this, 'render' ), 99 );
		add_shortcode( 'mbcc_cookie_settings', array( $this, 'settings_shortcode' ) );
	}

	/** Enqueue isolated frontend CSS/JS and configuration. */
	public function enqueue() {
		$language = $this->language();
		$texts    = 'sr' === $language ? $this->options['text_sr'] : $this->options['text_en'];

		wp_enqueue_style( 'mbcc-frontend', MBCC_URL . 'assets/css/frontend.css', array(), MBCC_VERSION );
		wp_enqueue_script( 'mbcc-frontend', MBCC_URL . 'assets/js/frontend.js', array(), MBCC_VERSION, true );
		wp_localize_script(
			'mbcc-frontend',
			'MBCC_CONFIG',
			array(
				'cookieName'     => 'mbcc_consent',
				'durationDays'   => absint( $this->options['duration_days'] ),
				'version'        => (string) $this->options['consent_version'],
				'language'       => $language,
				'bannerLayout'   => $this->options['banner_layout'],
				'position'       => $this->options['position'],
				'floatingSide'   => $this->options['floating_position'],
				'accentColor'    => $this->options['accent_color'],
				'allowDisableNecessary' => ! empty( $this->options['allow_disable_necessary'] ),
				'cookiePatterns' => $this->parse_cookie_rules( $this->options['cookie_patterns'] ),
				'text'           => $texts,
			)
		);
	}

	/**
	 * Emit denied-by-default Consent Mode before tracking tags.
	 * The compact reader is deliberately standalone and cache-safe.
	 */
	public function render_consent_mode() {
		$version = wp_json_encode( (string) $this->options['consent_version'] );
		$gcm     = ! empty( $this->options['google_consent_mode'] );
		?>
		<script data-mbcc-essential>(function(){"use strict";var c=null,m=document.cookie.match(/(?:^|; )mbcc_consent=([^;]*)/);if(m){try{c=JSON.parse(atob(decodeURIComponent(m[1]).replace(/-/g,"+").replace(/_/g,"/")));if(typeof c.necessary!=="boolean"){c.necessary=true;}}catch(e){c=null;}}if(c&&c.version===<?php echo $version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode. ?>){document.documentElement.classList.add("mbcc-consent-valid");}<?php if ( $gcm ) : ?>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};var a=c&&c.version===<?php echo $version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode. ?>?c:{necessary:true,analytics:false,marketing:false,preferences:false};window.gtag("consent","default",{ad_storage:a.marketing?"granted":"denied",analytics_storage:a.analytics?"granted":"denied",ad_user_data:a.marketing?"granted":"denied",ad_personalization:a.marketing?"granted":"denied",functionality_storage:a.preferences?"granted":"denied",personalization_storage:a.preferences?"granted":"denied",security_storage:a.necessary?"granted":"denied",wait_for_update:500});<?php endif; ?>}());</script>
		<?php
	}

	/** Render banner, preference dialog and persistent settings control. */
	public function render() {
		$language   = $this->language();
		$texts      = 'sr' === $language ? $this->options['text_sr'] : $this->options['text_en'];
		$policy_url = 'sr' === $language ? $this->options['privacy_url_sr'] : $this->options['privacy_url_en'];
		?>
		<div id="mbcc-banner" class="mbcc-banner mbcc-banner--<?php echo esc_attr( $this->options['position'] ); ?> mbcc-banner--layout-<?php echo esc_attr( str_replace( '_', '-', $this->options['banner_layout'] ) ); ?>" role="region" aria-labelledby="mbcc-banner-title" style="--mbcc-accent:<?php echo esc_attr( $this->options['accent_color'] ); ?>">
			<div class="mbcc-banner__content">
				<div class="mbcc-banner__copy"><h2 id="mbcc-banner-title"><?php echo esc_html( $texts['title'] ); ?></h2><p><?php echo esc_html( $texts['message'] ); ?><?php if ( $policy_url ) : ?> <a href="<?php echo esc_url( $policy_url ); ?>"><?php echo esc_html( $texts['privacy_policy'] ); ?></a><?php endif; ?></p></div>
				<div class="mbcc-banner__actions">
					<button type="button" class="mbcc-button mbcc-button--primary" data-mbcc-accept-all><?php echo esc_html( $texts['accept_all'] ); ?></button>
					<button type="button" class="mbcc-button" data-mbcc-reject><?php echo esc_html( $texts['reject_optional'] ); ?></button>
					<button type="button" class="mbcc-button mbcc-button--link" data-mbcc-open><?php echo esc_html( $texts['settings'] ); ?></button>
				</div>
			</div>
		</div>

		<div id="mbcc-modal" class="mbcc-modal" hidden>
			<div class="mbcc-modal__backdrop" data-mbcc-close></div>
			<div class="mbcc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mbcc-modal-title" tabindex="-1" style="--mbcc-accent:<?php echo esc_attr( $this->options['accent_color'] ); ?>">
				<div class="mbcc-modal__header"><h2 id="mbcc-modal-title"><?php echo esc_html( $texts['settings'] ); ?></h2><button type="button" class="mbcc-modal__close" data-mbcc-close aria-label="<?php echo esc_attr( $texts['close'] ); ?>">&times;</button></div>
				<p><?php echo esc_html( $texts['message'] ); ?><?php if ( $policy_url ) : ?> <a href="<?php echo esc_url( $policy_url ); ?>"><?php echo esc_html( $texts['privacy_policy'] ); ?></a><?php endif; ?></p>
				<?php if ( ! empty( $this->options['allow_disable_necessary'] ) ) : ?>
					<?php $this->category_row( 'necessary', $texts, true ); ?>
				<?php else : ?>
					<div class="mbcc-category"><div><strong><?php echo esc_html( $texts['necessary'] ); ?></strong><p><?php echo esc_html( $texts['necessary_desc'] ); ?></p></div><span class="mbcc-always"><?php echo esc_html( $texts['always_active'] ); ?></span></div>
				<?php endif; ?>
				<?php $this->category_row( 'preferences', $texts ); ?>
				<?php $this->category_row( 'analytics', $texts ); ?>
				<?php $this->category_row( 'marketing', $texts ); ?>
				<div class="mbcc-modal__actions"><button type="button" class="mbcc-button" data-mbcc-reject><?php echo esc_html( $texts['reject_optional'] ); ?></button><button type="button" class="mbcc-button mbcc-button--primary" data-mbcc-save><?php echo esc_html( $texts['save'] ); ?></button></div>
			</div>
		</div>

		<button type="button" id="mbcc-reopen" class="mbcc-reopen mbcc-reopen--<?php echo esc_attr( $this->options['floating_position'] ); ?>" data-mbcc-open aria-label="<?php echo esc_attr( $texts['reopen'] ); ?>" title="<?php echo esc_attr( $texts['reopen'] ); ?>" style="--mbcc-accent:<?php echo esc_attr( $this->options['accent_color'] ); ?>">⚙</button>
		<template id="mbcc-placeholder-template"><div class="mbcc-placeholder"><p><?php echo esc_html( $texts['blocked_content'] ); ?></p><button type="button" class="mbcc-button" data-mbcc-open><?php echo esc_html( $texts['allow_content'] ); ?></button></div></template>
		<?php
	}

	/** Render one optional consent row. */
	private function category_row( $category, $texts, $checked = false ) {
		$description = $category . '_desc';
		?>
		<label class="mbcc-category" for="mbcc-<?php echo esc_attr( $category ); ?>"><span><strong><?php echo esc_html( $texts[ $category ] ); ?></strong><span class="mbcc-category__description"><?php echo esc_html( $texts[ $description ] ); ?></span></span><span class="mbcc-switch"><input id="mbcc-<?php echo esc_attr( $category ); ?>" type="checkbox" data-mbcc-category-input="<?php echo esc_attr( $category ); ?>" <?php checked( $checked ); ?>><span aria-hidden="true"></span></span></label>
		<?php
	}

	/** Shortcode for privacy-policy pages or footer links. */
	public function settings_shortcode() {
		$texts = 'sr' === $this->language() ? $this->options['text_sr'] : $this->options['text_en'];
		return '<button type="button" class="mbcc-inline-settings" data-mbcc-open>' . esc_html( $texts['reopen'] ) . '</button>';
	}

	/** Determine Serbian or English from explicit mode, Polylang, WPML or locale. */
	private function language() {
		if ( in_array( $this->options['language_mode'], array( 'sr', 'en' ), true ) ) {
			$language = $this->options['language_mode'];
		} else {
			$code = '';
			if ( function_exists( 'pll_current_language' ) ) {
				$code = (string) pll_current_language( 'slug' );
			} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				$code = (string) ICL_LANGUAGE_CODE;
			} else {
				$code = (string) get_locale();
			}
			$language = 0 === strpos( strtolower( str_replace( '-', '_', $code ) ), 'sr' ) ? 'sr' : 'en';
		}

		return apply_filters( 'mbcc_language', $language );
	}

	/** Parse cookie deletion rules for JavaScript. */
	private function parse_cookie_rules( $raw ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && $parts[0] && in_array( $parts[1], array( 'necessary', 'preferences', 'analytics', 'marketing' ), true ) ) {
				$rules[] = array( 'name' => $parts[0], 'category' => $parts[1] );
			}
		}
		return $rules;
	}
}
