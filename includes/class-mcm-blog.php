<?php
/**
 * Blog posts shortcode — renders WordPress posts in a date-badge row layout.
 * Usage: [mcm_blog count="6" order="DESC" category="news"]
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Blog {

	const SVG_PEN   = 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z';
	const SVG_ARROW = 'M4 11h12.17l-5.59-5.59L12 4l8 8-8 8-1.41-1.41L16.17 13H4z';

	public function __construct() {
		add_shortcode( 'mcm_blog', array( $this, 'shortcode' ) );
	}

	public static function defaults() {
		return array(
			'count'     => 6,
			'orderby'   => 'date',      // date | title | rand | comment_count | menu_order.
			'order'     => 'DESC',      // ASC | DESC.
			'category'  => '',          // category slug(s), comma separated.
			'tag'       => '',          // tag slug(s), comma separated.
			'author'    => '',          // author ID.
			'post_type' => 'post',
			'offset'    => 0,
			'excerpt'   => 30,          // excerpt length in words (0 = hide).
			'intro'     => '',          // optional intro line above the list.
			'read_more' => '',          // custom read-more label.
			'class_css' => '',          // extra wrapper class.
		);
	}

	public function shortcode( $atts ) {
		return self::render( shortcode_atts( self::defaults(), $atts, 'mcm_blog' ) );
	}

	/**
	 * Build the blog list. Accepts a full or partial attribute array (so the
	 * Elementor widget can reuse it).
	 *
	 * @return string
	 */
	public static function render( $atts ) {
		return MCM_Safe::render(
			function () use ( $atts ) {
				return MCM_Blog::build( $atts );
			},
			'blog list'
		);
	}

	/**
	 * Unguarded builder; call render() instead.
	 *
	 * @internal
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function build( $atts ) {
		MCM_Assets::need();
		$a = wp_parse_args( $atts, self::defaults() );

		// Only publicly viewable types may be listed. Without this check,
		// post_type="mcm_student" (or any other plugin's private type) would
		// print private photos and text on a public page.
		$post_type = $a['post_type'] ? sanitize_key( $a['post_type'] ) : 'post';
		if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
			$post_type = 'post';
		}

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $a['count'] ),
			'orderby'             => sanitize_key( $a['orderby'] ),
			'order'               => ( 'ASC' === strtoupper( (string) $a['order'] ) ) ? 'ASC' : 'DESC',
			'offset'              => max( 0, (int) $a['offset'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( '' !== $a['author'] ) {
			$args['author'] = (int) $a['author'];
		}

		$tax_query = array();
		if ( '' !== $a['category'] ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $a['category'] ) ),
			);
		}
		if ( '' !== $a['tag'] ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $a['tag'] ) ),
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new WP_Query( apply_filters( 'mcm_blog_query_args', $args, $a ) );

		ob_start();
		echo '<section class="mcm-blog ' . esc_attr( $a['class_css'] ) . '">';

		if ( '' !== $a['intro'] ) {
			echo '<div class="mcm-blog-intro">' . esc_html( $a['intro'] ) . '</div>';
		}

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				echo self::post_row( get_the_ID(), $a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} else {
			echo '<p class="mcm-empty">' . esc_html__( 'No posts found.', 'medical-college-manager' ) . '</p>';
		}

		wp_reset_postdata();
		echo '</section>';
		return ob_get_clean();
	}

	private static function post_row( $id, $a ) {
		$link    = get_permalink( $id );
		$title   = get_the_title( $id );
		$day     = get_the_date( 'j', $id );
		$ym      = get_the_date( 'm, Y', $id );
		$has     = has_post_thumbnail( $id );
		$excerpt = self::excerpt( $id, (int) $a['excerpt'] );
		$rm      = '' !== $a['read_more'] ? $a['read_more'] : __( 'Read More', 'medical-college-manager' );

		ob_start();
		?>
		<article class="mcm-post<?php echo $has ? '' : ' mcm-post--no-thumb'; ?>">
			<div class="mcm-post-meta">
				<div class="mcm-date-badge">
					<span class="mcm-day"><?php echo esc_html( $day ); ?></span>
					<span class="mcm-ym"><?php echo esc_html( $ym ); ?></span>
				</div>
				<span class="mcm-edit-tile" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><path d="<?php echo self::SVG_PEN; // phpcs:ignore ?>"/></svg>
				</span>
			</div>

			<a class="mcm-post-thumb" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				if ( $has ) {
					echo get_the_post_thumbnail( $id, 'large', array( 'loading' => 'lazy', 'alt' => esc_attr( $title ) ) ); // phpcs:ignore
				} else {
					echo '<span class="mcm-post-thumb-ph"></span>';
				}
				?>
			</a>

			<div class="mcm-post-body">
				<h2 class="mcm-post-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></h2>
				<?php if ( '' !== $excerpt ) : ?>
					<p class="mcm-post-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				<a class="mcm-read-more" href="<?php echo esc_url( $link ); ?>">
					<?php echo esc_html( $rm ); ?>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="<?php echo self::SVG_ARROW; // phpcs:ignore ?>"/></svg>
				</a>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	private static function excerpt( $id, $words ) {
		if ( $words <= 0 ) {
			return '';
		}
		$text = get_the_excerpt( $id );
		if ( '' === $text ) {
			$text = wp_strip_all_tags( get_post_field( 'post_content', $id ) );
		}
		return wp_trim_words( $text, $words, '…' );
	}
}
