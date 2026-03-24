<?php
/**
 * Bridge Builder full-page template.
 *
 * Replaces the theme template entirely for builder pages. The theme does not run:
 * no theme header, footer, or main content. We output a minimal shell and render
 * BB header, page content, and footer via hooks. Theme styles/scripts still load
 * via wp_head()/wp_footer() if enqueued.
 *
 * @package Bridge_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
// BB header (priority 5) and singular content (priority 15) run from wp_body_open.
?>
<?php wp_footer(); ?>
</body>
</html>
