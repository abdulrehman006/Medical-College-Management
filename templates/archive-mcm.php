<?php
/**
 * Plugin archive template — renders filterable, AJAX-driven blocks for a CPT
 * archive or a taxonomy term archive, inside the active theme's header and
 * footer.
 *
 * Classic themes (Hello Elementor, Astra…) provide header.php/footer.php and
 * get_header()/get_footer() are used. Block themes (Twenty Twenty-Four…) have
 * no such files — calling them prints nothing and a deprecation notice — so
 * the theme's "header" and "footer" template parts are rendered instead, the
 * way WordPress's own template canvas does.
 *
 * A theme can override this file by copying it to /mcm/archive-mcm.php, or by
 * providing a standard archive-{cpt}.php / taxonomy-{tax}.php template.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcm_ctx = MCM_Templates::context();

// wp_is_block_theme() exists from WordPress 5.9; on 5.8 every theme is classic.
$mcm_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() && function_exists( 'do_blocks' );

if ( $mcm_block_theme ) {
	// Render the template parts BEFORE wp_head(), as the core template canvas
	// does, so the blocks they contain can enqueue their styles in <head>.
	$mcm_header_html = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
	$mcm_footer_html = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php
	echo $mcm_header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks from the active theme.
	echo '<main class="wp-block-group mcm-block-theme-main">';
} else {
	get_header();
	// The page's main landmark. Hello Elementor's own templates call it
	// #content (its skip link points there); other themes may already use that
	// ID in header.php, so they get a neutral one.
	echo '<main id="' . esc_attr( 'hello-elementor' === get_template() ? 'content' : 'mcm-main' ) . '" class="site-main mcm-main">';
}

if ( $mcm_ctx && isset( $mcm_ctx['page_html'] ) ) :
	// Department page template: the whole page, sections in a fixed order.
	echo $mcm_ctx['page_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by MCM_Department_Page::render().
elseif ( $mcm_ctx ) :
	// Full-width hero (video/image) sits ABOVE the title and breaks out of the
	// centred content column edge-to-edge. Hidden when nothing is set.
	if ( ! empty( $mcm_ctx['hero'] ) ) {
		echo '<div class="mcm-hero-full">' . $mcm_ctx['hero'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by MCM_Query::hero_banner().
	}
	?>
	<div class="mcm-archive">
		<header class="mcm-archive-header">
			<h1 class="mcm-archive-title"><?php echo esc_html( $mcm_ctx['heading'] ); ?></h1>
			<?php if ( ! empty( $mcm_ctx['description'] ) ) : ?>
				<div class="mcm-archive-desc"><?php echo wp_kses_post( $mcm_ctx['description'] ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( ! empty( $mcm_ctx['intro'] ) ) : ?>
			<?php echo $mcm_ctx['intro']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by MCM_TermMeta::message_html(). ?>
		<?php endif; ?>

		<?php if ( ! empty( $mcm_ctx['empty'] ) ) : ?>
			<p class="mcm-archive-empty"><?php esc_html_e( 'There is nothing to show here yet.', 'medical-college-manager' ); ?></p>
		<?php else : ?>
			<?php
			$mcm_multi = count( $mcm_ctx['blocks'] ) > 1;
			foreach ( $mcm_ctx['blocks'] as $mcm_block ) :
				?>
				<section class="mcm-archive-block">
					<?php if ( $mcm_multi && '' !== $mcm_block['label'] ) : ?>
						<h2 class="mcm-archive-section"><?php echo esc_html( $mcm_block['label'] ); ?></h2>
					<?php endif; ?>
					<?php echo MCM_Shortcodes::render_block( $mcm_block['atts'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render boundary output. ?>
				</section>
				<?php
			endforeach;
			?>
		<?php endif; ?>
	</div>
	<?php
endif; // Defensive: with no context only the theme chrome is printed, never a blank page.

if ( ! $mcm_block_theme ) {
	echo '</main>';
}
if ( $mcm_block_theme ) {
	echo '</main>';
	echo $mcm_footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks from the active theme.
	?>
</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
} else {
	get_footer();
}
