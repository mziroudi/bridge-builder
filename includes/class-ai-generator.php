<?php
/**
 * Bridge Builder AI Page Generator
 *
 * Supports multiple AI providers: NVIDIA NIM, OpenAI, Claude, Gemini, or custom OpenAI-compatible endpoint.
 * API keys are read from WordPress options; never exposed to the frontend.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_AI_Generator {

	const OPTION_PROVIDER       = 'bridge_builder_ai_provider';
	const OPTION_NVIDIA_KEY     = 'bridge_builder_nvidia_api_key';
	const OPTION_OPENAI_KEY     = 'bridge_builder_openai_api_key';
	const OPTION_CLAUDE_KEY     = 'bridge_builder_claude_api_key';
	const OPTION_GEMINI_KEY     = 'bridge_builder_gemini_api_key';
	const OPTION_CUSTOM_URL     = 'bridge_builder_custom_ai_url';
	const OPTION_CUSTOM_KEY     = 'bridge_builder_custom_ai_key';
	const OPTION_CUSTOM_MODEL   = 'bridge_builder_custom_ai_model';
	const REQUEST_TIMEOUT       = 60;

	const PROVIDER_NVIDIA  = 'nvidia';
	const PROVIDER_OPENAI  = 'openai';
	const PROVIDER_CLAUDE  = 'claude';
	const PROVIDER_GEMINI  = 'gemini';
	const PROVIDER_CUSTOM  = 'custom';

	/**
	 * Provider config: url, model, auth type.
	 *
	 * @return array<string, array{url: string, model: string, auth: string}>
	 */
	private static function provider_config() {
		return array(
			self::PROVIDER_NVIDIA => array(
				'url'   => 'https://integrate.api.nvidia.com/v1/chat/completions',
				'model' => 'meta/llama-3.1-70b-instruct',
				'auth'  => 'bearer',
			),
			self::PROVIDER_OPENAI => array(
				'url'   => 'https://api.openai.com/v1/chat/completions',
				'model' => 'gpt-4o',
				'auth'  => 'bearer',
			),
			self::PROVIDER_CLAUDE => array(
				'url'   => 'https://api.anthropic.com/v1/messages',
				'model' => 'claude-3-5-sonnet-20241022',
				'auth'  => 'anthropic',
			),
			self::PROVIDER_GEMINI => array(
				'url'   => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
				'model' => 'gemini-1.5-flash',
				'auth'  => 'query',
			),
			self::PROVIDER_CUSTOM => array(
				'url'   => '', // From option.
				'model' => '', // From option or default.
				'auth'  => 'bearer',
			),
		);
	}

	/**
	 * Get the API key for the given provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string Empty if not configured.
	 */
	public static function get_api_key( $provider ) {
		switch ( $provider ) {
			case self::PROVIDER_NVIDIA:
				return get_option( self::OPTION_NVIDIA_KEY, '' );
			case self::PROVIDER_OPENAI:
				return get_option( self::OPTION_OPENAI_KEY, '' );
			case self::PROVIDER_CLAUDE:
				return get_option( self::OPTION_CLAUDE_KEY, '' );
			case self::PROVIDER_GEMINI:
				return get_option( self::OPTION_GEMINI_KEY, '' );
			case self::PROVIDER_CUSTOM:
				return get_option( self::OPTION_CUSTOM_KEY, '' );
			default:
				return get_option( self::OPTION_NVIDIA_KEY, '' );
		}
	}

	/**
	 * Get the selected provider (default: nvidia).
	 *
	 * @return string
	 */
	public static function get_provider() {
		$p = get_option( self::OPTION_PROVIDER, self::PROVIDER_NVIDIA );
		$allowed = array( self::PROVIDER_NVIDIA, self::PROVIDER_OPENAI, self::PROVIDER_CLAUDE, self::PROVIDER_GEMINI, self::PROVIDER_CUSTOM );
		return in_array( $p, $allowed, true ) ? $p : self::PROVIDER_NVIDIA;
	}

	/**
	 * Allowed component types (must match frontend registry).
	 */
	private static function allowed_types() {
		return array(
			'Page',
			'Section',
			'Row',
			'Column',
			'Container',
			'Grid',
			'Heading',
			'Text',
			'Image',
			'Button',
			'Spacer',
			'Divider',
			'Icon',
			'IconBox',
			'Video',
			'Testimonial',
			'Counter',
			'CTA',
			'SocialIcons',
			'Gallery',
			'Carousel',
			'PricingTable',
			'Tabs',
			'Accordion',
			'Toggle',
			'List',
			'Blockquote',
			'ProgressBar',
			'VideoBox',
			'ImageBox',
			'ButtonGroup',
			'Countdown',
			'Menu',
			'Shortcode',
			'CustomHtml',
			'TeamMember',
			'GoogleMaps',
			'PostsGrid',
			'ProductGrid',
			'ProductTitle',
			'ProductPrice',
			'ProductImage',
			'ProductAddToCart',
			'ProductMeta',
			'ProductTabs',
			'RelatedProducts',
			'CartBlock',
			'CheckoutBlock',
			'MiniCart',
			'ContactForm',
			'QueryLoop',
			'PostTitle',
			'PostExcerpt',
			'PostFeaturedImage',
			'PostDate',
			'PostAuthor',
			'ReadingTime',
			'ArchiveTitle',
			'ArchiveDescription',
			'Pagination',
			'NoResults',
		);
	}

	/**
	 * Build system prompt describing the Bridge Builder JSON schema with strong styling and responsive rules.
	 */
	private static function get_system_prompt() {
		$types = implode( ', ', self::allowed_types() );
		return
			'You are a code generator for a WordPress page builder. Your output must be exactly one valid JSON object: a page tree for "Bridge Builder". Use full styling and proper responsive behavior.' . "\n\n" .
			'Schema (every node):' . "\n" .
			'- id: string (unique placeholder; replaced server-side)' . "\n" .
			'- type: string (required). Allowed types: ' . $types . "\n" .
			'- props: object (camelCase: tagName, content, src, alt, href, target, level for Heading "h1".."h6")' . "\n" .
			'- styles: object with BOTH "desktop" AND "mobile" keys for layout and content nodes. Use camelCase CSS: padding, backgroundColor, color, fontSize, fontWeight, lineHeight, margin, maxWidth, width, gap, borderRadius, border, boxShadow, textAlign, display, flexDirection, alignItems, justifyContent.' . "\n" .
			'- children: array (required; [] for leaf nodes)' . "\n\n" .
			'CRITICAL - Styling and responsive:' . "\n" .
			'1. Every Section MUST have styles.desktop AND styles.mobile. Example: desktop padding "80px 24px", backgroundColor (e.g. "#0f172a" or "#f8fafc"), textAlign where needed; mobile padding "48px 16px" or "40px 16px", often smaller font sizes.' . "\n" .
			'2. Headings: always set desktop fontSize (e.g. "48px" h1, "36px" h2, "24px" h3), fontWeight "700" or "800", color; mobile fontSize smaller (e.g. "32px" h1, "28px" h2).' . "\n" .
			'3. Text: desktop fontSize "16px" or "18px", color (e.g. "#64748b"), lineHeight "1.6"; mobile fontSize "14px" or "16px".' . "\n" .
			'4. Buttons: padding "14px 28px", borderRadius "8px", backgroundColor and color, fontSize "16px"; mobile can use same or slightly smaller padding.' . "\n" .
			'5. Container: use maxWidth "1200px" or "960px", margin "0 auto", width "100%", padding for desktop and mobile.' . "\n" .
			'6. Grid/Row: set gap "24px" or "32px" in desktop; mobile often flexDirection "column" for Row or gridTemplateColumns "1fr" for Grid.' . "\n" .
			'7. Use distinct background colors between sections (e.g. alternating #ffffff and #f1f5f9) and consistent typography scale.' . "\n\n" .
			'Structure: Root type "Page", children = array of Section nodes. Section contains Row, Container, or Grid. Row contains Column. Column/Container/Grid contain Heading, Text, Image, Button, Spacer, etc.' . "\n\n" .
			'Output ONLY the raw JSON object. No markdown, no code fence, no explanation.';
	}

	/**
	 * System prompt for generating a single section/block (for "Edit with AI" on one section).
	 */
	private static function get_section_system_prompt() {
		$types = implode( ', ', self::allowed_types() );
		return
			'You are a code generator for a WordPress page builder. Your output must be exactly one valid JSON object: a SINGLE node (one Section, Row, Container, or Grid with its children). Not a full page.' . "\n\n" .
			'Schema (every node):' . "\n" .
			'- id: string (unique placeholder)' . "\n" .
			'- type: string. Allowed: ' . $types . '. For the root of your output use Section, Container, Row, or Grid.' . "\n" .
			'- props: object (camelCase)' . "\n" .
			'- styles: object with BOTH "desktop" AND "mobile" keys. Use padding, backgroundColor, color, fontSize, fontWeight, margin, maxWidth, gap, borderRadius, etc. Desktop and mobile must both be set for responsive layout.' . "\n" .
			'- children: array (required)' . "\n\n" .
			'Styling: Use full styling. Section: desktop padding "80px 24px", mobile "48px 16px"; Heading desktop fontSize "36px" or "24px", mobile smaller; Text fontSize "16px"/"14px"; Button padding "14px 28px", borderRadius "8px". Set backgroundColor, color, gap, maxWidth where appropriate.' . "\n\n" .
			'Output ONLY the raw JSON object. No markdown, no code fence, no explanation.';
	}

	/**
	 * Call the configured AI provider and return the raw text response.
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @param int    $max_tokens    Max tokens to generate.
	 * @return string|WP_Error Raw content or WP_Error.
	 */
	private static function call_provider( $system_prompt, $user_prompt, $max_tokens = 8192 ) {
		$provider = self::get_provider();
		$api_key  = self::get_api_key( $provider );
		if ( empty( $api_key ) || ! is_string( $api_key ) ) {
			$provider_labels = array(
				self::PROVIDER_NVIDIA => 'NVIDIA',
				self::PROVIDER_OPENAI => 'OpenAI',
				self::PROVIDER_CLAUDE => 'Claude',
				self::PROVIDER_GEMINI => 'Gemini',
				self::PROVIDER_CUSTOM => 'Custom',
			);
			$label = isset( $provider_labels[ $provider ] ) ? $provider_labels[ $provider ] : $provider;
			/* translators: %1$s: AI provider label, e.g. OpenAI. */
			return new WP_Error( 'missing_api_key', sprintf( __( '%1$s API key is not configured. Go to Bridge Builder → Settings, select %1$s, and add your API key.', 'bridge-builder' ), $label ), array( 'status' => 400 ) );
		}

		$config = self::provider_config()[ $provider ];
		$url    = $config['url'];
		$model  = $config['model'];

		if ( $provider === self::PROVIDER_CUSTOM ) {
			$url   = get_option( self::OPTION_CUSTOM_URL, '' );
			$model = get_option( self::OPTION_CUSTOM_MODEL, 'gpt-4o' );
			if ( empty( $url ) ) {
				return new WP_Error( 'missing_custom_url', __( 'Custom AI endpoint URL is not configured.', 'bridge-builder' ), array( 'status' => 400 ) );
			}
		}

		if ( $provider === self::PROVIDER_GEMINI ) {
			$url = add_query_arg( 'key', $api_key, $url );
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( $config['auth'] === 'bearer' ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		} elseif ( $config['auth'] === 'anthropic' ) {
			$headers['x-api-key']         = $api_key;
			$headers['anthropic-version'] = '2023-06-01';
		}

		$body = self::build_request_body( $provider, $system_prompt, $user_prompt, $model, $max_tokens );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ai_request_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		if ( $code !== 200 ) {
			/* translators: 1: HTTP status code, 2: truncated provider response. */
			return new WP_Error( 'ai_error', sprintf( __( 'AI service returned error %1$s: %2$s', 'bridge-builder' ), $code, substr( wp_strip_all_tags( $body_raw ), 0, 200 ) ), array( 'status' => 502 ) );
		}

		return self::parse_response( $provider, $body_raw );
	}

	/**
	 * Build request body for the given provider.
	 *
	 * @param string $provider      Provider slug.
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @param string $model         Model name.
	 * @param int    $max_tokens    Max tokens.
	 * @return array|WP_Error
	 */
	private static function build_request_body( $provider, $system_prompt, $user_prompt, $model, $max_tokens ) {
		if ( $provider === self::PROVIDER_CLAUDE ) {
			return array(
				'model'      => $model,
				'max_tokens' => $max_tokens,
				'system'     => $system_prompt,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $user_prompt ),
				),
			);
		}

		if ( $provider === self::PROVIDER_GEMINI ) {
			$combined = $system_prompt . "\n\n" . $user_prompt;
			return array(
				'contents'         => array(
					array(
						'parts' => array( array( 'text' => $combined ) ),
					),
				),
				'generationConfig' => array(
					'maxOutputTokens' => $max_tokens,
					'temperature'     => 0.25,
				),
			);
		}

		// OpenAI, NVIDIA, Custom (OpenAI-compatible).
		return array(
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => 0.25,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);
	}

	/**
	 * Parse provider response and extract text content.
	 *
	 * @param string $provider  Provider slug.
	 * @param string $body_raw  Raw response body.
	 * @return string|WP_Error
	 */
	private static function parse_response( $provider, $body_raw ) {
		$decoded = json_decode( $body_raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'invalid_ai_response', __( 'Invalid response from AI service.', 'bridge-builder' ), array( 'status' => 502 ) );
		}

		if ( $provider === self::PROVIDER_CLAUDE ) {
			$content = isset( $decoded['content'][0]['text'] ) ? $decoded['content'][0]['text'] : '';
		} elseif ( $provider === self::PROVIDER_GEMINI ) {
			$content = isset( $decoded['candidates'][0]['content']['parts'][0]['text'] ) ? $decoded['candidates'][0]['content']['parts'][0]['text'] : '';
		} else {
			$content = isset( $decoded['choices'][0]['message']['content'] ) ? $decoded['choices'][0]['message']['content'] : '';
		}

		if ( empty( $content ) || ! is_string( $content ) ) {
			return new WP_Error( 'invalid_ai_response', __( 'Invalid response from AI service.', 'bridge-builder' ), array( 'status' => 502 ) );
		}

		return trim( $content );
	}

	/**
	 * Generate a page tree from a user prompt using the configured AI provider.
	 *
	 * @param string $user_prompt User description of the page.
	 * @return array|WP_Error The normalized page tree (root type Page) or WP_Error on failure.
	 */
	public static function generate_tree( $user_prompt ) {
		$system = self::get_system_prompt();
		$result = self::call_provider( $system, $user_prompt, 8192 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$content = $result;
		// Strip markdown code block if present.
		if ( preg_match( '/^```(?:json)?\s*([\s\S]*?)```\s*$/s', $content, $m ) ) {
			$content = trim( $m[1] );
		}
		$tree = json_decode( $content, true );
		if ( ! is_array( $tree ) ) {
			return new WP_Error( 'invalid_json', __( 'AI did not return valid JSON.', 'bridge-builder' ), array( 'status' => 502 ) );
		}

		// If root is array of sections (legacy), wrap in Page
		if ( isset( $tree[0] ) && is_array( $tree[0] ) ) {
			$tree = array(
				'id'       => wp_generate_uuid4(),
				'type'     => 'Page',
				'props'    => array(),
				'styles'   => array(),
				'children' => $tree,
			);
		}

		if ( empty( $tree['type'] ) || $tree['type'] !== 'Page' ) {
			return new WP_Error( 'invalid_root', __( 'Generated tree must have root type "Page".', 'bridge-builder' ), array( 'status' => 502 ) );
		}

		$normalized = self::normalize_tree( $tree );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		return $normalized;
	}

	/**
	 * Generate a single section/block (one node with children) for "Edit with AI".
	 *
	 * @param string      $user_prompt User instruction for the section.
	 * @param string|null $context_json Optional JSON of the current section for context.
	 * @return array|WP_Error The normalized single node (Section/Container/Row/Grid) or WP_Error.
	 */
	public static function generate_section( $user_prompt, $context_json = null ) {
		$system = self::get_section_system_prompt();
		$user   = $user_prompt;
		if ( ! empty( $context_json ) && is_string( $context_json ) ) {
			$user = "Current section (for context):\n" . $context_json . "\n\nUser request: " . $user_prompt;
		}

		$result = self::call_provider( $system, $user, 4096 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$content = $result;
		if ( preg_match( '/^```(?:json)?\s*([\s\S]*?)```\s*$/s', $content, $m ) ) {
			$content = trim( $m[1] );
		}
		$node = json_decode( $content, true );
		if ( ! is_array( $node ) ) {
			return new WP_Error( 'invalid_json', __( 'AI did not return valid JSON.', 'bridge-builder' ), array( 'status' => 502 ) );
		}

		$allowed_roots = array( 'Section', 'Row', 'Container', 'Grid' );
		$type         = isset( $node['type'] ) && is_string( $node['type'] ) ? $node['type'] : '';
		if ( ! in_array( $type, $allowed_roots, true ) ) {
			$node = array(
				'id'       => 'section-1',
				'type'     => 'Section',
				'props'    => array(),
				'styles'   => array( 'desktop' => array( 'padding' => '60px 24px' ), 'mobile' => array( 'padding' => '40px 16px' ) ),
				'children' => isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array( $node ),
			);
		}

		$normalized = self::normalize_tree( $node );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		return $normalized;
	}

	/**
	 * Recursively normalize tree: ensure id, type, props, styles, children; assign new UUIDs; allowlist types.
	 *
	 * @param array $node A single node.
	 * @return array|WP_Error Normalized node or WP_Error if invalid.
	 */
	private static function normalize_tree( $node ) {
		if ( ! is_array( $node ) ) {
			return new WP_Error( 'invalid_node', __( 'Invalid node in tree.', 'bridge-builder' ) );
		}

		$allowed = self::allowed_types();
		$type    = isset( $node['type'] ) && is_string( $node['type'] ) ? $node['type'] : '';
		if ( ! in_array( $type, $allowed, true ) ) {
			/* translators: %s: unknown component type key. */
			return new WP_Error( 'invalid_type', sprintf( __( 'Unknown component type: %s', 'bridge-builder' ), $type ) );
		}

		$normalized = array(
			'id'       => wp_generate_uuid4(),
			'type'     => $type,
			'props'    => isset( $node['props'] ) && is_array( $node['props'] ) ? $node['props'] : array(),
			'styles'   => isset( $node['styles'] ) && is_array( $node['styles'] ) ? $node['styles'] : array(),
			'children' => array(),
		);

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				$norm_child = self::normalize_tree( $child );
				if ( is_wp_error( $norm_child ) ) {
					return $norm_child;
				}
				$normalized['children'][] = $norm_child;
			}
		}

		return $normalized;
	}
}
