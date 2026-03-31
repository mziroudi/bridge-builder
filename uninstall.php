<?php
/**
 * Uninstall Bridge Builder.
 *
 * Removes plugin options and transient data.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Global options.
delete_option( 'bridge_builder_active_header_id' );
delete_option( 'bridge_builder_active_footer_id' );
delete_option( 'bridge_builder_frontend_styling' );
delete_option( 'bridge_builder_global_design_system' );
delete_option( 'bridge_builder_ai_provider' );
delete_option( 'bridge_builder_nvidia_api_key' );
delete_option( 'bridge_builder_openai_api_key' );
delete_option( 'bridge_builder_claude_api_key' );
delete_option( 'bridge_builder_gemini_api_key' );
delete_option( 'bridge_builder_custom_ai_url' );
delete_option( 'bridge_builder_custom_ai_key' );
delete_option( 'bridge_builder_custom_ai_model' );

// Multisite-safe cleanup.
delete_site_option( 'bridge_builder_active_header_id' );
delete_site_option( 'bridge_builder_active_footer_id' );
delete_site_option( 'bridge_builder_frontend_styling' );
delete_site_option( 'bridge_builder_global_design_system' );
delete_site_option( 'bridge_builder_ai_provider' );
delete_site_option( 'bridge_builder_nvidia_api_key' );
delete_site_option( 'bridge_builder_openai_api_key' );
delete_site_option( 'bridge_builder_claude_api_key' );
delete_site_option( 'bridge_builder_gemini_api_key' );
delete_site_option( 'bridge_builder_custom_ai_url' );
delete_site_option( 'bridge_builder_custom_ai_key' );
delete_site_option( 'bridge_builder_custom_ai_model' );
