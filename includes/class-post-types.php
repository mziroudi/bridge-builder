<?php
/**
 * Bridge Builder Custom Post Types
 *
 * Sections, Header, Footer, Popups, Templates — all store _builder_json / _builder_css.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bridge_Builder_Post_Types {

	/** Post type keys. */
	const TYPE_SECTION  = 'bb_section';
	const TYPE_HEADER   = 'bb_header';
	const TYPE_FOOTER   = 'bb_footer';
	const TYPE_POPUP    = 'bb_popup';
	const TYPE_TEMPLATE = 'bb_template';

	/** All builder CPTs (for allowlist in save/load). */
	const BUILDER_TYPES = array(
		self::TYPE_SECTION,
		self::TYPE_HEADER,
		self::TYPE_FOOTER,
		self::TYPE_POPUP,
		self::TYPE_TEMPLATE,
	);

	public static function register() {
		$types = array(
			self::TYPE_SECTION  => array(
				'labels'    => array(
					'name'          => __( 'Sections', 'bridge-builder' ),
					'singular_name' => __( 'Section', 'bridge-builder' ),
					'add_new'       => __( 'Add Section', 'bridge-builder' ),
					'add_new_item'  => __( 'Add New Section', 'bridge-builder' ),
					'edit_item'     => __( 'Edit Section', 'bridge-builder' ),
				),
				'menu_icon' => 'dashicons-layout',
			),
			self::TYPE_HEADER   => array(
				'labels'    => array(
					'name'          => __( 'Headers', 'bridge-builder' ),
					'singular_name' => __( 'Header', 'bridge-builder' ),
					'add_new'       => __( 'Add Header', 'bridge-builder' ),
					'add_new_item'  => __( 'Add New Header', 'bridge-builder' ),
					'edit_item'     => __( 'Edit Header', 'bridge-builder' ),
				),
				'menu_icon' => 'dashicons-arrow-up-alt',
			),
			self::TYPE_FOOTER   => array(
				'labels'    => array(
					'name'          => __( 'Footers', 'bridge-builder' ),
					'singular_name' => __( 'Footer', 'bridge-builder' ),
					'add_new'       => __( 'Add Footer', 'bridge-builder' ),
					'add_new_item'  => __( 'Add New Footer', 'bridge-builder' ),
					'edit_item'     => __( 'Edit Footer', 'bridge-builder' ),
				),
				'menu_icon' => 'dashicons-arrow-down-alt',
			),
			self::TYPE_POPUP    => array(
				'labels'    => array(
					'name'          => __( 'Popups', 'bridge-builder' ),
					'singular_name' => __( 'Popup', 'bridge-builder' ),
					'add_new'       => __( 'Add Popup', 'bridge-builder' ),
					'add_new_item'  => __( 'Add New Popup', 'bridge-builder' ),
					'edit_item'     => __( 'Edit Popup', 'bridge-builder' ),
				),
				'menu_icon' => 'dashicons-welcome-widgets-menus',
			),
			self::TYPE_TEMPLATE => array(
				'labels'    => array(
					'name'          => __( 'Templates', 'bridge-builder' ),
					'singular_name' => __( 'Template', 'bridge-builder' ),
					'add_new'       => __( 'Add Template', 'bridge-builder' ),
					'add_new_item'  => __( 'Add New Template', 'bridge-builder' ),
					'edit_item'     => __( 'Edit Template', 'bridge-builder' ),
				),
				'menu_icon' => 'dashicons-admin-page',
			),
		);

		foreach ( $types as $post_type => $config ) {
			register_post_type( $post_type, array(
				'labels'              => $config['labels'],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			) );
		}
	}
}
