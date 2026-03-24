<?php
/**
 * Bridge Builder REST API
 *
 * Registers REST endpoints for saving/loading the component tree JSON.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_REST_API {

	/** REST namespace. */
	const NAMESPACE = 'bridge-builder/v1';

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Save / Load by post ID
		register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_page_data' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_page_data' ),
				'permission_callback' => array( $this, 'can_edit' ),
			),
		) );

		// Get saved revisions list for a page (last 10)
		register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/revisions', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_revisions' ),
			'permission_callback' => array( $this, 'can_edit' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// Get a single revision by index (0 = most recent)
		register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/revisions/(?P<index>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_revision' ),
			'permission_callback' => array( $this, 'can_edit' ),
			'args'                => array(
				'id'    => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
				'index' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param >= 0 && (int) $param < 10;
					},
				),
			),
		) );

		// Get page data by URL (used by SPA navigation)
		register_rest_route( self::NAMESPACE, '/page-by-url', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_page_by_url' ),
			'permission_callback' => '__return_true', // Public endpoint
			'args'                => array(
				'url' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		// Contact form submission (public, requires nonce)
		register_rest_route( self::NAMESPACE, '/contact-form', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'submit_contact_form' ),
			'permission_callback' => '__return_true',
		) );

		// List items by type (sections, headers, footers, popups, templates)
		register_rest_route( self::NAMESPACE, '/items', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'can_edit_items' ),
			'args'                => array(
				'type' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function ( $param ) {
						return in_array( $param, array( 'bb_section', 'bb_header', 'bb_footer', 'bb_popup', 'bb_template' ), true );
					},
				),
			),
		) );

		// Get/Set active header and footer (options)
		register_rest_route( self::NAMESPACE, '/options', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_options' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
			),
		) );

		// Global design systems library (named systems for reuse)
		register_rest_route( self::NAMESPACE, '/design-systems', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_design_systems' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_design_systems' ),
				'permission_callback' => array( $this, 'can_manage_design_systems' ),
			),
		) );

		// Site-wide global design system (applies to all builder pages when no per-page override)
		register_rest_route( self::NAMESPACE, '/global-design-system', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_global_design_system' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_global_design_system' ),
				'permission_callback' => array( $this, 'can_manage_design_systems' ),
			),
		) );

		// AI: generate page tree from prompt (requires edit_posts)
		register_rest_route( self::NAMESPACE, '/generate-page', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate_page' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'prompt' => array(
					'required'          => true,
					'type'               => 'string',
					'sanitize_callback'  => 'sanitize_textarea_field',
					'validate_callback'  => function ( $param ) {
						return is_string( $param ) && strlen( $param ) > 0 && strlen( $param ) <= 2000;
					},
				),
			),
		) );

		// AI: check if generation is available (API key configured)
		register_rest_route( self::NAMESPACE, '/ai-status', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_ai_status' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
		) );

		// AI: generate single section/block (for "Edit with AI" on a section)
		register_rest_route( self::NAMESPACE, '/generate-section', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate_section' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'prompt'      => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'validate_callback' => function ( $param ) {
						return is_string( $param ) && strlen( $param ) > 0 && strlen( $param ) <= 2000;
					},
				),
				'contextJson'  => array(
					'required' => false,
					'type'     => 'string',
					'default'  => '',
				),
			),
		) );
	}

	/**
	 * Permission: user can edit posts (for AI generate and ai-status).
	 */
	public function can_edit_posts( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * POST /generate-page – Generate page tree from prompt via NVIDIA NIM.
	 */
	public function generate_page( $request ) {
		$prompt = $request->get_param( 'prompt' );
		if ( $prompt === null || $prompt === '' ) {
			$body  = $request->get_json_params() ?: array();
			$prompt = isset( $body['prompt'] ) && is_string( $body['prompt'] ) ? $body['prompt'] : '';
		}
		if ( $prompt === '' ) {
			return new WP_Error( 'missing_prompt', __( 'Prompt is required.', 'bridge-builder' ), array( 'status' => 400 ) );
		}
		$result = Bridge_Builder_AI_Generator::generate_tree( $prompt );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'tree' => $result ) );
	}

	/**
	 * GET /ai-status – Whether AI generation is available (API key set for selected provider). Does not expose the key.
	 */
	public function get_ai_status( $request ) {
		$provider = Bridge_Builder_AI_Generator::get_provider();
		$key      = Bridge_Builder_AI_Generator::get_api_key( $provider );
		$available = ! empty( $key ) && is_string( $key );
		if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_CUSTOM ) {
			$url = get_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_URL, '' );
			$available = $available && ! empty( $url );
		}
		return rest_ensure_response( array( 'available' => $available ) );
	}

	/**
	 * POST /generate-section – Generate a single section/block for "Edit with AI".
	 */
	public function generate_section( $request ) {
		$body = $request->get_json_params() ?: array();
		$prompt = isset( $body['prompt'] ) && is_string( $body['prompt'] ) ? $body['prompt'] : $request->get_param( 'prompt' );
		if ( empty( $prompt ) ) {
			return new WP_Error( 'missing_prompt', __( 'Prompt is required.', 'bridge-builder' ), array( 'status' => 400 ) );
		}
		$context_json = isset( $body['contextJson'] ) && is_string( $body['contextJson'] ) ? $body['contextJson'] : '';
		$result = Bridge_Builder_AI_Generator::generate_section( $prompt, $context_json ? $context_json : null );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'node' => $result ) );
	}

	/**
	 * Allowed post types for builder save/load.
	 */
	private function allowed_post_types() {
		return array( 'post', 'page', 'bb_section', 'bb_header', 'bb_footer', 'bb_popup', 'bb_template' );
	}

	public function can_edit_items( $request ) {
		return current_user_can( 'edit_posts' );
	}

	public function can_manage_options( $request ) {
		$method = strtoupper( $request->get_method() );
		if ( 'GET' === $method ) {
			return current_user_can( 'edit_posts' );
		}
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Permission: manage global design systems (writes only).
	 *
	 * Reads remain available to editors; writes require theme-level capability.
	 */
	public function can_manage_design_systems( $request ) {
		return current_user_can( 'edit_theme_options' );
	}

	public function get_items( $request ) {
		$type = $request['type'];
		$posts = get_posts( array(
			'post_type'      => $type,
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		$items = array();
		foreach ( $posts as $p ) {
			$items[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'date'  => get_the_date( '', $p ),
			);
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	public function get_options( $request ) {
		return rest_ensure_response( array(
			'activeHeaderId' => (int) get_option( 'bridge_builder_active_header_id', 0 ),
			'activeFooterId' => (int) get_option( 'bridge_builder_active_footer_id', 0 ),
		) );
	}

	public function save_options( $request ) {
		$body = $request->get_json_params() ?: array();
		if ( isset( $body['activeHeaderId'] ) ) {
			update_option( 'bridge_builder_active_header_id', absint( $body['activeHeaderId'] ) );
		}
		if ( isset( $body['activeFooterId'] ) ) {
			update_option( 'bridge_builder_active_footer_id', absint( $body['activeFooterId'] ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * GET /design-systems – Return the global library of named design systems.
	 */
	public function get_design_systems( $request ) {
		$lib = get_option( 'bridge_builder_design_systems', array() );
		if ( ! is_array( $lib ) ) {
			$lib = array();
		}
		return rest_ensure_response( array( 'designSystems' => $lib ) );
	}

	/**
	 * POST /design-systems – Save the global library of named design systems.
	 */
	public function save_design_systems( $request ) {
		$body = $request->get_json_params();
		if ( ! isset( $body['designSystems'] ) || ! is_array( $body['designSystems'] ) ) {
			return new WP_Error( 'invalid_data', 'designSystems must be an array.', array( 'status' => 400 ) );
		}
		update_option( 'bridge_builder_design_systems', $body['designSystems'] );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** Option key for the site-wide global design system (JSON string). */
	const OPTION_GLOBAL_DESIGN_SYSTEM = 'bridge_builder_global_design_system';

	/**
	 * GET /global-design-system – Return the site-wide design system (applies to all builder pages).
	 */
	public function get_global_design_system( $request ) {
		$raw = get_option( self::OPTION_GLOBAL_DESIGN_SYSTEM, '' );
		$design_system = null;
		if ( ! empty( $raw ) && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$design_system = $decoded;
			}
		}
		return rest_ensure_response( array( 'designSystem' => $design_system ) );
	}

	/**
	 * POST /global-design-system – Save the site-wide design system.
	 */
	public function save_global_design_system( $request ) {
		$body = $request->get_json_params();
		$design_system = isset( $body['designSystem'] ) ? $body['designSystem'] : null;
		if ( $design_system !== null && ! is_array( $design_system ) ) {
			return new WP_Error( 'invalid_data', 'designSystem must be an object.', array( 'status' => 400 ) );
		}
		update_option( self::OPTION_GLOBAL_DESIGN_SYSTEM, $design_system !== null ? wp_json_encode( $design_system ) : '' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Permission check: user can edit the specific post.
	 */
	public function can_edit( $request ) {
		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		// Fallback – should not normally be hit, but keeps things safe.
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /pages/{id} – Load the component tree JSON for a post.
	 * Query param draft=1 returns _builder_json_draft when present.
	 */
	public function get_page_data( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( ! in_array( $post->post_type, $this->allowed_post_types(), true ) ) {
			return new WP_Error( 'invalid_type', 'Post type not supported.', array( 'status' => 400 ) );
		}

		$use_draft = isset( $request['draft'] ) && ( $request['draft'] === '1' || $request['draft'] === true );
		$json      = $use_draft ? get_post_meta( $post_id, '_builder_json_draft', true ) : get_post_meta( $post_id, '_builder_json', true );
		if ( $use_draft && empty( $json ) ) {
			$json = get_post_meta( $post_id, '_builder_json', true );
		}

		$base_response = array( 'post_type' => $post->post_type, 'title' => $post->post_title );

		$design_system_raw = Bridge_Builder_Asset_Manager::get_resolved_design_system_json( $post_id );
		$design_system     = null;
		if ( ! empty( $design_system_raw ) && is_string( $design_system_raw ) ) {
			$decoded = json_decode( $design_system_raw, true );
			if ( is_array( $decoded ) ) {
				$design_system = $decoded;
			}
		}
		$base_response['designSystem'] = $design_system;

		if ( empty( $json ) ) {
			$base_response['tree'] = array(
				'id'       => wp_generate_uuid4(),
				'type'     => 'Page',
				'props'    => new stdClass(),
				'styles'   => new stdClass(),
				'children' => array(),
			);
			return rest_ensure_response( $base_response );
		}

		$tree = json_decode( $json, true );

		if ( null === $tree ) {
			$base_response['tree'] = array(
				'id'       => wp_generate_uuid4(),
				'type'     => 'Page',
				'props'    => array(),
				'styles'   => array(),
				'children' => array(),
			);
			return rest_ensure_response( $base_response );
		}

		// Normalize: support legacy format (root = list of sections) and ensure children is always an array.
		if ( ! is_array( $tree ) ) {
			$tree = array( 'id' => wp_generate_uuid4(), 'type' => 'Page', 'props' => array(), 'styles' => array(), 'children' => array() );
		} elseif ( isset( $tree[0] ) ) {
			// Legacy format: root is a list of section nodes.
			$tree = array(
				'id'       => wp_generate_uuid4(),
				'type'     => 'Page',
				'props'    => array(),
				'styles'   => array(),
				'children' => $tree,
			);
		} else {
			if ( ! isset( $tree['children'] ) || ! is_array( $tree['children'] ) ) {
				$tree['children'] = array();
			}
			if ( empty( $tree['id'] ) ) {
				$tree['id'] = wp_generate_uuid4();
			}
			if ( empty( $tree['type'] ) ) {
				$tree['type'] = 'Page';
			}
		}

		$response = array( 'tree' => $tree, 'post_type' => $post->post_type, 'title' => $post->post_title, 'designSystem' => $design_system );
		return rest_ensure_response( $response );
	}

	/**
	 * POST /pages/{id} – Save the component tree JSON and/or design system for a post.
	 * Body param draft=true saves to _builder_json_draft only (no CSS/revision).
	 * Body param designSystem= {...} persists _builder_design_system. Can be sent with or without tree.
	 */
	public function save_page_data( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( ! in_array( $post->post_type, $this->allowed_post_types(), true ) ) {
			return new WP_Error( 'invalid_type', 'Post type not supported.', array( 'status' => 400 ) );
		}

		$body          = $request->get_json_params();
		$tree          = isset( $body['tree'] ) ? $body['tree'] : null;
		$design_system = isset( $body['designSystem'] ) ? $body['designSystem'] : null;

		if ( $design_system !== null && ! is_array( $design_system ) ) {
			return new WP_Error( 'invalid_data', 'designSystem must be an object.', array( 'status' => 400 ) );
		}

		if ( $design_system !== null ) {
			update_post_meta( $post_id, '_builder_design_system', wp_json_encode( $design_system ) );
		}

		if ( ! $tree ) {
			return rest_ensure_response( array(
				'success' => true,
				'message' => $design_system !== null ? __( 'Design system saved.', 'bridge-builder' ) : __( 'No data to save.', 'bridge-builder' ),
			) );
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$tree = $this->strip_executable_custom_code( $tree );
		}

		$as_draft = ! empty( $body['draft'] );
		$json     = wp_json_encode( $tree );

		if ( $as_draft ) {
			update_post_meta( $post_id, '_builder_json_draft', $json );
			return rest_ensure_response( array( 'success' => true, 'message' => 'Draft saved.', 'draft' => true ) );
		}

		update_post_meta( $post_id, '_builder_json', $json );
		delete_post_meta( $post_id, '_builder_json_draft' );

		$css_generator  = new Bridge_Builder_CSS_Generator();
		$css            = $css_generator->generate( $tree );
		$css_structural = $css_generator->generate( $tree, true );
		update_post_meta( $post_id, '_builder_css', $css );
		update_post_meta( $post_id, '_builder_css_structural', $css_structural );

		$this->save_revision( $post_id, $json );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Page saved successfully.', 'bridge-builder' ),
		) );
	}

	/**
	 * Strip executable custom code (customJs, Code Embed JS/allowScript) for users without unfiltered_html.
	 *
	 * @param array $node Root tree node.
	 * @return array Sanitized tree.
	 */
	private function strip_executable_custom_code( $node ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}

		if ( isset( $node['props'] ) && is_array( $node['props'] ) ) {
			// Per-node advanced custom JS.
			if ( isset( $node['props']['customJs'] ) ) {
				$node['props']['customJs'] = '';
			}
			// CodeEmbed: always strip JS and disable allowScript.
			if ( isset( $node['type'] ) && $node['type'] === 'CodeEmbed' ) {
				if ( isset( $node['props']['js'] ) ) {
					$node['props']['js'] = '';
				}
				if ( isset( $node['props']['allowScript'] ) ) {
					$node['props']['allowScript'] = false;
				}
			}
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $i => $child ) {
				$node['children'][ $i ] = $this->strip_executable_custom_code( $child );
			}
		}

		return $node;
	}

	/**
	 * GET /page-by-url – Look up a post by its URL and return the tree.
	 * Used by the SPA navigation in the runtime.
	 */
	public function get_page_by_url( $request ) {
		$url     = sanitize_text_field( $request['url'] );
		$post_id = url_to_postid( home_url( $url ) );

		if ( ! $post_id ) {
			return new WP_Error( 'not_found', 'Page not found.', array( 'status' => 404 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) ) {
			return new WP_Error( 'not_found', 'Page not found.', array( 'status' => 404 ) );
		}

		$json = get_post_meta( $post_id, '_builder_json', true );

		if ( empty( $json ) ) {
			return new WP_Error( 'no_builder_data', 'No builder data for this page.', array( 'status' => 404 ) );
		}

		$tree = json_decode( $json, true );
		if ( ! is_array( $tree ) ) {
			$tree = array( 'id' => 'bb-page-root', 'type' => 'Page', 'props' => array(), 'styles' => array(), 'children' => array() );
		} else {
			$tree = $this->normalize_tree( $tree );
			$tree = $this->enrich_tree_for_frontend( $tree, $post_id );
		}

		$response = array( 'tree' => $tree, 'postId' => $post_id );

		// User state for Conditional Display (Phase 7).
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$response['user'] = array(
				'loggedIn' => true,
				'roles'    => is_array( $user->roles ) ? array_values( $user->roles ) : array(),
			);
		} else {
			$response['user'] = array( 'loggedIn' => false, 'roles' => array() );
		}

		// Include css + revision for SPA navigation so runtime can apply styles before rendering (single-source pipeline).
		if ( get_option( 'bridge_builder_frontend_styling', 'theme' ) === 'builder' ) {
			$css = get_post_meta( $post_id, '_builder_css', true );
			if ( empty( $css ) && ! empty( $json ) ) {
				$gen = new Bridge_Builder_CSS_Generator();
				$css = $gen->generate( json_decode( $json, true ) );
				update_post_meta( $post_id, '_builder_css', $css );
			}
			if ( ! empty( $css ) ) {
				$design_system_css = Bridge_Builder_Asset_Manager::design_system_to_css( $post_id );
				$full_css         = ( $design_system_css !== '' ? $design_system_css : '' ) . $css;
				$response['css']      = Bridge_Builder_Asset_Manager::scope_builder_css( $full_css );
				$response['revision'] = md5( $full_css );
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Normalize tree structure (legacy format, children).
	 */
	private function normalize_tree( $tree ) {
		if ( ! is_array( $tree ) ) {
			return array( 'id' => wp_generate_uuid4(), 'type' => 'Page', 'props' => array(), 'styles' => array(), 'children' => array() );
		}
		if ( isset( $tree[0] ) ) {
			return array( 'id' => wp_generate_uuid4(), 'type' => 'Page', 'props' => array(), 'styles' => array(), 'children' => $tree );
		}
		if ( ! isset( $tree['children'] ) || ! is_array( $tree['children'] ) ) {
			$tree['children'] = array();
		}
		return $tree;
	}

	/**
	 * Enrich tree for frontend (e.g. Posts Grid gets posts from WP_Query, Query Loop expanded).
	 * Public so Bridge_Builder_Asset_Manager can call it for inline page data.
	 */
	public function enrich_tree_for_frontend( $tree, $post_id ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		if ( ( isset( $tree['type'] ) && $tree['type'] === 'PostsGrid' ) || ( isset( $tree['type'] ) && $tree['type'] === 'PostsCarousel' ) ) {
			$props = isset( $tree['props'] ) && is_array( $tree['props'] ) ? $tree['props'] : array();
			$post_type = isset( $props['postType'] ) && is_string( $props['postType'] ) ? $props['postType'] : 'post';
			$ppp = isset( $props['postsPerPage'] ) ? absint( $props['postsPerPage'] ) : 6;
			$posts = $this->get_posts_for_grid( $post_type, $ppp );
			$tree['props'] = isset( $tree['props'] ) ? $tree['props'] : array();
			$tree['props']['posts'] = $posts;
		}
		if ( isset( $tree['type'] ) && $tree['type'] === 'ProductGrid' ) {
			$props = isset( $tree['props'] ) && is_array( $tree['props'] ) ? $tree['props'] : array();
			$tree['props'] = isset( $tree['props'] ) ? $tree['props'] : array();
			$tree['props']['products'] = function_exists( 'bridge_builder_get_product_grid_products' ) ? bridge_builder_get_product_grid_products( $props ) : array();
		}
		if ( isset( $tree['type'] ) && $tree['type'] === 'CartBlock' && function_exists( 'bridge_builder_woocommerce_active' ) && bridge_builder_woocommerce_active() ) {
			$tree['props'] = isset( $tree['props'] ) ? $tree['props'] : array();
			$tree['props']['html'] = do_shortcode( '[woocommerce_cart]' );
		}
		if ( isset( $tree['type'] ) && $tree['type'] === 'CheckoutBlock' && function_exists( 'bridge_builder_woocommerce_active' ) && bridge_builder_woocommerce_active() ) {
			$tree['props'] = isset( $tree['props'] ) ? $tree['props'] : array();
			$tree['props']['html'] = do_shortcode( '[woocommerce_checkout]' );
		}
		if ( isset( $tree['type'] ) && $tree['type'] === 'MiniCart' && function_exists( 'bridge_builder_get_mini_cart_html' ) ) {
			$tree['props'] = isset( $tree['props'] ) ? $tree['props'] : array();
			$tree['props']['html'] = bridge_builder_get_mini_cart_html();
		}
		if ( isset( $tree['type'] ) && $tree['type'] === 'QueryLoop' && ! empty( $tree['children'] ) && is_array( $tree['children'] ) ) {
			$props   = isset( $tree['props'] ) && is_array( $tree['props'] ) ? $tree['props'] : array();
			$args    = $this->get_query_loop_args_for_enrich( $props );
			$query   = new WP_Query( $args );
			$clones  = array();
			$template = $tree['children'][0];
			while ( $query->have_posts() ) {
				$query->the_post();
				$reading = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
				/* translators: %d: estimated reading time in minutes. */
				$reading = sprintf( _n( '%d min read', '%d min read', $reading, 'bridge-builder' ), $reading );
				$post_data = array(
					'title'       => get_the_title(),
					'excerpt'     => has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 55 ),
					'image'       => get_the_post_thumbnail_url( null, 'medium' ) ?: '',
					'alt'         => bridge_builder_get_post_thumbnail_alt( get_post_thumbnail_id() ) ?: get_the_title(),
					'url'         => get_permalink(),
					'date'        => get_the_date(),
					'dateFormat'  => get_the_date( 'c' ),
					'author'      => get_the_author(),
					'authorUrl'   => get_author_posts_url( get_the_author_meta( 'ID' ) ),
					'readingTime' => $reading,
					'postId'      => get_the_ID(),
					'post'        => array(
						'title'       => get_the_title(),
						'excerpt'     => has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 55 ),
						'image'       => get_the_post_thumbnail_url( null, 'medium' ) ?: '',
						'url'         => get_permalink(),
						'date'        => get_the_date(),
						'readingTime' => $reading,
						'author'      => get_the_author(),
						'authorUrl'   => get_author_posts_url( get_the_author_meta( 'ID' ) ),
					),
					'authorContext' => array(
						'name' => get_the_author(),
						'url'  => get_author_posts_url( get_the_author_meta( 'ID' ) ),
					),
				);
				$clone = $this->clone_tree_with_new_ids( $template );
				$this->inject_post_data_into_tree( $clone, $post_data );
				$clones[] = $clone;
			}
			wp_reset_postdata();
			$tree['children'] = $clones;
		}
		if ( ! empty( $tree['children'] ) && is_array( $tree['children'] ) ) {
			foreach ( $tree['children'] as $i => $child ) {
				$tree['children'][ $i ] = $this->enrich_tree_for_frontend( $child, $post_id );
			}
		}
		return $tree;
	}

	/**
	 * WP_Query args for Query Loop (enrichment).
	 */
	private function get_query_loop_args_for_enrich( $props ) {
		$post_type = isset( $props['postType'] ) && is_string( $props['postType'] ) ? $props['postType'] : 'post';
		$ppp       = isset( $props['postsPerPage'] ) ? absint( $props['postsPerPage'] ) : 6;
		$ppp       = min( $ppp, self::POSTS_GRID_MAX );
		$orderby   = isset( $props['orderBy'] ) && is_string( $props['orderBy'] ) ? $props['orderBy'] : 'date';
		$order     = isset( $props['order'] ) && in_array( $props['order'], array( 'ASC', 'DESC' ), true ) ? $props['order'] : 'DESC';
		$args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => $ppp,
			'post_status'            => 'publish',
			'orderby'                => $orderby,
			'order'                  => $order,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( ! empty( $props['categorySlug'] ) && is_string( $props['categorySlug'] ) ) {
			$args['category_name'] = sanitize_text_field( $props['categorySlug'] );
		}
		if ( isset( $props['authorId'] ) && is_numeric( $props['authorId'] ) && (int) $props['authorId'] > 0 ) {
			$args['author'] = (int) $props['authorId'];
		}
		return $args;
	}

	/**
	 * Deep-clone a tree node and assign new IDs.
	 */
	private function clone_tree_with_new_ids( $node ) {
		$new = $node;
		$new['id'] = function_exists( 'wp_generate_uuid' ) ? wp_generate_uuid() : uniqid( 'bb-', true );
		if ( ! empty( $new['children'] ) && is_array( $new['children'] ) ) {
			$new['children'] = array_map( array( $this, 'clone_tree_with_new_ids' ), $new['children'] );
		}
		return $new;
	}

	/**
	 * Build context array for token replacement (post, author, postId).
	 *
	 * @param array $post_data Post data from Query Loop enrichment.
	 * @return array Context for Bridge_Builder_Renderer::replace_tokens_with_context.
	 */
	private function token_context_from_post_data( $post_data ) {
		return array(
			'postId' => isset( $post_data['postId'] ) ? (int) $post_data['postId'] : 0,
			'post'   => isset( $post_data['post'] ) && is_array( $post_data['post'] ) ? $post_data['post'] : array(),
			'author' => isset( $post_data['authorContext'] ) && is_array( $post_data['authorContext'] ) ? $post_data['authorContext'] : array(),
		);
	}

	/**
	 * Inject post data into a tree (set props on PostTitle, PostExcerpt, etc.; replace tokens in Text, Heading, Image, Button).
	 */
	private function inject_post_data_into_tree( &$tree, $post_data ) {
		if ( ! is_array( $tree ) ) {
			return;
		}
		$type = isset( $tree['type'] ) ? $tree['type'] : '';
		$ctx  = $this->token_context_from_post_data( $post_data );

		if ( $type === 'PostTitle' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['content'] = $post_data['title'];
		} elseif ( $type === 'PostExcerpt' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['content'] = $post_data['excerpt'];
		} elseif ( $type === 'PostFeaturedImage' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['src'] = $post_data['image'];
			$tree['props']['alt'] = $post_data['alt'];
			$tree['props']['url'] = $post_data['url'];
		} elseif ( $type === 'PostDate' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['content'] = $post_data['date'];
			$tree['props']['format']  = $post_data['dateFormat'];
		} elseif ( $type === 'PostAuthor' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['content'] = $post_data['author'];
			$tree['props']['url']     = $post_data['authorUrl'];
		} elseif ( $type === 'ReadingTime' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			$tree['props']['content'] = $post_data['readingTime'];
		} elseif ( $type === 'Text' || $type === 'Heading' ) {
			if ( isset( $tree['props']['content'] ) && is_string( $tree['props']['content'] ) ) {
				$tree['props']['content'] = Bridge_Builder_Renderer::replace_tokens_with_context( $tree['props']['content'], $ctx );
			}
		} elseif ( $type === 'Image' ) {
			if ( isset( $tree['props']['src'] ) && is_string( $tree['props']['src'] ) ) {
				$tree['props']['src'] = Bridge_Builder_Renderer::replace_tokens_with_context( $tree['props']['src'], $ctx );
			}
			if ( isset( $tree['props']['alt'] ) && is_string( $tree['props']['alt'] ) ) {
				$tree['props']['alt'] = Bridge_Builder_Renderer::replace_tokens_with_context( $tree['props']['alt'], $ctx );
			}
		} elseif ( $type === 'Button' ) {
			if ( ! isset( $tree['props'] ) || ! is_array( $tree['props'] ) ) {
				$tree['props'] = array();
			}
			if ( isset( $tree['props']['content'] ) && is_string( $tree['props']['content'] ) ) {
				$tree['props']['content'] = Bridge_Builder_Renderer::replace_tokens_with_context( $tree['props']['content'], $ctx );
			}
			$href = isset( $tree['props']['href'] ) && is_string( $tree['props']['href'] ) ? $tree['props']['href'] : '#';
			$href = Bridge_Builder_Renderer::replace_tokens_with_context( $href, $ctx );
			$tree['props']['href'] = ( '' === $href || '#' === $href ) ? $post_data['url'] : $href;
		}
		if ( ! empty( $tree['children'] ) && is_array( $tree['children'] ) ) {
			foreach ( $tree['children'] as $i => $child ) {
				$this->inject_post_data_into_tree( $tree['children'][ $i ], $post_data );
			}
		}
	}

	/**
	 * Max posts to load per Posts Grid (prevents memory exhaustion).
	 */
	const POSTS_GRID_MAX = 24;

	/**
	 * Get posts for Posts Grid (WP_Query).
	 * Uses fields=>ids and minimal fetches to avoid loading full post_content.
	 */
	private function get_posts_for_grid( $post_type, $posts_per_page ) {
		$ppp = $posts_per_page ? absint( $posts_per_page ) : 6;
		$ppp = min( $ppp, self::POSTS_GRID_MAX );

		$args = array(
			'post_type'               => $post_type ? $post_type : 'post',
			'posts_per_page'          => $ppp,
			'post_status'             => 'publish',
			'orderby'                 => 'date',
			'order'                   => 'DESC',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'fields'                  => 'ids',
		);
		$query = new WP_Query( $args );
		$posts = array();
		foreach ( $query->posts as $post_id ) {
			$excerpt = get_post_field( 'post_excerpt', $post_id );
			$excerpt = trim( (string) $excerpt );
			if ( $excerpt === '' ) {
				$excerpt = wp_trim_words( get_post_field( 'post_content', $post_id ), 20 );
			} else {
				$excerpt = wp_trim_words( $excerpt, 20 );
			}
			$date_raw = get_post_field( 'post_date', $post_id );
			$content  = get_post_field( 'post_content', $post_id );
			$reading  = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( (string) $content ) ) / 200 ) ) . ' min read';
			$posts[]  = array(
				'title'       => get_the_title( $post_id ),
				'excerpt'     => $excerpt,
				'image'       => get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '',
				'url'         => get_permalink( $post_id ),
				'date'        => $date_raw ? date_i18n( get_option( 'date_format' ), strtotime( $date_raw ) ) : '',
				'author'      => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) ?: '',
				'authorUrl'   => get_author_posts_url( (int) get_post_field( 'post_author', $post_id ) ) ?: '',
				'readingTime' => $reading,
			);
		}
		return $posts;
	}

	/**
	 * POST /contact-form – Handle contact form submission.
	 */
	public function submit_contact_form( $request ) {
		// Require a valid REST nonce even though the route is public.
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', 'Invalid security token.', array( 'status' => 403 ) );
		}

		$body    = $request->get_json_params();
		// Simple honeypot field: if filled, silently accept but drop.
		$honeypot = isset( $body['honeypot'] ) ? trim( (string) $body['honeypot'] ) : '';
		if ( $honeypot !== '' ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		// Basic per-IP rate limiting via transient (1 submission per 60 seconds).
		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_key = $ip_raw !== '' ? $ip_raw : 'unknown';
		$rate_key = 'bb_cf_rate_' . md5( $ip_key );
		if ( get_transient( $rate_key ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many submissions. Please try again in a moment.', 'bridge-builder' ), array( 'status' => 429 ) );
		}
		set_transient( $rate_key, 1, 60 );

		$page_id = isset( $body['pageId'] ) ? absint( $body['pageId'] ) : 0;
		$node_id = isset( $body['nodeId'] ) ? sanitize_text_field( $body['nodeId'] ) : '';
		$fields = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : array();

		if ( ! $page_id || ! $node_id ) {
			return new WP_Error( 'missing_data', 'Page and form node required.', array( 'status' => 400 ) );
		}

		$json = get_post_meta( $page_id, '_builder_json', true );
		if ( empty( $json ) ) {
			return new WP_Error( 'not_found', 'Page not found.', array( 'status' => 404 ) );
		}

		$tree = json_decode( $json, true );
		$node = $this->find_node_by_id( $tree, $node_id );
		if ( ! $node || ( isset( $node['type'] ) && $node['type'] !== 'ContactForm' ) ) {
			return new WP_Error( 'not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$props = isset( $node['props'] ) && is_array( $node['props'] ) ? $node['props'] : array();
		$email = isset( $props['email'] ) && is_string( $props['email'] ) ? sanitize_email( $props['email'] ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Recipient email not configured.', array( 'status' => 400 ) );
		}

		$body_text = '';
		foreach ( $fields as $label => $value ) {
			$label_safe = sanitize_text_field( $label );
			if ( is_array( $value ) ) {
				$value_safe = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} else {
				$value_safe = sanitize_textarea_field( is_string( $value ) ? $value : (string) $value );
			}
			$body_text .= $label_safe . ': ' . $value_safe . "\n";
		}

		$subject = sprintf( '[%s] Contact form', get_bloginfo( 'name' ) );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent   = wp_mail( $email, $subject, $body_text, $headers );

		return rest_ensure_response( array( 'success' => $sent ) );
	}

	/**
	 * Find a node in the tree by ID.
	 */
	private function find_node_by_id( $node, $id ) {
		if ( ! is_array( $node ) ) {
			return null;
		}
		if ( isset( $node['id'] ) && $node['id'] === $id ) {
			return $node;
		}
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$found = $this->find_node_by_id( $child, $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Save a revision of the builder JSON (max 10 revisions).
	 */
	private function save_revision( $post_id, $json ) {
		$revisions = get_post_meta( $post_id, '_builder_revisions', true );
		if ( ! is_array( $revisions ) ) {
			$revisions = array();
		}

		array_unshift( $revisions, array(
			'timestamp' => time(),
			'json'      => $json,
		) );

		// Keep only the latest 10 revisions
		$revisions = array_slice( $revisions, 0, 10 );
		update_post_meta( $post_id, '_builder_revisions', $revisions );
	}

	/**
	 * GET /pages/{id}/revisions – List saved revisions (timestamps only).
	 */
	public function get_revisions( $request ) {
		$post_id    = absint( $request['id'] );
		$post      = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		$revisions = get_post_meta( $post_id, '_builder_revisions', true );
		if ( ! is_array( $revisions ) ) {
			$revisions = array();
		}

		$list = array();
		foreach ( $revisions as $i => $rev ) {
			$list[] = array(
				'index'     => $i,
				'timestamp' => isset( $rev['timestamp'] ) ? (int) $rev['timestamp'] : 0,
				'date'      => isset( $rev['timestamp'] ) ? wp_date( 'M j, Y \a\t g:i a', (int) $rev['timestamp'] ) : '',
			);
		}

		return rest_ensure_response( array( 'revisions' => $list ) );
	}

	/**
	 * GET /pages/{id}/revisions/{index} – Get the tree for one revision (0 = most recent).
	 */
	public function get_revision( $request ) {
		$post_id = absint( $request['id'] );
		$index   = (int) $request['index'];
		$post   = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		$revisions = get_post_meta( $post_id, '_builder_revisions', true );
		if ( ! is_array( $revisions ) || ! isset( $revisions[ $index ] ) ) {
			return new WP_Error( 'not_found', 'Revision not found.', array( 'status' => 404 ) );
		}

		$json = isset( $revisions[ $index ]['json'] ) ? $revisions[ $index ]['json'] : '';
		if ( empty( $json ) ) {
			return new WP_Error( 'invalid_data', 'Revision has no data.', array( 'status' => 404 ) );
		}

		$tree = json_decode( $json, true );
		return rest_ensure_response( array( 'tree' => $tree ) );
	}
}
