<?php
/**
 * Bridge Builder Asset Manager
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_Asset_Manager {

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'wp_head', array( $this, 'output_css' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_google_fonts_style' ), 15 );
		add_action( 'wp_footer', array( $this, 'output_css_footer' ), 99 );
	}

	public function enqueue_admin( $hook ) {
		// Load builder when we're on the builder or design-system screen with a post_id.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$is_builder_screen = ( $page === 'bridge-builder' && $post_id > 0 );
		$is_design_system_screen = ( $page === 'bridge-builder-design-system' );
		// Also allow by hook for backwards compatibility (e.g. bookmarks)
		$hook_ok = ( $hook === 'toplevel_page_bridge-builder' || $hook === 'bridge-builder_page_bridge-builder' || $hook === 'bridge-builder_page_bridge-builder-design-system' );
		if ( ! $is_builder_screen && ! $is_design_system_screen && ! ( $hook_ok && $post_id > 0 ) ) {
			return;
		}

		// Enqueue WordPress media library scripts so wp.media() is available
		wp_enqueue_media();

		wp_enqueue_style(
			'bridge-builder-css',
			BRIDGE_BUILDER_URL . 'dist/builder.css',
			array(),
			BRIDGE_BUILDER_VERSION
		);

		wp_enqueue_script(
			'bridge-builder-js',
			BRIDGE_BUILDER_URL . 'dist/builder.js',
			array( 'media-upload', 'thickbox' ),
			BRIDGE_BUILDER_VERSION,
			true
		);

		// Vite outputs ES modules — add type="module" attribute
		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 2 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$post_type    = '';
		$post_title   = '';
		$preview_url  = '';
		$preview_nonce = '';
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$post_type  = $post->post_type;
				$post_title = $post->post_title;
				if ( in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
					$preview_url   = get_permalink( $post_id );
					$preview_nonce = wp_create_nonce( 'bb_preview_' . $post_id );
				}
			}
		}

		$menus = array();
		$nav_menus = wp_get_nav_menus();
		if ( is_array( $nav_menus ) ) {
			foreach ( $nav_menus as $menu ) {
				$menus[] = array(
					'id'   => (int) $menu->term_id,
					'slug' => $menu->slug,
					'name' => $menu->name,
				);
			}
		}

		$base_builder_url = add_query_arg( array( 'page' => 'bridge-builder', 'post_id' => $post_id ), admin_url( 'admin.php' ) );
		$base_design_system_url = add_query_arg( array( 'page' => 'bridge-builder-design-system', 'post_id' => $post_id ), admin_url( 'admin.php' ) );
		$global_design_system_url = add_query_arg( array( 'page' => 'bridge-builder-design-system' ), admin_url( 'admin.php' ) );
		$dashboard_url = add_query_arg( array( 'page' => 'bridge-builder' ), admin_url( 'admin.php' ) );
		$screen = $is_design_system_screen ? 'design-system' : 'builder';
		$is_global_design_system = ( $is_design_system_screen && $post_id === 0 );

		wp_localize_script( 'bridge-builder-js', 'wpBuilderData', array(
			'apiUrl'               => rest_url( 'bridge-builder/v1/' ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'postId'               => $post_id,
			'postType'             => $post_type,
			'postTitle'            => $post_title,
			'previewUrl'           => $preview_url,
			'previewNonce'         => $preview_nonce,
			'adminUrl'             => admin_url(),
			'menus'                => $menus,
			'screen'               => $screen,
			'builderUrl'           => $base_builder_url,
			'designSystemUrl'      => $base_design_system_url,
			'globalDesignSystemUrl' => $global_design_system_url,
			'dashboardUrl'         => $dashboard_url,
			'isGlobalDesignSystem' => $is_global_design_system,
		) );
	}

	public function enqueue_frontend() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		$json    = $post_id ? get_post_meta( $post_id, '_builder_json', true ) : '';

		if ( empty( $json ) ) {
			return;
		}

		$css_path = BRIDGE_BUILDER_PATH . 'dist/runtime.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'bb-runtime-css', BRIDGE_BUILDER_URL . 'dist/runtime.css', array(), BRIDGE_BUILDER_VERSION );
		}

		// Defer runtime.js until after idle so FCP/LCP are not blocked by the script.
		$runtime_url = BRIDGE_BUILDER_URL . 'dist/runtime.js?' . BRIDGE_BUILDER_VERSION;
		$builder_data = array(
			'apiUrl' => rest_url( 'bridge-builder/v1/' ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'postId' => $post_id,
		);

		$loader = sprintf(
			"window.wpBuilderData=%s;(function(){var u=%s;function load(){var s=document.createElement('script');s.type='module';s.src=u;document.body.appendChild(s);}if(typeof requestIdleCallback!=='undefined'){requestIdleCallback(load,{timeout:2500});}else{setTimeout(load,1);}})();",
			wp_json_encode( $builder_data ),
			wp_json_encode( $runtime_url )
		);

		wp_register_script( 'bb-runtime-loader', false, array(), BRIDGE_BUILDER_VERSION, true );
		wp_enqueue_script( 'bb-runtime-loader' );
		wp_add_inline_script( 'bb-runtime-loader', $loader, 'after' );

		// Ensure runtime always has page data (in case the_content filter didn't run or script was stripped).
		add_action( 'wp_footer', array( $this, 'output_page_data_fallback' ), 5 );
		$this->page_data_post_id = $post_id;
	}

	/**
	 * Post ID used for footer page-data fallback (set when enqueue_frontend runs).
	 *
	 * @var int|null
	 */
	private $page_data_post_id = null;

	/**
	 * Output #bb-page-data in footer so the runtime always finds it when the page has builder data.
	 * If JSON is missing or invalid, output an empty page tree so the runtime never bails with "No page data found".
	 */
	public function output_page_data_fallback() {
		if ( null === $this->page_data_post_id ) {
			return;
		}
		$post_id = $this->page_data_post_id;
		$json    = get_post_meta( $post_id, '_builder_json', true );
		// Preview mode: use draft when valid nonce and user can edit
		if ( isset( $_GET['preview'] ) && $_GET['preview'] === '1' && isset( $_GET['_wpnonce'] ) && current_user_can( 'edit_post', $post_id ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_preview_' . $post_id ) ) {
				$draft = get_post_meta( $post_id, '_builder_json_draft', true );
				if ( ! empty( $draft ) ) {
					$json = $draft;
				}
			}
		}
		$tree  = ! empty( $json ) ? json_decode( $json, true ) : null;
		$valid = is_array( $tree ) && ( isset( $tree[0] ) || isset( $tree['children'] ) );

		if ( ! $valid ) {
			$tree = array(
				'id'       => 'bb-page-root',
				'type'     => 'Page',
				'props'    => array(),
				'styles'   => array(),
				'children' => array(),
			);
		} else {
			// Normalize: support legacy format (root = list of sections).
			if ( isset( $tree[0] ) ) {
				$tree = array( 'id' => 'bb-page-root', 'type' => 'Page', 'props' => array(), 'styles' => array(), 'children' => $tree );
			} elseif ( ! isset( $tree['children'] ) || ! is_array( $tree['children'] ) ) {
				$tree['children'] = array();
			}
			// Enrich Posts Grid nodes with posts for frontend.
			$rest_api = new Bridge_Builder_REST_API();
			$tree     = $rest_api->enrich_tree_for_frontend( $tree, $this->page_data_post_id );
		}

		// Single-source payload: include css + revision when builder styles so runtime can own #bb-styles.
		// On builder pages always use full design so the built layout matches the editor (theme setting ignored for these).
		$use_builder = ( get_option( 'bridge_builder_frontend_styling', 'theme' ) === 'builder' ) || bridge_builder_is_builder_page();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $use_builder && isset( $_GET['bb_builder_styles'] ) && sanitize_text_field( wp_unslash( $_GET['bb_builder_styles'] ) ) === '1' ) {
			$use_builder = true;
		}
		$payload = array( 'tree' => $tree );

		// User state for Conditional Display (Phase 7).
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$payload['user'] = array(
				'loggedIn' => true,
				'roles'    => is_array( $user->roles ) ? array_values( $user->roles ) : array(),
			);
		} else {
			$payload['user'] = array( 'loggedIn' => false, 'roles' => array() );
		}

		if ( $use_builder && $valid ) {
			$css      = get_post_meta( $this->page_data_post_id, '_builder_css', true );
			$json_meta = get_post_meta( $this->page_data_post_id, '_builder_json', true );
			if ( empty( $css ) && ! empty( $json_meta ) ) {
				$gen = new Bridge_Builder_CSS_Generator();
				$css = $gen->generate( json_decode( $json_meta, true ) );
				update_post_meta( $this->page_data_post_id, '_builder_css', $css );
			}
			if ( ! empty( $css ) ) {
				$design_system_css = Bridge_Builder_Asset_Manager::design_system_to_css( $this->page_data_post_id );
				$full_css = ( $design_system_css !== '' ? $design_system_css : '' ) . $css;
				$payload['css']      = Bridge_Builder_Asset_Manager::scope_builder_css( $full_css );
				$payload['revision'] = md5( $full_css );
			}
		}

		echo '<script type="application/json" id="bb-page-data">';
		echo wp_json_encode( $payload );
		echo '</script>';
	}

	/**
	 * Add type="module" to our script tags so ES module imports work.
	 */
	public function add_module_type( $tag, $handle ) {
		if ( 'bridge-builder-js' === $handle ) {
			$tag = str_replace( ' src=', ' type="module" src=', $tag );
		}
		return $tag;
	}

	public function output_css() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id   = get_queried_object_id();
		$json      = get_post_meta( $post_id, '_builder_json', true );
		$css       = get_post_meta( $post_id, '_builder_css', true );
		$structural = get_post_meta( $post_id, '_builder_css_structural', true );

		$is_preview = isset( $_GET['preview'] ) && $_GET['preview'] === '1' && isset( $_GET['_wpnonce'] ) && current_user_can( 'edit_post', $post_id )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_preview_' . $post_id );

		// Preview: use draft tree and full CSS so editor sees designed look.
		if ( $is_preview ) {
			$draft_json = get_post_meta( $post_id, '_builder_json_draft', true );
			if ( ! empty( $draft_json ) ) {
				$tree = json_decode( $draft_json, true );
				if ( $tree ) {
					$gen = new Bridge_Builder_CSS_Generator();
					$css = $gen->generate( $tree );
				}
			}
		}

		// Regenerate cache if missing.
		if ( empty( $css ) && ! empty( $json ) ) {
			$tree = json_decode( $json, true );
			if ( $tree ) {
				$gen = new Bridge_Builder_CSS_Generator();
				$css = $gen->generate( $tree );
				$structural = $gen->generate( $tree, true );
				update_post_meta( $post_id, '_builder_css', $css );
				update_post_meta( $post_id, '_builder_css_structural', $structural );
			}
		}

		// Frontend: use structural (theme) or full (builder) per setting. Builder pages + preview: always full CSS.
		$use_builder_styles = ( get_option( 'bridge_builder_frontend_styling', 'theme' ) === 'builder' ) || bridge_builder_is_builder_page();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $use_builder_styles && isset( $_GET['bb_builder_styles'] ) && sanitize_text_field( wp_unslash( $_GET['bb_builder_styles'] ) ) === '1' ) {
			$use_builder_styles = true;
		}
		$output_css = $is_preview ? $css : ( $use_builder_styles ? $css : $structural );
		if ( empty( $output_css ) && ! empty( $css ) ) {
			// No structural cached yet (e.g. old save); use full CSS this time and backfill.
			$output_css = $css;
			if ( ! empty( $json ) ) {
				$tree = json_decode( $json, true );
				if ( $tree ) {
					$gen = new Bridge_Builder_CSS_Generator();
					update_post_meta( $post_id, '_builder_css_structural', $gen->generate( $tree, true ) );
				}
			}
		}

		// Prepend design system tokens when present.
		$design_system_css = Bridge_Builder_Asset_Manager::design_system_to_css( $post_id );
		if ( $design_system_css !== '' ) {
			$output_css = $design_system_css . $output_css;
		}
		// Output in head only for theme mode or preview. Builder mode: single source in footer only (no duplicate #bb-styles).
		if ( ! empty( $output_css ) && ( $is_preview || ! $use_builder_styles ) ) {
			echo '<style id="bb-styles">' . "\n" . esc_html( $output_css ) . '</style>' . "\n";
		}
	}

	/**
	 * Output full builder CSS in footer when "Builder (full design)" is selected.
	 * Loads after theme styles so the design is not overridden (no flash of theme look).
	 */
	public function output_css_footer() {
		if ( ! is_singular() ) {
			return;
		}
		// On builder pages always output full design so it matches the editor (theme setting ignored).
		$use_builder = ( get_option( 'bridge_builder_frontend_styling', 'theme' ) === 'builder' ) || bridge_builder_is_builder_page();
		// Allow forcing builder styles via ?bb_builder_styles=1 for debugging.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $use_builder && isset( $_GET['bb_builder_styles'] ) && sanitize_text_field( wp_unslash( $_GET['bb_builder_styles'] ) ) === '1' ) {
			$use_builder = true;
		}
		if ( ! $use_builder ) {
			return;
		}

		$post_id = get_queried_object_id();
		$css     = get_post_meta( $post_id, '_builder_css', true );
		$json    = get_post_meta( $post_id, '_builder_json', true );

		if ( empty( $css ) && ! empty( $json ) ) {
			$tree = json_decode( $json, true );
			if ( $tree ) {
				$gen = new Bridge_Builder_CSS_Generator();
				$css = $gen->generate( $tree );
				update_post_meta( $post_id, '_builder_css', $css );
			}
		}

		if ( ! empty( $css ) ) {
			$design_system_css = Bridge_Builder_Asset_Manager::design_system_to_css( $post_id );
			$scoped = Bridge_Builder_Asset_Manager::scope_builder_css( ( $design_system_css !== '' ? $design_system_css : '' ) . $css );
			echo '<style id="bb-styles">' . "\n" . esc_html( $scoped ) . '</style>' . "\n";
		}
	}

	/** Option key for the site-wide global design system. */
	const OPTION_GLOBAL_DESIGN_SYSTEM = 'bridge_builder_global_design_system';

	/**
	 * Get the design system JSON string for a post: per-page meta if set, otherwise global.
	 *
	 * @param int $post_id Post ID (0 = global only).
	 * @return string JSON string or empty.
	 */
	public static function get_resolved_design_system_json( $post_id ) {
		if ( $post_id ) {
			$raw = get_post_meta( $post_id, '_builder_design_system', true );
			if ( ! empty( $raw ) && is_string( $raw ) ) {
				return $raw;
			}
		}
		return get_option( self::OPTION_GLOBAL_DESIGN_SYSTEM, '' );
	}

	/**
	 * Generate CSS custom properties block from design system (per-page or global).
	 * Scoped to #bb-render.bb-content so tokens apply only to builder content.
	 *
	 * @param int $post_id Post ID.
	 * @return string CSS block (including selector) or empty string.
	 */
	public static function design_system_to_css( $post_id ) {
		$raw = self::get_resolved_design_system_json( $post_id );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return '';
		}
		$system = json_decode( $raw, true );
		if ( ! is_array( $system ) ) {
			return '';
		}
		$vars = array();
		if ( ! empty( $system['colors'] ) && is_array( $system['colors'] ) ) {
			foreach ( $system['colors'] as $key => $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					$vars[ '--color-' . $key ] = $value;
				}
			}
		}
		if ( ! empty( $system['typography'] ) && is_array( $system['typography'] ) ) {
			$t = $system['typography'];
			if ( ! empty( $t['fontDisplay'] ) ) {
				$vars['--font-display'] = sanitize_text_field( $t['fontDisplay'] );
			}
			if ( ! empty( $t['fontBody'] ) ) {
				$vars['--font-body'] = sanitize_text_field( $t['fontBody'] );
			}
			if ( ! empty( $t['fontMono'] ) ) {
				$vars['--font-mono'] = sanitize_text_field( $t['fontMono'] );
			}
			if ( ! empty( $t['scale'] ) && is_array( $t['scale'] ) ) {
				foreach ( $t['scale'] as $key => $value ) {
					$vars[ '--text-' . $key ] = is_numeric( $value ) ? $value . 'px' : sanitize_text_field( (string) $value );
				}
			}
			if ( ! empty( $t['weights'] ) && is_array( $t['weights'] ) ) {
				foreach ( $t['weights'] as $key => $value ) {
					$vars[ '--font-weight-' . $key ] = is_numeric( $value ) ? (string) $value : sanitize_text_field( (string) $value );
				}
			}
			if ( ! empty( $t['lineHeights'] ) && is_array( $t['lineHeights'] ) ) {
				foreach ( $t['lineHeights'] as $key => $value ) {
					$vars[ '--line-height-' . $key ] = is_numeric( $value ) ? (string) $value : sanitize_text_field( (string) $value );
				}
			}
		}
		if ( ! empty( $system['spacing']['scale'] ) && is_array( $system['spacing']['scale'] ) ) {
			foreach ( $system['spacing']['scale'] as $i => $value ) {
				$vars[ '--space-' . $i ] = is_numeric( $value ) ? $value . 'px' : sanitize_text_field( (string) $value );
			}
		}
		if ( ! empty( $system['borders'] ) && is_array( $system['borders'] ) ) {
			$b = $system['borders'];
			if ( ! empty( $b['radius'] ) && is_array( $b['radius'] ) ) {
				foreach ( $b['radius'] as $key => $value ) {
					if ( is_string( $value ) && $value !== '' ) {
						$vars[ '--radius-' . $key ] = $value;
					}
				}
			}
			if ( ! empty( $b['width'] ) ) {
				$vars['--border-width'] = sanitize_text_field( (string) $b['width'] );
			}
			if ( ! empty( $b['style'] ) ) {
				$vars['--border-style'] = sanitize_text_field( (string) $b['style'] );
			}
			if ( ! empty( $b['color'] ) ) {
				$vars['--border-color'] = sanitize_text_field( (string) $b['color'] );
			}
		}
		if ( ! empty( $system['shadows'] ) && is_array( $system['shadows'] ) ) {
			foreach ( $system['shadows'] as $key => $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					$vars[ '--shadow-' . $key ] = $value;
				}
			}
		}
		if ( ! empty( $system['motion'] ) && is_array( $system['motion'] ) ) {
			$m = $system['motion'];
			if ( ! empty( $m['duration'] ) && is_array( $m['duration'] ) ) {
				foreach ( $m['duration'] as $key => $value ) {
					if ( is_string( $value ) && $value !== '' ) {
						$vars[ '--duration-' . $key ] = $value;
					}
				}
			}
			if ( ! empty( $m['easing'] ) && is_array( $m['easing'] ) ) {
				foreach ( $m['easing'] as $key => $value ) {
					if ( is_string( $value ) && $value !== '' ) {
						$vars[ '--easing-' . $key ] = $value;
					}
				}
			}
		}
		if ( empty( $vars ) ) {
			return '';
		}
		// Apply body font to entire builder content so design system typography applies site-wide.
		if ( ! empty( $system['typography']['fontBody'] ) ) {
			$vars['font-family'] = 'var(--font-body), sans-serif';
		}
		$lines = array( '#bb-render.bb-content, .bb-content {' );
		foreach ( $vars as $prop => $val ) {
			$lines[] = '  ' . $prop . ': ' . $val . ';';
		}
		$lines[] = '}';
		// Default hover/active for buttons and icons when design system has primary colors.
		if ( ! empty( $system['colors']['primary'] ) ) {
			$lines[] = '#bb-render.bb-content .bb-Button:hover, .bb-content .bb-Button:hover { background: var(--color-primary-hover, var(--color-primary)); color: var(--color-background, #fff); }';
			$lines[] = '#bb-render.bb-content .bb-Button:active, .bb-content .bb-Button:active { background: var(--color-primary-active, var(--color-primary)); }';
			$lines[] = '#bb-render.bb-content .bb-Button, .bb-content .bb-Button { transition: var(--duration-normal, 300ms) var(--easing-ease-in-out, ease); }';
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Enqueue Google Fonts stylesheet when design system typography uses Google Fonts.
	 */
	public static function enqueue_google_fonts_style() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$json    = $post_id ? get_post_meta( $post_id, '_builder_json', true ) : '';
		if ( empty( $json ) ) {
			return;
		}
		$raw = self::get_resolved_design_system_json( $post_id );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return;
		}
		$system = json_decode( $raw, true );
		if ( ! is_array( $system ) || empty( $system['typography'] ) || ! is_array( $system['typography'] ) ) {
			return;
		}
		$t   = $system['typography'];
		$out = array();
		if ( ! empty( $t['fontDisplay'] ) ) {
			$out[] = $t['fontDisplay'];
		}
		if ( ! empty( $t['fontBody'] ) ) {
			$out[] = $t['fontBody'];
		}
		if ( ! empty( $t['fontMono'] ) && strpos( $t['fontMono'], 'ui-monospace' ) === false ) {
			$out[] = $t['fontMono'];
		}
		$out = array_unique( array_filter( $out ) );
		if ( empty( $out ) ) {
			return;
		}
		$query = array();
		foreach ( $out as $family ) {
			$query[] = 'family=' . rawurlencode( str_replace( ' ', '+', $family ) . ':wght@400;500;600;700' );
		}
		$href = 'https://fonts.googleapis.com/css2?' . implode( '&', $query ) . '&display=swap';
		wp_enqueue_style( 'bb-google-fonts', esc_url_raw( $href ), array(), null );
	}

	/**
	 * Wrap each selector in #bb-render.bb-content and add !important so builder styles beat theme.
	 *
	 * @param string $css Raw CSS with [data-bb-id="..."] selectors.
	 * @return string Scoped CSS.
	 */
	public static function scope_builder_css( $css ) {
		$css = str_replace( '[data-bb-id=', '#bb-render.bb-content [data-bb-id=', $css );
		// Add !important to every declaration so theme/WordPress rules cannot override.
		return Bridge_Builder_Asset_Manager::add_important_to_css( $css );
	}

	/**
	 * Append !important to each CSS declaration so builder styles win over theme.
	 *
	 * @param string $css CSS string.
	 * @return string CSS with "value !important" for each declaration.
	 */
	public static function add_important_to_css( $css ) {
		// Match "property: value" followed by ; or } and insert !important before ; or }.
		return preg_replace( '/:\s*([^;}+]+)(\s*;|\s*})/s', ': $1 !important$2', $css );
	}
}
