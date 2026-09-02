<?php
/**
 * Cache-safe HTML and registered-script blocker.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

class MBCC_Blocker {
	/** @var array<string,mixed> */
	private $options;

	/** @var array<string,string> */
	private $handle_rules = array();

	/** @var array<string,string> */
	private $script_rules = array();

	/** @var array<string,string> */
	private $iframe_rules = array();

	/** Constructor. */
	public function __construct( $options ) {
		$this->options      = $options;
		$this->handle_rules = $this->parse_rules( $options['script_handles'] );
		$this->script_rules = $this->parse_rules( $options['script_patterns'] );
		$this->iframe_rules = $this->parse_rules( $options['iframe_patterns'] );
	}

	/** Register blocking hooks. */
	public function register_hooks() {
		add_filter( 'script_loader_tag', array( $this, 'filter_script_loader_tag' ), 999, 2 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
	}

	/**
	 * Block registered WordPress script handles on every cached variant.
	 *
	 * @param string $tag    Script HTML.
	 * @param string $handle Registered handle.
	 * @return string
	 */
	public function filter_script_loader_tag( $tag, $handle ) {
		$key = strtolower( (string) $handle );
		if ( ! isset( $this->handle_rules[ $key ] ) ) {
			return $tag;
		}

		return $this->block_script_tag( $tag, $this->handle_rules[ $key ] );
	}

	/** Start full-page buffering only for frontend HTML requests. */
	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		ob_start( array( $this, 'filter_html' ) );
	}

	/**
	 * Rewrite configured scripts and iframes before the response is sent.
	 *
	 * @param string $html Full HTML response.
	 * @return string
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$html = preg_replace_callback(
			'~<script\b[^>]*>.*?</script\s*>~is',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== stripos( $tag, 'data-mbcc-essential' ) || false !== stripos( $tag, 'data-mbcc-blocked' ) ) {
					return $tag;
				}

				$script_id = $this->attribute( $tag, 'id' );
				if ( $script_id && preg_match( '/^(.+)-js-(?:before|after)$/i', $script_id, $id_match ) ) {
					$related_handle = strtolower( $id_match[1] );
					if ( isset( $this->handle_rules[ $related_handle ] ) ) {
						return $this->block_script_tag( $tag, $this->handle_rules[ $related_handle ] );
					}
				}

				$manual = $this->attribute( $tag, 'data-mbcc-category' );
				if ( $manual && $this->valid_category( $manual ) ) {
					return $this->block_script_tag( $tag, $manual );
				}

				if ( empty( $this->options['auto_blocking'] ) ) {
					return $tag;
				}

				$src        = html_entity_decode( $this->attribute( $tag, 'src' ), ENT_QUOTES, 'UTF-8' );
				$searchable = $src ? $src : html_entity_decode( $tag, ENT_QUOTES, 'UTF-8' );
				$category   = $this->match_rule( $searchable, $this->script_rules );
				return $category ? $this->block_script_tag( $tag, $category ) : $tag;
			},
			$html
		);

		if ( empty( $this->options['auto_blocking'] ) ) {
			return $html;
		}

		return preg_replace_callback(
			'~<iframe\b[^>]*>~i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== stripos( $tag, 'data-mbcc-blocked' ) ) {
					return $tag;
				}

				$src      = html_entity_decode( $this->attribute( $tag, 'src' ), ENT_QUOTES, 'UTF-8' );
				$category = $this->match_rule( $src, $this->iframe_rules );
				if ( ! $category ) {
					return $tag;
				}

				$tag = $this->move_source_to_data( $tag );
				return substr_replace( $tag, ' data-mbcc-blocked="1" data-mbcc-category="' . esc_attr( $category ) . '"', -1, 0 );
			},
			$html
		);
	}

	/** Convert an executable script into inert markup. */
	private function block_script_tag( $tag, $category ) {
		if ( false !== stripos( $tag, 'data-mbcc-blocked' ) ) {
			return $tag;
		}

		$end = strpos( $tag, '>' );
		if ( false === $end ) {
			return $tag;
		}

		$opening  = substr( $tag, 0, $end + 1 );
		$remainder = substr( $tag, $end + 1 );
		$type      = $this->attribute( $opening, 'type' );
		$opening   = preg_replace( '/\s+type\s*=\s*(["\'])(.*?)\1/i', '', $opening, 1 );
		$opening   = $this->move_source_to_data( $opening );
		$attributes = ' type="text/plain" data-mbcc-blocked="1" data-mbcc-category="' . esc_attr( $category ) . '"';
		if ( $type && ! in_array( strtolower( $type ), array( 'text/javascript', 'text/plain' ), true ) ) {
			$attributes .= ' data-mbcc-type="' . esc_attr( $type ) . '"';
		}
		$opening = substr_replace( $opening, $attributes, -1, 0 );

		return $opening . $remainder;
	}

	/**
	 * Move a quoted src value into an escaped inert data attribute.
	 *
	 * @param string $tag Opening HTML tag.
	 * @return string
	 */
	private function move_source_to_data( $tag ) {
		return preg_replace_callback(
			'/\s+src\s*=\s*(["\'])(.*?)\1/i',
			function ( $matches ) {
				$value = html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' );
				return ' data-mbcc-src="' . esc_attr( $value ) . '"';
			},
			$tag,
			1
		);
	}

	/** Extract a quoted HTML attribute. */
	private function attribute( $tag, $name ) {
		$pattern = '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/i';
		return preg_match( $pattern, $tag, $match ) ? trim( $match[2] ) : '';
	}

	/** Match URL against configured substring rules, in configured order. */
	private function match_rule( $value, $rules ) {
		if ( '' === $value ) {
			return '';
		}

		foreach ( $rules as $pattern => $category ) {
			if ( false !== stripos( $value, $pattern ) ) {
				return $category;
			}
		}

		return '';
	}

	/** Parse value|category lines. */
	private function parse_rules( $raw ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) || '' === $parts[0] || ! $this->valid_category( $parts[1] ) ) {
				continue;
			}
			$rules[ strtolower( $parts[0] ) ] = strtolower( $parts[1] );
		}
		return $rules;
	}

	/** Whether category may be controlled by visitors. */
	private function valid_category( $category ) {
		return in_array( strtolower( (string) $category ), array( 'necessary', 'preferences', 'analytics', 'marketing' ), true );
	}
}
