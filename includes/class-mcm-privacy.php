<?php
/**
 * Privacy: personal-data exporter and eraser.
 *
 * The plugin stores identifiable data about real people — student records hold
 * PMDC number, email, phone, date of birth, address and guardian contacts, and
 * staff profiles hold email and phone. WordPress ships tools that let an admin
 * answer a subject-access or erasure request, but they only cover a plugin's
 * data if the plugin registers handlers. Without this, a request for a
 * student's data could not be fulfilled through wp-admin at all.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Privacy {

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Post types holding personal data, mapped to the meta fields that identify
	 * a person. The email key is how a record is matched to a data request.
	 *
	 * @return array
	 */
	public static function sources() {
		return apply_filters(
			'mcm_privacy_sources',
			array(
				'mcm_student'    => array(
					'label'  => __( 'Student Record', 'medical-college-manager' ),
					// Academic record kept on erasure (exported, but not personal data).
					'keep'   => array( 'mcm_program', 'mcm_batch', 'mcm_status' ),
					'email'  => 'mcm_email',
					'fields' => array(
						'mcm_student_id'  => __( 'Student ID', 'medical-college-manager' ),
						'mcm_roll_no'     => __( 'PMDC No.', 'medical-college-manager' ),
						'mcm_email'       => __( 'Email', 'medical-college-manager' ),
						'mcm_phone'       => __( 'Phone', 'medical-college-manager' ),
						'mcm_program'     => __( 'Program', 'medical-college-manager' ),
						'mcm_batch'       => __( 'Batch', 'medical-college-manager' ),
						'mcm_gender'      => __( 'Gender', 'medical-college-manager' ),
						'mcm_dob'         => __( 'Date of Birth', 'medical-college-manager' ),
						'mcm_status'      => __( 'Status', 'medical-college-manager' ),
						'mcm_blood_group' => __( 'Blood Group', 'medical-college-manager' ),
						'mcm_address'     => __( 'Address', 'medical-college-manager' ),
						'mcm_guardian'       => __( 'Guardian', 'medical-college-manager' ),
						'mcm_guardian_phone' => __( 'Guardian Phone', 'medical-college-manager' ),
					),
				),
				'mcm_consultant' => array(
					'label'  => __( 'Consultant Profile', 'medical-college-manager' ),
					'keep'   => array( 'mcm_designation' ),
					'email'  => 'mcm_email',
					'fields' => array(
						'mcm_designation' => __( 'Designation', 'medical-college-manager' ),
						'mcm_email'       => __( 'Email', 'medical-college-manager' ),
						'mcm_phone'       => __( 'Phone', 'medical-college-manager' ),
					),
				),
				'mcm_team'       => array(
					'label'  => __( 'Team Profile', 'medical-college-manager' ),
					'keep'   => array( 'mcm_designation' ),
					'email'  => 'mcm_email',
					'fields' => array(
						'mcm_designation' => __( 'Designation', 'medical-college-manager' ),
						'mcm_email'       => __( 'Email', 'medical-college-manager' ),
						'mcm_phone'       => __( 'Phone', 'medical-college-manager' ),
						'mcm_linkedin'    => __( 'LinkedIn', 'medical-college-manager' ),
					),
				),
			)
		);
	}

	/** @param array $exporters Registered exporters. */
	public function register_exporter( $exporters ) {
		$exporters['medical-college-manager'] = array(
			'exporter_friendly_name' => __( 'Medical College Manager', 'medical-college-manager' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/** @param array $erasers Registered erasers. */
	public function register_eraser( $erasers ) {
		$erasers['medical-college-manager'] = array(
			'eraser_friendly_name' => __( 'Medical College Manager', 'medical-college-manager' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Find every record across all sources matching an email address.
	 *
	 * @param string $email Email address from the data request.
	 * @param int    $page  1-based page.
	 * @return array{items:array,done:bool}
	 */
	private static function find( $email, $page ) {
		$per_page = 20;
		$items    = array();
		$more     = false;

		foreach ( self::sources() as $type => $cfg ) {
			$q = new WP_Query(
				array(
					'post_type'        => $type,
					'post_status'      => 'any',
					'posts_per_page'   => $per_page,
					'paged'            => max( 1, (int) $page ),
					'fields'           => 'ids',
					'no_found_rows'    => false,
					'suppress_filters' => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'       => array(
						array(
							'key'     => $cfg['email'],
							'value'   => $email,
							'compare' => '=',
						),
					),
				)
			);

			foreach ( $q->posts as $post_id ) {
				$items[] = array(
					'id'   => $post_id,
					'type' => $type,
					'cfg'  => $cfg,
				);
			}
			if ( $q->max_num_pages > $page ) {
				$more = true;
			}
		}

		return array(
			'items' => $items,
			'done'  => ! $more,
		);
	}

	/**
	 * Export handler.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$found  = self::find( $email, $page );
		$export = array();

		foreach ( $found['items'] as $item ) {
			$data = array();
			foreach ( $item['cfg']['fields'] as $key => $label ) {
				$value = MCM_Safe::meta( $item['id'], $key );
				if ( '' === $value || null === $value ) {
					continue;
				}
				$data[] = array(
					'name'  => $label,
					'value' => $value,
				);
			}
			if ( ! $data ) {
				continue;
			}
			// The record's own title is personal data too (it is the name).
			array_unshift(
				$data,
				array(
					'name'  => __( 'Name', 'medical-college-manager' ),
					'value' => get_the_title( $item['id'] ),
				)
			);

			$export[] = array(
				'group_id'    => 'mcm-' . $item['type'],
				'group_label' => $item['cfg']['label'],
				'item_id'     => 'mcm-' . $item['type'] . '-' . $item['id'],
				'data'        => $data,
			);
		}

		return array(
			'data' => $export,
			'done' => $found['done'],
		);
	}

	/**
	 * Erase handler.
	 *
	 * Clears the identifying meta rather than deleting the post. A college has
	 * a legitimate reason to keep the academic record itself (results, session,
	 * department), and silently deleting enrolment history would be a worse
	 * outcome than anonymising it. The admin is told this explicitly.
	 *
	 * A student's name (the post title) is personal data too, so it is
	 * replaced with "Anonymised student #<ID>" and the slug with
	 * "anonymised-student-<ID>". Staff profiles keep their name: they are
	 * public faculty pages, and the admin is told so.
	 *
	 * Paging: WordPress calls this with page 1, 2, 3… But erasing a record
	 * removes the email it was found by, so page 2 of what REMAINS would skip
	 * records. Every call therefore takes page 1 of the remaining matches, and
	 * reports done only when nothing matches any more.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (ignored, see above).
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		unset( $page );
		$found    = self::find( $email, 1 );
		$removed  = false;
		$retained = false;
		$progress = false;
		$msgs     = array();

		foreach ( $found['items'] as $item ) {
			$id      = (int) $item['id'];
			$cleared = 0;
			$keep    = isset( $item['cfg']['keep'] ) ? (array) $item['cfg']['keep'] : array();
			foreach ( array_keys( $item['cfg']['fields'] ) as $key ) {
				if ( in_array( $key, $keep, true ) ) {
					continue; // Not personal data — see 'keep' in sources().
				}
				if ( '' !== MCM_Safe::meta( $id, $key ) ) {
					delete_post_meta( $id, $key );
					++$cleared;
				}
			}

			$renamed = false;
			if ( 'mcm_student' === $item['type'] ) {
				/* translators: %d: post ID. */
				$anon = sprintf( __( 'Anonymised student #%d', 'medical-college-manager' ), $id );
				if ( get_post_field( 'post_title', $id ) !== $anon ) {
					$result  = wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => $anon,
							'post_name'  => 'anonymised-student-' . $id,
						),
						true
					);
					$renamed = ! is_wp_error( $result );
				}
			}

			if ( ! $cleared && ! $renamed ) {
				continue;
			}
			$progress = true;
			$removed  = true;
			$retained = true; // The record itself is always kept.

			if ( 'mcm_student' === $item['type'] ) {
				$msgs[] = sprintf(
					/* translators: 1: post ID, 2: number of fields removed. */
					__( 'Student record #%1$d: the name was replaced and %2$d personal field(s) removed. The academic record (program, batch, status, department, session, level) was kept; delete the record manually if a full removal is required.', 'medical-college-manager' ),
					$id,
					$cleared
				);
			} else {
				$msgs[] = sprintf(
					/* translators: 1: record type label, 2: record title, 3: number of fields removed. */
					__( '%1$s "%2$s": %3$d personal field(s) removed. The public profile, its name and designation were kept; delete the profile manually if it should be removed.', 'medical-college-manager' ),
					$item['cfg']['label'],
					get_the_title( $id ),
					$cleared
				);
			}
		}

		// Done when nothing matches any more — or when this pass could not
		// change anything, so a stuck record can never loop forever.
		$remaining = self::find( $email, 1 );

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $msgs,
			'done'           => ! $progress || empty( $remaining['items'] ),
		);
	}
}
