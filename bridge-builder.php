<?php
/**
 * Plugin Name: Bridge Builder
 * Description: Visual page builder for WordPress. Drag-and-drop blocks, fast front end, SEO-optimized. Build beautiful WordPress pages visually with AI-generated sections, a built-in design system, and zero coding required
 * Version: 1.4.3
 * Author: Mouad
 * Text Domain: bridge-builder
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRIDGE_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRIDGE_BUILDER_URL', plugin_dir_url( __FILE__ ) );
define( 'BRIDGE_BUILDER_VERSION', '1.4.3' );

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------

require_once BRIDGE_BUILDER_PATH . 'includes/class-css-generator.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-renderer.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-rest-api.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-ai-generator.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-asset-manager.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-frontend-layout.php';
require_once BRIDGE_BUILDER_PATH . 'includes/class-post-types.php';

// ---------------------------------------------------------------------------
// Helpers (WP version compatibility)
// ---------------------------------------------------------------------------

/**
 * Get post thumbnail alt text. WP 5.5+ has get_the_post_thumbnail_alt_text(); older versions use post meta.
 *
 * @param int $attachment_id Attachment (thumbnail) ID.
 * @return string Alt text or empty string.
 */
function bridge_builder_get_post_thumbnail_alt( $attachment_id ) {
	if ( ! $attachment_id ) {
		return '';
	}
	if ( function_exists( 'get_the_post_thumbnail_alt_text' ) ) {
		$alt = get_the_post_thumbnail_alt_text( $attachment_id );
		return is_string( $alt ) ? $alt : '';
	}
	$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	return is_string( $alt ) ? $alt : '';
}

/**
 * Check if WooCommerce is active (Phase 4).
 *
 * @return bool
 */
function bridge_builder_woocommerce_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
}

/**
 * Get current WooCommerce product (on single product page or when post is product).
 *
 * @return WC_Product|null
 */
function bridge_builder_get_current_product() {
	if ( ! bridge_builder_woocommerce_active() || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	if ( is_singular( 'product' ) ) {
		return wc_get_product( get_the_ID() );
	}
	$post_id = get_the_ID();
	if ( $post_id && get_post_type( $post_id ) === 'product' ) {
		return wc_get_product( $post_id );
	}
	return null;
}

/**
 * Get products array for Product Grid (id, title, priceHtml, image, url, addToCartUrl, onSale).
 * Returns empty array if WooCommerce not active.
 *
 * @param array $props Props: postsPerPage, categorySlug, tagSlug, featured, onSale, orderBy, order.
 * @return array
 */
function bridge_builder_get_product_grid_products( $props ) {
	if ( ! bridge_builder_woocommerce_active() ) {
		return array();
	}
	$ppp      = isset( $props['postsPerPage'] ) ? max( 1, min( 24, (int) $props['postsPerPage'] ) ) : 8;
	$category = isset( $props['categorySlug'] ) && is_string( $props['categorySlug'] ) ? trim( $props['categorySlug'] ) : '';
	$tag      = isset( $props['tagSlug'] ) && is_string( $props['tagSlug'] ) ? trim( $props['tagSlug'] ) : '';
	$featured = ! empty( $props['featured'] ) && $props['featured'] !== 'false';
	$on_sale  = ! empty( $props['onSale'] ) && $props['onSale'] !== 'false';
	$orderby  = isset( $props['orderBy'] ) && is_string( $props['orderBy'] ) ? $props['orderBy'] : 'menu_order';
	$order    = isset( $props['order'] ) && in_array( $props['order'], array( 'ASC', 'DESC' ), true ) ? $props['order'] : 'ASC';

	$args = array(
		'status'  => 'publish',
		'limit'   => $ppp,
		'orderby' => $orderby,
		'order'   => $order,
	);
	if ( $category !== '' ) {
		$args['category'] = array( $category );
	}
	if ( $tag !== '' ) {
		$args['tag'] = array( $tag );
	}
	if ( $featured ) {
		$args['featured'] = true;
	}
	if ( $on_sale ) {
		$args['on_sale'] = true;
	}

	$products = wc_get_products( $args );
	$out      = array();
	foreach ( $products as $product ) {
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		if ( ! is_string( $image_url ) ) {
			$image_url = '';
		}
		$add_to_cart_url = method_exists( $product, 'get_add_to_cart_url' ) ? $product->get_add_to_cart_url() : get_permalink( $product->get_id() );
		$out[] = array(
			'id'           => $product->get_id(),
			'title'        => $product->get_name(),
			'priceHtml'    => wp_kses_post( $product->get_price_html() ),
			'image'        => $image_url,
			'url'          => $product->get_permalink(),
			'addToCartUrl' => is_string( $add_to_cart_url ) ? $add_to_cart_url : get_permalink( $product->get_id() ),
			'onSale'       => $product->is_on_sale(),
		);
	}
	return $out;
}

/**
 * Get mini cart HTML (trigger button + drawer) for MiniCart block. Used by renderer and REST enrichment.
 *
 * @return string HTML fragment (inner content only, no wrapper).
 */
function bridge_builder_get_mini_cart_html() {
	if ( ! bridge_builder_woocommerce_active() ) {
		return '<span style="color:#64748b;font-size:14px;">' . esc_html__( 'Mini cart requires WooCommerce.', 'bridge-builder' ) . '</span>';
	}
	$cart     = WC()->cart;
	$count    = $cart ? $cart->get_cart_contents_count() : 0;
	$subtotal = $cart ? $cart->get_cart_subtotal() : '';
	$html     = '<button type="button" class="bb-MiniCart__trigger" aria-label="' . esc_attr__( 'Open cart', 'bridge-builder' ) . '" data-bb-minicart-trigger style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;">';
	$html    .= '<span class="bb-MiniCart__icon" style="display:inline-block;width:20px;height:20px;" aria-hidden="true">🛒</span>';
	$html    .= '<span class="bb-MiniCart__count" data-bb-minicart-count>' . esc_html( (string) $count ) . '</span>';
	$html    .= '<span class="bb-MiniCart__subtotal" data-bb-minicart-subtotal style="font-weight:600;">' . $subtotal . '</span></button>';
	$html    .= '<div class="bb-MiniCart__drawer" data-bb-minicart-drawer style="position:absolute;top:100%;right:0;margin-top:8px;min-width:320px;max-width:90vw;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.12);z-index:1000;padding:16px;display:none;">';
	$html    .= '<div class="bb-MiniCart__drawer-title" style="font-weight:600;margin-bottom:12px;font-size:16px;">' . esc_html__( 'Cart', 'bridge-builder' ) . '</div>';
	if ( $cart && $count > 0 ) {
		$html .= '<ul class="bb-MiniCart__items" style="list-style:none;margin:0 0 12px;padding:0;max-height:280px;overflow-y:auto;">';
		foreach ( $cart->get_cart() as $item ) {
			$product = $item['data'];
			$name    = $product instanceof WC_Product ? $product->get_name() : '';
			$qty     = $item['quantity'];
			$price   = $cart->get_product_subtotal( $product, $item['quantity'] );
			$html   .= '<li style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;gap:8px;"><span style="flex:1;">' . esc_html( $name ) . ' × ' . (int) $qty . '</span><span>' . $price . '</span></li>';
		}
		$html     .= '</ul><div class="bb-MiniCart__subtotal-line" style="font-weight:600;margin-bottom:12px;padding-top:8px;border-top:1px solid #e2e8f0;">' . esc_html__( 'Subtotal:', 'bridge-builder' ) . ' ' . $subtotal . '</div>';
		$cart_url  = wc_get_cart_url();
		$checkout_url = wc_get_checkout_url();
		$html     .= '<div style="display:flex;gap:8px;"><a href="' . esc_url( $cart_url ) . '" style="flex:1;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;font-weight:500;text-decoration:none;color:inherit;">' . esc_html__( 'View cart', 'bridge-builder' ) . '</a><a href="' . esc_url( $checkout_url ) . '" style="flex:1;text-align:center;padding:10px;background:#2563eb;color:#fff;border-radius:8px;font-weight:500;text-decoration:none;">' . esc_html__( 'Checkout', 'bridge-builder' ) . '</a></div>';
	} else {
		$html .= '<p style="margin:0 0 12px;color:#64748b;font-size:14px;">' . esc_html__( 'Your cart is empty.', 'bridge-builder' ) . '</p>';
		$html .= '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display:block;text-align:center;padding:10px;background:#2563eb;color:#fff;border-radius:8px;font-weight:500;text-decoration:none;">' . esc_html__( 'Go to shop', 'bridge-builder' ) . '</a>';
	}
	$html .= '</div>';
	return $html;
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

function bridge_builder_init() {
	$assets = new Bridge_Builder_Asset_Manager();
	$assets->init();
}
add_action( 'plugins_loaded', 'bridge_builder_init' );

/**
 * Constrain Bridge Builder menu icon to sidebar size and align with other menu icons.
 */
function bridge_builder_admin_menu_icon_css() {
	$icon_path = BRIDGE_BUILDER_PATH . 'assets/icon.png';
	if ( ! file_exists( $icon_path ) ) {
		return;
	}
	echo '<style id="bridge-builder-menu-icon">';
	/* 20px icon centered like dashicons; keep container so row aligns with other menu items */
	echo '#adminmenu .toplevel_page_bridge-builder .wp-menu-image {';
	echo ' display: flex !important; align-items: center !important; justify-content: center !important;';
	echo ' background-size: 20px 20px !important; background-position: center !important; background-repeat: no-repeat !important;';
	echo '}';
	echo '#adminmenu .toplevel_page_bridge-builder .wp-menu-image img {';
	echo ' width: 20px !important; height: 20px !important; max-width: 20px !important; max-height: 20px !important;';
	echo ' object-fit: contain !important; display: block !important;';
	echo '}';
	echo '</style>';
}
add_action( 'admin_head', 'bridge_builder_admin_menu_icon_css' );

function bridge_builder_register_cpts() {
	Bridge_Builder_Post_Types::register();
}
add_action( 'init', 'bridge_builder_register_cpts' );

/**
 * Handle create/delete/set_active/save_rules for global content before any output (so redirect works).
 */
function bridge_builder_global_content_actions() {
	if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$config = bridge_builder_global_content_config();
	if ( ! isset( $config[ $slug ] ) ) {
		return;
	}
	$c         = $config[ $slug ];
	$post_type = $c['type'];

	// Create: add new item and redirect to builder (must run before any output)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'create' && isset( $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_create_' . $post_type ) ) {
			/* translators: %s: singular post type label, e.g. "Section". */
			$new_id = wp_insert_post( array(
				'post_type'   => $post_type,
				/* translators: %s: singular post type label, e.g. "Section". */
				'post_title'  => sprintf( __( 'New %s', 'bridge-builder' ), $c['singular'] ),
				'post_status' => 'publish',
			) );
			if ( $new_id && ! is_wp_error( $new_id ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=bridge-builder&post_id=' . $new_id ) );
				exit;
			}
		}
	}

	// Delete
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$id = absint( $_GET['id'] );
		if ( $id && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_delete_' . $id ) ) {
			$p = get_post( $id );
			if ( $p && $p->post_type === $post_type && current_user_can( 'delete_post', $id ) ) {
				wp_trash_post( $id );
				wp_safe_redirect( admin_url( 'admin.php?page=' . $slug ) );
				exit;
			}
		}
	}

	// Set active (header/footer only)
	$is_header_footer = in_array( $post_type, array( 'bb_header', 'bb_footer' ), true );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $is_header_footer && isset( $_GET['action'] ) && $_GET['action'] === 'set_active' && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
		$id = absint( $_GET['id'] );
		if ( $id && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_set_active_' . $id ) ) {
			$p = get_post( $id );
			if ( $p && $p->post_type === $post_type && current_user_can( 'edit_post', $id ) ) {
				if ( $post_type === 'bb_header' ) {
					update_option( 'bridge_builder_active_header_id', $id );
				} else {
					update_option( 'bridge_builder_active_footer_id', $id );
				}
				wp_safe_redirect( admin_url( 'admin.php?page=' . $slug ) );
				exit;
			}
		}
	}

	// Save condition rules + priority (header/footer only).
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $is_header_footer && $method === 'POST' && isset( $_POST['bb_action'] ) && sanitize_text_field( wp_unslash( $_POST['bb_action'] ) ) === 'save_rules' && isset( $_POST['id'] ) && isset( $_POST['_wpnonce'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = absint( $_POST['id'] );
		if ( $id && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bb_save_rules_' . $id ) ) {
			$p = get_post( $id );
			if ( $p && $p->post_type === $post_type && current_user_can( 'edit_post', $id ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$scope = isset( $_POST['bb_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['bb_scope'] ) ) : 'site';
				if ( ! in_array( $scope, array( 'site', 'pages', 'posts', 'specific' ), true ) ) {
					$scope = 'site';
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$match = isset( $_POST['bb_match'] ) ? sanitize_text_field( wp_unslash( $_POST['bb_match'] ) ) : 'all';
				if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
					$match = 'all';
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$ids_raw = isset( $_POST['bb_specific_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['bb_specific_ids'] ) ) : '';
				$ids     = array();
				if ( $ids_raw !== '' ) {
					$parts = explode( ',', $ids_raw );
					foreach ( $parts as $part ) {
						$maybe_id = absint( trim( $part ) );
						if ( $maybe_id > 0 ) {
							$ids[] = $maybe_id;
						}
					}
					$ids = array_values( array_unique( $ids ) );
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$priority = isset( $_POST['bb_priority'] ) ? intval( wp_unslash( $_POST['bb_priority'] ) ) : 10;
				$priority = max( 0, min( 999, $priority ) );

				update_post_meta(
					$id,
					'_bb_template_conditions',
					array(
						'scope'        => $scope,
						'match'        => $match,
						'specific_ids' => $ids,
					)
				);
				update_post_meta( $id, '_bb_template_priority', $priority );

				wp_safe_redirect( add_query_arg( array( 'page' => $slug, 'updated' => '1', 'edit_rules' => $id ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}
}
add_action( 'admin_init', 'bridge_builder_global_content_actions', 5 );

/**
 * Handle create-from-template redirect before any output.
 */
function bridge_builder_create_from_template_action() {
	if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $page !== 'bridge-builder' || ! isset( $_GET['action'] ) || $_GET['action'] !== 'create_from_template' || ! isset( $_GET['template_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}
	$template_id = absint( $_GET['template_id'] );
	if ( ! $template_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_use_template_' . $template_id ) ) {
		return;
	}
	$new_id = bridge_builder_create_page_from_template( $template_id );
	if ( $new_id && ! is_wp_error( $new_id ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=bridge-builder&post_id=' . $new_id ) );
		exit;
	}
}
add_action( 'admin_init', 'bridge_builder_create_from_template_action', 5 );

/**
 * Save Bridge Builder settings (AI provider + API keys) from Settings page form.
 * Runs in admin_init priority 1 so redirect happens before any output (avoids "headers already sent").
 */
function bridge_builder_save_settings_action() {
	// Only when posting the settings form (admin-post.php or form post to admin.php).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'bridge_builder_save_settings' ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage these settings.', 'bridge-builder' ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bridge_builder_settings' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=bridge-builder-settings' ) );
		exit;
	}

	$providers = array(
		Bridge_Builder_AI_Generator::PROVIDER_NVIDIA,
		Bridge_Builder_AI_Generator::PROVIDER_OPENAI,
		Bridge_Builder_AI_Generator::PROVIDER_CLAUDE,
		Bridge_Builder_AI_Generator::PROVIDER_GEMINI,
		Bridge_Builder_AI_Generator::PROVIDER_CUSTOM,
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$provider = isset( $_POST['bridge_builder_ai_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_ai_provider'] ) ) : '';
	if ( in_array( $provider, $providers, true ) ) {
		update_option( Bridge_Builder_AI_Generator::OPTION_PROVIDER, $provider );
	}

	// Only update the selected provider's key. If the field is empty (e.g. page reload after save),
	// keep the existing key — password fields are not pre-filled for security.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_NVIDIA ) {
		$key = isset( $_POST['bridge_builder_nvidia_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_nvidia_api_key'] ) ) : '';
		if ( $key !== '' ) {
			update_option( Bridge_Builder_AI_Generator::OPTION_NVIDIA_KEY, $key );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_OPENAI ) {
		$key = isset( $_POST['bridge_builder_openai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_openai_api_key'] ) ) : '';
		if ( $key !== '' ) {
			update_option( Bridge_Builder_AI_Generator::OPTION_OPENAI_KEY, $key );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_CLAUDE ) {
		$key = isset( $_POST['bridge_builder_claude_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_claude_api_key'] ) ) : '';
		if ( $key !== '' ) {
			update_option( Bridge_Builder_AI_Generator::OPTION_CLAUDE_KEY, $key );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_GEMINI ) {
		$key = isset( $_POST['bridge_builder_gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_gemini_api_key'] ) ) : '';
		if ( $key !== '' ) {
			update_option( Bridge_Builder_AI_Generator::OPTION_GEMINI_KEY, $key );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $provider === Bridge_Builder_AI_Generator::PROVIDER_CUSTOM ) {
		$custom_url_raw = isset( $_POST['bridge_builder_custom_ai_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bridge_builder_custom_ai_url'] ) ) ) : '';
		$custom_url   = $custom_url_raw !== '' ? esc_url_raw( $custom_url_raw ) : '';
		$custom_key   = isset( $_POST['bridge_builder_custom_ai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_custom_ai_key'] ) ) : '';
		$custom_model = isset( $_POST['bridge_builder_custom_ai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_custom_ai_model'] ) ) : 'gpt-4o';
		update_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_URL, $custom_url );
		update_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_KEY, $custom_key );
		update_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_MODEL, $custom_model );
	}

	// Frontend styling: theme (layout only) or builder (full design).
	$frontend_styling = isset( $_POST['bridge_builder_frontend_styling'] ) ? sanitize_text_field( wp_unslash( $_POST['bridge_builder_frontend_styling'] ) ) : 'theme';
	if ( in_array( $frontend_styling, array( 'theme', 'builder' ), true ) ) {
		update_option( 'bridge_builder_frontend_styling', $frontend_styling );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'bridge-builder-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_init', 'bridge_builder_save_settings_action', 1 );

/**
 * Render Bridge Builder Settings page (AI provider + API keys for AI generation).
 */
function bridge_builder_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$provider = get_option( Bridge_Builder_AI_Generator::OPTION_PROVIDER, Bridge_Builder_AI_Generator::PROVIDER_NVIDIA );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Bridge Builder Settings', 'bridge-builder' ) . '</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bridge-builder' ) . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="bridge_builder_save_settings" />';
	wp_nonce_field( 'bridge_builder_settings' );
	echo '<table class="form-table" role="presentation">';

	// Provider selection.
	echo '<tr><th scope="row"><label for="bb-ai-provider">' . esc_html__( 'AI Provider', 'bridge-builder' ) . '</label></th><td>';
	echo '<select id="bb-ai-provider" name="bridge_builder_ai_provider">';
	$providers = array(
		Bridge_Builder_AI_Generator::PROVIDER_NVIDIA => __( 'NVIDIA NIM (Llama, etc.)', 'bridge-builder' ),
		Bridge_Builder_AI_Generator::PROVIDER_OPENAI => __( 'OpenAI (GPT-4, etc.)', 'bridge-builder' ),
		Bridge_Builder_AI_Generator::PROVIDER_CLAUDE => __( 'Anthropic Claude', 'bridge-builder' ),
		Bridge_Builder_AI_Generator::PROVIDER_GEMINI => __( 'Google Gemini', 'bridge-builder' ),
		Bridge_Builder_AI_Generator::PROVIDER_CUSTOM => __( 'Custom (OpenAI-compatible endpoint)', 'bridge-builder' ),
	);
	foreach ( $providers as $slug => $label ) {
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $provider, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo ' <span class="description">' . esc_html__( 'Choose which AI service to use for page and section generation.', 'bridge-builder' ) . '</span>';
	echo '</td></tr>';

	// Frontend styling: theme (layout only) or builder (full design).
	$frontend_styling = get_option( 'bridge_builder_frontend_styling', 'theme' );
	echo '<tr><th scope="row"><label for="bb-frontend-styling">' . esc_html__( 'Frontend styling', 'bridge-builder' ) . '</label></th><td>';
	echo '<select id="bb-frontend-styling" name="bridge_builder_frontend_styling">';
	echo '<option value="theme"' . selected( $frontend_styling, 'theme', false ) . '>' . esc_html__( 'Theme (layout only — theme controls colors, fonts, shadows)', 'bridge-builder' ) . '</option>';
	echo '<option value="builder"' . selected( $frontend_styling, 'builder', false ) . '>' . esc_html__( 'Builder (full design — frontend matches editor)', 'bridge-builder' ) . '</option>';
	echo '</select>';
	echo ' <span class="description">' . esc_html__( 'Use "Theme" so your theme styles the content; use "Builder" so the live page looks like the editor.', 'bridge-builder' ) . '</span>';
	echo '</td></tr>';

	// API key row — shown only for selected provider (nvidia, openai, claude, gemini).
	$nvidia_has = get_option( Bridge_Builder_AI_Generator::OPTION_NVIDIA_KEY, '' ) !== '';
	echo '<tr class="bb-provider-row" data-provider="nvidia" style="display:none;"><th scope="row"><label for="bb-nvidia-key">' . esc_html__( 'NVIDIA API Key', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="password" id="bb-nvidia-key" name="bridge_builder_nvidia_api_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $nvidia_has ? __( '•••••••• (leave blank to keep)', 'bridge-builder' ) : 'nvapi-…' ) . '" />';
	echo ' <span class="description">' . esc_html__( 'From build.nvidia.com', 'bridge-builder' ) . ( $nvidia_has ? ' ' . esc_html__( 'Key is saved.', 'bridge-builder' ) : '' ) . '</span>';
	echo '</td></tr>';
	$openai_has = get_option( Bridge_Builder_AI_Generator::OPTION_OPENAI_KEY, '' ) !== '';
	echo '<tr class="bb-provider-row" data-provider="openai" style="display:none;"><th scope="row"><label for="bb-openai-key">' . esc_html__( 'OpenAI API Key', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="password" id="bb-openai-key" name="bridge_builder_openai_api_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $openai_has ? __( '•••••••• (leave blank to keep)', 'bridge-builder' ) : 'sk-…' ) . '" />';
	echo ' <span class="description">' . esc_html__( 'From platform.openai.com', 'bridge-builder' ) . ( $openai_has ? ' ' . esc_html__( 'Key is saved.', 'bridge-builder' ) : '' ) . '</span>';
	echo '</td></tr>';
	$claude_has = get_option( Bridge_Builder_AI_Generator::OPTION_CLAUDE_KEY, '' ) !== '';
	echo '<tr class="bb-provider-row" data-provider="claude" style="display:none;"><th scope="row"><label for="bb-claude-key">' . esc_html__( 'Claude API Key', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="password" id="bb-claude-key" name="bridge_builder_claude_api_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $claude_has ? __( '•••••••• (leave blank to keep)', 'bridge-builder' ) : 'sk-ant-…' ) . '" />';
	echo ' <span class="description">' . esc_html__( 'From console.anthropic.com', 'bridge-builder' ) . ( $claude_has ? ' ' . esc_html__( 'Key is saved.', 'bridge-builder' ) : '' ) . '</span>';
	echo '</td></tr>';
	$gemini_has = get_option( Bridge_Builder_AI_Generator::OPTION_GEMINI_KEY, '' ) !== '';
	echo '<tr class="bb-provider-row" data-provider="gemini" style="display:none;"><th scope="row"><label for="bb-gemini-key">' . esc_html__( 'Gemini API Key', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="password" id="bb-gemini-key" name="bridge_builder_gemini_api_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $gemini_has ? __( '•••••••• (leave blank to keep)', 'bridge-builder' ) : 'AIza…' ) . '" />';
	echo ' <span class="description">' . esc_html__( 'From aistudio.google.com', 'bridge-builder' ) . ( $gemini_has ? ' ' . esc_html__( 'Key is saved.', 'bridge-builder' ) : '' ) . '</span>';
	echo '</td></tr>';

	// Custom: URL + Key + Model — shown only when custom selected.
	echo '<tr class="bb-provider-row" data-provider="custom" style="display:none;"><th scope="row"><label for="bb-custom-url">' . esc_html__( 'Custom Endpoint URL', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="url" id="bb-custom-url" name="bridge_builder_custom_ai_url" class="large-text" placeholder="https://api.example.com/v1/chat/completions" value="' . esc_attr( get_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_URL, '' ) ) . '" />';
	echo '</td></tr>';
	echo '<tr class="bb-provider-row" data-provider="custom" style="display:none;"><th scope="row"><label for="bb-custom-key">' . esc_html__( 'Custom API Key', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="password" id="bb-custom-key" name="bridge_builder_custom_ai_key" class="regular-text" autocomplete="off" />';
	echo '</td></tr>';
	echo '<tr class="bb-provider-row" data-provider="custom" style="display:none;"><th scope="row"><label for="bb-custom-model">' . esc_html__( 'Custom Model', 'bridge-builder' ) . '</label></th><td>';
	echo '<input type="text" id="bb-custom-model" name="bridge_builder_custom_ai_model" class="regular-text" placeholder="gpt-4o" value="' . esc_attr( get_option( Bridge_Builder_AI_Generator::OPTION_CUSTOM_MODEL, 'gpt-4o' ) ) . '" />';
	echo ' <span class="description">' . esc_html__( 'Model name for OpenAI-compatible APIs (e.g. gpt-4o, llama-3-70b)', 'bridge-builder' ) . '</span>';
	echo '</td></tr>';

	echo '</table>';
	echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__( 'Save', 'bridge-builder' ) . '" /></p>';
	echo '</form>';
	echo '<script>
	(function(){
		var sel = document.getElementById("bb-ai-provider");
		var rows = document.querySelectorAll(".bb-provider-row");
		function update() {
			var p = sel ? sel.value : "nvidia";
			rows.forEach(function(r){
				r.style.display = r.getAttribute("data-provider") === p ? "" : "none";
			});
		}
		if (sel) { sel.addEventListener("change", update); update(); }
	})();
	</script>';
	echo '<p class="description">' . esc_html__( 'Select a provider above and add your API key. Keys are stored securely and never exposed to the frontend.', 'bridge-builder' ) . '</p>';
	echo '</div>';
}

function bridge_builder_register_rest() {
	$api = new Bridge_Builder_REST_API();
	$api->register_routes();
}
add_action( 'rest_api_init', 'bridge_builder_register_rest' );

// ---------------------------------------------------------------------------
// Admin: Sidebar menu + full-screen builder
// ---------------------------------------------------------------------------

/**
 * Menu icon: custom image in assets/icon.png (recommended 20×20 or 36×36), or dashicon fallback.
 *
 * @return string Dashicon class or URL to icon image.
 */
function bridge_builder_menu_icon() {
	$icon_path = BRIDGE_BUILDER_PATH . 'assets/icon.png';
	if ( file_exists( $icon_path ) ) {
		return BRIDGE_BUILDER_URL . 'assets/icon.png';
	}
	return 'dashicons-building';
}

function bridge_builder_admin_menu() {
	$icon = bridge_builder_menu_icon();

	// Top-level: Bridge Builder in sidebar (with icon)
	add_menu_page(
		__( 'Bridge Builder', 'bridge-builder' ),
		__( 'Bridge Builder', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder',
		'bridge_builder_admin_page',
		$icon,
		30
	);

	// Global content: sections, header, footer, templates.
	add_submenu_page(
		'bridge-builder',
		__( 'Sections', 'bridge-builder' ),
		__( 'Sections', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder-sections',
		'bridge_builder_render_placeholder'
	);
	add_submenu_page(
		'bridge-builder',
		__( 'Header', 'bridge-builder' ),
		__( 'Header', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder-header',
		'bridge_builder_render_placeholder'
	);
	add_submenu_page(
		'bridge-builder',
		__( 'Footer', 'bridge-builder' ),
		__( 'Footer', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder-footer',
		'bridge_builder_render_placeholder'
	);
	add_submenu_page(
		'bridge-builder',
		__( 'Templates', 'bridge-builder' ),
		__( 'Templates', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder-templates',
		'bridge_builder_render_placeholder'
	);
	add_submenu_page(
		'bridge-builder',
		__( 'Design System', 'bridge-builder' ),
		__( 'Design System', 'bridge-builder' ),
		'edit_posts',
		'bridge-builder-design-system',
		'bridge_builder_render_design_system_studio'
	);
	add_submenu_page(
		'bridge-builder',
		__( 'Settings', 'bridge-builder' ),
		__( 'Settings', 'bridge-builder' ),
		'manage_options',
		'bridge-builder-settings',
		'bridge_builder_render_settings'
	);
}
add_action( 'admin_menu', 'bridge_builder_admin_menu' );

/**
 * Main admin page: if post_id in URL → full-screen editor (or redirect to Design System Studio); else → dashboard (pages list).
 * create_from_template is handled in admin_init so redirect runs before output.
 */
function bridge_builder_admin_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	if ( $post_id ) {
		// First-run gate: if no design system and no builder content yet, send to Design System Studio first.
		$design_system    = get_post_meta( $post_id, '_builder_design_system', true );
		$builder_json     = get_post_meta( $post_id, '_builder_json', true );
		$has_design_system = ! empty( $design_system ) && is_string( $design_system );
		$decoded          = $has_design_system ? json_decode( $design_system, true ) : null;
		$has_content      = ! empty( $builder_json ) && is_string( $builder_json );
		if ( ( ! $has_design_system || ! is_array( $decoded ) ) && ! $has_content ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'bridge-builder-design-system', 'post_id' => $post_id ), admin_url( 'admin.php' ) ) );
			exit;
		}
		bridge_builder_render_editor();
		return;
	}
	bridge_builder_render_dashboard();
}

/**
 * Create a new page (or post) by copying a template's builder data.
 *
 * @param int    $template_id Template post ID (bb_template).
 * @param string $post_type   New post type: 'page' or 'post'. Default 'page'.
 * @return int|WP_Error New post ID or error.
 */
function bridge_builder_create_page_from_template( $template_id, $post_type = 'page' ) {
	$template = get_post( $template_id );
	if ( ! $template || $template->post_type !== 'bb_template' ) {
		return new WP_Error( 'invalid_template', __( 'Template not found.', 'bridge-builder' ) );
	}
	$json  = get_post_meta( $template_id, '_builder_json', true );
	$css   = get_post_meta( $template_id, '_builder_css', true );
	$css_s = get_post_meta( $template_id, '_builder_css_structural', true );

	/* translators: %s: template title. */
	$new_title = sprintf( __( 'Copy of %s', 'bridge-builder' ), $template->post_title );
	$new_id    = wp_insert_post( array(
		'post_type'   => in_array( $post_type, array( 'page', 'post' ), true ) ? $post_type : 'page',
		'post_title'  => $new_title,
		'post_status' => 'draft',
	) );
	if ( ! $new_id || is_wp_error( $new_id ) ) {
		return $new_id ? $new_id : new WP_Error( 'create_failed', __( 'Could not create page.', 'bridge-builder' ) );
	}
	if ( ! empty( $json ) ) {
		update_post_meta( $new_id, '_builder_json', $json );
	}
	if ( ! empty( $css ) ) {
		update_post_meta( $new_id, '_builder_css', $css );
	}
	if ( ! empty( $css_s ) ) {
		update_post_meta( $new_id, '_builder_css_structural', $css_s );
	}
	return $new_id;
}

/**
 * Simple dashboard: list pages/posts with "Edit with Builder" and "Add from template".
 */
function bridge_builder_render_dashboard() {
	$pages = get_posts( array(
		'post_type'      => array( 'page', 'post' ),
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );
	$with_builder = array();
	foreach ( $pages as $p ) {
		if ( get_post_meta( $p->ID, '_builder_json', true ) ) {
			$with_builder[ $p->ID ] = true;
		}
	}

	$templates = get_posts( array(
		'post_type'      => 'bb_template',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );

	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Bridge Builder', 'bridge-builder' ) . '</h1>';
	echo '<p>' . esc_html__( 'Pages and posts you can edit with the builder.', 'bridge-builder' ) . '</p>';

	// Add from template
	if ( ! empty( $templates ) ) {
		echo '<div style="margin:16px 0; padding:12px 16px; background:#f0f6fc; border:1px solid #c5d9ed; border-radius:6px;">';
		echo '<strong style="display:block; margin-bottom:8px;">' . esc_html__( 'Add new from template', 'bridge-builder' ) . '</strong>';
		echo '<p style="margin:0 0 10px 0; font-size:13px; color:#1e3a5f;">' . esc_html__( 'Create a new draft page using a template layout.', 'bridge-builder' ) . '</p>';
		echo '<ul style="margin:0; padding:0; list-style:none;">';
		foreach ( $templates as $t ) {
			$use_url = wp_nonce_url( add_query_arg( array( 'page' => 'bridge-builder', 'action' => 'create_from_template', 'template_id' => $t->ID ), admin_url( 'admin.php' ) ), 'bb_use_template_' . $t->ID );
			echo '<li style="margin-bottom:6px;"><a href="' . esc_url( $use_url ) . '" style="font-size:14px;">' . esc_html( $t->post_title ) . '</a> <span style="color:#64748b;">— ' . esc_html__( 'Use template', 'bridge-builder' ) . '</span></li>';
		}
		echo '</ul></div>';
	}

	echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Title', 'bridge-builder' ) . '</th><th>' . esc_html__( 'Type', 'bridge-builder' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $pages as $p ) {
		$edit_url = admin_url( 'admin.php?page=bridge-builder&post_id=' . $p->ID );
		echo '<tr><td>' . esc_html( $p->post_title ) . '</td><td>' . esc_html( $p->post_type ) . '</td>';
		echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit with Builder', 'bridge-builder' ) . '</a></td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Map admin page slug to post type and label.
 */
function bridge_builder_global_content_config() {
	return array(
		'bridge-builder-sections'  => array( 'type' => 'bb_section', 'label' => __( 'Sections', 'bridge-builder' ), 'singular' => __( 'Section', 'bridge-builder' ) ),
		'bridge-builder-header'    => array( 'type' => 'bb_header', 'label' => __( 'Header', 'bridge-builder' ), 'singular' => __( 'Header', 'bridge-builder' ) ),
		'bridge-builder-footer'    => array( 'type' => 'bb_footer', 'label' => __( 'Footer', 'bridge-builder' ), 'singular' => __( 'Footer', 'bridge-builder' ) ),
		'bridge-builder-templates' => array( 'type' => 'bb_template', 'label' => __( 'Templates', 'bridge-builder' ), 'singular' => __( 'Template', 'bridge-builder' ) ),
	);
}

/**
 * Get normalized conditions for a header/footer template.
 *
 * @param int $post_id Post ID.
 * @return array{scope:string,match:string,specific_ids:int[]}
 */
function bridge_builder_get_template_conditions( $post_id ) {
	$defaults = array(
		'scope'        => 'site',
		'match'        => 'all',
		'specific_ids' => array(),
	);
	$raw = get_post_meta( $post_id, '_bb_template_conditions', true );
	if ( ! is_array( $raw ) ) {
		return $defaults;
	}
	$scope = isset( $raw['scope'] ) ? sanitize_text_field( (string) $raw['scope'] ) : 'site';
	if ( ! in_array( $scope, array( 'site', 'pages', 'posts', 'specific' ), true ) ) {
		$scope = 'site';
	}
	$match = isset( $raw['match'] ) ? sanitize_text_field( (string) $raw['match'] ) : 'all';
	if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
		$match = 'all';
	}
	$specific_ids = array();
	if ( isset( $raw['specific_ids'] ) && is_array( $raw['specific_ids'] ) ) {
		foreach ( $raw['specific_ids'] as $maybe_id ) {
			$id = absint( $maybe_id );
			if ( $id > 0 ) {
				$specific_ids[] = $id;
			}
		}
		$specific_ids = array_values( array_unique( $specific_ids ) );
	}
	return array(
		'scope'        => $scope,
		'match'        => $match,
		'specific_ids' => $specific_ids,
	);
}

/**
 * Get normalized template priority.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function bridge_builder_get_template_priority( $post_id ) {
	$priority = (int) get_post_meta( $post_id, '_bb_template_priority', true );
	if ( $priority <= 0 ) {
		$priority = 10;
	}
	return max( 0, min( 999, $priority ) );
}

/**
 * Human-readable conditions summary for admin list rows.
 *
 * @param array{scope:string,match:string,specific_ids:int[]} $conditions Conditions array.
 * @return string
 */
function bridge_builder_template_conditions_summary( $conditions ) {
	if ( $conditions['scope'] === 'pages' ) {
		return __( 'All pages', 'bridge-builder' );
	}
	if ( $conditions['scope'] === 'posts' ) {
		return __( 'All posts', 'bridge-builder' );
	}
	if ( $conditions['scope'] === 'specific' ) {
		if ( empty( $conditions['specific_ids'] ) ) {
			return __( 'Specific pages/posts (none selected)', 'bridge-builder' );
		}
		return sprintf(
			/* translators: %s: comma-separated IDs. */
			__( 'Specific IDs: %s', 'bridge-builder' ),
			implode( ', ', array_map( 'intval', $conditions['specific_ids'] ) )
		);
	}
	return __( 'Entire site', 'bridge-builder' );
}

/**
 * List and manage Sections / Header / Footer / Templates.
 */
function bridge_builder_render_placeholder() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slug   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$config = bridge_builder_global_content_config();
	if ( ! isset( $config[ $slug ] ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Bridge Builder', 'bridge-builder' ) . '</h1></div>';
		return;
	}

	$c        = $config[ $slug ];
	$post_type = $c['type'];
	$label     = $c['label'];
	$is_header_footer = in_array( $post_type, array( 'bb_header', 'bb_footer' ), true );

	// Create/delete/set_active are handled in admin_init (bridge_builder_global_content_actions) so redirect runs before output.

	$items   = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 100, 'orderby' => 'modified', 'order' => 'DESC' ) );
	$active_header = (int) get_option( 'bridge_builder_active_header_id', 0 );
	$active_footer = (int) get_option( 'bridge_builder_active_footer_id', 0 );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$updated = isset( $_GET['updated'] ) && $_GET['updated'] === '1';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit_rules_id = isset( $_GET['edit_rules'] ) ? absint( $_GET['edit_rules'] ) : 0;

	$add_url = wp_nonce_url( add_query_arg( 'action', 'create', admin_url( 'admin.php?page=' . $slug ) ), 'bb_create_' . $post_type );

	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html( $label ) . '</h1>';
	echo ' <a href="' . esc_url( $add_url ) . '" class="page-title-action">' . esc_html__( 'Add New', 'bridge-builder' ) . '</a>';
	echo '<hr class="wp-header-end">';
	if ( $updated ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template rules saved.', 'bridge-builder' ) . '</p></div>';
	}
	if ( $is_header_footer && $edit_rules_id > 0 ) {
		$edit_post = get_post( $edit_rules_id );
		if ( $edit_post && $edit_post->post_type === $post_type && current_user_can( 'edit_post', $edit_rules_id ) ) {
			$conditions = bridge_builder_get_template_conditions( $edit_rules_id );
			$priority   = bridge_builder_get_template_priority( $edit_rules_id );
			$cancel_url = admin_url( 'admin.php?page=' . $slug );
			echo '<div style="margin: 12px 0; padding: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px;">';
			echo '<h2 style="margin-top:0;">' . esc_html__( 'Template Conditions', 'bridge-builder' ) . ': ' . esc_html( $edit_post->post_title ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">';
			echo '<input type="hidden" name="bb_action" value="save_rules" />';
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $edit_rules_id ) . '" />';
			wp_nonce_field( 'bb_save_rules_' . $edit_rules_id );
			echo '<table class="form-table" role="presentation">';
			echo '<tr><th scope="row"><label for="bb-scope">' . esc_html__( 'Apply to', 'bridge-builder' ) . '</label></th><td>';
			echo '<select id="bb-scope" name="bb_scope">';
			echo '<option value="site"' . selected( $conditions['scope'], 'site', false ) . '>' . esc_html__( 'Entire site', 'bridge-builder' ) . '</option>';
			echo '<option value="pages"' . selected( $conditions['scope'], 'pages', false ) . '>' . esc_html__( 'All pages', 'bridge-builder' ) . '</option>';
			echo '<option value="posts"' . selected( $conditions['scope'], 'posts', false ) . '>' . esc_html__( 'All posts', 'bridge-builder' ) . '</option>';
			echo '<option value="specific"' . selected( $conditions['scope'], 'specific', false ) . '>' . esc_html__( 'Specific page/post IDs', 'bridge-builder' ) . '</option>';
			echo '</select>';
			echo '</td></tr>';
			echo '<tr><th scope="row"><label for="bb-match">' . esc_html__( 'Match mode', 'bridge-builder' ) . '</label></th><td>';
			echo '<select id="bb-match" name="bb_match">';
			echo '<option value="all"' . selected( $conditions['match'], 'all', false ) . '>' . esc_html__( 'All rules (AND)', 'bridge-builder' ) . '</option>';
			echo '<option value="any"' . selected( $conditions['match'], 'any', false ) . '>' . esc_html__( 'Any rule (OR)', 'bridge-builder' ) . '</option>';
			echo '</select>';
			echo '</td></tr>';
			echo '<tr><th scope="row"><label for="bb-specific-ids">' . esc_html__( 'Specific IDs', 'bridge-builder' ) . '</label></th><td>';
			echo '<input id="bb-specific-ids" type="text" class="regular-text" name="bb_specific_ids" value="' . esc_attr( implode( ',', $conditions['specific_ids'] ) ) . '" placeholder="12,34,56" />';
			echo ' <span class="description">' . esc_html__( 'Used when "Specific page/post IDs" is selected.', 'bridge-builder' ) . '</span>';
			echo '</td></tr>';
			echo '<tr><th scope="row"><label for="bb-priority">' . esc_html__( 'Priority', 'bridge-builder' ) . '</label></th><td>';
			echo '<input id="bb-priority" type="number" min="0" max="999" name="bb_priority" value="' . esc_attr( (string) $priority ) . '" />';
			echo ' <span class="description">' . esc_html__( 'Higher priority wins when multiple templates match.', 'bridge-builder' ) . '</span>';
			echo '</td></tr>';
			echo '</table>';
			echo '<p class="submit" style="margin-bottom:0;"><button type="submit" class="button button-primary">' . esc_html__( 'Save rules', 'bridge-builder' ) . '</button> ';
			echo '<a href="' . esc_url( $cancel_url ) . '" class="button">' . esc_html__( 'Cancel', 'bridge-builder' ) . '</a></p>';
			echo '</form></div>';
		}
	}
	if ( $is_header_footer ) {
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Title', 'bridge-builder' ) . '</th><th>' . esc_html__( 'Conditions', 'bridge-builder' ) . '</th><th>' . esc_html__( 'Priority', 'bridge-builder' ) . '</th><th>' . esc_html__( 'Modified', 'bridge-builder' ) . '</th><th></th></tr></thead><tbody>';
	} else {
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Title', 'bridge-builder' ) . '</th><th>' . esc_html__( 'Modified', 'bridge-builder' ) . '</th><th></th></tr></thead><tbody>';
	}
	foreach ( $items as $p ) {
		$edit_url = admin_url( 'admin.php?page=bridge-builder&post_id=' . $p->ID );
		if ( $is_header_footer ) {
			$conditions = bridge_builder_get_template_conditions( $p->ID );
			$priority   = bridge_builder_get_template_priority( $p->ID );
			$summary    = bridge_builder_template_conditions_summary( $conditions );
			$rules_url  = add_query_arg( array( 'page' => $slug, 'edit_rules' => $p->ID ), admin_url( 'admin.php' ) );
			echo '<tr><td>' . esc_html( $p->post_title ) . '</td>';
			echo '<td>' . esc_html( $summary ) . '<br /><a href="' . esc_url( $rules_url ) . '">' . esc_html__( 'Edit conditions', 'bridge-builder' ) . '</a></td>';
			echo '<td>' . esc_html( (string) $priority ) . '</td>';
			echo '<td>' . esc_html( get_the_modified_date( '', $p ) ) . '</td><td>';
		} else {
			echo '<tr><td>' . esc_html( $p->post_title ) . '</td><td>' . esc_html( get_the_modified_date( '', $p ) ) . '</td><td>';
		}
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit with Builder', 'bridge-builder' ) . '</a>';
		if ( $post_type === 'bb_header' && $p->ID === $active_header ) {
			echo ' | <strong>' . esc_html__( 'Active', 'bridge-builder' ) . '</strong>';
		} elseif ( $post_type === 'bb_header' ) {
			echo ' | <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'set_active', 'id' => $p->ID ), admin_url( 'admin.php?page=' . $slug ) ), 'bb_set_active_' . $p->ID ) ) . '">' . esc_html__( 'Set as active', 'bridge-builder' ) . '</a>';
		}
		if ( $post_type === 'bb_footer' && $p->ID === $active_footer ) {
			echo ' | <strong>' . esc_html__( 'Active', 'bridge-builder' ) . '</strong>';
		} elseif ( $post_type === 'bb_footer' ) {
			echo ' | <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'set_active', 'id' => $p->ID ), admin_url( 'admin.php?page=' . $slug ) ), 'bb_set_active_' . $p->ID ) ) . '">' . esc_html__( 'Set as active', 'bridge-builder' ) . '</a>';
		}
		$del_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'id' => $p->ID ), admin_url( 'admin.php?page=' . $slug ) ), 'bb_delete_' . $p->ID );
		echo ' | <a href="' . esc_url( $del_url ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( __( 'Move to Trash?', 'bridge-builder' ) ) . '\');">' . esc_html__( 'Trash', 'bridge-builder' ) . '</a>';
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Render Design System Studio — same root div as builder, React mounts with screen=design-system.
 * With post_id: edit that page's design system (per-page override). With no post_id: edit the global design system (applies to all builder pages).
 */
function bridge_builder_render_design_system_studio() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	// Same full-screen chrome whether editing global or per-page.
	echo '<style>
		html, body { overflow: hidden !important; height: 100% !important; margin: 0 !important; padding: 0 !important; }
		#wpcontent { padding: 0 !important; margin-left: 0 !important; height: 100% !important; }
		#wpbody, #wpbody-content { height: 100% !important; padding: 0 !important; overflow: hidden !important; }
		#wpfooter { display: none !important; }
		#adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar { display: none !important; }
		html.wp-toolbar { padding-top: 0 !important; }
	</style>';
	echo '<div id="bridge-builder-root"></div>';
}

/**
 * Render full-screen builder — just a root div, React takes over.
 */
function bridge_builder_render_editor() {
	// Remove admin chrome for full-screen experience
	echo '<style>
		html, body { overflow: hidden !important; height: 100% !important; margin: 0 !important; padding: 0 !important; }
		#wpcontent { padding: 0 !important; margin-left: 0 !important; height: 100% !important; }
		#wpbody, #wpbody-content { height: 100% !important; padding: 0 !important; overflow: hidden !important; }
		#wpfooter { display: none !important; }
		#adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar { display: none !important; }
		html.wp-toolbar { padding-top: 0 !important; }
	</style>';
	echo '<div id="bridge-builder-root"></div>';
}

// ---------------------------------------------------------------------------
// Front-end: Full template override for builder pages (no theme template)
// ---------------------------------------------------------------------------
// When a singular page/post has _builder_json, we load our own minimal template
// instead of the theme's. Theme never runs = no header/footer/main, no duplicates.
// Layout (full-bleed) is in Bridge_Builder_Frontend_Layout.

/**
 * Whether the current request is for a page/post that uses Bridge Builder content.
 * Used to always apply builder design on builder pages regardless of "Theme (layout only)" setting.
 *
 * @return bool True if singular and post has _builder_json (or draft in preview).
 */
function bridge_builder_is_builder_page() {
	if ( ! is_singular() ) {
		return false;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return false;
	}
	$json = get_post_meta( $post_id, '_builder_json', true );
	if ( $json ) {
		return true;
	}
	$is_preview = isset( $_GET['preview'] ) && isset( $_GET['_wpnonce'] ) && $_GET['preview'] === '1' && current_user_can( 'edit_post', $post_id );
	if ( $is_preview && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_preview_' . $post_id ) ) {
		$json = get_post_meta( $post_id, '_builder_json_draft', true ) ?: get_post_meta( $post_id, '_builder_json', true );
		return (bool) $json;
	}
	return false;
}

/**
 * Replace theme template with Bridge Builder full-page template for builder pages.
 * Prevents theme from loading; we render everything inside our template.
 *
 * @param string $template Path to the template file.
 * @return string Our template path for builder pages, else unchanged.
 */
function bridge_builder_template_include( $template ) {
	if ( ! bridge_builder_is_builder_page() ) {
		return $template;
	}
	return BRIDGE_BUILDER_PATH . 'templates/full-page.php';
}
add_filter( 'template_include', 'bridge_builder_template_include', 99 );

/**
 * Output builder page content at wp_body_open (after BB header). Only runs when our full-page template is used.
 */
function bridge_builder_output_singular_content() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$is_preview = isset( $_GET['preview'] ) && $_GET['preview'] === '1' && isset( $_GET['_wpnonce'] ) && current_user_can( 'edit_post', $post_id );
	$json = $is_preview && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bb_preview_' . $post_id )
		? ( get_post_meta( $post_id, '_builder_json_draft', true ) ?: get_post_meta( $post_id, '_builder_json', true ) )
		: get_post_meta( $post_id, '_builder_json', true );

	if ( empty( $json ) ) {
		return;
	}

	$renderer = new Bridge_Builder_Renderer();
	$html     = $renderer->render_from_json( $json );
	if ( $html === '' ) {
		return;
	}

	echo '<main id="bb-main" class="bb-main" role="main">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrap() returns trusted plugin-generated HTML.
	echo Bridge_Builder_Frontend_Layout::wrap( $html );
	echo '</main>';
}
add_action( 'wp_body_open', 'bridge_builder_output_singular_content', 15 );

/**
 * Body classes for builder pages and when BB global header/footer are active.
 */
function bridge_builder_body_class( $classes ) {
	if ( is_singular() ) {
		$pid = get_queried_object_id();
		if ( $pid && get_post_meta( $pid, '_builder_json', true ) ) {
			$classes[] = 'bb-builder-page';
		}
	}
	if ( bridge_builder_has_global_header() ) {
		$classes[] = 'bb-has-global-header';
	}
	if ( bridge_builder_has_global_footer() ) {
		$classes[] = 'bb-has-global-footer';
	}
	return $classes;
}
add_filter( 'body_class', 'bridge_builder_body_class' );

/**
 * Check whether a template's saved conditions match the current front-end request.
 *
 * @param array{scope:string,match:string,specific_ids:int[]} $conditions Template conditions.
 * @return bool
 */
function bridge_builder_template_matches_request( $conditions ) {
	if ( ! is_singular() ) {
		return false;
	}

	$scope = isset( $conditions['scope'] ) ? (string) $conditions['scope'] : 'site';
	if ( $scope === 'site' ) {
		return true;
	}
	if ( $scope === 'pages' ) {
		return is_singular( 'page' );
	}
	if ( $scope === 'posts' ) {
		return is_singular( 'post' );
	}
	if ( $scope === 'specific' ) {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return false;
		}
		$ids = isset( $conditions['specific_ids'] ) && is_array( $conditions['specific_ids'] )
			? array_map( 'absint', $conditions['specific_ids'] )
			: array();
		return in_array( (int) $post_id, $ids, true );
	}
	return false;
}

/**
 * Resolve the best matching global header/footer template for the current request.
 *
 * Priority rules:
 * 1) Match by conditions and highest priority (desc)
 * 2) Fallback to "active" option (for backward compatibility)
 *
 * @param string $type 'bb_header' or 'bb_footer'.
 * @return WP_Post|null
 */
function bridge_builder_resolve_global_template( $type ) {
	static $cache = array();
	if ( isset( $cache[ $type ] ) ) {
		return $cache[ $type ];
	}

	if ( ! in_array( $type, array( 'bb_header', 'bb_footer' ), true ) ) {
		$cache[ $type ] = null;
		return null;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		$cache[ $type ] = null;
		return null;
	}

	$items = get_posts(
		array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);

	$best_post     = null;
	$best_priority = PHP_INT_MIN;
	foreach ( $items as $item ) {
		$conditions = bridge_builder_get_template_conditions( $item->ID );
		if ( ! bridge_builder_template_matches_request( $conditions ) ) {
			continue;
		}
		$priority = bridge_builder_get_template_priority( $item->ID );
		if ( $priority > $best_priority ) {
			$best_priority = $priority;
			$best_post     = $item;
		}
	}
	if ( $best_post ) {
		$cache[ $type ] = $best_post;
		return $best_post;
	}

	// Backward-compatible fallback: previously selected active header/footer.
	$active_option = $type === 'bb_header' ? 'bridge_builder_active_header_id' : 'bridge_builder_active_footer_id';
	$active_id = (int) get_option( $active_option, 0 );
	if ( $active_id > 0 ) {
		$post = get_post( $active_id );
		if ( $post && $post->post_type === $type && $post->post_status === 'publish' ) {
			$cache[ $type ] = $post;
			return $post;
		}
	}

	$cache[ $type ] = null;
	return null;
}

/**
 * Whether a Bridge Builder global header is active (for theme compatibility).
 * Classic themes can add add_theme_support( 'bridge-builder-header-footer' ) and
 * skip outputting their header when this is true.
 *
 * @return bool
 */
function bridge_builder_has_global_header() {
	return (bool) bridge_builder_resolve_global_template( 'bb_header' );
}

/**
 * Whether a Bridge Builder global footer is active (for theme compatibility).
 *
 * @return bool
 */
function bridge_builder_has_global_footer() {
	return (bool) bridge_builder_resolve_global_template( 'bb_footer' );
}

// ---------------------------------------------------------------------------
// Add "Edit with Builder" link to post/page list tables
// ---------------------------------------------------------------------------

function bridge_builder_row_actions( $actions, $post ) {
	if ( in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
		$url = admin_url( 'admin.php?page=bridge-builder&post_id=' . $post->ID );
		$actions['bridge_builder'] = '<a href="' . esc_url( $url ) . '">Edit with Builder</a>';
	}
	return $actions;
}
add_filter( 'page_row_actions', 'bridge_builder_row_actions', 10, 2 );
add_filter( 'post_row_actions', 'bridge_builder_row_actions', 10, 2 );

// ---------------------------------------------------------------------------
// Admin bar: "Edit with Bridge Builder" when viewing a page/post
// ---------------------------------------------------------------------------

/**
 * Add "Edit with Bridge Builder" to the admin bar on the front end when viewing a singular page or post.
 */
function bridge_builder_admin_bar_menu( $wp_admin_bar ) {
	if ( ! is_singular() || ! is_user_logged_in() ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$edit_url = admin_url( 'admin.php?page=bridge-builder&post_id=' . $post_id );

	$wp_admin_bar->add_node( array(
		'id'     => 'bridge-builder-edit',
		'parent' => null,
		'title'  => __( 'Edit with Bridge Builder', 'bridge-builder' ),
		'href'   => $edit_url,
		'meta'   => array(
			'class' => 'bridge-builder-edit-link',
		),
	) );
}
add_action( 'admin_bar_menu', 'bridge_builder_admin_bar_menu', 80 );

// ---------------------------------------------------------------------------
// Front-end: Popups (global header/footer removed — focus on page builder)
// ---------------------------------------------------------------------------

function bridge_builder_output_popups() {
	$popups = get_posts( array(
		'post_type'      => 'bb_popup',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
	) );
	if ( empty( $popups ) ) {
		return;
	}
	$renderer = new Bridge_Builder_Renderer();
	foreach ( $popups as $p ) {
		$css = get_post_meta( $p->ID, '_builder_css', true );
		$html = $renderer->render_page( $p->ID );
		if ( $html !== '' ) {
			if ( ! empty( $css ) ) {
				echo '<style id="bb-popup-css-' . esc_attr( (string) $p->ID ) . '">' . "\n" . esc_html( $css ) . "\n" . '</style>';
			}
			echo '<div class="bb-popup" id="bb-popup-' . esc_attr( (string) $p->ID ) . '" data-bb-popup-id="' . esc_attr( (string) $p->ID ) . '" role="dialog" aria-modal="true" aria-hidden="true" style="display:none; position:fixed; inset:0; z-index:999999; align-items:center; justify-content:center;">';
			echo '<div class="bb-popup__backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.5);" data-bb-close></div>';
			echo '<div class="bb-popup__content" style="position:relative; max-width:90vw; max-height:90vh; overflow:auto;">' . wp_kses_post( $html ) . '</div>';
			echo '</div>';
		}
	}
}
add_action( 'wp_footer', 'bridge_builder_output_popups', 10 );
