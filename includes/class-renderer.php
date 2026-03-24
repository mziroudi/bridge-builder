<?php
/**
 * Bridge Builder Renderer
 *
 * Walks the JSON component tree and outputs semantic HTML.
 * This is the "SSR" layer – PHP renders the initial HTML for SEO and
 * first paint, then the React runtime hydrates it.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_Renderer {

	/**
	 * When inside a Query Loop, holds current post/author context for token replacement.
	 *
	 * @var array|null Keys: post, author, postId.
	 */
	private $loop_context = null;

	/**
	 * Render a full page from the component tree JSON.
	 *
	 * @param int $post_id The post ID to render.
	 * @return string The rendered HTML.
	 */
	public function render_page( $post_id ) {
		$json = get_post_meta( $post_id, '_builder_json', true );
		return $this->render_from_json( $json );
	}

	/**
	 * Render HTML from a JSON tree string (e.g. for preview using draft).
	 *
	 * @param string $json JSON string of the component tree.
	 * @return string Rendered HTML or empty string.
	 */
	public function render_from_json( $json ) {
		if ( empty( $json ) || ! is_string( $json ) ) {
			return '';
		}

		$tree = json_decode( $json, true );

		if ( ! $tree || ! is_array( $tree ) ) {
			return '';
		}

		$children = isset( $tree['children'] ) && is_array( $tree['children'] ) ? $tree['children'] : ( isset( $tree[0] ) ? $tree : array() );

		$html = '<div id="bb-render" class="bb-content">';
		foreach ( $children as $child ) {
			$html .= $this->render_component( $child );
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Recursively render a single component node to HTML.
	 *
	 * @param array $component The component node.
	 * @return string The rendered HTML.
	 */
	public function render_component( $component ) {
		if ( ! isset( $component['type'] ) || ! isset( $component['id'] ) ) {
			return '';
		}

		$type = $component['type'];
		$id   = sanitize_html_class( $component['id'] );
		$html = $this->render_component_inner( $component, $type, $id );
		return $this->maybe_wrap_custom_code( $html, $component );
	}

	/**
	 * Inner render by type (used so we can wrap all components with custom ID/class/CSS/JS).
	 *
	 * @param array  $component The component node.
	 * @param string $type      Component type.
	 * @param string $id        Sanitized ID.
	 * @return string HTML.
	 */
	private function render_component_inner( $component, $type, $id ) {
		switch ( $type ) {
			case 'Section':
				return $this->render_section( $component, $id );

			case 'Row':
				return $this->render_row( $component, $id );

			case 'Column':
				return $this->render_column( $component, $id );

			case 'Heading':
				return $this->render_heading( $component, $id );

			case 'Columns':
				return $this->render_columns( $component, $id );

			case 'Text':
				return $this->render_text( $component, $id );

			case 'Image':
				return $this->render_image( $component, $id );

			case 'Button':
				return $this->render_button( $component, $id );

			case 'Spacer':
				return $this->render_spacer( $component, $id );

			case 'Divider':
				return $this->render_divider( $component, $id );

			case 'Icon':
				return $this->render_icon( $component, $id );

			case 'IconBox':
				return $this->render_icon_box( $component, $id );

			case 'Card':
				return $this->render_card( $component, $id );

			case 'Video':
				return $this->render_video( $component, $id );

			case 'Testimonial':
				return $this->render_testimonial( $component, $id );

			case 'Container':
				return $this->render_container( $component, $id );

			case 'Grid':
				return $this->render_grid( $component, $id );

			case 'Counter':
				return $this->render_counter( $component, $id );

			case 'CTA':
				return $this->render_cta( $component, $id );

			case 'SocialIcons':
				return $this->render_social_icons( $component, $id );

			case 'Gallery':
				return $this->render_gallery( $component, $id );

			case 'Carousel':
				return $this->render_carousel( $component, $id );

			case 'PricingTable':
				return $this->render_pricing_table( $component, $id );

			case 'Tabs':
				return $this->render_tabs( $component, $id );

			case 'Accordion':
				return $this->render_accordion( $component, $id );

			case 'Toggle':
				return $this->render_toggle( $component, $id );

			case 'List':
				return $this->render_list( $component, $id );

			case 'Blockquote':
				return $this->render_blockquote( $component, $id );

			case 'ProgressBar':
				return $this->render_progress_bar( $component, $id );

			case 'VideoBox':
				return $this->render_video_box( $component, $id );

			case 'ImageBox':
				return $this->render_image_box( $component, $id );

			case 'ButtonGroup':
				return $this->render_button_group( $component, $id );

			case 'Countdown':
				return $this->render_countdown( $component, $id );

			case 'Menu':
				return $this->render_menu( $component, $id );

			case 'Shortcode':
				return $this->render_shortcode( $component, $id );

			case 'CustomHtml':
				return $this->render_custom_html( $component, $id );

			case 'TeamMember':
				return $this->render_team_member( $component, $id );

			case 'GoogleMaps':
				return $this->render_google_maps( $component, $id );

			case 'PostsGrid':
			case 'PostsCarousel':
				return $this->render_posts_grid( $component, $id );

			case 'ProductGrid':
				return $this->render_product_grid( $component, $id );

			case 'ProductTitle':
				return $this->render_product_title( $component, $id );
			case 'ProductPrice':
				return $this->render_product_price( $component, $id );
			case 'ProductImage':
				return $this->render_product_image( $component, $id );
			case 'ProductAddToCart':
				return $this->render_product_add_to_cart( $component, $id );
			case 'ProductMeta':
				return $this->render_product_meta( $component, $id );
			case 'ProductTabs':
				return $this->render_product_tabs( $component, $id );
			case 'RelatedProducts':
				return $this->render_related_products( $component, $id );
			case 'CartBlock':
				return $this->render_cart_block( $component, $id );
			case 'CheckoutBlock':
				return $this->render_checkout_block( $component, $id );
			case 'MiniCart':
				return $this->render_mini_cart( $component, $id );

			case 'PopupTrigger':
				return $this->render_popup_trigger( $component, $id );

			case 'ComparisonTable':
				return $this->render_comparison_table( $component, $id );

			case 'PricingCalculator':
				return $this->render_pricing_calculator( $component, $id );

			case 'LottieAnimation':
				return $this->render_lottie_animation( $component, $id );

			case 'DataTable':
				return $this->render_data_table( $component, $id );

			case 'Timeline':
				return $this->render_timeline( $component, $id );

			case 'BeforeAfterSlider':
				return $this->render_before_after_slider( $component, $id );

			case 'Chart':
				return $this->render_chart( $component, $id );

			case 'ConditionalDisplay':
				return $this->render_conditional_display( $component, $id );

			case 'CodeEmbed':
				return $this->render_code_embed( $component, $id );

			case 'ApiDataWidget':
				return $this->render_api_data_widget( $component, $id );

			case 'ContactForm':
				return $this->render_contact_form( $component, $id );

			case 'QueryLoop':
				return $this->render_query_loop( $component, $id );

			case 'PostTitle':
				return $this->render_post_title( $component, $id );

			case 'PostExcerpt':
				return $this->render_post_excerpt( $component, $id );

			case 'PostFeaturedImage':
				return $this->render_post_featured_image( $component, $id );

			case 'PostDate':
				return $this->render_post_date( $component, $id );

			case 'PostAuthor':
				return $this->render_post_author( $component, $id );

			case 'ReadingTime':
				return $this->render_reading_time( $component, $id );

			case 'ArchiveTitle':
				return $this->render_archive_title( $component, $id );

			case 'ArchiveDescription':
				return $this->render_archive_description( $component, $id );

			case 'Pagination':
				return $this->render_pagination( $component, $id );

			case 'NoResults':
				return $this->render_no_results( $component, $id );

			default:
				// Unknown component – render as div with children
				return $this->render_generic( $component, $id );
		}
	}

	/**
	 * Wrap component HTML with custom ID, class, CSS, and JS when set in props.
	 *
	 * @param string $html      Inner component HTML.
	 * @param array  $component The component node.
	 * @return string Wrapped HTML or original when no custom code.
	 */
	private function maybe_wrap_custom_code( $html, $component ) {
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$custom_id = isset( $props['customId'] ) && is_string( $props['customId'] ) ? sanitize_html_class( $props['customId'] ) : '';
		$custom_class_raw = isset( $props['customClassName'] ) && is_string( $props['customClassName'] ) ? trim( $props['customClassName'] ) : '';
		$custom_class = $custom_class_raw !== '' ? implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', $custom_class_raw, -1, PREG_SPLIT_NO_EMPTY ) ) ) : '';
		$custom_css = isset( $props['customCss'] ) && is_string( $props['customCss'] ) ? trim( $props['customCss'] ) : '';
		$custom_js  = isset( $props['customJs'] ) && is_string( $props['customJs'] ) ? trim( $props['customJs'] ) : '';

		if ( $custom_id === '' && $custom_class === '' && $custom_css === '' && $custom_js === '' ) {
			return $html;
		}

		$bb_id = esc_attr( $component['id'] );
		$id_attr = $custom_id !== '' ? ' id="' . esc_attr( $custom_id ) . '"' : '';
		$class_attr = ' class="bb-custom-root' . ( $custom_class !== '' ? ' ' . esc_attr( $custom_class ) : '' ) . '"';
		$out = '<div' . $id_attr . $class_attr . ' data-bb-id="' . $bb_id . '">';
		if ( $custom_css !== '' ) {
			$css_safe = wp_strip_all_tags( $custom_css );
			$css_safe = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $css_safe );
			$out .= '<style>' . $css_safe . '</style>';
		}
		$out .= $html;
		if ( $custom_js !== '' ) {
			$js_safe = preg_replace( '/<\/script\s*>/i', '', $custom_js );
			$out .= '<script>(function(){ var el = document.querySelector(\'[data-bb-id="' . $bb_id . '"]\'); if(!el) return; ' . $js_safe . ' })();</script>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render a Section component.
	 */
	private function render_section( $component, $id ) {
		$tag = isset( $component['props']['tagName'] )
			? sanitize_key( $component['props']['tagName'] )
			: 'section';

		$allowed_tags = array( 'section', 'div', 'article', 'header', 'footer', 'main', 'aside' );
		if ( ! in_array( $tag, $allowed_tags, true ) ) {
			$tag = 'section';
		}

		$bb_id     = esc_attr( $component['id'] );
		$video_url = isset( $component['props']['backgroundVideoUrl'] )
			? esc_url( $component['props']['backgroundVideoUrl'] )
			: '';

		$classes = 'bb-Section';
		if ( ! empty( $video_url ) ) {
			$classes .= ' bb-has-video';
		}

		$html = "<{$tag} id=\"bb-{$id}\" class=\"{$classes}\" data-bb-type=\"Section\" data-bb-id=\"{$bb_id}\">";

		if ( ! empty( $video_url ) ) {
			$html .= '<video class="bb-bg-video" src="' . $video_url . '" autoplay muted loop playsinline></video>';
		}

		$html .= $this->render_children( $component );
		$html .= "</{$tag}>";

		return $html;
	}

	/**
	 * Render a Columns component.
	 */
	private function render_columns( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$html  = "<div id=\"bb-{$id}\" class=\"bb-Columns\" data-bb-type=\"Columns\" data-bb-id=\"{$bb_id}\">";
		$html .= $this->render_children( $component );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a Text component.
	 */
	private function render_text( $component, $id ) {
		$tag = isset( $component['props']['tagName'] )
			? sanitize_key( $component['props']['tagName'] )
			: 'p';

		$allowed_tags = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div' );
		if ( ! in_array( $tag, $allowed_tags, true ) ) {
			$tag = 'p';
		}

		$content = isset( $component['props']['content'] ) && is_string( $component['props']['content'] )
			? $component['props']['content']
			: '';
		$content = $this->replace_tokens( $content );
		$content = wp_kses_post( $content );

		$bb_id = esc_attr( $component['id'] );
		return "<{$tag} id=\"bb-{$id}\" class=\"bb-Text\" data-bb-type=\"Text\" data-bb-id=\"{$bb_id}\">{$content}</{$tag}>";
	}

	/**
	 * Render a Row component.
	 */
	private function render_row( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$html  = "<div id=\"bb-{$id}\" class=\"bb-Row\" data-bb-type=\"Row\" data-bb-id=\"{$bb_id}\">";
		$html .= $this->render_children( $component );
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render a Column component.
	 */
	private function render_column( $component, $id ) {
		$bb_id     = esc_attr( $component['id'] );
		$video_url = isset( $component['props']['backgroundVideoUrl'] )
			? esc_url( $component['props']['backgroundVideoUrl'] )
			: '';

		$classes = 'bb-Column';
		if ( ! empty( $video_url ) ) {
			$classes .= ' bb-has-video';
		}

		$html  = "<div id=\"bb-{$id}\" class=\"{$classes}\" data-bb-type=\"Column\" data-bb-id=\"{$bb_id}\">";

		if ( ! empty( $video_url ) ) {
			$html .= '<video class="bb-bg-video" src="' . $video_url . '" autoplay muted loop playsinline></video>';
		}

		$html .= $this->render_children( $component );
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render a Heading component.
	 */
	private function render_heading( $component, $id ) {
		$level = isset( $component['props']['level'] )
			? sanitize_key( $component['props']['level'] )
			: 'h2';

		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
		if ( ! in_array( $level, $allowed, true ) ) {
			$level = 'h2';
		}

		$content = isset( $component['props']['content'] ) && is_string( $component['props']['content'] )
			? $component['props']['content']
			: '';
		$content = $this->replace_tokens( $content );
		$content = wp_kses_post( $content );

		$bb_id = esc_attr( $component['id'] );
		return "<{$level} id=\"bb-{$id}\" class=\"bb-Heading\" data-bb-type=\"Heading\" data-bb-id=\"{$bb_id}\">{$content}</{$level}>";
	}

	/**
	 * Render an Image component.
	 *
	 * If mediaId is available, use wp_get_attachment_image for srcset/sizes.
	 * Otherwise fall back to a plain <img> tag.
	 */
	private function render_image( $component, $id ) {
		$raw_src  = isset( $component['props']['src'] ) && is_string( $component['props']['src'] ) ? $component['props']['src'] : '';
		$raw_alt  = isset( $component['props']['alt'] ) && is_string( $component['props']['alt'] ) ? $component['props']['alt'] : '';
		$src      = $this->replace_tokens( $raw_src );
		$alt      = $this->replace_tokens( $raw_alt );
		$src      = esc_url( $src );
		$alt      = esc_attr( $alt );
		$media_id = isset( $component['props']['mediaId'] ) ? absint( $component['props']['mediaId'] ) : 0;
		$bb_id    = esc_attr( $component['id'] );

		if ( empty( $src ) && ! $media_id ) {
			return "<div id=\"bb-{$id}\" class=\"bb-Image bb-Image--placeholder\" data-bb-type=\"Image\" data-bb-id=\"{$bb_id}\">No image selected</div>";
		}

		// If we have a WP attachment ID and src was not token-replaced, use wp_get_attachment_image for srcset
		$src_has_token = is_string( $raw_src ) && strpos( $raw_src, '{' ) !== false;
		if ( $media_id && wp_attachment_is_image( $media_id ) && ! $src_has_token ) {
			$img = wp_get_attachment_image( $media_id, 'large', false, array(
				'id'           => "bb-{$id}",
				'class'        => 'bb-Image',
				'data-bb-type' => 'Image',
				'data-bb-id'   => $component['id'],
				'loading'      => 'lazy',
				'alt'          => $alt,
			) );
			if ( $img ) {
				return $img;
			}
		}

		// Fallback: plain img tag (or token-replaced URL)
		return "<img id=\"bb-{$id}\" class=\"bb-Image\" data-bb-type=\"Image\" data-bb-id=\"{$bb_id}\" src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" />";
	}

	/**
	 * Render a Spacer component.
	 */
	private function render_spacer( $component, $id ) {
		$height = isset( $component['props']['height'] )
			? esc_attr( $component['props']['height'] )
			: '40px';

		$bb_id = esc_attr( $component['id'] );
		return "<div id=\"bb-{$id}\" class=\"bb-Spacer\" data-bb-type=\"Spacer\" data-bb-id=\"{$bb_id}\" style=\"height:{$height}\" aria-hidden=\"true\"></div>";
	}

	/**
	 * Render a Divider component.
	 */
	private function render_divider( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		return "<hr id=\"bb-{$id}\" class=\"bb-Divider\" data-bb-type=\"Divider\" data-bb-id=\"{$bb_id}\" />";
	}

	/**
	 * Render a Button component.
	 */
	private function render_button( $component, $id ) {
		$content = isset( $component['props']['content'] ) && is_string( $component['props']['content'] )
			? $component['props']['content']
			: 'Click Me';
		$content = $this->replace_tokens( $content );
		$content = esc_html( $content );

		$href = isset( $component['props']['href'] ) && is_string( $component['props']['href'] )
			? $component['props']['href']
			: '#';
		$href = $this->replace_tokens( $href );
		if ( in_the_loop() && get_the_ID() && ( '' === $href || '#' === $href ) ) {
			$href = get_permalink();
		}
		$href = esc_url( $href );

		$target = isset( $component['props']['target'] )
			? esc_attr( $component['props']['target'] )
			: '_self';

		$rel = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';

		$bb_id = esc_attr( $component['id'] );
		return "<a id=\"bb-{$id}\" class=\"bb-Button\" data-bb-type=\"Button\" data-bb-id=\"{$bb_id}\" href=\"{$href}\" target=\"{$target}\"{$rel}>{$content}</a>";
	}

	/**
	 * Render an Icon component (fallback: span with data attr; React hydrates with real icon).
	 */
	private function render_icon( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$icon    = isset( $component['props']['icon'] ) ? esc_attr( $component['props']['icon'] ) : 'Star';
		$size    = isset( $component['props']['size'] ) ? absint( $component['props']['size'] ) : 24;
		$color   = isset( $component['props']['color'] ) ? esc_attr( $component['props']['color'] ) : 'currentColor';
		$href    = isset( $component['props']['link'] ) ? esc_url( $component['props']['link'] ) : '';
		$target  = isset( $component['props']['target'] ) ? esc_attr( $component['props']['target'] ) : '_self';
		$rel     = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';
		$inline  = sprintf( 'display:inline-block;width:%dpx;height:%dpx;color:%s', $size, $size, $color );
		$custom  = isset( $component['props']['customSvg'] ) && is_string( $component['props']['customSvg'] ) && preg_match( '/^\s*<svg/i', $component['props']['customSvg'] ) ? wp_kses( $component['props']['customSvg'], $this->get_svg_allowed_html() ) : '';
		$inner   = $custom ? sprintf( '<span class="bb-Icon bb-Icon--custom" style="width:%dpx;height:%dpx;display:inline-block;color:%s">%s</span>', $size, $size, $color, $custom ) : sprintf( '<span class="bb-Icon__fallback" data-bb-icon="%s" aria-hidden="true"></span>', $icon );
		if ( $href ) {
			return sprintf(
				'<a id="bb-%s" class="bb-Icon bb-Icon--link" data-bb-type="Icon" data-bb-id="%s" href="%s" target="%s"%s style="%s">%s</a>',
				$id,
				$bb_id,
				$href,
				$target,
				$rel,
				$inline,
				$inner
			);
		}
		return sprintf(
			'<span id="bb-%s" class="bb-Icon" data-bb-type="Icon" data-bb-id="%s" style="%s">%s</span>',
			$id,
			$bb_id,
			$inline,
			$inner
		);
	}

	/**
	 * Render an IconBox component.
	 */
	private function render_icon_box( $component, $id ) {
		$bb_id       = esc_attr( $component['id'] );
		$title       = isset( $component['props']['title'] ) ? esc_html( $component['props']['title'] ) : 'Title';
		$description = isset( $component['props']['description'] ) ? wp_kses_post( $component['props']['description'] ) : 'Description text.';
		$position    = isset( $component['props']['position'] ) ? esc_attr( $component['props']['position'] ) : 'top';
		$icon_size   = isset( $component['props']['iconSize'] ) ? absint( $component['props']['iconSize'] ) : 32;
		$icon_color  = isset( $component['props']['iconColor'] ) ? esc_attr( $component['props']['iconColor'] ) : 'currentColor';
		$href        = isset( $component['props']['link'] ) ? esc_url( $component['props']['link'] ) : '';
		$target      = isset( $component['props']['target'] ) ? esc_attr( $component['props']['target'] ) : '_self';
		$rel         = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';
		$icon_name   = isset( $component['props']['icon'] ) ? esc_attr( $component['props']['icon'] ) : 'Star';
		$custom_svg  = isset( $component['props']['customSvg'] ) && is_string( $component['props']['customSvg'] ) && preg_match( '/^\s*<svg/i', $component['props']['customSvg'] ) ? wp_kses( $component['props']['customSvg'], $this->get_svg_allowed_html() ) : '';
		$flex_dir    = ( 'left' === $position || 'right' === $position ) ? 'row' : 'column';
		$icon_span   = $custom_svg
			? sprintf( '<span class="bb-IconBox__icon" style="color:%s;flex-shrink:0;width:%dpx;height:%dpx;display:block">%s</span>', $icon_color, $icon_size, $icon_size, $custom_svg )
			: sprintf( '<span class="bb-IconBox__icon" style="color:%s;flex-shrink:0;width:%dpx;height:%dpx;">[%s]</span>', $icon_color, $icon_size, $icon_size, $icon_name );
		$content = '<div class="bb-IconBox__content"><div class="bb-IconBox__title">' . $title . '</div><div class="bb-IconBox__description">' . $description . '</div></div>';
		$parts   = array();
		if ( 'left' === $position ) {
			$parts[] = $icon_span;
			$parts[] = $content;
		} elseif ( 'right' === $position ) {
			$parts[] = $content;
			$parts[] = $icon_span;
		} else {
			$parts[] = '<div style="margin-bottom:8px">' . $icon_span . '</div>';
			$parts[] = $content;
		}
		$inner = implode( '', $parts );
		$style = sprintf( 'display:flex;flex-direction:%s;align-items:flex-start;gap:12px;', $flex_dir );
		if ( $href ) {
			return sprintf(
				'<a id="bb-%s" class="bb-IconBox bb-IconBox--link" data-bb-type="IconBox" data-bb-id="%s" href="%s" target="%s"%s style="%s">%s</a>',
				$id,
				$bb_id,
				$href,
				$target,
				$rel,
				$style,
				$inner
			);
		}
		return sprintf(
			'<div id="bb-%s" class="bb-IconBox" data-bb-type="IconBox" data-bb-id="%s" style="%s">%s</div>',
			$id,
			$bb_id,
			$style,
			$inner
		);
	}

	/**
	 * Allowed HTML for custom SVG (Icon/IconBox) to pass wp_kses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function get_svg_allowed_html() {
		return array(
			'svg'      => array( 'xmlns' => true, 'viewbox' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'class' => true, 'aria-hidden' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
		);
	}

	/**
	 * Render a Card component (image, title, text, button).
	 */
	private function render_card( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$src     = isset( $component['props']['src'] ) ? esc_url( $component['props']['src'] ) : '';
		$alt     = isset( $component['props']['alt'] ) ? esc_attr( $component['props']['alt'] ) : '';
		$title   = isset( $component['props']['title'] ) ? $this->replace_tokens( $component['props']['title'] ) : 'Card title';
		$title   = esc_html( $title );
		$text    = isset( $component['props']['text'] ) ? $this->replace_tokens( $component['props']['text'] ) : '';
		$text    = wp_kses_post( $text );
		$btn     = isset( $component['props']['buttonLabel'] ) ? $this->replace_tokens( $component['props']['buttonLabel'] ) : 'Learn more';
		$btn     = esc_html( $btn );
		$href    = isset( $component['props']['buttonLink'] ) ? $component['props']['buttonLink'] : '';
		$href    = $this->replace_tokens( $href );
		$href    = esc_url( $href ? $href : '#' );
		$target  = isset( $component['props']['buttonTarget'] ) ? esc_attr( $component['props']['buttonTarget'] ) : '_self';
		$rel     = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';

		$img_block = '';
		if ( $src ) {
			$img_block = sprintf( '<div style="aspect-ratio:16/10;overflow:hidden;flex-shrink:0;"><img src="%s" alt="%s" style="width:100%%;height:100%%;object-fit:cover;display:block;" /></div>', $src, $alt );
		} else {
			$img_block = '<div style="aspect-ratio:16/10;background:var(--bb-bg-tertiary,#f1f5f9);display:flex;align-items:center;justify-content:center;color:var(--bb-text-muted,#94a3b8);font-size:12px;">' . esc_html__( 'No image', 'bridge-builder' ) . '</div>';
		}

		$button_block = '';
		if ( $btn ) {
			$button_block = sprintf( '<a href="%s" target="%s"%s class="bb-Card__button bb-Button" style="align-self:flex-start;display:inline-block;padding:10px 20px;font-size:var(--text-sm,14px);font-weight:600;color:var(--color-background,#fff);background:var(--color-primary,#111);border-radius:var(--radius-md,6px);text-decoration:none;">%s</a>', $href, $target, $rel, $btn );
		}

		$inner = $img_block . '<div style="padding:var(--space-6,24px);flex:1;display:flex;flex-direction:column;gap:12px;">' .
			'<h3 class="bb-Card__title" style="margin:0;font-size:var(--text-xl,20px);font-weight:var(--font-weight-bold,700);line-height:var(--line-height-tight,1.25);color:var(--color-foreground,#111);">' . $title . '</h3>' .
			'<p class="bb-Card__text" style="margin:0;flex:1;font-size:var(--text-md,16px);line-height:var(--line-height-normal,1.6);color:var(--color-foreground,#374151);opacity:0.9;">' . $text . '</p>' .
			$button_block . '</div>';

		return sprintf(
			'<div id="bb-%s" class="bb-Card" data-bb-type="Card" data-bb-id="%s" style="display:flex;flex-direction:column;height:100%%;background:var(--color-background,#fff);border-radius:var(--radius-lg,8px);overflow:hidden;box-shadow:var(--shadow-md);border:1px solid var(--bb-border,#e5e7eb);">%s</div>',
			$id,
			$bb_id,
			$inner
		);
	}

	/**
	 * Render a Video component.
	 */
	private function render_video( $component, $id ) {
		$bb_id        = esc_attr( $component['id'] );
		$type         = isset( $component['props']['type'] ) ? sanitize_key( $component['props']['type'] ) : 'self-hosted';
		$src          = isset( $component['props']['src'] ) ? esc_url( $component['props']['src'] ) : '';
		$poster       = isset( $component['props']['poster'] ) ? esc_url( $component['props']['poster'] ) : '';
		$aspect_ratio = isset( $component['props']['aspectRatio'] ) ? esc_attr( $component['props']['aspectRatio'] ) : '16/9';
		$autoplay     = filter_var( $component['props']['autoplay'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$loop         = filter_var( $component['props']['loop'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$muted        = filter_var( $component['props']['muted'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$controls     = filter_var( $component['props']['controls'] ?? true, FILTER_VALIDATE_BOOLEAN );

		$style = "position:relative;width:100%;overflow:hidden;border-radius:8px;aspect-ratio:{$aspect_ratio};max-width:100%;";

		if ( 'youtube' === $type && $src ) {
			$embed_id = preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $src, $m ) ? $m[1] : ( 11 === strlen( trim( $src ) ) ? trim( $src ) : '' );
			$embed_url = $embed_id ? 'https://www.youtube.com/embed/' . $embed_id : '';
			if ( $embed_url ) {
				$embed_url .= ( strpos( $embed_url, '?' ) !== false ? '&' : '?' ) . 'autoplay=' . ( $autoplay ? '1' : '0' ) . '&mute=' . ( $muted ? '1' : '0' ) . '&controls=' . ( $controls ? '1' : '0' );
				return sprintf( '<div id="bb-%s" class="bb-Video bb-Video--embed" data-bb-type="Video" data-bb-id="%s" style="%s"><iframe src="%s" title="YouTube video" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;"></iframe></div>', $id, $bb_id, $style, esc_url( $embed_url ) );
			}
		}

		if ( 'vimeo' === $type && $src ) {
			$embed_id = preg_match( '/vimeo\.com\/(\d+)/', $src, $m ) ? $m[1] : ( is_numeric( trim( $src ) ) ? trim( $src ) : '' );
			$embed_url = $embed_id ? 'https://player.vimeo.com/video/' . $embed_id : '';
			if ( $embed_url ) {
				return sprintf( '<div id="bb-%s" class="bb-Video bb-Video--embed" data-bb-type="Video" data-bb-id="%s" style="%s"><iframe src="%s" title="Vimeo video" allow="autoplay;fullscreen;picture-in-picture" allowfullscreen style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;"></iframe></div>', $id, $bb_id, $style, esc_url( $embed_url ) );
			}
		}

		if ( 'self-hosted' === $type && $src ) {
			$poster_attr = $poster ? ' poster="' . $poster . '"' : '';
			$ap          = $autoplay ? ' autoplay' : '';
			$lp          = $loop ? ' loop' : '';
			$mu          = $muted ? ' muted' : '';
			$ctrl        = $controls ? ' controls' : '';
			return sprintf( '<div id="bb-%s" class="bb-Video bb-Video--native" data-bb-type="Video" data-bb-id="%s" style="%s"><video src="%s"%s%s%s%s%s playsinline style="position:absolute;top:0;left:0;width:100%%;height:100%%;object-fit:cover;"></video></div>', $id, $bb_id, $style, $src, $poster_attr, $ap, $lp, $mu, $ctrl );
		}

		return sprintf( '<div id="bb-%s" class="bb-Video bb-Video--placeholder" data-bb-type="Video" data-bb-id="%s" style="%s">%s</div>', $id, $bb_id, $style . 'background:#f1f3f5;display:flex;align-items:center;justify-content:center;color:#9aa1b0;font-size:12px;', esc_html__( 'Add video URL', 'bridge-builder' ) );
	}

	/**
	 * Render a Testimonial component.
	 */
	private function render_testimonial( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$quote  = isset( $component['props']['quote'] ) ? wp_kses_post( $component['props']['quote'] ) : 'Customer quote here.';
		$author = isset( $component['props']['author'] ) ? esc_html( $component['props']['author'] ) : 'Author Name';
		$role   = isset( $component['props']['role'] ) ? esc_html( $component['props']['role'] ) : '';
		$company = isset( $component['props']['company'] ) ? esc_html( $component['props']['company'] ) : '';
		$avatar  = isset( $component['props']['avatar'] ) ? esc_url( $component['props']['avatar'] ) : '';
		$rating  = isset( $component['props']['rating'] ) ? absint( $component['props']['rating'] ) : 0;
		$rating  = min( 5, max( 0, $rating ) );
		$style_preset = isset( $component['props']['style'] ) ? sanitize_key( $component['props']['style'] ) : 'card';

		$rating_html = '';
		if ( $rating > 0 ) {
			$rating_html = '<div class="bb-Testimonial__rating" style="display:flex;gap:2px;margin-bottom:12px;color:#f59e0b;">';
			for ( $i = 0; $i < 5; $i++ ) {
				$fill = $i < $rating ? 'currentColor' : 'none';
				$op   = $i < $rating ? 1 : 0.3;
				$rating_html .= '<span style="display:inline-block;width:16px;height:16px;fill:' . $fill . ';opacity:' . $op . ';">★</span>';
			}
			$rating_html .= '</div>';
		}

		$author_line = $role || $company ? ' <span style="font-size:13px;color:#64748b;">' . esc_html( implode( ' · ', array_filter( array( $role, $company ) ) ) ) . '</span>' : '';
		$avatar_html = $avatar
			? '<img src="' . $avatar . '" alt="' . $author . '" style="width:48px;height:48px;border-radius:50%;object-fit:cover;" />'
			: '<div style="width:48px;height:48px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:600;color:#64748b;">' . esc_html( mb_substr( $author, 0, 1 ) ) . '</div>';

		$inner = $rating_html . '<blockquote class="bb-Testimonial__quote" style="margin:0 0 16px 0;font-size:1.05em;line-height:1.6;">' . $quote . '</blockquote><footer class="bb-Testimonial__author" style="display:flex;align-items:center;gap:12px;">' . $avatar_html . '<div><strong>' . $author . '</strong>' . $author_line . '</div></footer>';

		$style = 'padding:24px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;';
		if ( 'bubble' === $style_preset ) {
			$style = 'padding:20px 24px;border-radius:20px;border-bottom-left-radius:4px;background:#f1f5f9;';
		} elseif ( 'minimal' === $style_preset ) {
			$style = 'padding:16px 0;border-left:4px solid #2563eb;padding-left:20px;';
		}

		return sprintf( '<div id="bb-%s" class="bb-Testimonial bb-Testimonial--%s" data-bb-type="Testimonial" data-bb-id="%s" style="%s">%s</div>', $id, esc_attr( $style_preset ), $bb_id, $style, $inner );
	}

	/**
	 * Render a Container component (flexible wrapper with optional max-width).
	 */
	private function render_container( $component, $id ) {
		$tag = isset( $component['props']['tagName'] ) ? sanitize_key( $component['props']['tagName'] ) : 'div';
		$allowed = array( 'div', 'section', 'article', 'main', 'aside' );
		if ( ! in_array( $tag, $allowed, true ) ) {
			$tag = 'div';
		}
		$bb_id    = esc_attr( $component['id'] );
		$max_width = isset( $component['props']['maxWidth'] ) && is_string( $component['props']['maxWidth'] ) ? esc_attr( $component['props']['maxWidth'] ) : '';
		$style    = 'width:100%;margin-left:auto;margin-right:auto;box-sizing:border-box;';
		if ( $max_width !== '' ) {
			$style .= 'max-width:' . $max_width . ';';
		}
		$html = "<{$tag} id=\"bb-{$id}\" class=\"bb-Container\" data-bb-type=\"Container\" data-bb-id=\"{$bb_id}\" style=\"{$style}\">";
		$html .= $this->render_children( $component );
		$html .= "</{$tag}>";
		return $html;
	}

	/**
	 * Render a Grid component (CSS grid layout).
	 */
	private function render_grid( $component, $id ) {
		$bb_id     = esc_attr( $component['id'] );
		$columns   = isset( $component['props']['columns'] ) ? absint( $component['props']['columns'] ) : 3;
		$columns   = $columns >= 1 && $columns <= 12 ? $columns : 3;
		$gap       = isset( $component['props']['gap'] ) && is_string( $component['props']['gap'] ) ? esc_attr( $component['props']['gap'] ) : '20px';
		$auto_fit  = isset( $component['props']['autoFit'] ) && ( $component['props']['autoFit'] === true || $component['props']['autoFit'] === 'true' );
		$grid_cols = $auto_fit ? 'repeat(auto-fit, minmax(min(100%, 200px), 1fr))' : 'repeat(' . $columns . ', 1fr)';
		$style     = 'display:grid;gap:' . $gap . ';width:100%;grid-template-columns:' . $grid_cols . ';';
		$html      = '<div id="bb-' . $id . '" class="bb-Grid" data-bb-type="Grid" data-bb-id="' . $bb_id . '" style="' . $style . '">';
		$html     .= $this->render_children( $component );
		$html     .= '</div>';
		return $html;
	}

	/**
	 * Render a Counter component.
	 */
	private function render_counter( $component, $id ) {
		$bb_id    = esc_attr( $component['id'] );
		$end_val  = isset( $component['props']['endValue'] ) ? absint( $component['props']['endValue'] ) : 0;
		$prefix   = isset( $component['props']['prefix'] ) && is_string( $component['props']['prefix'] ) ? esc_html( $component['props']['prefix'] ) : '';
		$suffix   = isset( $component['props']['suffix'] ) && is_string( $component['props']['suffix'] ) ? esc_html( $component['props']['suffix'] ) : '';
		$inner    = $prefix . '<span class="bb-Counter__value">' . $end_val . '</span>' . $suffix;
		return '<div id="bb-' . $id . '" class="bb-Counter" data-bb-type="Counter" data-bb-id="' . $bb_id . '">' . $inner . '</div>';
	}

	/**
	 * Render a CTA (Call to action) component.
	 */
	private function render_cta( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$title   = isset( $component['props']['title'] ) && is_string( $component['props']['title'] ) ? esc_html( $component['props']['title'] ) : 'Call to action';
		$desc    = isset( $component['props']['description'] ) && is_string( $component['props']['description'] ) ? esc_html( $component['props']['description'] ) : '';
		$btn_txt = isset( $component['props']['buttonText'] ) && is_string( $component['props']['buttonText'] ) ? esc_html( $component['props']['buttonText'] ) : 'Learn more';
		$btn_url = isset( $component['props']['buttonUrl'] ) ? esc_url( $component['props']['buttonUrl'] ) : '#';
		$target  = isset( $component['props']['buttonTarget'] ) && $component['props']['buttonTarget'] === '_blank' ? '_blank' : '_self';
		$rel     = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
		$html    = '<div id="bb-' . $id . '" class="bb-CTA" data-bb-type="CTA" data-bb-id="' . $bb_id . '" style="width:100%;text-align:center;padding:40px 24px;">';
		$html   .= '<h3 class="bb-CTA__title" style="margin:0 0 12px;font-size:24px;font-weight:700;">' . $title . '</h3>';
		if ( $desc !== '' ) {
			$html .= '<p class="bb-CTA__description" style="margin:0 0 20px;line-height:1.6;">' . $desc . '</p>';
		}
		$html .= '<a href="' . $btn_url . '" target="' . esc_attr( $target ) . '"' . $rel . ' class="bb-CTA__button" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;">' . $btn_txt . '</a>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse social networks list (array prop first, then legacy networksJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{network: string, url: string}> Array of network items.
	 */
	private function parse_networks_list( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['networks'] ) && is_array( $props['networks'] ) && ! empty( $props['networks'] ) ) {
			$out = array();
			foreach ( $props['networks'] as $n ) {
				$out[] = array(
					'network' => isset( $n['network'] ) && is_string( $n['network'] ) ? $n['network'] : 'link',
					'url'     => isset( $n['url'] ) && is_string( $n['url'] ) ? esc_url( $n['url'] ) : '#',
				);
			}
			return $out;
		}
		$raw    = isset( $props['networksJson'] ) ? $props['networksJson'] : '[]';
		$arr    = is_string( $raw ) ? json_decode( $raw, true ) : array();
		$list   = is_array( $arr ) ? $arr : array();
		$out = array();
		foreach ( $list as $n ) {
			$out[] = array(
				'network' => isset( $n['network'] ) && is_string( $n['network'] ) ? $n['network'] : 'link',
				'url'     => isset( $n['url'] ) && is_string( $n['url'] ) ? esc_url( $n['url'] ) : '#',
			);
		}
		return $out;
	}

	/**
	 * Render Social Icons component.
	 */
	private function render_social_icons( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$list  = $this->parse_networks_list( $component );
		$size  = isset( $component['props']['size'] ) ? absint( $component['props']['size'] ) : 40;
		$icons  = array( 'facebook' => 'f', 'twitter' => '𝕏', 'instagram' => '📷', 'linkedin' => 'in', 'youtube' => '▶', 'link' => '↗' );
		$html   = '<div id="bb-' . $id . '" class="bb-SocialIcons" data-bb-type="SocialIcons" data-bb-id="' . $bb_id . '" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">';
		foreach ( $list as $n ) {
			$net  = isset( $n['network'] ) && is_string( $n['network'] ) ? $n['network'] : 'link';
			$url  = isset( $n['url'] ) && is_string( $n['url'] ) ? esc_url( $n['url'] ) : '#';
			$char = isset( $icons[ $net ] ) ? $icons[ $net ] : $icons['link'];
			$html .= '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $net ) . '" style="display:inline-flex;align-items:center;justify-content:center;width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;background:#f1f5f9;color:#475569;text-decoration:none;font-size:' . ( $size / 2 ) . 'px;">' . esc_html( $char ) . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse gallery images (array prop first, then legacy imagesJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{src: string, alt: string}> Array of image items.
	 */
	private function parse_gallery_images( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['images'] ) && is_array( $props['images'] ) ) {
			$out = array();
			foreach ( $props['images'] as $i ) {
				if ( ! is_array( $i ) || empty( $i['src'] ) || ! is_string( $i['src'] ) ) {
					continue;
				}
				$out[] = array(
					'src' => esc_url( $i['src'] ),
					'alt' => isset( $i['alt'] ) && is_string( $i['alt'] ) ? esc_attr( $i['alt'] ) : '',
				);
			}
			return $out;
		}
		$raw = isset( $props['imagesJson'] ) ? $props['imagesJson'] : '[]';
		if ( ! is_string( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $i ) {
			if ( ! is_array( $i ) || empty( $i['src'] ) || ! is_string( $i['src'] ) ) {
				continue;
			}
			$out[] = array(
				'src' => esc_url( $i['src'] ),
				'alt' => isset( $i['alt'] ) && is_string( $i['alt'] ) ? esc_attr( $i['alt'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Render a Gallery component.
	 */
	private function render_gallery( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$images  = $this->parse_gallery_images( $component );
		$columns = isset( $component['props']['columns'] ) ? absint( $component['props']['columns'] ) : 3;
		$columns = $columns >= 1 && $columns <= 6 ? $columns : 3;
		$style   = 'display:grid;grid-template-columns:repeat(' . $columns . ',1fr);gap:16px;width:100%;';
		$html    = '<div id="bb-' . $id . '" class="bb-Gallery" data-bb-type="Gallery" data-bb-id="' . $bb_id . '" style="' . $style . '">';
		foreach ( $images as $img ) {
			$html .= '<div style="aspect-ratio:1;overflow:hidden;border-radius:8px;"><img src="' . $img['src'] . '" alt="' . $img['alt'] . '" style="width:100%;height:100%;object-fit:cover;" loading="lazy" /></div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse carousel slides (array prop first, then legacy slidesJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{content: string, image?: string}> Array of slides.
	 */
	private function parse_carousel_slides( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['slides'] ) && is_array( $props['slides'] ) && ! empty( $props['slides'] ) ) {
			$out = array();
			foreach ( $props['slides'] as $s ) {
				$out[] = array(
					'content' => isset( $s['content'] ) && is_string( $s['content'] ) ? $s['content'] : 'Slide',
					'image'   => isset( $s['image'] ) && is_string( $s['image'] ) ? esc_url( $s['image'] ) : '',
				);
			}
			return $out;
		}
		$raw = isset( $props['slidesJson'] ) ? $props['slidesJson'] : '[{"content":"Slide 1"}]';
		if ( ! is_string( $raw ) ) {
			return array( array( 'content' => 'Slide 1' ) );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return array( array( 'content' => 'Slide 1' ) );
		}
		$out = array();
		foreach ( $decoded as $s ) {
			$out[] = array(
				'content' => isset( $s['content'] ) && is_string( $s['content'] ) ? $s['content'] : 'Slide',
				'image'   => isset( $s['image'] ) && is_string( $s['image'] ) ? esc_url( $s['image'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Render a Carousel component (first slide visible; JS runtime handles switching).
	 */
	private function render_carousel( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$slides = $this->parse_carousel_slides( $component );
		$html   = '<div id="bb-' . $id . '" class="bb-Carousel" data-bb-type="Carousel" data-bb-id="' . $bb_id . '" style="width:100%;position:relative;overflow:hidden;">';
		$html  .= '<div style="display:flex;">';
		foreach ( $slides as $s ) {
			$html .= '<div style="min-width:100%;padding:24px;box-sizing:border-box;text-align:center;">';
			if ( ! empty( $s['image'] ) ) {
				$html .= '<img src="' . $s['image'] . '" alt="" style="max-width:100%;height:auto;margin-bottom:12px;" loading="lazy" />';
			}
			$html .= '<div>' . esc_html( $s['content'] ) . '</div></div>';
		}
		$html .= '</div><div class="bb-Carousel__dots" style="display:flex;justify-content:center;gap:8px;margin-top:12px;">';
		foreach ( array_keys( $slides ) as $i ) {
			$html .= '<button type="button" aria-label="Slide ' . ( $i + 1 ) . '" style="width:8px;height:8px;border-radius:50%;border:none;background:#cbd5e1;cursor:pointer;"></button>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Parse pricing plans (array prop first, then legacy plansJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{name: string, price: string, period: string, features: string, featured: bool}> Array of plans.
	 */
	private function parse_pricing_plans( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['plans'] ) && is_array( $props['plans'] ) && ! empty( $props['plans'] ) ) {
			$out = array();
			foreach ( $props['plans'] as $pl ) {
				$out[] = array(
					'name'     => isset( $pl['name'] ) && is_string( $pl['name'] ) ? $pl['name'] : 'Plan',
					'price'    => isset( $pl['price'] ) && is_string( $pl['price'] ) ? $pl['price'] : '$0',
					'period'   => isset( $pl['period'] ) && is_string( $pl['period'] ) ? $pl['period'] : '/mo',
					'features' => isset( $pl['features'] ) && is_string( $pl['features'] ) ? $pl['features'] : '',
					'featured' => ! empty( $pl['featured'] ),
				);
			}
			return $out;
		}
		$raw = isset( $props['plansJson'] ) ? $props['plansJson'] : '[]';
		if ( ! is_string( $raw ) ) {
			return array( array( 'name' => 'Basic', 'price' => '$0', 'period' => '/mo', 'features' => 'Feature 1', 'featured' => false ) );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return array( array( 'name' => 'Basic', 'price' => '$0', 'period' => '/mo', 'features' => 'Feature 1', 'featured' => false ) );
		}
		$out = array();
		foreach ( $decoded as $pl ) {
			$out[] = array(
				'name'     => isset( $pl['name'] ) && is_string( $pl['name'] ) ? $pl['name'] : 'Plan',
				'price'    => isset( $pl['price'] ) && is_string( $pl['price'] ) ? $pl['price'] : '$0',
				'period'   => isset( $pl['period'] ) && is_string( $pl['period'] ) ? $pl['period'] : '/mo',
				'features' => isset( $pl['features'] ) && is_string( $pl['features'] ) ? $pl['features'] : '',
				'featured' => ! empty( $pl['featured'] ),
			);
		}
		return $out;
	}

	/**
	 * Render a Pricing Table component.
	 */
	private function render_pricing_table( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$plans = $this->parse_pricing_plans( $component );
		$count = count( $plans );
		$style = 'display:grid;grid-template-columns:repeat(' . max( 1, $count ) . ',1fr);gap:24px;width:100%;max-width:100%;';
		$html  = '<div id="bb-' . $id . '" class="bb-PricingTable" data-bb-type="PricingTable" data-bb-id="' . $bb_id . '" style="' . $style . '">';
		foreach ( $plans as $plan ) {
			$plan_style = 'border:1px solid #e2e8f0;border-radius:12px;padding:24px;background:#fff;';
			if ( ! empty( $plan['featured'] ) ) {
				$plan_style = 'border:1px solid #2563eb;border-radius:12px;padding:24px;background:#f0f9ff;';
			}
			$html .= '<div style="' . $plan_style . '">';
			$html .= '<div style="font-weight:700;font-size:18px;margin-bottom:8px;">' . esc_html( $plan['name'] ) . '</div>';
			$html .= '<div style="font-size:28px;font-weight:700;margin-bottom:4px;">' . esc_html( $plan['price'] ) . '<span style="font-size:14px;font-weight:400;">' . esc_html( $plan['period'] ) . '</span></div>';
			$html .= '<div style="white-space:pre-line;font-size:14px;line-height:1.6;">' . esc_html( $plan['features'] ) . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse tabs array (array prop first, then legacy tabsJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{title: string, content: string}> Array of tab items.
	 */
	private function parse_tabs_list( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['tabs'] ) && is_array( $props['tabs'] ) && ! empty( $props['tabs'] ) ) {
			$out = array();
			foreach ( $props['tabs'] as $t ) {
				$out[] = array(
					'title'   => isset( $t['title'] ) && is_string( $t['title'] ) ? $t['title'] : 'Tab',
					'content' => isset( $t['content'] ) && is_string( $t['content'] ) ? $t['content'] : '',
				);
			}
			return $out;
		}
		$raw = isset( $props['tabsJson'] ) ? $props['tabsJson'] : '';
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array(
				array( 'title' => 'Tab 1', 'content' => 'Content for tab 1.' ),
				array( 'title' => 'Tab 2', 'content' => 'Content for tab 2.' ),
			);
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return array(
				array( 'title' => 'Tab 1', 'content' => 'Content for tab 1.' ),
				array( 'title' => 'Tab 2', 'content' => 'Content for tab 2.' ),
			);
		}
		$out = array();
		foreach ( $decoded as $t ) {
			$out[] = array(
				'title'   => isset( $t['title'] ) && is_string( $t['title'] ) ? $t['title'] : 'Tab',
				'content' => isset( $t['content'] ) && is_string( $t['content'] ) ? $t['content'] : '',
			);
		}
		return $out;
	}

	/**
	 * Render Tabs component.
	 */
	private function render_tabs( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$tabs   = $this->parse_tabs_list( $component );
		$style  = isset( $component['props']['style'] ) && $component['props']['style'] === 'vertical' ? 'vertical' : 'horizontal';
		$is_vert = $style === 'vertical';
		$flex_dir = $is_vert ? 'flex-direction:row;' : 'flex-direction:column;';
		$list_style = 'display:flex;' . ( $is_vert ? 'flex-direction:column;border-right:1px solid #e2e8f0;' : 'flex-direction:row;border-bottom:1px solid #e2e8f0;' );
		$html = '<div id="bb-' . $id . '" class="bb-Tabs" data-bb-type="Tabs" data-bb-id="' . $bb_id . '" style="display:flex;' . $flex_dir . 'width:100%;">';
		$html .= '<div role="tablist" class="bb-Tabs__list" style="' . $list_style . 'flex-shrink:0;">';
		foreach ( $tabs as $i => $tab ) {
			$html .= '<button type="button" role="tab" aria-selected="false" aria-controls="bb-tabpanel-' . $id . '-' . $i . '" id="bb-tab-' . $id . '-' . $i . '" style="padding:12px 20px;border:none;background:transparent;cursor:pointer;font-weight:400;text-align:left;">' . esc_html( $tab['title'] ) . '</button>';
		}
		$html .= '</div><div class="bb-Tabs__panels" style="flex:1;min-width:0;padding:16px;">';
		foreach ( $tabs as $i => $tab ) {
			$html .= '<div role="tabpanel" id="bb-tabpanel-' . $id . '-' . $i . '" aria-labelledby="bb-tab-' . $id . '-' . $i . '" style="display:' . ( $i === 0 ? 'block' : 'none' ) . ';">' . esc_html( $tab['content'] ) . '</div>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Parse accordion items (array prop first, then legacy itemsJson).
	 *
	 * @param array $component Component node.
	 * @return array<int, array{title: string, content: string}> Array of accordion items.
	 */
	private function parse_accordion_items( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['items'] ) && is_array( $props['items'] ) && ! empty( $props['items'] ) ) {
			$out = array();
			foreach ( $props['items'] as $t ) {
				$out[] = array(
					'title'   => isset( $t['title'] ) && is_string( $t['title'] ) ? $t['title'] : 'Item',
					'content' => isset( $t['content'] ) && is_string( $t['content'] ) ? $t['content'] : '',
				);
			}
			return $out;
		}
		$raw = isset( $props['itemsJson'] ) ? $props['itemsJson'] : '';
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array(
				array( 'title' => 'Item 1', 'content' => 'Content for item 1.' ),
				array( 'title' => 'Item 2', 'content' => 'Content for item 2.' ),
			);
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return array(
				array( 'title' => 'Item 1', 'content' => 'Content for item 1.' ),
				array( 'title' => 'Item 2', 'content' => 'Content for item 2.' ),
			);
		}
		$out = array();
		foreach ( $decoded as $t ) {
			$out[] = array(
				'title'   => isset( $t['title'] ) && is_string( $t['title'] ) ? $t['title'] : 'Item',
				'content' => isset( $t['content'] ) && is_string( $t['content'] ) ? $t['content'] : '',
			);
		}
		return $out;
	}

	/**
	 * Render Accordion component (first item open by default).
	 */
	private function render_accordion( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$items = $this->parse_accordion_items( $component );
		$html  = '<div id="bb-' . $id . '" class="bb-Accordion" data-bb-type="Accordion" data-bb-id="' . $bb_id . '" style="width:100%;">';
		foreach ( $items as $i => $item ) {
			$open = $i === 0;
			$html .= '<div class="bb-Accordion__item" style="border:1px solid #e2e8f0;' . ( $i < count( $items ) - 1 ? 'border-bottom:none;' : '' ) . '">';
			$html .= '<button type="button" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" style="width:100%;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border:none;background:#f8fafc;cursor:pointer;font-weight:600;font-size:15px;text-align:left;">';
			$html .= '<span>' . esc_html( $item['title'] ) . '</span><span aria-hidden="true">▼</span></button>';
			$html .= '<div style="display:' . ( $open ? 'block' : 'none' ) . ';padding:12px 16px 16px;border-top:1px solid #e2e8f0;background:#fff;line-height:1.6;">' . esc_html( $item['content'] ) . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Toggle component (single expand/collapse).
	 */
	private function render_toggle( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$title   = isset( $props['title'] ) && is_string( $props['title'] ) ? $props['title'] : 'Toggle title';
		$content = isset( $props['content'] ) && is_string( $props['content'] ) ? $props['content'] : 'Toggle content goes here.';
		$open    = ! empty( $props['defaultOpen'] );
		$bb_id   = esc_attr( $component['id'] );
		$title   = esc_html( $title );
		$content = esc_html( $content );
		$html    = '<div id="bb-' . $id . '" class="bb-Toggle" data-bb-type="Toggle" data-bb-id="' . $bb_id . '" style="width:100%;">';
		$html   .= '<div class="bb-Toggle__item" style="border:1px solid #e2e8f0">';
		$html   .= '<button type="button" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" style="width:100%;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border:none;background:#f8fafc;cursor:pointer;font-weight:600;font-size:15px;text-align:left;">';
		$html   .= '<span>' . $title . '</span><span aria-hidden="true">▼</span></button>';
		$html   .= '<div style="display:' . ( $open ? 'block' : 'none' ) . ';padding:12px 16px 16px;border-top:1px solid #e2e8f0;background:#fff;line-height:1.6;">' . $content . '</div>';
		$html   .= '</div></div>';
		return $html;
	}

	/**
	 * Parse list items (array of strings or itemsJson fallback).
	 *
	 * @param array $component Component node.
	 * @return array List of strings.
	 */
	private function parse_list_items( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['items'] ) && is_array( $props['items'] ) && ! empty( $props['items'] ) ) {
			$out = array();
			foreach ( $props['items'] as $t ) {
				if ( is_string( $t ) ) {
					$out[] = $t;
				} elseif ( is_array( $t ) && isset( $t['text'] ) && is_string( $t['text'] ) ) {
					$out[] = $t['text'];
				} else {
					$out[] = 'Item';
				}
			}
			return $out;
		}
		$raw = isset( $props['itemsJson'] ) ? $props['itemsJson'] : '';
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				$out = array();
				foreach ( $decoded as $t ) {
					$out[] = is_string( $t ) ? $t : ( is_array( $t ) && isset( $t['text'] ) && is_string( $t['text'] ) ? $t['text'] : 'Item' );
				}
				return $out;
			}
		}
		return array( 'List item 1', 'List item 2', 'List item 3' );
	}

	/**
	 * Render List component (bullet, number, or icon style).
	 */
	private function render_list( $component, $id ) {
		$bb_id     = esc_attr( $component['id'] );
		$items     = $this->parse_list_items( $component );
		$props     = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$style_type = isset( $props['style'] ) && is_string( $props['style'] ) ? $props['style'] : 'bullet';
		$tag       = ( $style_type === 'number' ) ? 'ol' : 'ul';
		$list_style = ( $style_type === 'number' ) ? 'decimal' : ( ( $style_type === 'icon' ) ? 'none' : 'disc' );
		$show_icon  = ( $style_type === 'icon' );
		$padding    = ( $style_type === 'number' ) ? '24px' : '20px';

		$html = '<div id="bb-' . $id . '" class="bb-List" data-bb-type="List" data-bb-id="' . $bb_id . '" style="width:100%;">';
		$html .= '<' . $tag . ' style="list-style:' . esc_attr( $list_style ) . ';padding-left:' . esc_attr( $padding ) . ';margin:0;">';
		foreach ( $items as $i => $text ) {
			$text_esc = esc_html( $text );
			$margin   = ( $i < count( $items ) - 1 ) ? '6px' : '0';
			$li_style = 'margin-bottom:' . $margin . ';line-height:1.6;';
			if ( $show_icon ) {
				$li_style .= 'display:flex;align-items:center;gap:8px;';
			}
			$html .= '<li style="' . esc_attr( $li_style ) . '">';
			if ( $show_icon ) {
				$html .= '<span aria-hidden="true" style="color:#2563eb;flex-shrink:0;">✓</span>';
			}
			$html .= $text_esc . '</li>';
		}
		$html .= '</' . $tag . '></div>';
		return $html;
	}

	/**
	 * Render Blockquote component.
	 */
	private function render_blockquote( $component, $id ) {
		$props      = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$quote      = isset( $props['quote'] ) && is_string( $props['quote'] ) ? $props['quote'] : 'The only way to do great work is to love what you do.';
		$author     = isset( $props['author'] ) && is_string( $props['author'] ) ? $props['author'] : '';
		$citation   = isset( $props['citation'] ) && is_string( $props['citation'] ) ? $props['citation'] : '';
		$style_type = isset( $props['style'] ) && is_string( $props['style'] ) ? $props['style'] : 'default';
		$bb_id      = esc_attr( $component['id'] );
		$quote      = esc_html( $quote );
		$author     = esc_html( $author );
		$citation   = esc_html( $citation );

		$border_left   = ( $style_type === 'border-left' ) ? '4px solid #2563eb' : 'none';
		$padding_left = ( $style_type === 'border-left' ) ? '20px' : '0';
		$border_top    = ( $style_type === 'minimal' ) ? '1px solid #e2e8f0' : 'none';

		$html  = '<blockquote id="bb-' . $id . '" class="bb-Blockquote" data-bb-type="Blockquote" data-bb-id="' . $bb_id . '" style="width:100%;margin:0;padding:16px 0;padding-left:' . esc_attr( $padding_left ) . ';border-left:' . esc_attr( $border_left ) . ';border-top:' . esc_attr( $border_top ) . ';border-bottom:none;border-right:none;">';
		$html .= '<p style="font-size:1.1em;line-height:1.6;color:#1e293b;margin:0 0 12px 0;">&ldquo;' . $quote . '&rdquo;</p>';
		if ( $author !== '' || $citation !== '' ) {
			$html .= '<footer style="font-size:0.9em;color:#64748b;">';
			if ( $author !== '' ) {
				$html .= '<cite style="font-style:normal;font-weight:600;">' . $author . '</cite>';
			}
			if ( $citation !== '' ) {
				$html .= ( $author !== '' ? ' &mdash; ' : '' ) . $citation;
			}
			$html .= '</footer>';
		}
		$html .= '</blockquote>';
		return $html;
	}

	/**
	 * Render Progress Bar component.
	 */
	private function render_progress_bar( $component, $id ) {
		$props      = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$label      = isset( $props['label'] ) && is_string( $props['label'] ) ? $props['label'] : 'Progress';
		$percentage = 70;
		if ( isset( $props['percentage'] ) ) {
			if ( is_numeric( $props['percentage'] ) ) {
				$percentage = (int) $props['percentage'];
			} elseif ( is_string( $props['percentage'] ) ) {
				$percentage = (int) $props['percentage'];
			}
			$percentage = max( 0, min( 100, $percentage ) );
		}
		$color = isset( $props['color'] ) && is_string( $props['color'] ) ? $props['color'] : '#2563eb';
		$color = sanitize_hex_color( $color ) ?: '#2563eb';
		$bb_id = esc_attr( $component['id'] );
		$label = esc_attr( $label );

		$html  = '<div id="bb-' . $id . '" class="bb-ProgressBar" data-bb-type="ProgressBar" data-bb-id="' . $bb_id . '" style="width:100%;">';
		$html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
		$html .= '<span style="font-size:14px;font-weight:500;">' . esc_html( $props['label'] ?? 'Progress' ) . '</span>';
		$html .= '<span style="font-size:12px;color:#64748b;">' . (int) $percentage . '%</span></div>';
		$html .= '<div role="progressbar" aria-valuenow="' . (int) $percentage . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . $label . '" style="height:10px;background-color:#e2e8f0;border-radius:999px;overflow:hidden;">';
		$html .= '<div style="width:' . (int) $percentage . '%;height:100%;background-color:' . esc_attr( $color ) . ';border-radius:999px;"></div></div></div>';
		return $html;
	}

	/**
	 * Render Video Box (poster + play button; JS hydrates to play).
	 */
	private function render_video_box( $component, $id ) {
		$props    = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$video_src = isset( $props['videoSrc'] ) && is_string( $props['videoSrc'] ) ? $props['videoSrc'] : '';
		$poster   = isset( $props['poster'] ) && is_string( $props['poster'] ) ? $props['poster'] : '';
		$bb_id    = esc_attr( $component['id'] );
		$html     = '<div id="bb-' . $id . '" class="bb-VideoBox" data-bb-type="VideoBox" data-bb-id="' . $bb_id . '" style="position:relative;width:100%;overflow:hidden;border-radius:8px;aspect-ratio:16/9;max-width:100%;background:#1a1a1a;">';
		if ( $poster ) {
			$html .= '<img src="' . esc_url( $poster ) . '" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" />';
		}
		$html .= '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:' . ( $poster ? 'rgba(0,0,0,0.3)' : 'transparent' ) . ';cursor:pointer;" role="button" tabindex="0" aria-label="Play video">';
		$html .= '<span style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.9);display:flex;align-items:center;justify-content:center;color:#1a1a1a;">▶</span>';
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Render Image Box (image + overlay).
	 */
	private function render_image_box( $component, $id ) {
		$props       = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$src         = isset( $props['src'] ) && is_string( $props['src'] ) ? $props['src'] : '';
		$alt         = isset( $props['alt'] ) && is_string( $props['alt'] ) ? $props['alt'] : '';
		$overlay     = isset( $props['overlayText'] ) && is_string( $props['overlayText'] ) ? $props['overlayText'] : '';
		$bb_id       = esc_attr( $component['id'] );
		$html        = '<div id="bb-' . $id . '" class="bb-ImageBox" data-bb-type="ImageBox" data-bb-id="' . $bb_id . '" style="position:relative;width:100%;overflow:hidden;border-radius:8px;aspect-ratio:16/10;max-width:100%;background:#e2e8f0;">';
		if ( $src ) {
			$html .= '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" style="width:100%;height:100%;object-fit:cover;" />';
			if ( $overlay !== '' ) {
				$html .= '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.2);padding:16px;">';
				$html .= '<span style="color:#fff;font-size:1.1em;font-weight:600;text-align:center;">' . esc_html( $overlay ) . '</span></div>';
			}
		} else {
			$html .= '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;">Add image</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse button group buttons array.
	 */
	private function parse_button_group_buttons( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['buttons'] ) && is_array( $props['buttons'] ) && ! empty( $props['buttons'] ) ) {
			$out = array();
			foreach ( $props['buttons'] as $b ) {
				$out[] = array(
					'content' => isset( $b['content'] ) && is_string( $b['content'] ) ? $b['content'] : 'Button',
					'href'   => isset( $b['href'] ) && is_string( $b['href'] ) ? $b['href'] : '#',
					'target' => isset( $b['target'] ) && is_string( $b['target'] ) ? $b['target'] : '_self',
				);
			}
			return $out;
		}
		return array(
			array( 'content' => 'Primary', 'href' => '#', 'target' => '_self' ),
			array( 'content' => 'Secondary', 'href' => '#', 'target' => '_self' ),
		);
	}

	/**
	 * Render Button Group.
	 */
	private function render_button_group( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$layout  = isset( $props['layout'] ) && is_string( $props['layout'] ) ? $props['layout'] : 'horizontal';
		$buttons = $this->parse_button_group_buttons( $component );
		$bb_id   = esc_attr( $component['id'] );
		$dir     = ( $layout === 'vertical' ) ? 'column' : 'row';
		$html    = '<div id="bb-' . $id . '" class="bb-ButtonGroup" data-bb-type="ButtonGroup" data-bb-id="' . $bb_id . '" style="width:100%;display:flex;flex-direction:' . esc_attr( $dir ) . ';flex-wrap:wrap;gap:12px;align-items:' . ( $layout === 'vertical' ? 'stretch' : 'center' ) . ';">';
		foreach ( $buttons as $btn ) {
			$href   = esc_url( $btn['href'] );
			$target = esc_attr( $btn['target'] );
			$rel    = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';
			$html  .= '<a href="' . $href . '" target="' . $target . '"' . $rel . ' style="display:inline-block;padding:10px 20px;background-color:#2563eb;color:#fff;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;">' . esc_html( $btn['content'] ) . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Countdown (static fallback: show target date; JS hydrates for live countdown).
	 */
	private function render_countdown( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$target  = isset( $props['targetDate'] ) ? $props['targetDate'] : '';
		$expired = isset( $props['expiredMessage'] ) && is_string( $props['expiredMessage'] ) ? $props['expiredMessage'] : 'Event has ended.';
		$bb_id   = esc_attr( $component['id'] );
		$html    = '<div id="bb-' . $id . '" class="bb-Countdown" data-bb-type="Countdown" data-bb-id="' . $bb_id . '" style="width:100%;">';
		if ( is_string( $target ) && $target !== '' ) {
			$html .= '<span data-bb-countdown-target="' . esc_attr( $target ) . '" data-bb-countdown-expired="' . esc_attr( $expired ) . '">' . esc_html( $target ) . '</span>';
		} else {
			$html .= '<span style="color:#64748b;">Set target date</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Menu (wp_nav_menu).
	 */
	private function render_menu( $component, $id ) {
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$menu   = isset( $props['menu'] ) && is_string( $props['menu'] ) ? trim( $props['menu'] ) : '';
		$style  = isset( $props['style'] ) && is_string( $props['style'] ) ? $props['style'] : 'horizontal';
		$bb_id  = esc_attr( $component['id'] );
		$nav_id = 'bb-' . $id;

		$menu_args = array(
			'echo'            => false,
			'container'       => false,
			'menu_class'      => 'bb-Menu__list',
			'menu_id'         => '',
			'fallback_cb'     => false,
		);
		if ( $menu !== '' ) {
			if ( is_numeric( $menu ) ) {
				$menu_args['menu'] = (int) $menu;
			} else {
				$menu_args['menu'] = $menu;
			}
		}

		$menu_html = wp_nav_menu( $menu_args );
		$ul_style  = 'list-style:none;margin:0;padding:0;display:flex;flex-direction:' . ( $style === 'vertical' ? 'column' : 'row' ) . ';flex-wrap:wrap;gap:' . ( $style === 'vertical' ? '4px' : '24px' ) . ';';
		if ( $menu_html ) {
			$menu_html = preg_replace( '/<ul\s[^>]*class="[^"]*"/', '<ul class="bb-Menu__list" style="' . esc_attr( $ul_style ) . '"', $menu_html, 1 );
			if ( strpos( $menu_html, 'style=' ) === false ) {
				$menu_html = preg_replace( '/<ul/', '<ul style="' . esc_attr( $ul_style ) . '"', $menu_html, 1 );
			}
		} else {
			$menu_html = '<ul class="bb-Menu__list" style="' . esc_attr( $ul_style ) . '"><li style="color:#64748b;font-size:14px;">' . ( $menu ? esc_html( 'Menu: ' . $menu ) : 'Select a menu' ) . '</li></ul>';
		}

		return '<nav id="' . $nav_id . '" class="bb-Menu" data-bb-type="Menu" data-bb-id="' . $bb_id . '" style="width:100%;" aria-label="Navigation">' . $menu_html . '</nav>';
	}

	/**
	 * Render Shortcode (do_shortcode).
	 */
	private function render_shortcode( $component, $id ) {
		$props     = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$shortcode = isset( $props['shortcode'] ) && is_string( $props['shortcode'] ) ? trim( $props['shortcode'] ) : '';
		$bb_id     = esc_attr( $component['id'] );
		$content   = $shortcode ? do_shortcode( $shortcode ) : '';
		$html      = '<div id="bb-' . $id . '" class="bb-Shortcode" data-bb-type="Shortcode" data-bb-id="' . $bb_id . '" style="width:100%;">';
		if ( $content !== '' ) {
			$html .= $content;
		} else {
			$html .= $shortcode ? '<div style="padding:12px;background:#f1f5f9;border-radius:6px;font-size:12px;color:#64748b;font-family:monospace;">[Shortcode] ' . esc_html( $shortcode ) . '</div>' : '<span style="color:#64748b;">Add shortcode</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Custom HTML (sanitized).
	 */
	private function render_custom_html( $component, $id ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$html_raw = isset( $props['html'] ) && is_string( $props['html'] ) ? $props['html'] : '';
		$bb_id    = esc_attr( $component['id'] );
		$html     = '<div id="bb-' . $id . '" class="bb-CustomHtml" data-bb-type="CustomHtml" data-bb-id="' . $bb_id . '" style="width:100%;">';
		if ( $html_raw !== '' ) {
			$html .= wp_kses_post( $html_raw );
		} else {
			$html .= '<span style="color:#64748b;">Add custom HTML</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse Team Member social links array.
	 */
	private function parse_team_member_social( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['social'] ) && is_array( $props['social'] ) && ! empty( $props['social'] ) ) {
			$out = array();
			foreach ( $props['social'] as $s ) {
				$out[] = array(
					'network' => isset( $s['network'] ) && is_string( $s['network'] ) ? $s['network'] : 'link',
					'url'     => isset( $s['url'] ) && is_string( $s['url'] ) ? $s['url'] : '#',
				);
			}
			return $out;
		}
		return array( array( 'network' => 'linkedin', 'url' => '#' ), array( 'network' => 'twitter', 'url' => '#' ) );
	}

	/**
	 * Render Team Member component.
	 */
	private function render_team_member( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$name    = isset( $props['name'] ) && is_string( $props['name'] ) ? $props['name'] : 'Team Member';
		$role    = isset( $props['role'] ) && is_string( $props['role'] ) ? $props['role'] : '';
		$image   = isset( $props['image'] ) && is_string( $props['image'] ) ? $props['image'] : '';
		$bio     = isset( $props['bio'] ) && is_string( $props['bio'] ) ? $props['bio'] : '';
		$social  = $this->parse_team_member_social( $component );
		$bb_id   = esc_attr( $component['id'] );
		$name    = esc_html( $name );
		$role    = esc_html( $role );
		$bio     = esc_html( $bio );
		$icons   = array( 'facebook' => 'f', 'twitter' => '𝕏', 'instagram' => '📷', 'linkedin' => 'in', 'youtube' => '▶', 'link' => '↗' );

		$html  = '<div id="bb-' . $id . '" class="bb-TeamMember" data-bb-type="TeamMember" data-bb-id="' . $bb_id . '" style="width:100%;text-align:center;">';
		$html .= '<div style="margin-bottom:16px;">';
		if ( $image ) {
			$html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin:0 auto;display:block;" />';
		} else {
			$init  = mb_substr( $props['name'] ?? 'T', 0, 1 );
			$html .= '<div style="width:120px;height:120px;border-radius:50%;background:#e2e8f0;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:600;color:#64748b;">' . esc_html( $init ) . '</div>';
		}
		$html .= '</div><h3 style="margin:0 0 4px 0;font-size:18px;font-weight:600;">' . $name . '</h3>';
		if ( $role !== '' ) {
			$html .= '<div style="font-size:14px;color:#64748b;margin-bottom:12px;">' . $role . '</div>';
		}
		if ( $bio !== '' ) {
			$html .= '<p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;">' . $bio . '</p>';
		}
		$html .= '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">';
		foreach ( $social as $s ) {
			$icon = isset( $icons[ $s['network'] ] ) ? $icons[ $s['network'] ] : '↗';
			$html .= '<a href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $s['network'] ) . '" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#f1f5f9;color:#475569;text-decoration:none;font-size:14px;">' . esc_html( $icon ) . '</a>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Render Google Maps component.
	 */
	private function render_google_maps( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$address = isset( $props['address'] ) && is_string( $props['address'] ) ? trim( $props['address'] ) : '';
		$zoom    = 14;
		if ( isset( $props['zoom'] ) ) {
			$zoom = is_numeric( $props['zoom'] ) ? (int) $props['zoom'] : 14;
			$zoom = max( 1, min( 21, $zoom ) );
		}
		$height = isset( $props['height'] ) && is_string( $props['height'] ) ? $props['height'] : '300px';
		$bb_id  = esc_attr( $component['id'] );

		$html = '<div id="bb-' . $id . '" class="bb-GoogleMaps" data-bb-type="GoogleMaps" data-bb-id="' . $bb_id . '" style="width:100%;height:' . esc_attr( $height ) . ';min-height:200px;overflow:hidden;border-radius:8px;background:#e2e8f0;">';
		if ( $address !== '' ) {
			$encoded = rawurlencode( $address );
			$embed   = 'https://www.google.com/maps?q=' . $encoded . '&z=' . $zoom . '&output=embed';
			$html   .= '<iframe src="' . esc_url( $embed ) . '" title="Google Map" width="100%" height="100%" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
		} else {
			$html .= '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:14px;">Add address</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Max posts to load per Posts Grid (prevents memory exhaustion).
	 */
	const POSTS_GRID_MAX = 24;

	/**
	 * Build WP_Query args from Query Loop component props.
	 *
	 * @param array $props Component props.
	 * @return array WP_Query args.
	 */
	private function get_query_loop_args( $props ) {
		$post_type  = isset( $props['postType'] ) && is_string( $props['postType'] ) ? $props['postType'] : 'post';
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
	 * Render Query Loop: run WP_Query and render template children once per post.
	 */
	private function render_query_loop( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$bb_id   = esc_attr( $component['id'] );
		$args    = $this->get_query_loop_args( $props );
		$query   = new WP_Query( $args );
		$html    = '<div id="bb-' . $id . '" class="bb-QueryLoop" data-bb-type="QueryLoop" data-bb-id="' . $bb_id . '" style="width:100%;display:flex;flex-direction:column;gap:24px;">';
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->loop_context = $this->get_loop_context();
				$html .= '<article class="bb-QueryLoop-item" style="width:100%;">';
				$html .= $this->render_children( $component );
				$html .= '</article>';
				$this->loop_context = null;
			}
			wp_reset_postdata();
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Build current loop context for token replacement (Phase 2.1).
	 *
	 * @return array Keys: post, author, postId.
	 */
	private function get_loop_context() {
		$content = get_the_content();
		$words   = str_word_count( wp_strip_all_tags( $content ) );
		$mins    = max( 1, (int) ceil( $words / 200 ) );
		/* translators: %d: estimated reading time in minutes. */
		$reading = sprintf( _n( '%d min read', '%d min read', $mins, 'bridge-builder' ), $mins );
		return array(
			'postId' => get_the_ID(),
			'post'   => array(
				'title'       => get_the_title(),
				'excerpt'     => has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 55 ),
				'image'       => get_the_post_thumbnail_url( null, 'medium' ) ?: '',
				'url'         => get_permalink(),
				'date'        => get_the_date(),
				'readingTime' => $reading,
				'author'      => get_the_author(),
				'authorUrl'   => get_author_posts_url( get_the_author_meta( 'ID' ) ),
			),
			'author' => array(
				'name' => get_the_author(),
				'url'  => get_author_posts_url( get_the_author_meta( 'ID' ) ),
			),
		);
	}

	/**
	 * Replace tokens {post.x}, {author.x}, {meta.key} in a string (Phase 2.1).
	 *
	 * @param string $str Content that may contain tokens.
	 * @return string Content with tokens replaced.
	 */
	private function replace_tokens( $str ) {
		if ( null === $this->loop_context ) {
			return $str;
		}
		return self::replace_tokens_with_context( $str, $this->loop_context );
	}

	/**
	 * Replace tokens in a string given a context array (for REST/frontend enrichment).
	 *
	 * @param string $str     Content that may contain tokens.
	 * @param array  $context Keys: post, author, postId (same shape as get_loop_context).
	 * @return string Content with tokens replaced.
	 */
	public static function replace_tokens_with_context( $str, $context ) {
		if ( ! is_string( $str ) || '' === $str ) {
			return $str;
		}
		if ( ! is_array( $context ) ) {
			return $str;
		}
		$post    = isset( $context['post'] ) && is_array( $context['post'] ) ? $context['post'] : array();
		$author  = isset( $context['author'] ) && is_array( $context['author'] ) ? $context['author'] : array();
		$post_id = isset( $context['postId'] ) ? (int) $context['postId'] : 0;

		$replace = array(
			'{post.title}'       => isset( $post['title'] ) ? $post['title'] : '',
			'{post.excerpt}'     => isset( $post['excerpt'] ) ? $post['excerpt'] : '',
			'{post.image}'       => isset( $post['image'] ) ? $post['image'] : '',
			'{post.url}'         => isset( $post['url'] ) ? $post['url'] : '',
			'{post.date}'        => isset( $post['date'] ) ? $post['date'] : '',
			'{post.readingTime}' => isset( $post['readingTime'] ) ? $post['readingTime'] : '',
			'{post.author}'      => isset( $post['author'] ) ? $post['author'] : '',
			'{author.name}'      => isset( $author['name'] ) ? $author['name'] : '',
			'{author.url}'       => isset( $author['url'] ) ? $author['url'] : '',
		);
		$str = str_replace( array_keys( $replace ), array_values( $replace ), $str );

		// {meta.key} replacement
		if ( $post_id && preg_match_all( '/\{meta\.([a-zA-Z0-9_-]+)\}/', $str, $m ) ) {
			foreach ( array_unique( $m[1] ) as $meta_key ) {
				$value = get_post_meta( $post_id, $meta_key, true );
				$str   = str_replace( '{meta.' . $meta_key . '}', is_string( $value ) ? $value : (string) $value, $str );
			}
		}
		return $str;
	}

	/**
	 * Render Post Title (current post in loop or global post).
	 */
	private function render_post_title( $component, $id ) {
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$level  = isset( $props['level'] ) && in_array( $props['level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $props['level'] : 'h2';
		$title  = get_the_title();
		$bb_id  = esc_attr( $component['id'] );
		$tag    = esc_attr( $level );
		return '<div id="bb-' . $id . '" class="bb-PostTitle" data-bb-type="PostTitle" data-bb-id="' . $bb_id . '"><' . $tag . ' style="margin:0;">' . esc_html( $title ) . '</' . $tag . '></div>';
	}

	/**
	 * Render Post Excerpt.
	 */
	private function render_post_excerpt( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$length  = isset( $props['length'] ) && is_numeric( $props['length'] ) ? max( 10, min( 200, (int) $props['length'] ) ) : 55;
		$excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), $length );
		$bb_id   = esc_attr( $component['id'] );
		return '<div id="bb-' . $id . '" class="bb-PostExcerpt" data-bb-type="PostExcerpt" data-bb-id="' . $bb_id . '"><p style="margin:0;">' . esc_html( $excerpt ) . '</p></div>';
	}

	/**
	 * Render Post Featured Image.
	 */
	private function render_post_featured_image( $component, $id ) {
		$props       = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$size        = isset( $props['size'] ) && is_string( $props['size'] ) ? $props['size'] : 'medium';
		$link_to_post = ! isset( $props['linkToPost'] ) || ( $props['linkToPost'] !== false && $props['linkToPost'] !== 'false' );
		$bb_id       = esc_attr( $component['id'] );
		$src         = get_the_post_thumbnail_url( null, $size );
		$alt         = bridge_builder_get_post_thumbnail_alt( get_post_thumbnail_id() ) ?: get_the_title();
		$url         = get_permalink();
		if ( empty( $src ) ) {
			return '<div id="bb-' . $id . '" class="bb-PostFeaturedImage" data-bb-type="PostFeaturedImage" data-bb-id="' . $bb_id . '" style="aspect-ratio:16/10;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:14px;">' . esc_html__( 'Featured image', 'bridge-builder' ) . '</div>';
		}
		$img = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit;" />';
		if ( $link_to_post ) {
			$img = '<a href="' . esc_url( $url ) . '" style="display:block;aspect-ratio:16/10;overflow:hidden;">' . $img . '</a>';
		} else {
			$img = '<div style="aspect-ratio:16/10;overflow:hidden;">' . $img . '</div>';
		}
		return '<div id="bb-' . $id . '" class="bb-PostFeaturedImage" data-bb-type="PostFeaturedImage" data-bb-id="' . $bb_id . '" style="width:100%;overflow:hidden;border-radius:8px;">' . $img . '</div>';
	}

	/**
	 * Render Post Date.
	 */
	private function render_post_date( $component, $id ) {
		$format = isset( $component['props']['format'] ) && is_string( $component['props']['format'] ) ? $component['props']['format'] : '';
		$date   = $format ? get_the_date( $format ) : get_the_date();
		$bb_id  = esc_attr( $component['id'] );
		return '<time id="bb-' . $id . '" class="bb-PostDate" data-bb-type="PostDate" data-bb-id="' . $bb_id . '" datetime="' . esc_attr( get_the_date( 'c' ) ) . '" style="font-size:0.875em;color:#64748b;">' . esc_html( $date ) . '</time>';
	}

	/**
	 * Render Post Author.
	 */
	private function render_post_author( $component, $id ) {
		$props      = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$show_link  = ! isset( $props['showLink'] ) || ( $props['showLink'] !== false && $props['showLink'] !== 'false' );
		$name       = get_the_author();
		$bb_id      = esc_attr( $component['id'] );
		if ( $show_link ) {
			$url = get_author_posts_url( get_the_author_meta( 'ID' ) );
			$name = '<a href="' . esc_url( $url ) . '" style="color:inherit;text-decoration:none;">' . esc_html( $name ) . '</a>';
		} else {
			$name = esc_html( $name );
		}
		return '<span id="bb-' . $id . '" class="bb-PostAuthor" data-bb-type="PostAuthor" data-bb-id="' . $bb_id . '">' . $name . '</span>';
	}

	/**
	 * Render Reading Time (estimated from word count).
	 */
	private function render_reading_time( $component, $id ) {
		$content = get_the_content();
		$words   = str_word_count( wp_strip_all_tags( $content ) );
		$mins    = max( 1, (int) ceil( $words / 200 ) );
		/* translators: %d: estimated reading time in minutes. */
		$text    = sprintf( _n( '%d min read', '%d min read', $mins, 'bridge-builder' ), $mins );
		$bb_id   = esc_attr( $component['id'] );
		return '<span id="bb-' . $id . '" class="bb-ReadingTime" data-bb-type="ReadingTime" data-bb-id="' . $bb_id . '" style="font-size:0.875em;color:#64748b;">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Render Archive Title (on archive/home).
	 */
	private function render_archive_title( $component, $id ) {
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$level  = isset( $props['level'] ) && in_array( $props['level'], array( 'h1', 'h2', 'h3' ), true ) ? $props['level'] : 'h1';
		$title  = ( is_archive() || is_home() ) ? get_the_archive_title() : ( isset( $props['content'] ) ? $props['content'] : '' );
		$title  = trim( (string) $title );
		$bb_id  = esc_attr( $component['id'] );
		$tag    = esc_attr( $level );
		if ( $title === '' ) {
			$title = __( 'Archive', 'bridge-builder' );
		}
		return '<div id="bb-' . $id . '" class="bb-ArchiveTitle" data-bb-type="ArchiveTitle" data-bb-id="' . $bb_id . '"><' . $tag . ' style="margin:0;">' . $title . '</' . $tag . '></div>';
	}

	/**
	 * Render Archive Description.
	 */
	private function render_archive_description( $component, $id ) {
		$desc   = ( is_archive() || is_home() ) ? get_the_archive_description() : '';
		$desc   = trim( (string) $desc );
		$bb_id  = esc_attr( $component['id'] );
		if ( $desc === '' ) {
			$desc = isset( $component['props']['content'] ) ? $component['props']['content'] : __( 'Archive description.', 'bridge-builder' );
			$desc = esc_html( (string) $desc );
		} else {
			$desc = wp_kses_post( $desc );
		}
		return '<div id="bb-' . $id . '" class="bb-ArchiveDescription" data-bb-type="ArchiveDescription" data-bb-id="' . $bb_id . '"><p style="margin:0;">' . $desc . '</p></div>';
	}

	/**
	 * Render Pagination (main query).
	 */
	private function render_pagination( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		ob_start();
		if ( is_archive() || is_home() ) {
			the_posts_pagination(
				array(
					'mid_size'  => 2,
					'prev_text' => __( 'Previous', 'bridge-builder' ),
					'next_text' => __( 'Next', 'bridge-builder' ),
				)
			);
		}
		$nav = ob_get_clean();
		$nav = trim( (string) $nav );
		if ( $nav === '' ) {
			$nav = '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:14px;color:#64748b;">' . esc_html__( 'Pagination (prev / next / numbers)', 'bridge-builder' ) . '</div>';
		}
		return '<nav id="bb-' . $id . '" class="bb-Pagination navigation pagination" data-bb-type="Pagination" data-bb-id="' . $bb_id . '" aria-label="' . esc_attr__( 'Pagination', 'bridge-builder' ) . '">' . $nav . '</nav>';
	}

	/**
	 * Render No Results (when main query has no posts).
	 */
	private function render_no_results( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$content = isset( $props['content'] ) ? $props['content'] : __( 'No posts found.', 'bridge-builder' );
		$bb_id   = esc_attr( $component['id'] );
		$show    = ( is_archive() || is_home() ) && ! have_posts();
		if ( ! $show ) {
			return '<div id="bb-' . $id . '" class="bb-NoResults" data-bb-type="NoResults" data-bb-id="' . $bb_id . '" style="display:none;"></div>';
		}
		return '<div id="bb-' . $id . '" class="bb-NoResults" data-bb-type="NoResults" data-bb-id="' . $bb_id . '" style="padding:24px;text-align:center;color:#64748b;">' . esc_html( (string) $content ) . '</div>';
	}

	/**
	 * Get posts for Posts Grid (WP_Query).
	 * Uses fields=>ids and minimal fetches to avoid loading full post_content.
	 */
	private function get_posts_grid_posts( $post_type, $posts_per_page ) {
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
			$date_raw  = get_post_field( 'post_date', $post_id );
			$content   = get_post_field( 'post_content', $post_id );
			$author_id = (int) get_post_field( 'post_author', $post_id );
			$posts[]   = array(
				'title'       => get_the_title( $post_id ),
				'excerpt'     => $excerpt,
				'image'       => get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '',
				'url'         => get_permalink( $post_id ),
				'date'        => $date_raw ? date_i18n( get_option( 'date_format' ), strtotime( $date_raw ) ) : '',
				'author'      => $author_id ? get_the_author_meta( 'display_name', $author_id ) : '',
				'authorUrl'   => $author_id ? get_author_posts_url( $author_id ) : '',
				'readingTime' => max( 1, (int) ceil( str_word_count( wp_strip_all_tags( (string) $content ) ) / 200 ) ) . ' min read',
			);
		}
		return $posts;
	}

	/**
	 * Render Posts Grid / Posts Carousel component.
	 */
	private function render_posts_grid( $component, $id ) {
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$post_type = isset( $props['postType'] ) && is_string( $props['postType'] ) ? $props['postType'] : 'post';
		$ppp    = isset( $props['postsPerPage'] ) ? absint( $props['postsPerPage'] ) : 6;
		$columns = isset( $props['columns'] ) ? max( 1, min( 6, (int) $props['columns'] ) ) : 3;
		$show_title       = ! isset( $props['showTitle'] ) || ( $props['showTitle'] !== false && $props['showTitle'] !== 'false' );
		$show_excerpt     = ! isset( $props['showExcerpt'] ) || ( $props['showExcerpt'] !== false && $props['showExcerpt'] !== 'false' );
		$show_image       = ! isset( $props['showImage'] ) || ( $props['showImage'] !== false && $props['showImage'] !== 'false' );
		$show_date        = ! isset( $props['showDate'] ) || ( $props['showDate'] !== false && $props['showDate'] !== 'false' );
		$show_author      = ! isset( $props['showAuthor'] ) || ( $props['showAuthor'] !== false && $props['showAuthor'] !== 'false' );
		$show_reading_time = ! isset( $props['showReadingTime'] ) || ( $props['showReadingTime'] !== false && $props['showReadingTime'] !== 'false' );
		$bb_id  = esc_attr( $component['id'] );
		$type   = isset( $component['type'] ) && $component['type'] === 'PostsCarousel' ? 'PostsCarousel' : 'PostsGrid';
		$posts  = $this->get_posts_grid_posts( $post_type, $ppp );

		$html = '<div id="bb-' . $id . '" class="bb-PostsGrid" data-bb-type="' . esc_attr( $type ) . '" data-bb-id="' . $bb_id . '" style="width:100%;display:grid;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));gap:24px;">';
		foreach ( $posts as $post ) {
			$html .= '<article style="display:flex;flex-direction:column;gap:12px;background:#f8fafc;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">';
			if ( $show_image && ! empty( $post['image'] ) ) {
				$html .= '<a href="' . esc_url( $post['url'] ) . '" style="display:block;aspect-ratio:16/10;overflow:hidden;"><img src="' . esc_url( $post['image'] ) . '" alt="' . esc_attr( $post['title'] ) . '" style="width:100%;height:100%;object-fit:cover;" /></a>';
			}
			$html .= '<div style="padding:16px;flex:1;display:flex;flex-direction:column;gap:8px;">';
			if ( ( $show_date && ! empty( $post['date'] ) ) || ( $show_author && ! empty( $post['author'] ) ) ) {
				$meta = array();
				if ( $show_date && ! empty( $post['date'] ) ) {
					$meta[] = '<time style="font-size:12px;color:#64748b;">' . esc_html( $post['date'] ) . '</time>';
				}
				if ( $show_author && ! empty( $post['author'] ) ) {
					$meta[] = ! empty( $post['authorUrl'] ) ? '<a href="' . esc_url( $post['authorUrl'] ) . '" style="font-size:12px;color:#64748b;">' . esc_html( $post['author'] ) . '</a>' : '<span style="font-size:12px;color:#64748b;">' . esc_html( $post['author'] ) . '</span>';
				}
				$html .= '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">' . implode( ' — ', $meta ) . '</div>';
			}
			if ( $show_title ) {
				$html .= '<h3 style="margin:0;font-size:18px;font-weight:600;"><a href="' . esc_url( $post['url'] ) . '" style="color:inherit;text-decoration:none;">' . esc_html( $post['title'] ) . '</a></h3>';
			}
			if ( $show_excerpt && ! empty( $post['excerpt'] ) ) {
				$html .= '<p style="margin:0;font-size:14px;line-height:1.6;color:#475569;flex:1;">' . esc_html( $post['excerpt'] ) . '</p>';
			}
			if ( $show_reading_time && ! empty( $post['readingTime'] ) ) {
				$html .= '<span style="font-size:12px;color:#94a3b8;">' . esc_html( $post['readingTime'] ) . '</span>';
			}
			$html .= '</div></article>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Get products for Product Grid (WooCommerce). Delegates to bridge_builder_get_product_grid_products.
	 *
	 * @param array $props Component props.
	 * @return array List of product data.
	 */
	private function get_product_grid_products( $props ) {
		return bridge_builder_get_product_grid_products( $props );
	}

	/**
	 * Render Product Grid component (WooCommerce). Placeholder if WC not active.
	 */
	private function render_product_grid( $component, $id ) {
		$props    = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$bb_id    = esc_attr( $component['id'] );
		$products = $this->get_product_grid_products( $props );
		$columns  = isset( $props['columns'] ) ? max( 1, min( 6, (int) $props['columns'] ) ) : 4;
		$show_title       = ! isset( $props['showTitle'] ) || ( $props['showTitle'] !== false && $props['showTitle'] !== 'false' );
		$show_price       = ! isset( $props['showPrice'] ) || ( $props['showPrice'] !== false && $props['showPrice'] !== 'false' );
		$show_image       = ! isset( $props['showImage'] ) || ( $props['showImage'] !== false && $props['showImage'] !== 'false' );
		$show_add_to_cart = ! isset( $props['showAddToCart'] ) || ( $props['showAddToCart'] !== false && $props['showAddToCart'] !== 'false' );

		if ( ! bridge_builder_woocommerce_active() ) {
			return '<div id="bb-' . $id . '" class="bb-ProductGrid" data-bb-type="ProductGrid" data-bb-id="' . $bb_id . '" style="width:100%;padding:24px;background:#f1f5f9;border-radius:8px;text-align:center;color:#64748b;font-size:14px;">' . esc_html__( 'Product Grid requires WooCommerce.', 'bridge-builder' ) . '</div>';
		}

		$html = '<div id="bb-' . $id . '" class="bb-ProductGrid" data-bb-type="ProductGrid" data-bb-id="' . $bb_id . '" style="width:100%;display:grid;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));gap:24px;">';
		$card_style = 'display:flex;flex-direction:column;gap:12px;background:#f8fafc;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;';
		foreach ( $products as $p ) {
			$html .= '<article style="' . $card_style . '">';
			if ( $show_image ) {
				if ( ! empty( $p['image'] ) ) {
					$html .= '<a href="' . esc_url( $p['url'] ) . '" style="display:block;aspect-ratio:1;overflow:hidden;"><img src="' . esc_url( $p['image'] ) . '" alt="' . esc_attr( $p['title'] ) . '" style="width:100%;height:100%;object-fit:cover;" loading="lazy" /></a>';
				} else {
					$html .= '<div style="aspect-ratio:1;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">' . esc_html__( 'Product image', 'bridge-builder' ) . '</div>';
				}
			}
			$html .= '<div style="padding:16px;flex:1;display:flex;flex-direction:column;gap:8px;">';
			if ( $show_title ) {
				$html .= '<h3 style="margin:0;font-size:18px;font-weight:600;"><a href="' . esc_url( $p['url'] ) . '" style="color:inherit;text-decoration:none;">' . esc_html( $p['title'] ) . '</a></h3>';
			}
			if ( $show_price && ! empty( $p['priceHtml'] ) ) {
				$html .= '<div class="bb-ProductGrid__price" style="font-size:16px;font-weight:600;">' . $p['priceHtml'] . '</div>';
			}
			if ( $show_add_to_cart ) {
				$html .= '<a href="' . esc_url( $p['addToCartUrl'] ) . '" class="bb-ProductGrid__add-to-cart button" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;border-radius:6px;font-size:14px;font-weight:500;text-decoration:none;text-align:center;margin-top:auto;">' . esc_html__( 'Add to cart', 'bridge-builder' ) . '</a>';
			}
			$html .= '</div></article>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Product Title (single product context). Placeholder when no product.
	 */
	private function render_product_title( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$level  = isset( $component['props']['level'] ) && in_array( $component['props']['level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $component['props']['level'] : 'h1';
		$product = bridge_builder_get_current_product();
		$title   = $product ? $product->get_name() : ( isset( $component['props']['content'] ) ? $component['props']['content'] : __( 'Product title', 'bridge-builder' ) );
		$tag     = esc_attr( $level );
		return '<div id="bb-' . $id . '" class="bb-ProductTitle" data-bb-type="ProductTitle" data-bb-id="' . $bb_id . '"><' . $tag . ' style="margin:0;">' . esc_html( $title ) . '</' . $tag . '></div>';
	}

	/**
	 * Render Product Price (single product context).
	 */
	private function render_product_price( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$product = bridge_builder_get_current_product();
		$html_price = $product ? wp_kses_post( $product->get_price_html() ) : '<span class="price">' . esc_html__( '£0.00', 'bridge-builder' ) . '</span>';
		return '<div id="bb-' . $id . '" class="bb-ProductPrice" data-bb-type="ProductPrice" data-bb-id="' . $bb_id . '" style="font-size:1.25em;font-weight:600;">' . $html_price . '</div>';
	}

	/**
	 * Render Product Image / main image (single product context).
	 */
	private function render_product_image( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$product = bridge_builder_get_current_product();
		if ( ! $product ) {
			return '<div id="bb-' . $id . '" class="bb-ProductImage" data-bb-type="ProductImage" data-bb-id="' . $bb_id . '" style="aspect-ratio:1;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:14px;">' . esc_html__( 'Product image', 'bridge-builder' ) . '</div>';
		}
		$img_id = $product->get_image_id();
		$src    = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
		$alt    = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : $product->get_name();
		if ( ! is_string( $alt ) ) {
			$alt = $product->get_name();
		}
		if ( empty( $src ) ) {
			return '<div id="bb-' . $id . '" class="bb-ProductImage" data-bb-type="ProductImage" data-bb-id="' . $bb_id . '" style="aspect-ratio:1;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:14px;">' . esc_html__( 'Product image', 'bridge-builder' ) . '</div>';
		}
		return '<div id="bb-' . $id . '" class="bb-ProductImage" data-bb-type="ProductImage" data-bb-id="' . $bb_id . '" style="width:100%;overflow:hidden;border-radius:8px;"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" style="width:100%;height:auto;display:block;aspect-ratio:1;object-fit:cover;" loading="lazy" /></div>';
	}

	/**
	 * Render Add to Cart (single product context). Outputs WooCommerce form when product exists.
	 */
	private function render_product_add_to_cart( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$product = bridge_builder_get_current_product();
		if ( ! $product ) {
			return '<div id="bb-' . $id . '" class="bb-ProductAddToCart" data-bb-type="ProductAddToCart" data-bb-id="' . $bb_id . '" style="padding:12px 0;color:#64748b;font-size:14px;">' . esc_html__( 'Add to cart (product context required)', 'bridge-builder' ) . '</div>';
		}
		ob_start();
		woocommerce_template_single_add_to_cart();
		$btn = ob_get_clean();
		$btn = is_string( $btn ) ? trim( $btn ) : '';
		if ( $btn === '' ) {
			$btn = '<a href="' . esc_url( $product->get_add_to_cart_url() ) . '" class="button">' . esc_html__( 'Add to cart', 'bridge-builder' ) . '</a>';
		}
		return '<div id="bb-' . $id . '" class="bb-ProductAddToCart" data-bb-type="ProductAddToCart" data-bb-id="' . $bb_id . '">' . $btn . '</div>';
	}

	/**
	 * Render Product Meta (SKU, categories, tags).
	 */
	private function render_product_meta( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$product = bridge_builder_get_current_product();
		if ( ! $product ) {
			return '<div id="bb-' . $id . '" class="bb-ProductMeta" data-bb-type="ProductMeta" data-bb-id="' . $bb_id . '" style="font-size:14px;color:#64748b;">' . esc_html__( 'Product meta (product context required)', 'bridge-builder' ) . '</div>';
		}
		$parts = array();
		if ( $product->get_sku() ) {
			$parts[] = '<span class="sku_wrapper">' . esc_html__( 'SKU:', 'bridge-builder' ) . ' <span class="sku">' . esc_html( $product->get_sku() ) . '</span></span>';
		}
		$cats = wc_get_product_category_list( $product->get_id() );
		if ( $cats ) {
			$parts[] = '<span class="posted_in">' . esc_html__( 'Categories:', 'bridge-builder' ) . ' ' . $cats . '</span>';
		}
		$tags = wc_get_product_tag_list( $product->get_id() );
		if ( $tags ) {
			$parts[] = '<span class="tagged_as">' . esc_html__( 'Tags:', 'bridge-builder' ) . ' ' . $tags . '</span>';
		}
		$inner = empty( $parts ) ? esc_html__( 'No meta.', 'bridge-builder' ) : implode( ' | ', $parts );
		return '<div id="bb-' . $id . '" class="bb-ProductMeta" data-bb-type="ProductMeta" data-bb-id="' . $bb_id . '" style="font-size:14px;color:#64748b;">' . $inner . '</div>';
	}

	/**
	 * Render Product Tabs (description, additional info, reviews) as simple sections.
	 */
	private function render_product_tabs( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$product = bridge_builder_get_current_product();
		if ( ! $product ) {
			return '<div id="bb-' . $id . '" class="bb-ProductTabs" data-bb-type="ProductTabs" data-bb-id="' . $bb_id . '" style="padding:16px;background:#f8fafc;border-radius:8px;">' . esc_html__( 'Product tabs (product context required)', 'bridge-builder' ) . '</div>';
		}
		$html = '<div id="bb-' . $id . '" class="bb-ProductTabs" data-bb-type="ProductTabs" data-bb-id="' . $bb_id . '" style="width:100%;">';
		$desc = $product->get_description();
		if ( $desc !== '' ) {
			$html .= '<div class="bb-ProductTabs__description" style="margin-bottom:24px;"><h3 style="margin:0 0 12px;font-size:18px;">' . esc_html__( 'Description', 'bridge-builder' ) . '</h3><div class="product-description" style="line-height:1.6;">' . wp_kses_post( $desc ) . '</div></div>';
		}
		ob_start();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
		do_action( 'woocommerce_product_additional_information', $product );
		$add_info = ob_get_clean();
		if ( $add_info !== '' ) {
			$html .= '<div class="bb-ProductTabs__additional" style="margin-bottom:24px;"><h3 style="margin:0 0 12px;font-size:18px;">' . esc_html__( 'Additional information', 'bridge-builder' ) . '</h3>' . $add_info . '</div>';
		}
		$html .= '<div class="bb-ProductTabs__reviews" style="margin-bottom:24px;">';
		ob_start();
		comments_template();
		$reviews = ob_get_clean();
		if ( $reviews !== '' ) {
			$html .= $reviews;
		} else {
			$html .= '<p style="color:#64748b;font-size:14px;">' . esc_html__( 'Reviews load here when on single product page.', 'bridge-builder' ) . '</p>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Render Related Products grid (single product context).
	 */
	private function render_related_products( $component, $id ) {
		$bb_id    = esc_attr( $component['id'] );
		$product  = bridge_builder_get_current_product();
		$columns  = isset( $component['props']['columns'] ) ? max( 1, min( 6, (int) $component['props']['columns'] ) ) : 4;
		$limit    = isset( $component['props']['postsPerPage'] ) ? max( 1, min( 12, (int) $component['props']['postsPerPage'] ) ) : 4;
		if ( ! $product ) {
			return '<div id="bb-' . $id . '" class="bb-RelatedProducts" data-bb-type="RelatedProducts" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f8fafc;border-radius:8px;text-align:center;color:#64748b;">' . esc_html__( 'Related products (product context required)', 'bridge-builder' ) . '</div>';
		}
		$related_ids = wc_get_related_products( $product->get_id(), $limit );
		if ( empty( $related_ids ) ) {
			return '<div id="bb-' . $id . '" class="bb-RelatedProducts" data-bb-type="RelatedProducts" data-bb-id="' . $bb_id . '" style="width:100%;display:grid;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));gap:24px;">' . esc_html__( 'No related products.', 'bridge-builder' ) . '</div>';
		}
		$props_for_grid = array( 'postsPerPage' => $limit, 'columns' => $columns );
		$products_data = array();
		foreach ( $related_ids as $rid ) {
			$p = wc_get_product( $rid );
			if ( ! $p || ! $p->is_visible() ) {
				continue;
			}
			$img_id = $p->get_image_id();
			$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
			if ( ! is_string( $img_url ) ) {
				$img_url = '';
			}
			$products_data[] = array(
				'id'           => $p->get_id(),
				'title'        => $p->get_name(),
				'priceHtml'    => wp_kses_post( $p->get_price_html() ),
				'image'        => $img_url,
				'url'          => $p->get_permalink(),
				'addToCartUrl' => $p->get_add_to_cart_url(),
				'onSale'       => $p->is_on_sale(),
			);
		}
		$card_style = 'display:flex;flex-direction:column;gap:12px;background:#f8fafc;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;';
		$html = '<div id="bb-' . $id . '" class="bb-RelatedProducts" data-bb-type="RelatedProducts" data-bb-id="' . $bb_id . '" style="width:100%;display:grid;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));gap:24px;">';
		foreach ( $products_data as $p ) {
			$html .= '<article style="' . $card_style . '">';
			$html .= '<a href="' . esc_url( $p['url'] ) . '" style="display:block;aspect-ratio:1;overflow:hidden;"><img src="' . esc_url( $p['image'] ) . '" alt="' . esc_attr( $p['title'] ) . '" style="width:100%;height:100%;object-fit:cover;" loading="lazy" /></a>';
			$html .= '<div style="padding:16px;">';
			$html .= '<h3 style="margin:0 0 8px;font-size:16px;font-weight:600;"><a href="' . esc_url( $p['url'] ) . '" style="color:inherit;text-decoration:none;">' . esc_html( $p['title'] ) . '</a></h3>';
			$html .= '<div style="font-size:14px;font-weight:600;">' . $p['priceHtml'] . '</div>';
			$html .= '<a href="' . esc_url( $p['addToCartUrl'] ) . '" class="button" style="display:inline-block;padding:8px 12px;background:#2563eb;color:#fff;border-radius:6px;font-size:13px;text-decoration:none;margin-top:8px;">' . esc_html__( 'Add to cart', 'bridge-builder' ) . '</a>';
			$html .= '</div></article>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Cart block (WooCommerce cart shortcode). 4.3
	 */
	private function render_cart_block( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		if ( ! bridge_builder_woocommerce_active() ) {
			return '<div id="bb-' . $id . '" class="bb-CartBlock" data-bb-type="CartBlock" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f8fafc;border-radius:8px;text-align:center;color:#64748b;">' . esc_html__( 'Cart block requires WooCommerce.', 'bridge-builder' ) . '</div>';
		}
		$content = do_shortcode( '[woocommerce_cart]' );
		return '<div id="bb-' . $id . '" class="bb-CartBlock" data-bb-type="CartBlock" data-bb-id="' . $bb_id . '">' . $content . '</div>';
	}

	/**
	 * Render Checkout block (WooCommerce checkout shortcode). 4.3
	 */
	private function render_checkout_block( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		if ( ! bridge_builder_woocommerce_active() ) {
			return '<div id="bb-' . $id . '" class="bb-CheckoutBlock" data-bb-type="CheckoutBlock" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f8fafc;border-radius:8px;text-align:center;color:#64748b;">' . esc_html__( 'Checkout block requires WooCommerce.', 'bridge-builder' ) . '</div>';
		}
		$content = do_shortcode( '[woocommerce_checkout]' );
		return '<div id="bb-' . $id . '" class="bb-CheckoutBlock" data-bb-type="CheckoutBlock" data-bb-id="' . $bb_id . '">' . $content . '</div>';
	}

	/**
	 * Render Mini Cart (icon + drawer). 4.4
	 */
	private function render_mini_cart( $component, $id ) {
		$bb_id = esc_attr( $component['id'] );
		$inner = function_exists( 'bridge_builder_get_mini_cart_html' ) ? bridge_builder_get_mini_cart_html() : '';
		return '<div id="bb-' . $id . '" class="bb-MiniCart" data-bb-type="MiniCart" data-bb-id="' . $bb_id . '" style="position:relative;display:inline-flex;align-items:center;gap:8px;">' . $inner . '</div>';
	}

	/**
	 * Parse Contact Form fields array (includes options for select, radio, checkbox).
	 */
	private function parse_contact_form_fields( $component ) {
		$props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		if ( isset( $props['fields'] ) && is_array( $props['fields'] ) && ! empty( $props['fields'] ) ) {
			$out = array();
			foreach ( $props['fields'] as $f ) {
				$options = array();
				if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
					$options = array_values( array_filter( array_map( function ( $o ) {
						return is_string( $o ) ? trim( $o ) : '';
					}, $f['options'] ) ) );
				}
				$out[] = array(
					'type'     => isset( $f['type'] ) && is_string( $f['type'] ) ? $f['type'] : 'text',
					'label'    => isset( $f['label'] ) && is_string( $f['label'] ) ? $f['label'] : 'Field',
					'required' => ! empty( $f['required'] ),
					'options'  => $options,
				);
			}
			return $out;
		}
		return array(
			array( 'type' => 'text', 'label' => 'Name', 'required' => true, 'options' => array() ),
			array( 'type' => 'email', 'label' => 'Email', 'required' => true, 'options' => array() ),
			array( 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'options' => array() ),
		);
	}

	/**
	 * Render Contact Form component (HTML form; JS submits via REST).
	 */
	private function render_contact_form( $component, $id ) {
		$props      = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$fields     = $this->parse_contact_form_fields( $component );
		$submit     = isset( $props['submitText'] ) && is_string( $props['submitText'] ) ? $props['submitText'] : 'Send';
		$bb_id      = esc_attr( $component['id'] );
		$submit     = esc_html( $submit );

		$html  = '<div id="bb-' . $id . '" class="bb-ContactForm" data-bb-type="ContactForm" data-bb-id="' . $bb_id . '" style="width:100%;max-width:480px;">';
		$html .= '<form method="post" action="#" style="display:flex;flex-direction:column;gap:16px;">';
		$field_style = 'display:flex;flex-direction:column;gap:4px;';
		$input_style = 'width:100%;padding:10px;border-radius:6px;border:1px solid #e2e8f0;';
		$opt_style   = 'display:flex;flex-direction:column;gap:6px;';
		foreach ( $fields as $f ) {
			$req   = $f['required'] ? ' required' : '';
			$label = esc_html( $f['label'] );
			$name  = esc_attr( 'field_' . $f['label'] );
			$html .= '<div style="' . $field_style . '"><label style="font-size:14px;font-weight:500;">' . $label . ( $f['required'] ? ' <span style="color:#dc2626;">*</span>' : '' ) . '</label>';
			if ( $f['type'] === 'textarea' ) {
				$html .= '<textarea name="' . $name . '" rows="4"' . $req . ' class="bb-input" style="' . $input_style . 'resize:vertical;"></textarea>';
			} elseif ( $f['type'] === 'select' && ! empty( $f['options'] ) ) {
				$html .= '<select name="' . $name . '"' . $req . ' class="bb-input bb-select" style="' . $input_style . '"><option value="">Select...</option>';
				foreach ( $f['options'] as $opt ) {
					$html .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
				}
				$html .= '</select>';
			} elseif ( $f['type'] === 'radio' && ! empty( $f['options'] ) ) {
				$html .= '<div style="' . $opt_style . '" role="radiogroup" aria-required="' . ( $f['required'] ? 'true' : 'false' ) . '">';
				foreach ( $f['options'] as $opt ) {
					$html .= '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="radio" name="' . $name . '" value="' . esc_attr( $opt ) . '"' . $req . ' /><span>' . esc_html( $opt ) . '</span></label>';
				}
				$html .= '</div>';
			} elseif ( $f['type'] === 'checkbox' && ! empty( $f['options'] ) ) {
				$html .= '<div style="' . $opt_style . '" role="group">';
				foreach ( $f['options'] as $opt ) {
					$html .= '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr( $opt ) . '" /><span>' . esc_html( $opt ) . '</span></label>';
				}
				$html .= '</div>';
			} else {
				$html .= '<input type="' . esc_attr( $f['type'] === 'email' ? 'email' : 'text' ) . '" name="' . $name . '"' . $req . ' class="bb-input" style="' . $input_style . '" />';
			}
			$html .= '</div>';
		}
		$html .= '<input type="hidden" name="bb_node_id" value="' . $bb_id . '" />';
		$html .= '<button type="submit" style="padding:12px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;">' . $submit . '</button>';
		$html .= '</form></div>';
		return $html;
	}

	// -----------------------------------------------------------------------
	// Phase 5 — Marketing & Conversion widgets
	// -----------------------------------------------------------------------

	private function render_popup_trigger( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) ? $component['props'] : array();
		$trigger = isset( $props['trigger'] ) ? sanitize_text_field( $props['trigger'] ) : 'click';
		$delay   = isset( $props['delay'] ) ? absint( $props['delay'] ) : 3000;
		$scroll  = isset( $props['scrollPercent'] ) ? absint( $props['scrollPercent'] ) : 50;
		$freq    = isset( $props['frequency'] ) ? sanitize_text_field( $props['frequency'] ) : 'session';
		$btn_txt = isset( $props['buttonText'] ) ? esc_html( $props['buttonText'] ) : 'Open';
		$content = isset( $props['content'] ) ? wp_kses_post( $props['content'] ) : '';

		$overlay  = '<div class="bb-popup-overlay" data-bb-popup-content="' . $bb_id . '" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;display:none;align-items:center;justify-content:center;">';
		$overlay .= '<div class="bb-popup-box" style="background:#fff;border-radius:12px;padding:32px;max-width:520px;width:90%;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">';
		$overlay .= '<button class="bb-popup-close" aria-label="Close" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:24px;cursor:pointer;color:#64748b;">&times;</button>';
		$overlay .= '<div class="bb-popup-body">' . $content . '</div>';
		$overlay .= '</div></div>';

		if ( $trigger === 'click' ) {
			$html  = '<div id="bb-' . $id . '" class="bb-PopupTrigger" data-bb-type="PopupTrigger" data-bb-id="' . $bb_id . '">';
			$html .= '<button class="bb-popup-trigger-btn" data-bb-popup-id="' . $bb_id . '" style="padding:10px 24px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">' . $btn_txt . '</button>';
			$html .= $overlay;
			$html .= '</div>';
		} else {
			$html  = '<div id="bb-' . $id . '" class="bb-PopupTrigger" data-bb-type="PopupTrigger" data-bb-id="' . $bb_id . '"';
			$html .= ' data-bb-popup-trigger="' . esc_attr( $trigger ) . '"';
			$html .= ' data-bb-popup-delay="' . $delay . '"';
			$html .= ' data-bb-popup-scroll="' . $scroll . '"';
			$html .= ' data-bb-popup-freq="' . esc_attr( $freq ) . '"';
			$html .= ' style="display:none;">';
			$html .= $overlay;
			$html .= '</div>';
		}
		return $html;
	}

	private function render_comparison_table( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$props  = isset( $component['props'] ) ? $component['props'] : array();
		$plans  = isset( $props['plans'] ) && is_array( $props['plans'] ) ? $props['plans'] : array();
		$feats  = isset( $props['features'] ) && is_array( $props['features'] ) ? $props['features'] : array();
		$sticky = isset( $props['stickyColumn'] ) && $props['stickyColumn'] !== false;
		$hl     = isset( $props['highlightPlan'] ) ? intval( $props['highlightPlan'] ) : -1;

		if ( empty( $plans ) || empty( $feats ) ) {
			return '<div id="bb-' . $id . '" class="bb-ComparisonTable" data-bb-type="ComparisonTable" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Add plans and features in the right panel to build your comparison table.</div>';
		}

		$sticky_css = $sticky ? 'position:sticky;left:0;z-index:1;background:#fff;' : '';

		$html  = '<div id="bb-' . $id . '" class="bb-ComparisonTable" data-bb-type="ComparisonTable" data-bb-id="' . $bb_id . '" style="overflow-x:auto;width:100%;">';
		$html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
		$html .= '<thead><tr><th style="' . $sticky_css . 'padding:12px 16px;text-align:left;border-bottom:2px solid #e5e7eb;font-weight:600;color:#374151;">Feature</th>';
		foreach ( $plans as $i => $plan ) {
			$bg = $i === $hl ? 'background:#eff6ff;' : '';
			$cl = $i === $hl ? 'color:#2563eb;' : 'color:#374151;';
			$html .= '<th style="padding:12px 16px;text-align:center;border-bottom:2px solid #e5e7eb;font-weight:700;font-size:15px;' . $cl . $bg . '">' . esc_html( $plan ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $feats as $fi => $feat ) {
			if ( ! is_array( $feat ) || ! isset( $feat['name'] ) ) continue;
			$row_bg = $fi % 2 === 0 ? '#f9fafb' : '#fff';
			$html .= '<tr style="background:' . $row_bg . ';">';
			$cell_bg = $sticky ? $row_bg : '';
			$html .= '<td style="' . $sticky_css . ( $cell_bg ? 'background:' . $cell_bg . ';' : '' ) . 'padding:10px 16px;font-weight:500;border-bottom:1px solid #e5e7eb;color:#374151;">' . esc_html( $feat['name'] ) . '</td>';
			$vals = isset( $feat['values'] ) && is_array( $feat['values'] ) ? $feat['values'] : array();
			foreach ( $vals as $vi => $val ) {
				$is_check = in_array( $val, array( '✓', 'yes', 'true', 'Yes', 'True' ), true );
				$is_cross = in_array( $val, array( '✗', 'no', 'false', 'No', 'False' ), true );
				$cl = $is_check ? 'color:#16a34a;font-weight:700;font-size:18px;' : ( $is_cross ? 'color:#dc2626;font-weight:700;font-size:18px;' : 'color:#6b7280;' );
				$bg = $vi === $hl ? 'background:#eff6ff;' : '';
				$disp = $is_check ? '✓' : ( $is_cross ? '✗' : esc_html( $val ) );
				$html .= '<td style="padding:10px 16px;text-align:center;border-bottom:1px solid #e5e7eb;' . $cl . $bg . '">' . $disp . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	private function render_pricing_calculator( $component, $id ) {
		$bb_id    = esc_attr( $component['id'] );
		$props    = isset( $component['props'] ) ? $component['props'] : array();
		$fields   = isset( $props['fields'] ) && is_array( $props['fields'] ) ? $props['fields'] : array();
		$base     = isset( $props['basePrice'] ) ? floatval( $props['basePrice'] ) : 0;
		$currency = isset( $props['currency'] ) ? esc_html( $props['currency'] ) : '$';
		$label    = isset( $props['resultLabel'] ) ? esc_html( $props['resultLabel'] ) : 'Estimated total';
		$period   = isset( $props['period'] ) ? esc_html( $props['period'] ) : '/mo';

		if ( empty( $fields ) ) {
			return '<div id="bb-' . $id . '" class="bb-PricingCalculator" data-bb-type="PricingCalculator" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:12px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Add calculator fields in the right panel (range sliders and checkboxes with prices).</div>';
		}

		$fields_json = esc_attr( wp_json_encode( $fields ) );

		$html  = '<div id="bb-' . $id . '" class="bb-PricingCalculator" data-bb-type="PricingCalculator" data-bb-id="' . $bb_id . '"';
		$html .= ' data-bb-calc-fields="' . $fields_json . '"';
		$html .= ' data-bb-calc-base="' . $base . '"';
		$html .= ' data-bb-calc-currency="' . $currency . '"';
		$html .= ' data-bb-calc-label="' . $label . '"';
		$html .= ' data-bb-calc-period="' . $period . '"';
		$html .= ' style="width:100%;">';

		$html .= '<div style="display:flex;flex-direction:column;gap:20px;padding:24px;background:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;">';
		$total = $base;
		foreach ( $fields as $i => $f ) {
			if ( ! is_array( $f ) ) continue;
			$f_type  = isset( $f['type'] ) ? $f['type'] : 'range';
			$f_label = isset( $f['label'] ) ? esc_html( $f['label'] ) : 'Field';
			$f_price = isset( $f['price'] ) ? floatval( $f['price'] ) : 0;
			$f_def   = isset( $f['default'] ) ? floatval( $f['default'] ) : 0;

			$html .= '<div style="display:flex;flex-direction:column;gap:6px;">';
			if ( $f_type === 'range' ) {
				$min  = isset( $f['min'] ) ? floatval( $f['min'] ) : 0;
				$max  = isset( $f['max'] ) ? floatval( $f['max'] ) : 100;
				$step = isset( $f['step'] ) ? floatval( $f['step'] ) : 1;
				$html .= '<div style="display:flex;justify-content:space-between;font-size:14px;font-weight:500;color:#374151;"><span>' . $f_label . '</span><span style="color:#2563eb;font-weight:600;" data-bb-calc-val="' . $i . '">' . intval( $f_def ) . '</span></div>';
				$html .= '<input type="range" min="' . $min . '" max="' . $max . '" step="' . $step . '" value="' . $f_def . '" data-bb-calc-field="' . $i . '" style="width:100%;accent-color:#2563eb;" />';
				$total += $f_def * $f_price;
			} elseif ( $f_type === 'checkbox' ) {
				$checked = $f_def ? ' checked' : '';
				$html .= '<div style="display:flex;justify-content:space-between;font-size:14px;font-weight:500;color:#374151;"><span>' . $f_label . '</span><span style="color:#6b7280;font-size:13px;">' . $currency . number_format( $f_price, 2 ) . '</span></div>';
				$html .= '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" data-bb-calc-field="' . $i . '"' . $checked . ' style="accent-color:#2563eb;width:18px;height:18px;" /><span style="font-size:13px;color:#6b7280;">Add ' . $f_label . '</span></label>';
				if ( $f_def ) $total += $f_price;
			}
			$html .= '</div>';
		}
		$html .= '<div style="border-top:2px solid #e5e7eb;padding-top:16px;display:flex;justify-content:space-between;align-items:baseline;">';
		$html .= '<span style="font-size:16px;font-weight:600;color:#374151;">' . $label . '</span>';
		$html .= '<span style="font-size:28px;font-weight:700;color:#2563eb;" data-bb-calc-total>' . $currency . number_format( $total, 2 ) . '<span style="font-size:14px;font-weight:400;color:#6b7280;">' . $period . '</span></span>';
		$html .= '</div></div></div>';
		return $html;
	}

	private function render_lottie_animation( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) ? $component['props'] : array();
		$src     = isset( $props['src'] ) ? esc_url( $props['src'] ) : '';
		$loop    = isset( $props['loop'] ) && $props['loop'] !== false ? 'true' : 'false';
		$auto    = isset( $props['autoplay'] ) && $props['autoplay'] !== false ? 'true' : 'false';
		$speed   = isset( $props['speed'] ) ? floatval( $props['speed'] ) : 1;
		$trigger = isset( $props['trigger'] ) ? sanitize_text_field( $props['trigger'] ) : 'none';

		if ( ! $src ) {
			return '<div id="bb-' . $id . '" class="bb-LottieAnimation" data-bb-type="LottieAnimation" data-bb-id="' . $bb_id . '" style="width:200px;height:200px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">Lottie animation</div>';
		}

		return '<div id="bb-' . $id . '" class="bb-LottieAnimation" data-bb-type="LottieAnimation" data-bb-id="' . $bb_id . '"'
			. ' data-bb-lottie-src="' . $src . '"'
			. ' data-bb-lottie-loop="' . $loop . '"'
			. ' data-bb-lottie-autoplay="' . $auto . '"'
			. ' data-bb-lottie-speed="' . $speed . '"'
			. ' data-bb-lottie-trigger="' . esc_attr( $trigger ) . '"'
			. '></div>';
	}

	/**
	 * Render Data Table (Phase 6).
	 */
	private function render_data_table( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$columns = isset( $props['columns'] ) && is_array( $props['columns'] ) ? $props['columns'] : array();
		$rows    = isset( $props['rows'] ) && is_array( $props['rows'] ) ? $props['rows'] : array();
		$stack   = ! isset( $props['responsiveStack'] ) || $props['responsiveStack'] !== false;

		if ( count( $columns ) === 0 ) {
			return '<div id="bb-' . $id . '" class="bb-DataTable" data-bb-type="DataTable" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Add columns and rows in the right panel.</div>';
		}

		$html  = '<div id="bb-' . $id . '" class="bb-DataTable' . ( $stack ? ' bb-DataTable--stack' : '' ) . '" data-bb-type="DataTable" data-bb-id="' . $bb_id . '" data-bb-sortable="true" data-bb-filterable="false" data-bb-responsive-stack="' . ( $stack ? 'true' : 'false' ) . '" style="width:100%;overflow-x:auto;">';
		$html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr>';
		foreach ( $columns as $col ) {
			$html .= '<th style="padding:12px 16px;text-align:left;border-bottom:2px solid #e5e7eb;font-weight:600;color:#374151;">' . esc_html( is_string( $col ) ? $col : '' ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		$ri = 0;
		foreach ( $rows as $row ) {
			$row_arr = is_array( $row ) ? $row : array();
			$bg      = ( $ri % 2 === 0 ) ? '#f9fafb' : '#fff';
			$html   .= '<tr style="background:' . $bg . ';">';
			$ri++;
			foreach ( $columns as $ci => $col ) {
				$cell = isset( $row_arr[ $ci ] ) ? $row_arr[ $ci ] : '';
				$html .= '<td style="padding:10px 16px;border-bottom:1px solid #e5e7eb;color:#374151;"' . ( $stack ? ' data-bb-label="' . esc_attr( is_string( $col ) ? $col : '' ) . '"' : '' ) . '>' . esc_html( is_string( $cell ) ? $cell : (string) $cell ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Render Timeline (Phase 6).
	 */
	private function render_timeline( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$items   = isset( $props['items'] ) && is_array( $props['items'] ) ? $props['items'] : array();
		$orient  = isset( $props['orientation'] ) && $props['orientation'] === 'horizontal' ? 'horizontal' : 'vertical';
		$is_h    = $orient === 'horizontal';

		if ( count( $items ) === 0 ) {
			return '<div id="bb-' . $id . '" class="bb-Timeline" data-bb-type="Timeline" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Add timeline items in the right panel.</div>';
		}

		$html = '<div id="bb-' . $id . '" class="bb-Timeline bb-Timeline--' . $orient . '" data-bb-type="Timeline" data-bb-id="' . $bb_id . '" data-bb-orientation="' . esc_attr( $orient ) . '" style="width:100%;">';
		$html .= '<div style="display:' . ( $is_h ? 'flex' : 'block' ) . ';' . ( $is_h ? 'flex-direction:row;' : '' ) . 'gap:' . ( $is_h ? '0' : '24px' ) . ';position:relative;">';
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$date    = isset( $item['date'] ) ? $item['date'] : '';
			$title   = isset( $item['title'] ) ? $item['title'] : 'Title';
			$content = isset( $item['content'] ) ? $item['content'] : '';
			$html   .= '<div class="bb-Timeline-item" style="display:' . ( $is_h ? 'flex' : 'block' ) . ';' . ( $is_h ? 'flex:1;flex-direction:column;align-items:center;' : '' ) . 'position:relative;padding-left:' . ( $is_h ? '0' : '32px' ) . ';padding-top:' . ( $is_h ? '0' : '4px' ) . ';">';
			if ( $i < count( $items ) - 1 ) {
				$html .= '<div style="position:absolute;' . ( $is_h ? 'left:50%;top:12px;width:100%;height:2px;background:#e5e7eb;' : 'left:7px;top:28px;bottom:-24px;width:2px;background:#e5e7eb;' ) . '"></div>';
			}
			$html .= '<div style="position:absolute;left:' . ( $is_h ? '50%' : '0' ) . ';top:0;' . ( $is_h ? 'transform:translateX(-50%);' : '' ) . 'width:16px;height:16px;border-radius:50%;background:#2563eb;border:2px solid #fff;box-shadow:0 0 0 2px #e5e7eb;"></div>';
			if ( $date !== '' ) {
				$html .= '<div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;' . ( $is_h ? 'text-align:center;' : '' ) . '">' . esc_html( $date ) . '</div>';
			}
			$html .= '<div style="font-size:15px;font-weight:600;color:#111827;margin-bottom:4px;' . ( $is_h ? 'text-align:center;' : '' ) . '">' . esc_html( $title ) . '</div>';
			if ( $content !== '' ) {
				$html .= '<div style="font-size:14px;color:#6b7280;line-height:1.5;' . ( $is_h ? 'text-align:center;' : '' ) . '">' . esc_html( $content ) . '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Render Before/After image slider (Phase 6).
	 */
	private function render_before_after_slider( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$before = isset( $props['beforeSrc'] ) ? esc_url( $props['beforeSrc'] ) : '';
		$after  = isset( $props['afterSrc'] ) ? esc_url( $props['afterSrc'] ) : '';
		$pos    = isset( $props['defaultPosition'] ) ? min( 100, max( 0, (int) $props['defaultPosition'] ) ) : 50;
		$before_alt = isset( $props['beforeAlt'] ) ? esc_attr( $props['beforeAlt'] ) : 'Before';
		$after_alt  = isset( $props['afterAlt'] ) ? esc_attr( $props['afterAlt'] ) : 'After';

		if ( $before === '' && $after === '' ) {
			return '<div id="bb-' . $id . '" class="bb-BeforeAfterSlider" data-bb-type="BeforeAfterSlider" data-bb-id="' . $bb_id . '" style="min-height:200px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;display:flex;align-items:center;justify-content:center;">Set before and after images in the right panel.</div>';
		}

		$html  = '<div id="bb-' . $id . '" class="bb-BeforeAfterSlider" data-bb-type="BeforeAfterSlider" data-bb-id="' . $bb_id . '" data-bb-default-position="' . $pos . '" style="position:relative;width:100%;overflow:hidden;border-radius:8px;user-select:none;">';
		$html .= '<div style="position:absolute;inset:0;background-size:cover;background-position:center;">';
		if ( $after !== '' ) {
			$html .= '<img src="' . $after . '" alt="' . $after_alt . '" style="width:100%;height:100%;object-fit:cover;display:block;" />';
		} else {
			$html .= '<div style="width:100%;height:100%;background:#e5e7eb;"></div>';
		}
		$html .= '</div>';
		$html .= '<div style="position:absolute;left:0;top:0;bottom:0;width:' . $pos . '%;overflow:hidden;z-index:1;">';
		if ( $before !== '' ) {
			$html .= '<img src="' . $before . '" alt="' . $before_alt . '" style="position:absolute;left:0;top:0;height:100%;width:' . ( $pos > 0 ? ( ( 100 / $pos ) * 100 ) : 0 ) . '%;max-width:none;object-fit:cover;display:block;" />';
		} else {
			$html .= '<div style="width:100%;height:100%;background:#d1d5db;"></div>';
		}
		$html .= '</div>';
		$html .= '<div role="slider" aria-valuenow="' . $pos . '" aria-valuemin="0" aria-valuemax="100" style="position:absolute;left:' . $pos . '%;top:0;bottom:0;width:4px;margin-left:-2px;background:#fff;cursor:ew-resize;box-shadow:0 0 8px rgba(0,0,0,0.3);z-index:2;"><div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:40px;height:40px;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.2);display:flex;align-items:center;justify-content:center;font-size:18px;">⟷</div></div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Render Chart placeholder (Phase 6). Runtime draws via React/canvas.
	 */
	private function render_chart( $component, $id ) {
		$bb_id    = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$type    = isset( $props['type'] ) && in_array( $props['type'], array( 'bar', 'line', 'pie' ), true ) ? $props['type'] : 'bar';
		$labels  = isset( $props['labels'] ) && is_array( $props['labels'] ) ? $props['labels'] : array();
		$datasets = isset( $props['datasets'] ) && is_array( $props['datasets'] ) ? $props['datasets'] : array();

		if ( count( $datasets ) === 0 || ( $type !== 'pie' && count( $labels ) === 0 ) ) {
			return '<div id="bb-' . $id . '" class="bb-Chart" data-bb-type="Chart" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Add labels and datasets in the right panel.</div>';
		}

		$datasets_json = wp_json_encode( $datasets );
		$labels_json   = wp_json_encode( $labels );
		return '<div id="bb-' . $id . '" class="bb-Chart" data-bb-type="Chart" data-bb-id="' . $bb_id . '" data-bb-chart-type="' . esc_attr( $type ) . '" data-bb-chart-labels="' . esc_attr( $labels_json ) . '" data-bb-chart-datasets="' . esc_attr( $datasets_json ) . '" style="width:100%;height:280px;"></div>';
	}

	/**
	 * Render an unknown/generic component as a div.
	 */
	private function render_generic( $component, $id ) {
		$type  = esc_attr( $component['type'] );
		$bb_id = esc_attr( $component['id'] );
		$html  = "<div id=\"bb-{$id}\" class=\"bb-{$type}\" data-bb-type=\"{$type}\" data-bb-id=\"{$bb_id}\">";
		$html .= $this->render_children( $component );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render Conditional Display (Phase 7). Wrapper with rules; runtime evaluates.
	 */
	private function render_conditional_display( $component, $id ) {
		$bb_id  = esc_attr( $component['id'] );
		$props  = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$rules  = isset( $props['rules'] ) && is_array( $props['rules'] ) ? $props['rules'] : array();
		$match  = isset( $props['matchMode'] ) && $props['matchMode'] === 'any' ? 'any' : 'all';
		$rules_json = wp_json_encode( $rules );
		$html   = '<div id="bb-' . $id . '" class="bb-ConditionalDisplay" data-bb-type="ConditionalDisplay" data-bb-id="' . $bb_id . '"';
		$html  .= ' data-bb-conditional-rules="' . esc_attr( $rules_json ) . '" data-bb-conditional-match="' . esc_attr( $match ) . '" style="width:100%;">';
		$html  .= $this->render_children( $component );
		$html  .= '</div>';
		return $html;
	}

	/**
	 * Render Code Embed / Pro (Phase 7). HTML + scoped CSS + optional JS.
	 */
	private function render_code_embed( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$html_raw = isset( $props['html'] ) ? $props['html'] : '';
		$css_raw  = isset( $props['css'] ) ? $props['css'] : '';
		$js_raw   = isset( $props['js'] ) ? $props['js'] : '';
		$allow_script = ! empty( $props['allowScript'] );

		$html_safe = is_string( $html_raw ) ? wp_kses_post( $html_raw ) : '';
		$css_safe  = is_string( $css_raw ) ? wp_strip_all_tags( $css_raw ) : '';
		$css_safe  = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $css_safe );
		$css_safe  = preg_replace( '/expression\s*\(/i', '', $css_safe );

		$out = '<div id="bb-' . $id . '" class="bb-CodeEmbed" data-bb-type="CodeEmbed" data-bb-id="' . $bb_id . '" style="width:100%;">';
		if ( $css_safe !== '' ) {
			$out .= '<style scoped>#bb-' . $id . ' { box-sizing: border-box; } #bb-' . $id . ' *, #bb-' . $id . ' *::before, #bb-' . $id . ' *::after { box-sizing: inherit; } ' . $css_safe . '</style>';
		}
		if ( $html_safe !== '' ) {
			$out .= $html_safe;
		}
		if ( $allow_script && is_string( $js_raw ) && trim( $js_raw ) !== '' ) {
			$js_safe = preg_replace( '/<\/script\s*>/i', '', $js_raw );
			$out .= '<script>(function(){ var el = document.getElementById("bb-' . $id . '"); if(!el) return; ' . $js_safe . ' })();</script>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render API Data widget placeholder (Phase 7). Runtime fetches and renders.
	 */
	private function render_api_data_widget( $component, $id ) {
		$bb_id   = esc_attr( $component['id'] );
		$props   = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
		$url     = isset( $props['url'] ) ? esc_url( $props['url'] ) : '';
		$list_path = isset( $props['listPath'] ) ? sanitize_text_field( $props['listPath'] ) : 'data';
		$title_key = isset( $props['titleKey'] ) ? sanitize_text_field( $props['titleKey'] ) : 'title';
		$body_key  = isset( $props['bodyKey'] ) ? sanitize_text_field( $props['bodyKey'] ) : 'body';
		$image_key = isset( $props['imageKey'] ) ? sanitize_text_field( $props['imageKey'] ) : 'image';
		$layout    = isset( $props['layout'] ) && $props['layout'] === 'cards' ? 'cards' : 'list';
		$empty_msg = isset( $props['emptyMessage'] ) ? esc_html( $props['emptyMessage'] ) : 'No items.';

		if ( $url === '' ) {
			return '<div id="bb-' . $id . '" class="bb-ApiDataWidget" data-bb-type="ApiDataWidget" data-bb-id="' . $bb_id . '" style="padding:24px;background:#f9fafb;border-radius:8px;border:1px dashed #e5e7eb;color:#6b7280;font-size:13px;text-align:center;">Set API URL and optional list path in the right panel.</div>';
		}

		$data_attrs = ' data-bb-api-url="' . $url . '" data-bb-api-list-path="' . esc_attr( $list_path ) . '"';
		$data_attrs .= ' data-bb-api-title-key="' . esc_attr( $title_key ) . '" data-bb-api-body-key="' . esc_attr( $body_key ) . '" data-bb-api-image-key="' . esc_attr( $image_key ) . '"';
		$data_attrs .= ' data-bb-api-layout="' . esc_attr( $layout ) . '" data-bb-api-empty="' . esc_attr( $empty_msg ) . '"';
		return '<div id="bb-' . $id . '" class="bb-ApiDataWidget" data-bb-type="ApiDataWidget" data-bb-id="' . $bb_id . '"' . $data_attrs . ' style="width:100%;min-height:80px;">Loading…</div>';
	}

	/**
	 * Render all children of a component.
	 */
	private function render_children( $component ) {
		if ( empty( $component['children'] ) || ! is_array( $component['children'] ) ) {
			return '';
		}

		$html = '';
		foreach ( $component['children'] as $child ) {
			$html .= $this->render_component( $child );
		}

		return $html;
	}
}
