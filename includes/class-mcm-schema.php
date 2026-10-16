<?php
/**
 * schema.org structured data for single pages.
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

class MCM_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/** Print the JSON-LD block for the current single page. */
	public function output() {
		if ( ! is_singular() ) {
			return;
		}

		$id   = get_the_ID();
		$type = get_post_type( $id );

		// Never describe a private type, and never publish student data.
		if ( ! is_post_type_viewable( $type ) || 'mcm_student' === $type ) {
			return;
		}

		$data = $this->build( $id, $type );
		if ( empty( $data ) ) {
			return;
		}

		$data = apply_filters( 'mcm_schema_data', $data, $id, $type );

		echo '<script type="application/ld+json">'
			// JSON_HEX_TAG|JSON_HEX_AMP so a literal </script> in any value can
			// never break out of the script element (defence in depth — values
			// are already tag-stripped upstream).
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
			. '</script>' . "\n";
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
				$job  = get_post_meta( $id, 'mcm_designation', true );
				$dept = self::first_term( $id, 'mcm_department' );
				$out  = array_merge(
					$base,
					array(
						'@type'      => 'Person',
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
				$email = get_post_meta( $id, 'mcm_email', true );
				$phone = get_post_meta( $id, 'mcm_phone', true );
				if ( $email ) {
					$out['email'] = $email;
				}
				if ( $phone ) {
					$out['telephone'] = $phone;
				}
				return $out;

			case 'mcm_event':
				$date = get_post_meta( $id, 'mcm_event_date', true );
				$out  = array_merge( $base, array( '@type' => 'Event' ) );
				if ( $date ) {
					$start = get_post_meta( $id, 'mcm_start_time', true );
					$out['startDate'] = $start ? $date . 'T' . $start : $date;
					$end = get_post_meta( $id, 'mcm_end_time', true );
					if ( $end ) {
						$out['endDate'] = $date . 'T' . $end;
					}
				}
				$venue = get_post_meta( $id, 'mcm_venue', true );
				if ( $venue ) {
					$out['location'] = array(
						'@type' => 'Place',
						'name'  => $venue,
						'address' => $venue,
					);
				}
				$org = get_post_meta( $id, 'mcm_organizer', true );
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
				$dur = get_post_meta( $id, 'mcm_duration', true );
				if ( $dur ) {
					$out['timeToComplete'] = $dur;
				}
				return $out;

			case 'mcm_publication':
				$out  = array_merge( $base, array( '@type' => 'ScholarlyArticle' ) );
				$year = get_post_meta( $id, 'mcm_pub_year', true );
				if ( $year ) {
					$out['datePublished'] = $year;
				}
				$journal = get_post_meta( $id, 'mcm_pub_journal', true );
				if ( $journal ) {
					$out['isPartOf'] = array(
						'@type' => 'Periodical',
						'name'  => $journal,
					);
				}
				$doi = get_post_meta( $id, 'mcm_pub_doi', true );
				if ( $doi ) {
					$out['identifier'] = preg_match( '#^https?://#i', $doi ) ? $doi : 'https://doi.org/' . ltrim( $doi, '/' );
				}
				$authors = get_post_meta( $id, 'mcm_pub_authors', true );
				if ( $authors ) {
					$out['author'] = array(
						'@type' => 'Person',
						'name'  => $authors,
					);
				}
				return $out;

			case 'mcm_research':
				$out = array_merge( $base, array( '@type' => 'ResearchProject' ) );
				$pi  = get_post_meta( $id, 'mcm_res_pi', true );
				if ( $pi ) {
					$out['member'] = array(
						'@type' => 'Person',
						'name'  => $pi,
					);
				}
				$funder = get_post_meta( $id, 'mcm_res_funding', true );
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
		}

		return array();
	}

	/** First term name for a taxonomy, or '' (uses the primed term cache). */
	private static function first_term( $id, $tax ) {
		$names = MCM_Query::cached_term_names( $id, $tax );
		return $names ? $names[0] : '';
	}
}
