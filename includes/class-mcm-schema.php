<?php
/**
 * Structured data (schema.org JSON-LD) for single pages.
 *
 * The review asked for SEO improvement. Titles and descriptions are an SEO
 * plugin's job, but nothing else can describe *what* these pages are — only
 * this plugin knows that a page is a faculty member, an event or a course.
 * Emitting JSON-LD lets search engines show richer results for them.
 *
 * Deliberately narrow: only types with a clear schema.org mapping, only on
 * their own single page, and never for students (private, and personal data
 * should not be handed to crawlers).
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured data (schema.org JSON-LD) for single pages.
 */
class MCM_Schema {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
		add_action( 'wp_head', array( $this, 'meta_description' ), 2 );
	}

	/**
	 * Is an SEO plugin managing meta descriptions? Then the plugin stays out.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		$active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) || defined( 'SLIM_SEO_VER' );
		/**
		 * Filter whether another plugin handles meta descriptions.
		 *
		 * @param bool $active Detected.
		 */
		return (bool) apply_filters( 'mcm_seo_plugin_active', $active );
	}

	/** Print <meta name="description"> on the plugin's pages (no SEO plugin). */
	public function meta_description() {
		if ( self::seo_plugin_active() ) {
			return;
		}
		$desc = MCM_Safe::render( array( __CLASS__, 'describe' ), 'meta description' );
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}

	/**
	 * Plain-text description of the current plugin page ('' elsewhere):
	 * the excerpt, else the text, else the entry's key facts; for listings
	 * the term description or a short line naming what is listed.
	 *
	 * @internal
	 * @return string
	 */
	public static function describe() {
		$site = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$desc = '';
		if ( is_singular() ) {
			$id   = (int) get_queried_object_id();
			$type = (string) get_post_type( $id );
			if ( 0 !== strpos( $type, 'mcm_' ) || ! is_post_type_viewable( $type ) || post_password_required( $id ) ) {
				return '';
			}
			$post = get_post( $id );
			$desc = has_excerpt( $id ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
			if ( '' === trim( $desc ) ) {
				// One "Label: value" per fact line, facts separated by " · ".
				$facts = preg_replace( '#</span>\s*</span>#', ' · ', MCM_Query::meta_lines( $id, $type ) );
				$facts = wp_strip_all_tags( (string) $facts );
				$facts = trim( preg_replace( '/(\s*·\s*)+/u', ' · ', $facts ), " ·\t\n" );
				$desc  = get_the_title( $id ) . ( '' !== $facts ? ' — ' . $facts : '' );
			}
		} elseif ( is_post_type_archive() ) {
			$type = (string) get_query_var( 'post_type' );
			$obj  = 0 === strpos( $type, 'mcm_' ) ? get_post_type_object( $type ) : null;
			if ( ! $obj ) {
				return '';
			}
			/* translators: 1: content type, e.g. "Events"; 2: site name. */
			$desc = sprintf( __( '%1$s at %2$s — browse and filter by department.', 'medical-college-manager' ), $obj->labels->name, $site );
		} elseif ( is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || 0 !== strpos( $term->taxonomy, 'mcm_' ) ) {
				return '';
			}
			$desc = wp_strip_all_tags( term_description( $term ) );
			if ( '' === trim( $desc ) ) {
				$tax = get_taxonomy( $term->taxonomy );
				/* translators: 1: term, e.g. "Dr. Ayesha Noor"; 2: taxonomy, e.g. "Faculty"; 3: site name. */
				$desc = sprintf( __( '%1$s (%2$s) — %3$s.', 'medical-college-manager' ), $term->name, $tax ? $tax->labels->singular_name : '', $site );
				if ( 'mcm_department' === $term->taxonomy ) {
					/* translators: 1: department, e.g. "Anatomy"; 2: site name. */
					$desc = sprintf( __( 'Department of %1$s at %2$s: faculty, courses, timetable, research and news.', 'medical-college-manager' ), $term->name, $site );
				}
			}
		} else {
			return '';
		}
		$desc = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $desc, ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $desc ) > 160 ) {
			$desc = rtrim( mb_substr( $desc, 0, 157 ), ' ,.;:-' ) . '…';
		}
		/**
		 * Filter the meta description the plugin prints ('' prints none).
		 *
		 * @param string $desc Description.
		 */
		return (string) apply_filters( 'mcm_meta_description', $desc );
	}

	/** Print the JSON-LD block for the current single page. */
	public function output() {
		// Render boundary: structured data must never break the <head>.
		echo MCM_Safe::render( array( $this, 'build_output' ), 'schema.org JSON-LD' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and encoded in build_output().
	}

	/**
	 * The JSON-LD block for the current single page, or ''.
	 *
	 * @internal
	 * @return string
	 */
	public function build_output() {
		if ( ! is_singular() ) {
			return '';
		}

		// The queried object, not the loop global: wp_head runs before the loop.
		$id   = (int) get_queried_object_id();
		$type = $id ? get_post_type( $id ) : '';

		// Never describe a private type, never publish student data, and never
		// describe a password-protected entry (email/phone would leak).
		if ( ! $type || ! is_post_type_viewable( $type ) || 'mcm_student' === $type || post_password_required( $id ) ) {
			return '';
		}

		$data = $this->build( $id, $type );
		if ( empty( $data ) ) {
			return '';
		}

		$data = apply_filters( 'mcm_schema_data', $data, $id, $type );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( ! is_string( $json ) ) {
			return '';
		}

		// JSON_HEX_TAG|JSON_HEX_AMP so a literal </script> in any value can
		// never break out of the script element (defence in depth — values
		// are already tag-stripped upstream).
		return '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	/**
	 * Map one post to a schema.org object.
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Post type.
	 * @return array Empty when the type has no sensible mapping.
	 */
	private function build( $id, $type ) {
		$base = array(
			'@context' => 'https://schema.org',
			'name'     => wp_strip_all_tags( get_the_title( $id ) ),
			'url'      => get_permalink( $id ),
		);

		$image = get_the_post_thumbnail_url( $id, 'large' );
		if ( $image ) {
			$base['image'] = $image;
		}

		$desc = wp_strip_all_tags( get_the_excerpt( $id ) );
		if ( $desc ) {
			$base['description'] = $desc;
		}

		switch ( $type ) {
			case 'mcm_consultant':
			case 'mcm_team':
				$job  = MCM_Safe::meta( $id, 'mcm_designation' );
				$dept = self::first_term( $id, 'mcm_department' );
				$out  = array_merge(
					$base,
					array(
						'@type'       => 'Person',
						'affiliation' => array(
							'@type' => 'CollegeOrUniversity',
							'name'  => get_bloginfo( 'name' ),
						),
					)
				);
				if ( $job ) {
					$out['jobTitle'] = $job;
				}
				if ( $dept ) {
					$out['worksFor'] = array(
						'@type' => 'Organization',
						'name'  => $dept,
					);
				}
				// Contact details are already public on the profile page.
				$email = MCM_Safe::meta( $id, 'mcm_email' );
				$phone = MCM_Safe::meta( $id, 'mcm_phone' );
				if ( $email ) {
					$out['email'] = $email;
				}
				if ( $phone ) {
					$out['telephone'] = $phone;
				}
				return $out;

			case 'mcm_event':
				$date = MCM_Safe::meta( $id, 'mcm_event_date' );
				$out  = array_merge( $base, array( '@type' => 'Event' ) );
				if ( $date ) {
					$start            = MCM_Safe::meta( $id, 'mcm_start_time' );
					$out['startDate'] = $start ? $date . 'T' . $start : $date;
					$end              = MCM_Safe::meta( $id, 'mcm_end_time' );
					if ( $end ) {
						$out['endDate'] = $date . 'T' . $end;
					}
				}
				$venue = MCM_Safe::meta( $id, 'mcm_venue' );
				if ( $venue ) {
					$out['location'] = array(
						'@type'   => 'Place',
						'name'    => $venue,
						'address' => $venue,
					);
				}
				$org = MCM_Safe::meta( $id, 'mcm_organizer' );
				if ( $org ) {
					$out['organizer'] = array(
						'@type' => 'Organization',
						'name'  => $org,
					);
				}
				return $out;

			case 'mcm_course':
				return array_merge(
					$base,
					array(
						'@type'    => 'Course',
						'provider' => array(
							'@type' => 'CollegeOrUniversity',
							'name'  => get_bloginfo( 'name' ),
						),
					)
				);

			case 'mcm_program':
				$out = array_merge(
					$base,
					array(
						'@type'    => 'EducationalOccupationalProgram',
						'provider' => array(
							'@type' => 'CollegeOrUniversity',
							'name'  => get_bloginfo( 'name' ),
						),
					)
				);
				$dur = MCM_Safe::meta( $id, 'mcm_duration' );
				if ( $dur ) {
					$out['timeToComplete'] = $dur;
				}
				return $out;

			case 'mcm_publication':
				$out  = array_merge( $base, array( '@type' => 'ScholarlyArticle' ) );
				$year = MCM_Safe::meta( $id, 'mcm_pub_year' );
				if ( $year ) {
					$out['datePublished'] = $year;
				}
				$journal = MCM_Safe::meta( $id, 'mcm_pub_journal' );
				if ( $journal ) {
					$out['isPartOf'] = array(
						'@type' => 'Periodical',
						'name'  => $journal,
					);
				}
				$doi = MCM_Safe::meta( $id, 'mcm_pub_doi' );
				if ( $doi ) {
					$out['identifier'] = preg_match( '#^https?://#i', $doi ) ? $doi : 'https://doi.org/' . ltrim( $doi, '/' );
				}
				$authors = MCM_Safe::meta( $id, 'mcm_pub_authors' );
				if ( $authors ) {
					$out['author'] = array(
						'@type' => 'Person',
						'name'  => $authors,
					);
				}
				return $out;

			case 'mcm_research':
				$out = array_merge( $base, array( '@type' => 'ResearchProject' ) );
				$pi  = MCM_Safe::meta( $id, 'mcm_res_pi' );
				if ( $pi ) {
					$out['member'] = array(
						'@type' => 'Person',
						'name'  => $pi,
					);
				}
				$funder = MCM_Safe::meta( $id, 'mcm_res_funding' );
				if ( $funder ) {
					$out['funder'] = array(
						'@type' => 'Organization',
						'name'  => $funder,
					);
				}
				return $out;

			case 'mcm_scholarship':
				return array_merge(
					$base,
					array(
						'@type'    => 'EducationalOccupationalProgram',
						'provider' => array(
							'@type' => 'CollegeOrUniversity',
							'name'  => get_bloginfo( 'name' ),
						),
					)
				);

			case 'mcm_job':
				return self::job_posting( $id, $base );
		}

		return array();
	}

	/**
	 * JobPosting for an open job (Google's required properties: title,
	 * description, datePosted, hiringOrganization, jobLocation). Nothing at all
	 * once the job is closed, so search engines drop an expired posting.
	 *
	 * @param int   $id   Post ID.
	 * @param array $base Shared properties (context, name, url, image).
	 * @return array
	 */
	private static function job_posting( $id, $base ) {
		if ( ! class_exists( 'MCM_Job' ) || ! MCM_Job::is_accepting( MCM_Job::status( $id ) ) ) {
			return array();
		}

		$post = get_post( $id );
		// Block-editor comments are markup noise, not description.
		$desc = $post ? trim( (string) preg_replace( '/<!--.*?-->/s', '', wp_kses_post( $post->post_content ) ) ) : '';
		if ( '' === $desc ) {
			$desc = isset( $base['description'] ) ? $base['description'] : wp_strip_all_tags( get_the_title( $id ) );
		}
		$lh  = MCM_Query::letterhead();
		$org = array(
			'@type'  => 'CollegeOrUniversity',
			'name'   => $lh['org'],
			'sameAs' => home_url( '/' ),
		);
		if ( $lh['logo'] ) {
			$org['logo'] = $lh['logo'];
		}

		$address = array( '@type' => 'PostalAddress' );
		if ( $lh['address'] ) {
			$address['streetAddress'] = $lh['address'];
		}
		/**
		 * ISO 3166-1 country code for the job location (default PK).
		 *
		 * @param string $country Country code.
		 * @param int    $id      Job post ID.
		 */
		$country = (string) apply_filters( 'mcm_job_address_country', 'PK', $id );
		if ( '' !== $country ) {
			$address['addressCountry'] = $country;
		}

		$out                       = $base;
		$out['@type']              = 'JobPosting';
		$out['title']              = wp_strip_all_tags( get_the_title( $id ) );
		$out['description']        = $desc;
		$out['datePosted']         = MCM_Job::publish_date( $id );
		$out['hiringOrganization'] = $org;
		$out['jobLocation']        = array(
			'@type'   => 'Place',
			'address' => $address,
		);
		unset( $out['name'] );

		$deadline = MCM_Job::effective_deadline( $id );
		if ( $deadline ) {
			$out['validThrough'] = $deadline->format( 'c' ); // ISO 8601 with offset.
		}

		$map  = array(
			'Regular'    => 'FULL_TIME',
			'Contract'   => 'CONTRACTOR',
			'Ad-hoc'     => 'CONTRACTOR',
			'Visiting'   => 'PART_TIME',
			'Internship' => 'INTERN',
		);
		$kind = MCM_Safe::meta( $id, 'mcm_job_type' );
		if ( isset( $map[ $kind ] ) ) {
			$out['employmentType'] = $map[ $kind ];
		}

		$posts = absint( MCM_Safe::meta( $id, 'mcm_job_posts' ) );
		if ( $posts > 0 ) {
			$out['totalJobOpenings'] = $posts;
		}

		return $out;
	}

	/**
	 * First term name for a taxonomy, or '' (uses the primed term cache).
	 *
	 * @param int    $id Post ID.
	 * @param string $tax Taxonomy name.
	 */
	private static function first_term( $id, $tax ) {
		$names = MCM_Query::cached_term_names( $id, $tax );
		return $names ? $names[0] : '';
	}
}
