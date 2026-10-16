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
					'email'  => 'mcm_email',
					'fields' => array(
						'mcm_designation' => __( 'Designation', 'medical-college-manager' ),
						'mcm_email'       => __( 'Email', 'medical-college-manager' ),
						'mcm_phone'       => __( 'Phone', 'medical-college-manager' ),
					),
				),
				'mcm_team'       => array(
					'label'  => __( 'Team Profile', 'medical-college-manager' ),
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
				$value = get_post_meta( $item['id'], $key, true );
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
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		$found   = self::find( $email, $page );
		$removed = false;
		$msgs    = array();

		foreach ( $found['items'] as $item ) {
			$cleared = 0;
			foreach ( array_keys( $item['cfg']['fields'] ) as $key ) {
				if ( '' !== (string) get_post_meta( $item['id'], $key, true ) ) {
					delete_post_meta( $item['id'], $key );
					++$cleared;
				}
			}
			if ( $cleared ) {
				$removed = true;
				$msgs[]  = sprintf(
					/* translators: 1: record type label, 2: record title. */
					__( 'Anonymised the personal fields on %1$s "%2$s". The record itself was kept, because academic history is retained; delete it manually if a full removal is required.', 'medical-college-manager' ),
					$item['cfg']['label'],
					get_the_title( $item['id'] )
				);
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $msgs,
			'done'           => $found['done'],
		);
	}
}
