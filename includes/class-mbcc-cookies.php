<?php
/**
 * Administrator-reviewed cookie inventory. No cookie values are stored.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

class MBCC_Cookies {
	const OPTION_NAME = 'mbcc_cookie_inventory';
	const MAX_RECORDS = 1000;
	private $page_hook = '';

	public function register_hooks() {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_mbcc_save_cookie', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_page() {
		$this->page_hook = add_submenu_page( 'mb-cookie-consent', __( 'Cookies and categories', 'mb-cookie-consent' ), __( 'Cookies and categories', 'mb-cookie-consent' ), 'manage_options', 'mb-cookie-consent-cookies', array( $this, 'render_page' ) );
	}

	public function enqueue( $hook ) {
		if ( $hook === $this->page_hook ) { wp_enqueue_style( 'mbcc-admin-cookies', MBCC_URL . 'assets/css/admin-cookies.css', array(), MBCC_VERSION ); }
	}

	public static function categories() {
		return array( 'necessary' => __( 'Necessary', 'mb-cookie-consent' ), 'preferences' => __( 'Preferences', 'mb-cookie-consent' ), 'analytics' => __( 'Analytics', 'mb-cookie-consent' ), 'marketing' => __( 'Marketing', 'mb-cookie-consent' ), '' => __( 'Unclassified', 'mb-cookie-consent' ) );
	}

	/** Keep confirmed metadata when a manual scan observes an existing cookie again. */
	public static function record( $item, $category = null ) {
		$records = get_option( self::OPTION_NAME, array() );
		$records = is_array( $records ) ? $records : array();
		$name = sanitize_text_field( $item['value'] );
		$id = sha1( strtolower( $name ) );
		$old = isset( $records[ $id ] ) ? $records[ $id ] : array();
		if ( empty( $old ) && count( $records ) >= self::MAX_RECORDS ) { return false; }
		$row = array_merge( array( 'value' => $name, 'category' => '', 'domain' => '', 'service' => '', 'source_url' => '', 'server' => false, 'httponly' => false, 'linked_rule' => '' ), $old );
		foreach ( array( 'source_url', 'domain' ) as $key ) {
			if ( empty( $row[ $key ] ) && ! empty( $item[ $key ] ) ) {
				$row[ $key ] = 'source_url' === $key ? esc_url_raw( $item[ $key ] ) : sanitize_text_field( $item[ $key ] );
			}
		}
		$row['server'] = ! empty( $row['server'] ) || ! empty( $item['server'] );
		$row['httponly'] = ! empty( $row['httponly'] ) || ! empty( $item['httponly'] );
		if ( null !== $category ) { $row['category'] = $category; }
		$records[ $id ] = $row;
		return $old === $row || update_option( self::OPTION_NAME, $records, false );
	}

	/** Return distinct rules in configuration order. */
	public static function rules( $settings ) {
		$rules = array();
		foreach ( array( 'cookie_patterns', 'script_patterns', 'script_handles', 'iframe_patterns' ) as $key ) {
			foreach ( preg_split( '/\r\n|\r|\n/', isset( $settings[ $key ] ) ? $settings[ $key ] : '' ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				if ( 2 !== count( $parts ) || '' === $parts[0] || ! in_array( $parts[1], array( 'necessary', 'preferences', 'analytics', 'marketing' ), true ) ) { continue; }
				$id = sha1( $key . '|' . strtolower( $parts[0] ) );
				if ( ! isset( $rules[ $id ] ) ) { $rules[ $id ] = array( 'key' => $key, 'value' => $parts[0], 'category' => $parts[1] ); }
			}
		}
		return $rules;
	}

	public static function matching_rule( $name, $rules ) {
		foreach ( $rules as $rule ) {
			if ( 'cookie_patterns' === $rule['key'] && preg_match( '/^' . str_replace( '\\*', '.*', preg_quote( $rule['value'], '/' ) ) . '$/i', $name ) ) { return $rule; }
		}
		return null;
	}

	/** Replace the selected pattern in place and collapse only its exact duplicates. */
	public static function replace_rule( $stored, $pattern, $category ) {
		$lines = preg_split( '/\r\n|\r|\n/', $stored );
		$out = array();
		$found = false;
		foreach ( $lines as $line ) {
			$parts = explode( '|', trim( $line ), 2 );
			if ( 0 === strcasecmp( trim( $parts[0] ), $pattern ) ) {
				if ( ! $found ) { $out[] = $pattern . '|' . $category; }
				$found = true;
			} else { $out[] = $line; }
		}
		if ( ! $found ) { $out[] = $pattern . '|' . $category; }
		return trim( implode( "\n", $out ) );
	}

	public static function inventory( $settings ) {
		$records = get_option( self::OPTION_NAME, array() );
		$records = is_array( $records ) ? $records : array();
		$findings = get_option( MBCC_Scanner::RESULTS_OPTION, array() );
		foreach ( is_array( $findings ) ? $findings : array() as $item ) {
			if ( 'cookie' === $item['type'] ) {
				$id = sha1( strtolower( $item['value'] ) );
				if ( ! isset( $records[ $id ] ) ) { $records[ $id ] = $item; }
			}
		}
		$rules = self::rules( $settings );
		foreach ( $rules as $rule ) {
			if ( 'cookie_patterns' === $rule['key'] ) {
				$id = sha1( strtolower( $rule['value'] ) );
				if ( ! isset( $records[ $id ] ) ) { $records[ $id ] = array( 'value' => $rule['value'] ); }
			}
		}
		$records[ sha1( 'mbcc_consent' ) ] = array( 'value' => 'mbcc_consent', 'category' => 'necessary', 'service' => 'MB Cookie Consent' );
		foreach ( $records as &$row ) {
			$row = array_merge( array( 'category' => '', 'domain' => '', 'service' => '', 'source_url' => '', 'server' => false, 'httponly' => false, 'linked_rule' => '' ), $row );
			$row['rule'] = self::matching_rule( $row['value'], $rules );
			if ( empty( $row['server'] ) && 'mbcc_consent' !== strtolower( $row['value'] ) ) { $row['category'] = $row['rule'] ? $row['rule']['category'] : ''; }
		}
		unset( $row );
		return $records;
	}

	/** Capability, nonce and optimistic revision checks protect every edit. */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to perform this action.', 'mb-cookie-consent' ) ); }
		check_admin_referer( 'mbcc_save_cookie' );
		$name = isset( $_POST['cookie_name'] ) && is_string( $_POST['cookie_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cookie_name'] ) ) : '';
		$category = isset( $_POST['category'] ) && is_string( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		if ( ! preg_match( '/^[!#$%&\'*+.^_`~0-9A-Za-z-]{1,200}$/D', $name ) || 'mbcc_consent' === strtolower( $name ) || ! array_key_exists( $category, self::categories() ) ) {
			wp_die( esc_html__( 'Invalid cookie name or category.', 'mb-cookie-consent' ) );
		}
		$settings = MBCC_Settings::get();
		$records = get_option( self::OPTION_NAME, array() );
		$records = is_array( $records ) ? $records : array();
		$revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? sanitize_text_field( wp_unslash( $_POST['revision'] ) ) : '';
		if ( ! hash_equals( self::revision( $settings, $records ), $revision ) ) { wp_die( esc_html__( 'Settings changed. Reload the page before saving.', 'mb-cookie-consent' ) ); }
		$id = sha1( strtolower( $name ) );
		$rows = self::inventory( $settings );
		$row = isset( $rows[ $id ] ) ? $rows[ $id ] : array( 'value' => $name );
		if ( ! isset( $records[ $id ] ) && count( $records ) >= self::MAX_RECORDS ) { wp_die( esc_html__( 'The cookie inventory is full.', 'mb-cookie-consent' ) ); }
		foreach ( array( 'domain', 'service', 'linked_rule' ) as $key ) {
			$row[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		$rules = self::rules( $settings );
		if ( '' !== $row['linked_rule'] && ( ! isset( $rules[ $row['linked_rule'] ] ) || 'cookie_patterns' === $rules[ $row['linked_rule'] ]['key'] ) ) { wp_die( esc_html__( 'Invalid linked rule.', 'mb-cookie-consent' ) ); }
		$row['httponly'] = ! empty( $row['httponly'] ) || ! empty( $_POST['httponly'] );
		$row['server'] = ! empty( $row['server'] ) || ! empty( $_POST['server'] ) || $row['httponly'];
		$row['category'] = $category;
		$rule = self::matching_rule( $name, $rules );
		if ( empty( $row['server'] ) ) {
			if ( '' === $category && $rule ) { wp_die( esc_html__( 'A configured rule requires a category. Remove the rule in Settings to leave it unclassified.', 'mb-cookie-consent' ) ); }
			if ( '' !== $category ) {
				foreach ( $rules as $other ) {
					if ( $rule && 'cookie_patterns' === $other['key'] && 0 !== strcasecmp( $other['value'], $rule['value'] ) && $category !== $other['category'] && self::matching_rule( $name, array( $other ) ) ) {
						wp_die( esc_html__( 'Overlapping cookie patterns use different categories. Resolve them in Settings before moving this cookie.', 'mb-cookie-consent' ) );
					}
				}
				$updated = $settings;
				$updated['cookie_patterns'] = self::replace_rule( $settings['cookie_patterns'], $rule ? $rule['value'] : $name, $category );
				if ( $updated !== $settings && ! update_option( MBCC_Settings::OPTION_NAME, $updated, true ) ) { wp_die( esc_html__( 'The rule could not be saved. Please try again.', 'mb-cookie-consent' ) ); }
			}
		}
		unset( $row['rule'], $row['type'], $row['status'], $row['suggested_category'] );
		$updated_records = $records;
		$updated_records[ $id ] = $row;
		if ( $updated_records !== $records && ! update_option( self::OPTION_NAME, $updated_records, false ) ) { wp_die( esc_html__( 'The record could not be saved. Review Settings before retrying; a rule change may already have been saved.', 'mb-cookie-consent' ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=mb-cookie-consent-cookies' ) );
		exit;
	}

	public static function revision( $settings, $records ) {
		return hash( 'sha256', wp_json_encode( array( $settings, $records ) ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = MBCC_Settings::get();
		$rules = self::rules( $settings );
		$rows = self::inventory( $settings );
		$revision = self::revision( $settings, get_option( self::OPTION_NAME, array() ) );
		?>
		<div class="wrap mbcc-inventory">
		<h1><?php echo esc_html__( 'Cookies and categories', 'mb-cookie-consent' ); ?></h1>
		<p><?php echo esc_html__( 'Recorded cookies and configured patterns, not a live browser cookie list. Cookie values are never stored. A category change does not change the category of a linked script or service.', 'mb-cookie-consent' ); ?></p>
		<p><?php echo esc_html__( 'Server control required: server and HttpOnly records are informational only. Their category does not block or delete them. Existing removal rules remain visible in Settings.', 'mb-cookie-consent' ); ?></p>
		<?php foreach ( self::categories() as $category => $label ) : ?>
		<h2><?php echo esc_html( $label ); ?></h2>
		<table class="widefat striped"><thead><tr>
		<th><?php echo esc_html__( 'Cookie / pattern', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Service / domain', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Related rule / source', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Action', 'mb-cookie-consent' ); ?></th>
		</tr></thead><tbody>
		<?php $count = 0; foreach ( $rows as $row ) : if ( $category !== $row['category'] ) { continue; } ++$count; ?>
		<tr><td><code><?php echo esc_html( $row['value'] ); ?></code>
		<?php if ( $row['server'] ) : ?><p><?php echo esc_html__( 'Server control required', 'mb-cookie-consent' ); ?><?php echo $row['httponly'] ? ' (HttpOnly)' : ''; ?></p><?php endif; ?></td>
		<td><?php echo esc_html( $row['service'] ); ?><br><?php echo esc_html( $row['domain'] ? $row['domain'] : (string) wp_parse_url( $row['source_url'], PHP_URL_HOST ) ); ?></td>
		<td><?php if ( $row['rule'] ) { echo esc_html( $row['rule']['value'] . ' | ' . $row['rule']['category'] ); } ?>
		<?php if ( isset( $rules[ $row['linked_rule'] ] ) ) : ?><p><?php $linked = $rules[ $row['linked_rule'] ]; echo esc_html( $linked['key'] . ': ' . $linked['value'] . ' | ' . $linked['category'] ); ?></p><?php endif; ?>
		<?php if ( $row['source_url'] ) : ?><p><a href="<?php echo esc_url( $row['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Example page', 'mb-cookie-consent' ); ?></a></p><?php endif; ?></td>
		<td><?php if ( 'mbcc_consent' === strtolower( $row['value'] ) ) : echo esc_html__( 'Required to remember consent', 'mb-cookie-consent' ); else : ?>
		<details><summary><?php echo esc_html__( 'Edit', 'mb-cookie-consent' ); ?></summary>
		<?php $this->form( $row, $rules, $revision ); ?></details>
		<?php endif; ?></td></tr>
		<?php endforeach; if ( ! $count ) : ?><tr><td colspan="4"><?php echo esc_html__( 'No recorded cookies in this category.', 'mb-cookie-consent' ); ?></td></tr><?php endif; ?>
		</tbody></table>
		<?php endforeach; ?>
		<h2><?php echo esc_html__( 'Add cookie record', 'mb-cookie-consent' ); ?></h2>
		<?php $this->form( array( 'value' => '', 'category' => '', 'service' => '', 'domain' => '', 'linked_rule' => '', 'server' => false, 'httponly' => false ), $rules, $revision ); ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=mb-cookie-consent' ) ); ?>"><?php echo esc_html__( 'Review blocking rules and Consent version in Settings.', 'mb-cookie-consent' ); ?></a></p>
		</div>
		<?php
	}

	private function form( $row, $rules, $revision ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mbcc_save_cookie"><input type="hidden" name="revision" value="<?php echo esc_attr( $revision ); ?>">
		<?php wp_nonce_field( 'mbcc_save_cookie' ); ?>
		<p><label><?php echo esc_html__( 'Cookie / pattern', 'mb-cookie-consent' ); ?> <input name="cookie_name" required maxlength="200" value="<?php echo esc_attr( $row['value'] ); ?>" <?php echo '' !== $row['value'] ? 'readonly' : ''; ?>></label></p>
		<p><label><?php echo esc_html__( 'Category', 'mb-cookie-consent' ); ?> <select name="category">
		<?php foreach ( self::categories() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['category'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label></p>
		<?php foreach ( array( 'service' => __( 'Service', 'mb-cookie-consent' ), 'domain' => __( 'Domain', 'mb-cookie-consent' ) ) as $key => $label ) : ?>
		<p><label><?php echo esc_html( $label ); ?> <input name="<?php echo esc_attr( $key ); ?>" maxlength="200" value="<?php echo esc_attr( $row[ $key ] ); ?>"></label></p>
		<?php endforeach; ?>
		<p><label><input type="checkbox" name="server" value="1" <?php checked( ! empty( $row['server'] ) ); ?> <?php disabled( ! empty( $row['server'] ) ); ?>> <?php echo esc_html__( 'Server-set cookie (record only)', 'mb-cookie-consent' ); ?></label></p>
		<p><label><input type="checkbox" name="httponly" value="1" <?php checked( ! empty( $row['httponly'] ) ); ?> <?php disabled( ! empty( $row['httponly'] ) ); ?>> HttpOnly</label></p>
		<p><label><?php echo esc_html__( 'Linked script / iframe rule (optional)', 'mb-cookie-consent' ); ?> <select name="linked_rule"><option value=""><?php echo esc_html__( 'None', 'mb-cookie-consent' ); ?></option>
		<?php foreach ( $rules as $id => $rule ) : if ( 'cookie_patterns' === $rule['key'] ) { continue; } ?>
		<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $row['linked_rule'], $id ); ?>><?php echo esc_html( $rule['key'] . ': ' . $rule['value'] . ' | ' . $rule['category'] ); ?></option>
		<?php endforeach; ?></select></label></p>
		<?php if ( empty( $row['server'] ) && ! empty( $row['rule'] ) ) : ?><p><?php echo esc_html__( 'This edit updates the matching pattern for every cookie it covers:', 'mb-cookie-consent' ); ?> <code><?php echo esc_html( $row['rule']['value'] ); ?></code></p><?php endif; ?>
		<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save', 'mb-cookie-consent' ); ?></button>
		</form>
		<?php
	}
}
