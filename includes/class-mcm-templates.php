<?php
/**
 * Front-end template integration: makes the canonical WordPress surfaces
 * (single CPT pages and archives) reflect the plugin's custom data without
 * shipping theme template files, so it works with any theme.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archive, department and single-page template integration.
 */
class MCM_Templates {

	/**
	 * Archive render context for the current request (set by archive_template).
	 *
	 * @var array|null
	 */
	private static $context = null;

	/**
	 * Post IDs whose featured image the theme has already rendered.
	 *
	 * @var array<int,bool>
	 */
	private static $thumb_shown = array();

	/**
	 * Post IDs whose single-page details were already appended this request.
	 *
	 * @var array<int,bool>
	 */
	private static $details_done = array();

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		// Show the custom fields on the native single CPT page.
		add_filter( 'the_content', array( $this, 'single_meta' ), 20 );
		// Must run before the_content so we know whether the theme already
		// printed the featured image for this post.
		add_filter( 'post_thumbnail_html', array( $this, 'note_thumbnail' ), 10, 2 );
		// Sensible default ordering on CPT archives.
		add_action( 'pre_get_posts', array( $this, 'archive_order' ) );
		// Align the main archive query's per-page with our block so pagination
		// (and /page/N/ URLs) resolve without a 404.
		add_action( 'pre_get_posts', array( $this, 'set_archive_per_page' ) );
		// Filterable archive / taxonomy templates (theme can override).
		add_filter( 'template_include', array( $this, 'archive_template' ) );
	}

	/**
	 * Per-page count for a CPT archive (filterable).
	 *
	 * @param string $cpt Post type.
	 */
	public static function archive_per_page( $cpt ) {
		return max( 1, (int) apply_filters( 'mcm_archive_per_page', 12, $cpt ) );
	}

	/**
	 * Match the archive main query's page size to the plugin's listing, so /page/N/ resolves.
	 *
	 * @param WP_Query $query The query.
	 */
	public function set_archive_per_page( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive() ) {
			return;
		}
		if ( '1' !== MCM_Settings::value( 'archive_filters' ) ) {
			return;
		}
		$cpt = $query->get( 'post_type' );
		if ( in_array( $cpt, array_keys( MCM_CPT::post_types() ), true ) ) {
			$query->set( 'posts_per_page', self::archive_per_page( $cpt ) );
		}
	}

	/** Accessor for the archive template file. */
	public static function context() {
		return self::$context;
	}

	/**
	 * Swap in the plugin's filterable archive template for our CPT archives and
	 * our taxonomy term archives — unless the feature is off or the active theme
	 * provides its own specific template (which always wins).
	 *
	 * @param string $template Template path.
	 */
	public function archive_template( $template ) {
		// Boundary: if building the archive context fails, the theme's own
		// template is used instead — the page still renders.
		// run(), not render(): the value is a file path, so no notice markup
		// may ever be appended to it.
		$chosen = MCM_Safe::run( array( $this, 'choose_archive_template' ), 'archive template', array( $template ), $template );
		return is_string( $chosen ) && '' !== $chosen ? $chosen : $template;
	}

	/**
	 * Unguarded template choice; see archive_template().
	 *
	 * @internal
	 * @param string $template Template path chosen by WordPress.
	 * @return string
	 */
	public function choose_archive_template( $template ) {
		if ( is_admin() || is_feed() ) {
			return $template;
		}
		// The department page template is its own switch: it works even when
		// the filterable archive pages are turned off.
		$dept_page = is_tax( 'mcm_department' ) && MCM_Department_Page::enabled();
		if ( '1' !== MCM_Settings::value( 'archive_filters' ) && ! $dept_page ) {
			return $template;
		}

		$ctx = $this->build_context();
		if ( null === $ctx ) {
			return $template;
		}

		// Theme override: if the theme ships a matching specific template, honour it.
		if ( locate_template( $ctx['specific'] ) ) {
			return $template;
		}

		// Elementor Pro Theme Builder: when an Archive template's conditions
		// match this page, the site owner designed it there — step aside.
		if ( apply_filters( 'mcm_yield_to_theme_builder', true ) && self::theme_builder_has( 'archive' ) ) {
			return $template;
		}

		// Allow a child/parent theme to override the plugin template via /mcm/.
		$override = locate_template( array( 'mcm/archive-mcm.php' ) );

		// 1.32 department page template — only with the plugin's own template
		// file (a theme's copy may predate it and keeps the classic page).
		// Rendered now, before the theme header, so its assets reach <head>;
		// if it fails, the classic context below is used instead.
		$page = null;
		if ( $dept_page && ! $override ) {
			$page = MCM_Department_Page::render_or_null( get_queried_object() );
			if ( null !== $page ) {
				$ctx['page_html'] = $page;
				MCM_Department_Page::mark_page();
			}
		}
		// Archive pages switched off: without the template, the theme's own page.
		if ( null === $page && '1' !== MCM_Settings::value( 'archive_filters' ) ) {
			return $template;
		}

		self::$context = $ctx;
		return $override ? $override : MCM_PATH . 'templates/archive-mcm.php';
	}

	/**
	 * Does Elementor Pro's Theme Builder have a template whose conditions match
	 * the current request for this location ('archive' or 'single')?
	 *
	 * Written defensively against Pro's internal API: every class and method is
	 * checked before use, and any failure counts as "no" (the plugin's own
	 * template is then used, as before). Not exercised without Elementor Pro.
	 *
	 * @param string $location Theme Builder location.
	 * @return bool
	 */
	public static function theme_builder_has( $location ) {
		$module_class = '\ElementorPro\Modules\ThemeBuilder\Module';
		if ( ! class_exists( $module_class ) || ! method_exists( $module_class, 'instance' ) ) {
			return false;
		}
		try {
			$module = call_user_func( array( $module_class, 'instance' ) );
			if ( ! is_object( $module ) || ! method_exists( $module, 'get_conditions_manager' ) ) {
				return false;
			}
			$conditions = $module->get_conditions_manager();
			if ( ! is_object( $conditions ) || ! method_exists( $conditions, 'get_documents_for_location' ) ) {
				return false;
			}
			$documents = $conditions->get_documents_for_location( $location );
			return ! empty( $documents );
		} catch ( \Throwable $e ) {
			MCM_Health::report( 'Elementor Pro Theme Builder check', $e );
			return false;
		}
	}

	/**
	 * Build the render context (heading, description, blocks, theme-override
	 * candidates) for the current archive, or null when we should not take over.
	 *
	 * @return array|null
	 */
	private function build_context() {
		$cpts = array_keys( MCM_CPT::post_types() );

		// CPT archive — one filterable block for that post type.
		if ( is_post_type_archive( $cpts ) ) {
			$obj = get_queried_object();
			$cpt = ( $obj instanceof WP_Post_Type ) ? $obj->name : '';
			if ( ! $cpt || ! is_post_type_viewable( $cpt ) ) {
				return null;
			}
			return array(
				'heading'     => post_type_archive_title( '', false ),
				'description' => '',
				// Optional full-width title banner (video/image) from Settings >
				// Page Banners, rendered above the heading.
				'hero'        => MCM_Settings::archive_banner_html( $cpt ),
				'intro'       => '',
				'specific'    => array( "archive-{$cpt}.php" ),
				'blocks'      => array(
					array(
						'label' => '',
						'atts'  => array(
							'type'     => $cpt,
							'filters'  => 'yes',
							'print'    => 'yes',
							'view'     => self::cpt_archive_view( $cpt ),
							'count'    => self::archive_per_page( $cpt ),
							'paginate' => 'yes',
							'paged'    => max( 1, (int) get_query_var( 'paged' ) ),
						),
					),
				),
			);
		}

		// Taxonomy term archive — one filterable block per public CPT that uses
		// this taxonomy, each scoped (locked) to the current term.
		$taxonomies = MCM_CPT::taxonomies();
		if ( is_tax( array_keys( $taxonomies ) ) ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return null;
			}
			$tax     = $term->taxonomy;
			$blocks  = self::term_blocks( $term );
			$tobj    = get_taxonomy( $tax );
			$heading = ( $tobj ? $tobj->labels->singular_name . ': ' : '' ) . $term->name;

			if ( empty( $blocks ) ) {
				// The term exists but has no public content yet — show a clean
				// heading with a friendly note rather than a bare/500 page.
				return array(
					'heading'     => $heading,
					'description' => term_description(),
					// Full-width hero above the title; HOD message stays in-column.
					'hero'        => MCM_TermMeta::hero_html( $term ),
					'intro'       => MCM_TermMeta::message_html( $term ),
					'specific'    => array( "taxonomy-{$tax}-{$term->slug}.php", "taxonomy-{$tax}.php" ),
					'blocks'      => array(),
					'empty'       => true,
				);
			}

			/*
			 * Term pages default to 12 per block, which silently hid the rest of
			 * a prolific professor's publications. A single block can own the
			 * page's ?paged var and paginate properly; several blocks cannot
			 * share one pager, so those get a generous cap instead.
			 */
			if ( 1 === count( $blocks ) ) {
				$blocks[0]['atts']['paginate'] = 'yes';
				$blocks[0]['atts']['paged']    = max( 1, (int) get_query_var( 'paged' ) );
				$blocks[0]['atts']['count']    = self::archive_per_page( $blocks[0]['atts']['type'] );
			} else {
				foreach ( $blocks as $i => $block ) {
					$blocks[ $i ]['atts']['count'] = (int) apply_filters( 'mcm_term_block_count', 100, $block['atts']['type'], $tax );
				}
			}

			return array(
				'heading'     => $heading,
				// Full-width hero above the title; HOD message stays in-column.
				'hero'        => MCM_TermMeta::hero_html( $term ),
				'intro'       => MCM_TermMeta::message_html( $term ),
				'description' => term_description(),
				'specific'    => array( "taxonomy-{$tax}-{$term->slug}.php", "taxonomy-{$tax}.php" ),
				'blocks'      => $blocks,
			);
		}

		return null;
	}

	/**
	 * Build one block per content type a term ACTUALLY has (populated only), so
	 * a department page is never a wall of empty "No results" sections. A filter
	 * bar is attached only where a section is big enough to warrant one. Shared
	 * by the term archive and the [mcm_department_sections] shortcode.
	 *
	 * @param WP_Term $term Term object.
	 * @return array[] Blocks (label + atts); empty if the term has no content.
	 */
	public static function term_blocks( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return array();
		}
		$tax        = $term->taxonomy;
		$taxonomies = MCM_CPT::taxonomies();
		$uses       = isset( $taxonomies[ $tax ]['post_types'] ) ? $taxonomies[ $tax ]['post_types'] : array();
		$threshold  = (int) apply_filters( 'mcm_term_filter_threshold', 8, $tax );
		$blocks     = array();

		// Sections an admin chose to hide for this department (e.g. timetable).
		$hidden = ( 'mcm_department' === $tax ) ? MCM_TermMeta::hidden_sections( $term->term_id ) : array();

		foreach ( $uses as $cpt ) {
			if ( ! is_post_type_viewable( $cpt ) ) {
				continue; // skip private types (e.g. students).
			}
			if ( isset( $hidden[ $cpt ] ) ) {
				continue; // admin hid this section on the department page.
			}
			$probe_args = array(
				'post_type'              => $cpt,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'              => array(
					array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			);
			// Run the probe through the same filters as the section itself, so
			// expired tenders/notices (hidden by mcm_query_args) cannot create a
			// section that then renders "No results found".
			$probe_atts = MCM_Query::parse_atts(
				array(
					'type'      => $cpt,
					'lock_tax'  => $tax,
					'lock_term' => $term->slug,
				)
			);
			$probe      = new WP_Query( apply_filters( 'mcm_query_args', $probe_args, $probe_atts ) );
			$found      = (int) $probe->found_posts;
			if ( $found < 1 ) {
				continue; // empty — no section.
			}
			$pt       = get_post_type_object( $cpt );
			$blocks[] = array(
				'label' => 'mcm_job' === $cpt ? __( 'Vacancies', 'medical-college-manager' ) : ( $pt ? $pt->labels->name : $cpt ),
				'atts'  => array(
					'type'      => $cpt,
					'filters'   => ( $found > $threshold ) ? 'yes' : 'no',
					'print'     => 'yes',
					'view'      => self::default_view( $cpt ),
					'lock_tax'  => $tax,
					'lock_term' => $term->slug,
					'count'     => (int) apply_filters( 'mcm_term_block_count', 100, $cpt, $tax ),
				),
			);
		}
		return $blocks;
	}

	/**
	 * Render every populated content section for a term as titled blocks — the
	 * body of a department page, usable standalone via [mcm_department_sections].
	 *
	 * @param WP_Term $term Term object.
	 * @return string
	 */
	public static function term_sections_html( $term ) {
		$blocks = self::term_blocks( $term );
		if ( empty( $blocks ) ) {
			return '';
		}
		// Always label each section here — when composed into a custom template
		// a titled "Courses" / "Faculty" block reads far clearer than a bare list.
		$out = '<div class="mcm-archive mcm-dept-sections">';
		foreach ( $blocks as $block ) {
			$out .= '<section class="mcm-archive-block">';
			if ( '' !== $block['label'] ) {
				$out .= '<h2 class="mcm-archive-section">' . esc_html( $block['label'] ) . '</h2>';
			}
			$out .= MCM_Shortcodes::render_block( $block['atts'] );
			$out .= '</section>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Timetable reads best as a table; everything else defaults to a grid.
	 *
	 * @param string $cpt Post type.
	 */
	private static function default_view( $cpt ) {
		if ( 'mcm_timetable' === $cpt ) {
			return 'table';
		}
		if ( 'mcm_publication' === $cpt ) {
			return 'citation'; // professor/department pages list proper citations.
		}
		return 'grid';
	}

	/**
	 * View for a single-CPT archive page. All archives use the Directory layout
	 * (left filter sidebar); the main area adapts per type (timetable = table,
	 * consultants/team = profile cards, others = info cards). Filterable.
	 *
	 * @param string $cpt Post type.
	 */
	private static function cpt_archive_view( $cpt ) {
		return (string) apply_filters( 'mcm_archive_view', 'directory', $cpt );
	}

	/**
	 * Append the meta block (and contact details, where relevant) to the
	 * content of a single MCM post on its own URL.
	 *
	 * @param string $content Post content.
	 */
	public function single_meta( $content ) {
		// Boundary: on failure the post content is returned untouched.
		return MCM_Safe::render( array( $this, 'build_single_meta' ), 'single page details', array( $content ), $content );
	}

	/**
	 * Unguarded single-page additions; see single_meta().
	 *
	 * @internal
	 * @param string $content Post content.
	 * @return string
	 */
	public function build_single_meta( $content ) {
		if ( is_admin() || is_feed() || ! is_singular() || ! is_main_query() ) {
			return $content;
		}

		$id = (int) get_the_ID();

		/*
		 * Only the post this page is about, rendered as body content, once:
		 *  - in the loop (classic themes), OR the queried post itself when a
		 *    builder renders it outside the loop (Elementor Pro Theme Builder's
		 *    Post Content widget);
		 *  - never while <head> is being built or an excerpt is generated (SEO
		 *    plugins run the_content there) — that would use up the "once";
		 *  - exactly once per request, so a builder that renders the content
		 *    twice can never print the details twice.
		 */
		if ( ! $id || ( ! in_the_loop() && get_queried_object_id() !== $id ) ) {
			return $content;
		}
		// (Not "after wp_head": block themes render the template, post content
		// included, BEFORE wp_head runs.)
		if ( doing_action( 'wp_head' ) || doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) ) {
			return $content;
		}
		if ( isset( self::$details_done[ $id ] ) ) {
			return $content;
		}

		if ( post_password_required( $id ) ) {
			return $content;
		}
		$type = get_post_type( $id );
		if ( 0 !== strpos( (string) $type, 'mcm_' ) ) {
			return $content;
		}

		self::$details_done[ $id ] = true;
		MCM_Assets::need();

		$details = MCM_Query::single_details( $id, $type );

		// Featured image above the content. Themes vary wildly in whether they
		// show one for a CPT — many only do it for posts — so the plugin makes
		// sure a set image is never simply invisible. See should_show_image()
		// for how a theme that already rendered it avoids a duplicate.
		$media  = '';
		$person = in_array( $type, array( 'mcm_consultant', 'mcm_team' ), true );
		// Jobs show their advertisement (the featured image) inside the details
		// panel, linked to full size, so it is not repeated here. People get a
		// profile photo beside their details instead of a banner, and timetable
		// entries are schedule rows with no banner at all (display standard §3).
		if ( ! $person && ! in_array( $type, array( 'mcm_job', 'mcm_timetable' ), true ) && has_post_thumbnail( $id ) && self::should_show_image( $id ) ) {
			$media = '<div class="mcm-wrap mcm-single-media">'
				. get_the_post_thumbnail( $id, 'large', array( 'class' => 'mcm-single-img' ) )
				. '</div>';
		}

		// People: one profile layout, photo beside the details — the person's own
		// photo, or the default photo (male / female / general / silhouette). The
		// same frame either way, so a profile never changes shape because a photo
		// is missing. When the theme already printed the photo, it is not repeated.
		$avatar = '';
		if ( $person ) {
			if ( has_post_thumbnail( $id ) ) {
				if ( self::should_show_image( $id ) ) {
					$avatar = get_the_post_thumbnail(
						$id,
						'medium_large',
						array(
							'class' => 'mcm-single-img',
							'alt'   => esc_attr( get_the_title( $id ) ),
						)
					);
				}
			} else {
				$avatar = MCM_Query::default_avatar( 'mcm-single-img', 'mcm-single-ph', $id, 'medium_large' );
			}
			if ( '' !== $avatar ) {
				$avatar = '<div class="mcm-single-avatar">' . $avatar . '</div>';
			}
		}

		// A Program page lists its own curriculum, schedule and intake (matched
		// by the shared Program connector). Empty string when nothing is tagged.
		$related = ( 'mcm_program' === $type ) ? MCM_Program::related_sections( $id ) : '';

		// Live stream: embed the player at the top of an event page when a
		// stream URL is set and is embeddable (YouTube/Vimeo/MP4). Otherwise the
		// "Watch Live" button in the details card is the fallback. Hidden when
		// no stream URL is set — never shows an empty box.
		$stream_embed = '';
		if ( 'mcm_event' === $type ) {
			$stream_embed = MCM_Query::video_embed(
				MCM_Safe::meta( $id, 'mcm_event_stream' ),
				'mcm-hero mcm-hero--video mcm-wrap'
			);
		}

		if ( '' === $details && '' === $avatar ) {
			return $stream_embed . $media . $content . $related;
		}

		$meta_class = '' !== $avatar ? 'mcm-wrap mcm-single-meta mcm-single-meta--profile' : 'mcm-wrap mcm-single-meta';
		return $stream_embed . $media . $content . '<div class="' . esc_attr( $meta_class ) . '">' . $avatar . '<div class="mcm-single-meta-main">' . $details . '</div></div>' . $related;
	}

	/**
	 * Record that a featured image was rendered for a post.
	 *
	 * Hooked to post_thumbnail_html, which every the_post_thumbnail() call
	 * passes through — so if the theme printed the image before the_content
	 * ran, we know not to print a second one.
	 *
	 * @param string $html    Thumbnail markup.
	 * @param int    $post_id Post ID.
	 * @return string Unmodified markup.
	 */
	public function note_thumbnail( $html, $post_id ) {
		if ( '' !== $html ) {
			self::$thumb_shown[ (int) $post_id ] = true;
		}
		return $html;
	}

	/**
	 * Should the plugin output the featured image on this single page?
	 *
	 * Auto (default) — only when the theme has not already rendered it, so
	 *                  themes that handle CPT images keep working untouched and
	 *                  themes that ignore them get the image anyway.
	 * Always         — output regardless (for a theme that renders it *after*
	 *                  the content, or one that shows it too small).
	 * Never          — leave it entirely to the theme.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private static function should_show_image( $id ) {
		$s    = MCM_Settings::get();
		$mode = isset( $s['single_image'] ) ? $s['single_image'] : 'auto';

		if ( 'never' === $mode ) {
			return false;
		}
		if ( 'always' === $mode ) {
			return true;
		}
		return empty( self::$thumb_shown[ (int) $id ] );
	}

	/**
	 * Default ordering for front-end CPT archives where date-DESC is wrong.
	 * Events list soonest-first by their event date; timetable by start time.
	 *
	 * @param WP_Query $query The query.
	 */
	public function archive_order( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive() ) {
			return;
		}

		// Respect an explicit ?orderby in the URL.
		if ( $query->get( 'orderby' ) ) {
			return;
		}

		$type = $query->get( 'post_type' );

		if ( 'mcm_event' === $type ) {
			$this->order_by_optional_meta( $query, 'mcm_event_date', 'DATE' );
		} elseif ( 'mcm_timetable' === $type ) {
			$this->order_by_optional_meta( $query, 'mcm_start_time', 'CHAR' );
		} elseif ( 'mcm_publication' === $type ) {
			// Keeps year ordering on /publications/ even when filterable
			// archives are switched off and the shortcode path never runs.
			$this->order_by_optional_meta( $query, 'mcm_pub_year', 'SIGNED', 'DESC' );
		}
	}

	/**
	 * Order an archive by a meta value WITHOUT dropping posts that lack it, and
	 * without the arbitrary-row problem the EXISTS/NOT EXISTS meta_query trick
	 * has. See MCM_Query::order_by_optional_meta() for the full explanation.
	 *
	 * @param WP_Query $query The query.
	 * @param string   $meta_key Meta key.
	 * @param string   $meta_type Meta type.
	 * @param string   $dir ASC or DESC.
	 */
	private function order_by_optional_meta( $query, $meta_key, $meta_type, $dir = 'ASC' ) {
		$cast = ( 'NUMERIC' === $meta_type ) ? 'SIGNED' : $meta_type;
		$cfg  = MCM_Query::order_by_optional_meta( array(), $meta_key, $cast, $dir );
		$query->set( 'mcm_order_meta', $cfg['mcm_order_meta'] );
	}
}
