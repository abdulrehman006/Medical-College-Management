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

		// Never describe a private type, and never publish student data.
		if ( ! $type || ! is_post_type_viewable( $type ) || 'mcm_student' === $type ) {
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
