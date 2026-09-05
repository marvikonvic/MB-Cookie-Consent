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
		if ( 'mbcc-frontend' === $key || ! isset( $this->handle_rules[ $key ] ) ) {
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

		return $this->rewrite_target_tags( $html );
	}

	/** Rewrite script elements and iframe opening tags without reparsing the document. */
	public function rewrite_target_tags( $html, $visitor = null ) {
		$cursor = 0;
		$search = 0;
		$output = '';
		$length = strlen( $html );

		while ( $search < $length && false !== ( $start = strpos( $html, '<', $search ) ) ) {
			if ( 0 === substr_compare( $html, '<!--', $start, 4 ) ) {
				$comment_end = strpos( $html, '-->', $start + 4 );
				if ( false === $comment_end ) {
					break;
				}
				$search = $comment_end + 3;
				continue;
			}

			$opening_end = $this->find_tag_end( $html, $start + 1 );
			if ( false === $opening_end ) {
				break;
			}
			$opening = substr( $html, $start, $opening_end - $start + 1 );
			if ( ! preg_match( '/^<\s*(\/?)\s*([A-Za-z][A-Za-z0-9:-]*)/', $opening, $name_match ) || '' !== $name_match[1] ) {
				$search = $opening_end + 1;
				continue;
			}

			$name = strtolower( $name_match[2] );
			if ( 'plaintext' === $name ) { break; }
			if ( in_array( $name, array( 'textarea', 'title', 'style', 'xmp', 'noembed', 'noframes', 'noscript' ), true ) ) {
				$closing = $this->find_closing_tag( $html, $name, $opening_end + 1 );
				if ( false === $closing ) { break; }
				$search = $closing[1] + 1;
				continue;
			}
			if ( 'script' === $name ) {
				$closing = $this->find_closing_tag( $html, 'script', $opening_end + 1 );
				if ( false === $closing ) {
					break;
				}
				$element_end = $closing[1];
				$element     = substr( $html, $start, $element_end - $start + 1 );
				$replacement = $visitor ? call_user_func( $visitor, 'script', $element, $opening ) : $this->filter_script_element( $element );
				if ( $replacement !== $element ) {
					$output .= substr( $html, $cursor, $start - $cursor ) . $replacement;
					$cursor  = $element_end + 1;
				}
				$search = $element_end + 1;
				continue;
			}

			if ( 'iframe' === $name && ( $visitor || ! empty( $this->options['auto_blocking'] ) ) ) {
				$replacement = $visitor ? call_user_func( $visitor, 'iframe', $opening, $opening ) : $this->filter_iframe_tag( $opening );
				if ( $replacement !== $opening ) {
					$output .= substr( $html, $cursor, $start - $cursor ) . $replacement;
					$cursor  = $opening_end + 1;
				}
			}
			$search = $opening_end + 1;
			if ( 'iframe' === $name ) {
				$closing = $this->find_closing_tag( $html, 'iframe', $search );
				if ( false === $closing ) { break; }
				$search = $closing[1] + 1;
			}
		}

		return $output . substr( $html, $cursor );
	}

	/** Apply configured rules to one complete script element. */
	private function filter_script_element( $tag ) {
		if ( $this->is_own_script( $tag ) ) {
			return $tag;
		}
		if ( $this->has_attribute( $tag, 'data-mbcc-essential' ) || $this->is_inert( $tag, true ) ) {
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
	}

	/** Apply configured rules to one iframe opening tag. */
	private function filter_iframe_tag( $tag ) {
		if ( $this->is_inert( $tag, false ) ) {
			return $tag;
		}

		$src      = html_entity_decode( $this->attribute( $tag, 'src' ), ENT_QUOTES, 'UTF-8' );
		$category = $this->match_rule( $src, $this->iframe_rules );
		if ( ! $category ) {
			return $tag;
		}

		$tag = $this->move_source_to_data( $tag );
		$tag = $this->remove_attribute( $this->remove_attribute( $tag, 'data-mbcc-blocked' ), 'data-mbcc-category' );
		return substr_replace( $tag, ' data-mbcc-blocked="1" data-mbcc-category="' . esc_attr( $category ) . '"', -1, 0 );
	}

	/** Find an opening or closing tag boundary while respecting quoted values. */
	private function find_tag_end( $html, $start ) {
		$length = strlen( $html );
		$quote  = '';
		for ( $index = $start; $index < $length; ++$index ) {
			$character = $html[ $index ];
			if ( $quote ) {
				if ( $character === $quote ) {
					$quote = '';
				}
			} elseif ( '"' === $character || "'" === $character ) {
				$quote = $character;
			} elseif ( '>' === $character ) {
				return $index;
			}
		}
		return false;
	}

	/** Preserve the existing closing-tag matching; do not interpret JS strings. */
	private function find_closing_tag( $html, $name, $start ) {
		if ( preg_match( '~</' . preg_quote( $name, '~' ) . '\s*>~i', $html, $match, PREG_OFFSET_CAPTURE, $start ) ) {
			return array( $match[0][1], $match[0][1] + strlen( $match[0][0] ) - 1 );
		}
		return false;
	}

	/** Never gate the consent runtime or its WordPress-generated configuration. */
	private function is_own_script( $tag ) {
		$end = $this->find_tag_end( $tag, 1 );
		if ( false === $end ) {
			return false;
		}

		// Inspect the opening tag only; an ID inside JS text is not an exemption.
		$id = strtolower( $this->attribute( substr( $tag, 0, $end + 1 ), 'id' ) );
		return in_array(
			$id,
			array( 'mbcc-frontend-js', 'mbcc-frontend-js-extra', 'mbcc-frontend-js-before', 'mbcc-frontend-js-after' ),
			true
		);
	}

	/** Convert an executable script into inert markup. */
	private function block_script_tag( $tag, $category ) {
		if ( $this->is_own_script( $tag ) || $this->is_inert( $tag, true ) ) {
			return $tag;
		}

		$end = $this->find_tag_end( $tag, 1 );
		if ( false === $end ) {
			return $tag;
		}

		$opening  = substr( $tag, 0, $end + 1 );
		$remainder = substr( $tag, $end + 1 );
		$type      = $this->attribute( $opening, 'type' );
		$opening   = $this->remove_attribute( $opening, 'type' );
		$opening   = $this->move_source_to_data( $opening );
		$opening = $this->remove_attribute( $this->remove_attribute( $opening, 'data-mbcc-blocked' ), 'data-mbcc-category' );
		$attributes = ' type="text/plain" data-mbcc-blocked="1" data-mbcc-category="' . esc_attr( $category ) . '"';
		if ( $type && ! in_array( strtolower( $type ), array( 'text/javascript', 'text/plain' ), true ) ) {
			$attributes .= ' data-mbcc-type="' . esc_attr( $type ) . '"';
		}
		$opening = substr_replace( $opening, $attributes, -1, 0 );

		return $opening . $remainder;
	}

	/**
	 * Move a quoted or unquoted src value into an escaped inert data attribute.
	 *
	 * @param string $tag Opening HTML tag.
	 * @return string
	 */
	private function move_source_to_data( $tag ) {
		foreach ( $this->parse_attributes( $tag ) as $attribute ) {
			if ( 'src' === $attribute['name'] ) {
				$value = html_entity_decode( $attribute['value'], ENT_QUOTES, 'UTF-8' );
				$tag = $this->remove_attribute( $tag, 'src' );
				return substr_replace( $tag, ' data-mbcc-src="' . esc_attr( $value ) . '"', -1, 0 );
			}
		}
		return $tag;
	}

	/** Extract a quoted or unquoted HTML attribute. */
	public function attribute( $tag, $name ) {
		foreach ( $this->parse_attributes( $tag ) as $attribute ) {
			if ( strtolower( $name ) === $attribute['name'] ) {
				return trim( $attribute['value'] );
			}
		}
		return '';
	}

	/** Check actual opening-tag attributes, including boolean attributes. */
	public function has_attribute( $tag, $name ) {
		foreach ( $this->parse_attributes( $tag ) as $attribute ) {
			if ( $name === $attribute['name'] ) { return true; }
		}
		return false;
	}

	/** A marker alone is not proof of an inert resource. */
	private function is_inert( $tag, $script ) {
		return '1' === $this->attribute( $tag, 'data-mbcc-blocked' )
			&& $this->valid_category( $this->attribute( $tag, 'data-mbcc-category' ) )
			&& ! $this->has_attribute( $tag, 'src' )
			&& ( ! $script || 'text/plain' === strtolower( $this->attribute( $tag, 'type' ) ) );
	}

	/** Remove every occurrence, including duplicate attributes. */
	private function remove_attribute( $tag, $name ) {
		foreach ( array_reverse( $this->parse_attributes( $tag ) ) as $attribute ) {
			if ( strtolower( $name ) === $attribute['name'] ) {
				$tag = substr_replace( $tag, '', $attribute['start'], $attribute['length'] );
			}
		}
		return $tag;
	}

	/** Tokenize only opening-tag attributes; quoted values are consumed as a unit. */
	private function parse_attributes( $tag ) {
		$end = $this->find_tag_end( $tag, 1 );
		if ( false === $end || ! preg_match( '/^<[A-Za-z][A-Za-z0-9:-]*/', $tag, $head ) ) {
			return array();
		}
		$opening = substr( $tag, 0, $end + 1 );
		$offset = strlen( $head[0] );
		$attributes = array();
		$pattern = '~\G[\t\n\f\r ]*([^\t\n\f\r />=]+)(?:[\t\n\f\r ]*=[\t\n\f\r ]*(?:"([^"]*)"|\'([^\']*)\'|([^\t\n\f\r >]+)))?~';
		while ( preg_match( $pattern, $opening, $match, PREG_UNMATCHED_AS_NULL, $offset ) ) {
			$value = '';
			foreach ( array( 2, 3, 4 ) as $index ) {
				if ( isset( $match[ $index ] ) ) {
					$value = $match[ $index ];
					break;
				}
			}
			$attributes[] = array( 'name' => strtolower( $match[1] ), 'value' => $value, 'start' => $offset, 'length' => strlen( $match[0] ) );
			$offset += strlen( $match[0] );
		}
		return $attributes;
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
