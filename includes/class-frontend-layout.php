<?php
/**
 * Bridge Builder Frontend Layout
 *
 * Single place for how builder content is displayed on the public site.
 * Full-bleed, no horizontal scroll, inner content constrained.
 * All output is filterable so themes and other plugins can adapt.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_Frontend_Layout {

	/**
	 * Default CSS for builder pages.
	 *
	 * The plugin overrides the theme: we force full-bleed with !important so that
	 * theme rules (e.g. .is-layout-constrained max-width) cannot constrain our wrapper.
	 * No reliance on theme classes like alignfull.
	 *
	 * Contract:
	 * - body.bb-builder-page: prevents horizontal scroll.
	 * - .bb-builder-wrap: full viewport width; theme max-width/margins overridden.
	 * - .bb-builder-wrap #bb-render: inner content constrained to wrapper.
	 *
	 * @return string CSS (no wrapping <style> tag).
	 */
	public static function get_default_css() {
		$css = array(
			'/* Bridge Builder: plugin overrides theme for full-bleed */',
			'body.bb-builder-page{overflow-x:hidden;}',
			'.bb-builder-wrap{width:100vw!important;max-width:none!important;position:relative!important;left:50%!important;margin-left:-50vw!important;margin-right:-50vw!important;box-sizing:border-box;}',
			'.bb-builder-wrap #bb-render{max-width:100%;box-sizing:border-box;}',
			'.bb-builder-wrap #bb-render *{box-sizing:border-box;}',
		);
		return implode( "\n", $css );
	}

	/**
	 * Get the CSS to output for builder pages (filterable).
	 *
	 * @return string CSS (no <style> tag).
	 */
	public static function get_wrapper_css() {
		$css = self::get_default_css();
		return (string) apply_filters( 'bridge_builder_frontend_wrapper_css', $css );
	}

	/**
	 * Wrap builder HTML with layout styles and container.
	 *
	 * Plugin CSS uses !important to override theme constraints; no theme classes required.
	 *
	 * @param string $inner_html The rendered builder HTML (e.g. from Bridge_Builder_Renderer::render_page).
	 * @return string Full output: <style> + wrapper div with $inner_html inside.
	 */
	public static function wrap( $inner_html ) {
		$css   = self::get_wrapper_css();
		$wrap  = '<div class="bb-builder-wrap">' . $inner_html . '</div>';
		$html  = '<style id="bb-builder-layout-css">' . "\n" . esc_html( $css ) . "\n" . '</style>' . $wrap;

		return (string) apply_filters( 'bridge_builder_frontend_wrapper_html', $html, $inner_html );
	}
}
