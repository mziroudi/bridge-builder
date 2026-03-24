<?php
/**
 * Bridge Builder CSS Generator
 *
 * Converts the JSON component tree's style definitions into scoped CSS.
 * Output is cached in post_meta and regenerated only on save.
 * Frontend can use structural-only CSS so theme styling is not overwritten.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_CSS_Generator {

	/**
	 * When true, only layout/spacing properties are output (for frontend theme compatibility).
	 *
	 * @var bool
	 */
	private $structural_only = false;

	/**
	 * Style keys included when structural_only is true (layout, spacing, dimensions).
	 *
	 * @var array
	 */
	private $structural_keys = array(
		'padding', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
		'margin', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
		'gap', 'rowGap', 'columnGap',
		'width', 'height', 'maxWidth', 'minWidth', 'maxHeight', 'minHeight',
		'display', 'flexDirection', 'flexWrap', 'alignItems', 'justifyContent', 'flex',
		'gridTemplateColumns', 'gridTemplateRows', 'gridColumn', 'gridRow',
		'alignSelf', 'order', 'boxSizing',
	);

	/**
	 * Map of camelCase JS property names to CSS property names.
	 *
	 * @var array
	 */
	private $css_map = array(
		'padding'         => 'padding',
		'paddingTop'      => 'padding-top',
		'paddingRight'    => 'padding-right',
		'paddingBottom'   => 'padding-bottom',
		'paddingLeft'     => 'padding-left',
		'margin'          => 'margin',
		'marginTop'       => 'margin-top',
		'marginRight'     => 'margin-right',
		'marginBottom'    => 'margin-bottom',
		'marginLeft'      => 'margin-left',
		'gap'             => 'gap',
		'rowGap'          => 'row-gap',
		'columnGap'       => 'column-gap',
		'width'           => 'width',
		'height'          => 'height',
		'maxWidth'        => 'max-width',
		'minWidth'        => 'min-width',
		'maxHeight'       => 'max-height',
		'minHeight'       => 'min-height',
		'display'         => 'display',
		'flexDirection'   => 'flex-direction',
		'flexWrap'        => 'flex-wrap',
		'alignItems'      => 'align-items',
		'justifyContent'  => 'justify-content',
		'flex'            => 'flex',
		'backgroundColor' => 'background-color',
		'color'           => 'color',
		'fontSize'        => 'font-size',
		'fontWeight'      => 'font-weight',
		'fontFamily'      => 'font-family',
		'lineHeight'      => 'line-height',
		'textAlign'       => 'text-align',
		'textDecoration'  => 'text-decoration',
		'border'                  => 'border',
		'borderRadius'            => 'border-radius',
		'borderTopLeftRadius'     => 'border-top-left-radius',
		'borderTopRightRadius'    => 'border-top-right-radius',
		'borderBottomRightRadius' => 'border-bottom-right-radius',
		'borderBottomLeftRadius'  => 'border-bottom-left-radius',
		'borderWidth'             => 'border-width',
		'borderColor'     => 'border-color',
		'boxShadow'            => 'box-shadow',
		'opacity'              => 'opacity',
		'transform'            => 'transform',
		'cursor'               => 'cursor',
		'gridTemplateColumns'  => 'grid-template-columns',
		'gridTemplateRows'     => 'grid-template-rows',
		'gridColumn'           => 'grid-column',
		'gridRow'              => 'grid-row',
		'alignSelf'            => 'align-self',
		'justifySelf'          => 'justify-self',
		'order'                => 'order',
		'boxSizing'            => 'box-sizing',
		'objectFit'            => 'object-fit',
		'objectPosition'       => 'object-position',
		'transition'           => 'transition',
		'pointerEvents'        => 'pointer-events',
		'zIndex'               => 'z-index',
		'top'                  => 'top',
		'right'                => 'right',
		'bottom'               => 'bottom',
		'left'                 => 'left',
		'position'             => 'position',
		'overflowX'            => 'overflow-x',
		'overflowY'            => 'overflow-y',
		'overflow'             => 'overflow',
	);

	/**
	 * Generate CSS for an entire component tree.
	 *
	 * @param array $tree            The root component node.
	 * @param bool  $structural_only If true, only layout/spacing/display properties (for frontend; theme controls look).
	 * @return string The generated CSS.
	 */
	public function generate( $tree, $structural_only = false ) {
		$this->structural_only = $structural_only;
		$css = '';

		// Support same shapes as renderer: root with 'children' or root as array of sections.
		$components = array();
		if ( isset( $tree['children'] ) && is_array( $tree['children'] ) ) {
			$components = $tree['children'];
		} elseif ( isset( $tree[0] ) && is_array( $tree ) ) {
			$components = $tree;
		}
		foreach ( $components as $component ) {
			$css .= $this->process_component( $component );
		}

		// Phase 6: DataTable responsive stack (mobile card layout)
		$css .= "\n/* DataTable responsive stack */\n";
		$css .= "@media (max-width: 768px) {\n";
		$css .= "  #bb-render.bb-content .bb-DataTable--stack thead { display: none; }\n";
		$css .= "  #bb-render.bb-content .bb-DataTable--stack tr { display: block; border-bottom: 1px solid #e5e7eb; padding: 8px 0; }\n";
		$css .= "  #bb-render.bb-content .bb-DataTable--stack td { display: block; padding: 4px 0 4px 0; }\n";
		$css .= "  #bb-render.bb-content .bb-DataTable--stack td::before { content: attr(data-bb-label); font-weight: 600; margin-right: 8px; color: #374151; }\n";
		$css .= "}\n";

		return $css;
	}

	/**
	 * Process a single component and its children recursively.
	 *
	 * @param array $component The component node.
	 * @return string CSS rules for this component.
	 */
	private function process_component( $component ) {
		if ( ! isset( $component['id'] ) ) {
			return '';
		}

		$id     = sanitize_html_class( $component['id'] );
		$styles = isset( $component['styles'] ) ? $component['styles'] : array();
		$props  = isset( $component['props'] ) ? $component['props'] : array();
		$output = '';

		// Desktop styles (default)
		$desktop = isset( $styles['desktop'] ) ? $styles['desktop'] : array();
		$has_hover = ! empty( $props['hoverBackgroundColor'] ) || ! empty( $props['hoverColor'] );
		if ( $has_hover && is_array( $desktop ) ) {
			$desktop['transition'] = isset( $props['transitionDuration'] ) && is_string( $props['transitionDuration'] )
				? $props['transitionDuration']
				: '300ms ease';
		}
		if ( ! empty( $desktop ) ) {
			$css_string = $this->styles_to_css( $desktop );
			if ( $css_string ) {
				$output .= "[data-bb-id=\"{$component['id']}\"] { {$css_string} }\n";
			}
		}

		// Hover styles (Button, Icon, etc.) from props
		if ( $has_hover ) {
			$hover_parts = array();
			if ( ! empty( $props['hoverBackgroundColor'] ) && is_string( $props['hoverBackgroundColor'] ) ) {
				$hover_parts[] = 'background-color: ' . $this->sanitize_css_value( $props['hoverBackgroundColor'] );
			}
			if ( ! empty( $props['hoverColor'] ) && is_string( $props['hoverColor'] ) ) {
				$hover_parts[] = 'color: ' . $this->sanitize_css_value( $props['hoverColor'] );
			}
			if ( ! empty( $hover_parts ) ) {
				$output .= "[data-bb-id=\"{$component['id']}\"]:hover { " . implode( '; ', $hover_parts ) . " }\n";
			}
		}

		// Tablet styles (768px – 1023px)
		$tablet = isset( $styles['tablet'] ) ? $styles['tablet'] : array();
		if ( ! empty( $tablet ) ) {
			$css_string = $this->styles_to_css( $tablet );
			if ( $css_string ) {
				$output .= "@media (min-width: 768px) and (max-width: 1023px) { [data-bb-id=\"{$component['id']}\"] { {$css_string} } }\n";
			}
		}

		// Mobile styles (max-width: 767px)
		$mobile = isset( $styles['mobile'] ) ? $styles['mobile'] : array();
		if ( ! empty( $mobile ) ) {
			$css_string = $this->styles_to_css( $mobile );
			if ( $css_string ) {
				$output .= "@media (max-width: 767px) { [data-bb-id=\"{$component['id']}\"] { {$css_string} } }\n";
			}
		}

		// Column: hide on tablet/mobile via props
		if ( isset( $component['props'] ) ) {
			$props = $component['props'];
			if ( ! empty( $props['hideOnMobile'] ) && $props['hideOnMobile'] !== 'false' && $props['hideOnMobile'] !== false ) {
				$output .= "@media (max-width: 767px) { [data-bb-id=\"{$component['id']}\"] { display: none !important; } }\n";
			}
			if ( ! empty( $props['hideOnTablet'] ) && $props['hideOnTablet'] !== 'false' && $props['hideOnTablet'] !== false ) {
				$output .= "@media (min-width: 768px) and (max-width: 1023px) { [data-bb-id=\"{$component['id']}\"] { display: none !important; } }\n";
			}
		}

		// Recurse into children
		if ( isset( $component['children'] ) && is_array( $component['children'] ) ) {
			foreach ( $component['children'] as $child ) {
				$output .= $this->process_component( $child );
			}
		}

		return $output;
	}

	/**
	 * Convert a styles associative array to a CSS string.
	 *
	 * @param array $styles Key-value pairs of camelCase CSS properties.
	 * @return string CSS declarations.
	 */
	private function styles_to_css( $styles ) {
		$declarations = array();

		foreach ( $styles as $key => $value ) {
			// Skip empty values
			if ( '' === $value || null === $value ) {
				continue;
			}

			// In structural-only mode, skip decorative properties so theme can style.
			if ( $this->structural_only && ! in_array( $key, $this->structural_keys, true ) ) {
				continue;
			}

			// Map camelCase to CSS property
			$prop = isset( $this->css_map[ $key ] ) ? $this->css_map[ $key ] : $this->camel_to_kebab( $key );

			// Sanitize the value (basic sanitization)
			$safe_value = $this->sanitize_css_value( $value );

			$declarations[] = "{$prop}: {$safe_value}";
		}

		return implode( '; ', $declarations );
	}

	/**
	 * Convert camelCase to kebab-case.
	 *
	 * @param string $str The camelCase string.
	 * @return string The kebab-case string.
	 */
	private function camel_to_kebab( $str ) {
		return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $str ) );
	}

	/**
	 * Basic sanitization of a CSS value.
	 * Strips potentially dangerous content while allowing valid CSS.
	 *
	 * @param string $value The CSS value.
	 * @return string Sanitized CSS value.
	 */
	private function sanitize_css_value( $value ) {
		// Remove any script-related content
		$value = preg_replace( '/expression\s*\(/i', '', $value );
		$value = preg_replace( '/javascript\s*:/i', '', $value );
		$value = preg_replace( '/url\s*\(\s*["\']?\s*javascript/i', '', $value );

		// Remove control characters
		$value = preg_replace( '/[\x00-\x1f\x7f]/', '', $value );

		return $value;
	}
}
