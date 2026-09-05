<?php
/**
 * Manual, same-site cookie and resource audit scanner.
 *
 * @package MBCookieConsent
 */

defined( 'ABSPATH' ) || exit;

class MBCC_Scanner {
	const RESULTS_OPTION = 'mbcc_scan_results';
	const JOB_PREFIX     = 'mbcc_scan_job_';
	const MAX_URLS       = 250;
	const BATCH_SIZE     = 4;
	const MAX_RESULTS    = 500;

	/** Register admin-only hooks. */
	public function register_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_mbcc_start_scan', array( $this, 'start_scan' ) );
		add_action( 'wp_ajax_mbcc_scan_batch', array( $this, 'scan_batch' ) );
		add_action( 'admin_post_mbcc_add_scan_rule', array( $this, 'add_rule' ) );
		add_action( 'admin_post_mbcc_ignore_scan_item', array( $this, 'ignore_item' ) );
		add_action( 'admin_post_mbcc_clear_scan_results', array( $this, 'clear_results' ) );
	}

	/** Add a dedicated scanner page below Settings. */
	public function add_page() {
		add_options_page(
			__( 'MB Cookie Scanner', 'mb-cookie-consent' ),
			__( 'MB Cookie Scanner', 'mb-cookie-consent' ),
			'manage_options',
			'mb-cookie-consent-scanner',
			array( $this, 'render_page' )
		);
	}

	/** Load scanner JavaScript only on its own screen. */
	public function enqueue( $hook_suffix ) {
		if ( 'settings_page_mb-cookie-consent-scanner' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'mbcc-admin-scanner', MBCC_URL . 'assets/js/admin-scanner.js', array(), MBCC_VERSION, true );
		wp_localize_script(
			'mbcc-admin-scanner',
			'MBCC_SCANNER',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mbcc_scan' ),
				'starting' => __( 'Preparing the manual scan…', 'mb-cookie-consent' ),
				'scanning' => __( 'Scanned %1$d of %2$d URLs.', 'mb-cookie-consent' ),
				'finished' => __( 'Scan complete. Reloading results…', 'mb-cookie-consent' ),
				'failed'   => __( 'The scan could not be completed.', 'mb-cookie-consent' ),
			)
		);
	}

	/** Render scanner controls and unreviewed findings. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$results = get_option( self::RESULTS_OPTION, array() );
		$results = is_array( $results ) ? $results : array();
		$pending = array_filter(
			$results,
			static function ( $item ) {
				return is_array( $item ) && empty( $item['status'] );
			}
		);
		$type_labels = array(
			'cookie' => __( 'Cookie', 'mb-cookie-consent' ),
			'script' => __( 'Script', 'mb-cookie-consent' ),
			'iframe' => __( 'Iframe', 'mb-cookie-consent' ),
		);
		$category_labels = array(
			'necessary'   => __( 'Necessary', 'mb-cookie-consent' ),
			'preferences' => __( 'Preferences', 'mb-cookie-consent' ),
			'analytics'   => __( 'Analytics', 'mb-cookie-consent' ),
			'marketing'   => __( 'Marketing', 'mb-cookie-consent' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'MB Cookie Scanner', 'mb-cookie-consent' ); ?></h1>
			<p><?php echo esc_html__( 'Manually scan public URLs on this site for new cookie names, scripts and iframes. Cookie values are never stored.', 'mb-cookie-consent' ); ?></p>
			<p><?php echo esc_html__( 'Suggestions are informational. Nothing is blocked and no setting is changed until you confirm a category.', 'mb-cookie-consent' ); ?></p>
			<p><button type="button" class="button button-primary" id="mbcc-start-scan"><?php echo esc_html__( 'Start manual scan', 'mb-cookie-consent' ); ?></button></p>
			<div id="mbcc-scan-progress" role="status" aria-live="polite"></div>

			<h2><?php echo esc_html__( 'New items requiring review', 'mb-cookie-consent' ); ?></h2>
			<?php if ( empty( $pending ) ) : ?>
				<p><?php echo esc_html__( 'No unreviewed items are currently stored.', 'mb-cookie-consent' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'Type', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Detected item', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Example page', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Category', 'mb-cookie-consent' ); ?></th><th><?php echo esc_html__( 'Action', 'mb-cookie-consent' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $pending as $id => $item ) : ?>
						<?php $form_id = 'mbcc-add-rule-' . $id; ?>
						<tr>
							<td><?php echo esc_html( isset( $type_labels[ $item['type'] ] ) ? $type_labels[ $item['type'] ] : $item['type'] ); ?></td>
							<td><code><?php echo esc_html( $item['value'] ); ?></code></td>
							<td><a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['source_url'] ); ?></a></td>
							<td>
									<select name="category" form="<?php echo esc_attr( $form_id ); ?>" required>
										<option value=""><?php echo esc_html__( 'Choose category', 'mb-cookie-consent' ); ?></option>
										<?php foreach ( $category_labels as $category => $category_label ) : ?>
											<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $item['suggested_category'], $category ); ?>><?php echo esc_html( $category_label ); ?></option>
										<?php endforeach; ?>
									</select>
							</td>
							<td>
								<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="mbcc_add_scan_rule">
									<input type="hidden" name="item_id" value="<?php echo esc_attr( $id ); ?>">
									<?php wp_nonce_field( 'mbcc_scan_item_' . $id ); ?>
									<button class="button button-primary" type="submit"><?php echo esc_html__( 'Add rule', 'mb-cookie-consent' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-top:4px">
									<input type="hidden" name="action" value="mbcc_ignore_scan_item">
									<input type="hidden" name="item_id" value="<?php echo esc_attr( $id ); ?>">
									<?php wp_nonce_field( 'mbcc_scan_item_' . $id ); ?>
									<button class="button" type="submit"><?php echo esc_html__( 'Ignore', 'mb-cookie-consent' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="mbcc_clear_scan_results">
				<?php wp_nonce_field( 'mbcc_clear_scan_results' ); ?>
				<button class="button" type="submit"><?php echo esc_html__( 'Clear scan history', 'mb-cookie-consent' ); ?></button>
			</form>
			<p class="description"><?php echo esc_html__( 'The scanner cannot guarantee discovery of HttpOnly, third-party or conditionally created cookies. Review the site in a browser as part of every audit.', 'mb-cookie-consent' ); ?></p>
		</div>
		<?php
	}

	/** Create a fresh manual scan job. */
	public function start_scan() {
		$this->authorize_ajax();
		$urls = $this->collect_urls();
		$job  = array( 'urls' => $urls, 'offset' => 0, 'errors' => 0 );
		set_transient( self::JOB_PREFIX . get_current_user_id(), $job, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'total' => count( $urls ) ) );
	}

	/** Scan the next small batch to avoid long admin requests. */
	public function scan_batch() {
		$this->authorize_ajax();
		$key = self::JOB_PREFIX . get_current_user_id();
		$job = get_transient( $key );
		if ( ! is_array( $job ) || ! isset( $job['urls'], $job['offset'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The scan job has expired. Start it again.', 'mb-cookie-consent' ) ), 400 );
		}

		$batch = array_slice( $job['urls'], (int) $job['offset'], self::BATCH_SIZE );
		foreach ( $batch as $url ) {
			if ( ! $this->scan_url( $url ) ) {
				++$job['errors'];
			}
		}
		$job['offset'] += count( $batch );
		$remaining      = max( 0, count( $job['urls'] ) - $job['offset'] );

		if ( 0 === $remaining ) {
			delete_transient( $key );
		} else {
			set_transient( $key, $job, HOUR_IN_SECONDS );
		}

		wp_send_json_success( array( 'scanned' => $job['offset'], 'total' => count( $job['urls'] ), 'remaining' => $remaining, 'errors' => $job['errors'] ) );
	}

	/** Add a confirmed result to the corresponding blocking-rule setting. */
	public function add_rule() {
		$this->authorize_item_action();
		$id       = isset( $_POST['item_id'] ) ? sanitize_key( wp_unslash( $_POST['item_id'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		if ( ! in_array( $category, array( 'necessary', 'preferences', 'analytics', 'marketing' ), true ) ) {
			wp_die( esc_html__( 'Invalid cookie category.', 'mb-cookie-consent' ) );
		}

		$results = get_option( self::RESULTS_OPTION, array() );
		if ( ! is_array( $results ) || empty( $results[ $id ] ) ) {
			wp_die( esc_html__( 'The selected scan result no longer exists.', 'mb-cookie-consent' ) );
		}

		$item = $results[ $id ];
		$map  = array( 'cookie' => 'cookie_patterns', 'script' => 'script_patterns', 'iframe' => 'iframe_patterns' );
		if ( empty( $map[ $item['type'] ] ) ) {
			wp_die( esc_html__( 'Unsupported scan result type.', 'mb-cookie-consent' ) );
		}

		$settings = get_option( MBCC_Settings::OPTION_NAME, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$key      = $map[ $item['type'] ];
		$stored   = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		if ( ! self::has_rule_value( $stored, $item['value'], 'cookie' === $item['type'] ) ) {
			$settings[ $key ] = $stored . ( '' === $stored || preg_match( '/(?:\r\n|\r|\n)$/', $stored ) ? '' : "\n" ) . $item['value'] . '|' . $category;
			update_option( MBCC_Settings::OPTION_NAME, $settings, true );
		}

		unset( $results[ $id ] );
		update_option( self::RESULTS_OPTION, $results, false );
		$this->redirect();
	}

	/** Keep an ignored item from reappearing until scan history is cleared. */
	public function ignore_item() {
		$this->authorize_item_action();
		$id      = isset( $_POST['item_id'] ) ? sanitize_key( wp_unslash( $_POST['item_id'] ) ) : '';
		$results = get_option( self::RESULTS_OPTION, array() );
		if ( is_array( $results ) && isset( $results[ $id ] ) ) {
			$results[ $id ]['status'] = 'ignored';
			update_option( self::RESULTS_OPTION, $results, false );
		}
		$this->redirect();
	}

	/** Clear findings and ignored-item history. */
	public function clear_results() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'mb-cookie-consent' ) );
		}
		check_admin_referer( 'mbcc_clear_scan_results' );
		delete_option( self::RESULTS_OPTION );
		delete_transient( self::JOB_PREFIX . get_current_user_id() );
		$this->redirect();
	}

	/** Discover same-site public content, archive and taxonomy URLs. */
	private function collect_urls() {
		$urls = array( home_url( '/' ) );
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$ids = get_posts( array( 'post_type' => array_values( $post_types ), 'post_status' => 'publish', 'numberposts' => self::MAX_URLS, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => false ) );
		foreach ( $ids as $id ) {
			$urls[] = get_permalink( $id );
		}
		foreach ( $post_types as $post_type ) {
			if ( 'post' !== $post_type && get_post_type_archive_link( $post_type ) ) {
				$urls[] = get_post_type_archive_link( $post_type );
			}
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 50 ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$url = get_term_link( $term );
				if ( ! is_wp_error( $url ) ) {
					$urls[] = $url;
				}
			}
		}

		$urls = array_values( array_unique( array_filter( array_map( array( $this, 'same_site_url' ), $urls ) ) ) );
		return array_slice( $urls, 0, self::MAX_URLS );
	}

	/** Fetch and inspect one URL. */
	private function scan_url( $url ) {
		$response = wp_safe_remote_get( $url, array( 'timeout' => 12, 'redirection' => 3, 'limit_response_size' => 2097152, 'user-agent' => 'MB-Cookie-Consent-Scanner/' . MBCC_VERSION . '; ' . home_url( '/' ) ) );
		if ( is_wp_error( $response ) || 200 > wp_remote_retrieve_response_code( $response ) || 399 < wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$items   = self::extract_items( wp_remote_retrieve_body( $response ), wp_remote_retrieve_header( $response, 'set-cookie' ) );
		$results = get_option( self::RESULTS_OPTION, array() );
		$results = is_array( $results ) ? $results : array();
		$settings = MBCC_Settings::get();

		foreach ( $items as $item ) {
			if ( self::configured( $item, $settings ) ) {
				continue;
			}
			$id = sha1( $item['type'] . '|' . strtolower( $item['value'] ) );
			if ( isset( $results[ $id ] ) ) {
				continue;
			}
			if ( count( $results ) >= self::MAX_RESULTS ) {
				break;
			}
			$results[ $id ] = array( 'type' => $item['type'], 'value' => sanitize_text_field( $item['value'] ), 'source_url' => $url, 'suggested_category' => self::suggest_category( $item['type'], $item['value'] ), 'status' => '' );
		}
		update_option( self::RESULTS_OPTION, $results, false );
		return true;
	}

	/** Extract cookie names and resource URLs without storing cookie values. */
	public static function extract_items( $html, $set_cookie = '' ) {
		$items = array();
		$headers = is_array( $set_cookie ) ? $set_cookie : array( (string) $set_cookie );
		foreach ( $headers as $header ) {
			if ( preg_match_all( '/(?:^|,\s*)([!#$%&\'*+.^_`|~0-9A-Za-z-]+)=/', $header, $matches ) ) {
				foreach ( $matches[1] as $name ) {
					$items[] = array( 'type' => 'cookie', 'value' => $name );
				}
			}
		}
		if ( preg_match_all( '/document\.cookie\s*=\s*(["\'])([!#$%&*+.^_`|~0-9A-Za-z-]+)=/i', (string) $html, $matches ) ) {
			foreach ( $matches[2] as $name ) {
				$items[] = array( 'type' => 'cookie', 'value' => $name );
			}
		}
		foreach ( array( 'script', 'iframe' ) as $type ) {
			if ( preg_match_all( '/<' . $type . '\b[^>]*(?:data-mbcc-src|src)\s*=\s*(["\'])(.*?)\1/is', (string) $html, $matches ) ) {
				foreach ( $matches[2] as $source ) {
					$value = self::normalize_resource( html_entity_decode( $source, ENT_QUOTES, 'UTF-8' ) );
					if ( '' !== $value ) {
						$items[] = array( 'type' => $type, 'value' => $value );
					}
				}
			}
		}

		$unique = array();
		foreach ( $items as $item ) {
			$unique[ $item['type'] . '|' . strtolower( $item['value'] ) ] = $item;
		}
		return array_values( $unique );
	}

	/** Convert a resource URL to a stable host/path rule suggestion. */
	public static function normalize_resource( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || 0 === strpos( $url, 'data:' ) ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( $host ) {
			return strtolower( $host ) . ( $path ? $path : '' );
		}
		return strtok( ltrim( $url, '/' ), '?#' );
	}

	/** Suggest a category from conservative, built-in signatures. */
	public static function suggest_category( $type, $value ) {
		$value = strtolower( (string) $value );
		$map = array(
			'necessary'   => array( 'mbcc_consent', 'wordpress_', 'wp-settings', 'phpessid', 'phpsessid', 'woocommerce_cart', 'woocommerce_items', 'wp_woocommerce_session' ),
			'preferences' => array( 'pll_language', 'locale', 'language' ),
			'analytics'   => array( '_ga', '_gid', '_gat', '_clck', '_clsk', '_hj', 'google-analytics.com', 'googletagmanager.com/gtag', 'clarity.ms', 'hotjar.com' ),
			'marketing'   => array( '_fbp', '_gcl_', 'doubleclick.net', 'connect.facebook.net', 'tiktok.com', 'snap.licdn.com', 'googleadservices.com' ),
		);
		foreach ( $map as $category => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( false !== strpos( $value, $pattern ) ) {
					return $category;
				}
			}
		}
		return '';
	}

	/** Check whether a finding already has a configured rule. */
	private static function configured( $item, $settings ) {
		if ( 'script' === $item['type'] && false !== stripos( $item['value'], '/mb-cookie-consent/assets/' ) ) {
			return true;
		}
		$map = array( 'cookie' => 'cookie_patterns', 'script' => 'script_patterns', 'iframe' => 'iframe_patterns' );
		$key = $map[ $item['type'] ];
		return self::has_rule_value( isset( $settings[ $key ] ) ? $settings[ $key ] : '', $item['value'], 'cookie' === $item['type'] );
	}

	/** Match an item against newline-delimited settings rules. */
	private static function has_rule_value( $stored, $value, $wildcards = false ) {
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $stored ) as $line ) {
			$parts   = explode( '|', trim( $line ), 2 );
			$pattern = strtolower( trim( $parts[0] ) );
			if ( '' === $pattern ) {
				continue;
			}
			if ( $wildcards && preg_match( '/^' . str_replace( '\\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i', $value ) ) {
				return true;
			}
			if ( ! $wildcards && false !== stripos( $value, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/** Allow only HTTP(S) URLs on the site's own host. */
	private function same_site_url( $url ) {
		$url    = esc_url_raw( $url );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$home   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return $url && $host === $home && in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	/** Check capability and AJAX nonce. */
	private function authorize_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'mb-cookie-consent' ) ), 403 );
		}
		check_ajax_referer( 'mbcc_scan', 'nonce' );
	}

	/** Check capability and result-specific nonce. */
	private function authorize_item_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'mb-cookie-consent' ) );
		}
		$id = isset( $_POST['item_id'] ) ? sanitize_key( wp_unslash( $_POST['item_id'] ) ) : '';
		check_admin_referer( 'mbcc_scan_item_' . $id );
	}

	/** Return to the scanner page. */
	private function redirect() {
		wp_safe_redirect( admin_url( 'options-general.php?page=mb-cookie-consent-scanner' ) );
		exit;
	}
}
