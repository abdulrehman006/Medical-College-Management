<?php
/**
 * Plugin archive template — renders filterable, AJAX-driven blocks for a CPT
 * archive or a taxonomy term archive. Wrapped in the active theme's header and
 * footer so it inherits site chrome. A theme can override this file by copying
 * it to /mcm/archive-mcm.php, or by providing a standard archive-{cpt}.php /
 * taxonomy-{tax}.php template.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcm_ctx = MCM_Templates::context();

get_header();

if ( ! $mcm_ctx ) {
	// Defensive: should never happen, but never render a blank page.
	get_footer();
	return;
}

// Full-width hero (video/image) sits ABOVE the title and breaks out of the
// centred content column edge-to-edge. Hidden when nothing is set.
if ( ! empty( $mcm_ctx['hero'] ) ) {
	echo '<div class="mcm-hero-full">' . $mcm_ctx['hero'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		<?php echo $mcm_ctx['intro']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
				<?php echo MCM_Shortcodes::render_block( $mcm_block['atts'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
			<?php
		endforeach;
		?>
	<?php endif; ?>
</div>
<?php
get_footer();
