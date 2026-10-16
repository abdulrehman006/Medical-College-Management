<?php
/**
 * Custom fields / meta boxes for each CPT.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Meta {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'admin_init', array( $this, 'register_admin_columns' ) );
	}

	/**
	 * REST/storage scalar type. Post meta is stored as strings, and an empty
	 * value fails REST 'number' schema validation, so everything is exposed as
	 * 'string' (numeric fields still hold clean numeric strings).
	 */
	public static function rest_type( $type ) {
		return 'string';
	}

	/**
	 * Single source of truth for sanitizing a field value by its type.
	 * Shared by the meta box save, the CSV import, and the REST sanitizer.
	 */
	public static function sanitize_value( $type, $raw ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $raw );
			case 'url':
			case 'media':
				return esc_url_raw( $raw );
			case 'textarea':
				return sanitize_textarea_field( $raw );
			case 'number':
				return is_numeric( $raw ) ? $raw + 0 : '';
			default:
				return sanitize_text_field( $raw );
		}
	}

	/**
	 * Register every meta key with the REST API / block editor (proper WP
	 * standard) so the custom fields are typed, sanitized and authorised.
	 */
	public function register_meta_fields() {
		foreach ( self::fields() as $cpt => $cfg ) {
			// Meta follows the post type's own REST visibility — so private
			// types (e.g. students) never leak their fields through /wp-json.
			$pt_obj    = get_post_type_object( $cpt );
			$in_rest   = $pt_obj ? (bool) $pt_obj->show_in_rest : true;

			foreach ( $cfg['fields'] as $key => $field ) {
				$type = $field['type'];
				register_post_meta(
					$cpt,
					$key,
					array(
						'type'              => self::rest_type( $type ),
						'single'            => true,
						'show_in_rest'      => $in_rest,
						'sanitize_callback' => function ( $value ) use ( $type ) {
							return self::sanitize_value( $type, $value );
						},
						'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Field definitions per post type.
	 * Supported types: text, textarea, number, date, time, email, url, select.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'mcm_timetable'  => array(
				'box_title' => __( 'Timetable Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_day'        => array( 'label' => __( 'Day', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ) ),
					'mcm_start_time' => array( 'label' => __( 'Start Time', 'medical-college-manager' ), 'type' => 'time' ),
					'mcm_end_time'   => array( 'label' => __( 'End Time', 'medical-college-manager' ), 'type' => 'time' ),
					'mcm_subject'    => array( 'label' => __( 'Subject / Topic', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_room'       => array( 'label' => __( 'Room / Venue', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_year'       => array( 'label' => __( 'Year / Semester', 'medical-college-manager' ), 'type' => 'text' ),
				),
			),
			'mcm_program'    => array(
				'box_title' => __( 'Program Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_degree_level' => array( 'label' => __( 'Degree Level', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_duration'     => array( 'label' => __( 'Duration', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_fees'         => array( 'label' => __( 'Fees', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_intake'       => array( 'label' => __( 'Intake / Seats', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_apply_url'    => array( 'label' => __( 'Apply / Info URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_course'     => array(
				'box_title' => __( 'Course Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_course_code'  => array( 'label' => __( 'Course Code', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_credit_hours' => array( 'label' => __( 'Credit Hours', 'medical-college-manager' ), 'type' => 'number' ),
					'mcm_semester'     => array( 'label' => __( 'Semester', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_course_lead'  => array( 'label' => __( 'Course Lead', 'medical-college-manager' ), 'type' => 'text' ),
				),
			),
			'mcm_event'      => array(
				'box_title' => __( 'Event Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_event_date' => array( 'label' => __( 'Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_start_time' => array( 'label' => __( 'Start Time', 'medical-college-manager' ), 'type' => 'time' ),
					'mcm_end_time'   => array( 'label' => __( 'End Time', 'medical-college-manager' ), 'type' => 'time' ),
					'mcm_venue'      => array( 'label' => __( 'Venue', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_organizer'  => array( 'label' => __( 'Organizer', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_event_stream' => array( 'label' => __( 'Live Stream / Embed URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_event_url'  => array( 'label' => __( 'Registration URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_consultant' => array(
				'box_title' => __( 'Consultant / Doctor Profile', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_designation'    => array( 'label' => __( 'Designation', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_qualifications' => array( 'label' => __( 'Qualifications', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_experience'     => array( 'label' => __( 'Experience', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_joining_date'  => array( 'label' => __( 'Joining / Appointment Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_opd_days'       => array( 'label' => __( 'OPD / Available Days', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_opd_time'       => array( 'label' => __( 'OPD Timing', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_phone'          => array( 'label' => __( 'Phone', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_email'          => array( 'label' => __( 'Email', 'medical-college-manager' ), 'type' => 'email' ),
					'mcm_linkedin'       => array( 'label' => __( 'LinkedIn URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_orcid'          => array( 'label' => __( 'ORCID iD URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_scholar'        => array( 'label' => __( 'Google Scholar URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_pubmed'         => array( 'label' => __( 'PubMed Author URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_team'       => array(
				'box_title' => __( 'Team Member Profile', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_designation'    => array( 'label' => __( 'Designation / Role', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_qualifications' => array( 'label' => __( 'Qualifications', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_experience'     => array( 'label' => __( 'Experience', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_joining_date'  => array( 'label' => __( 'Joining / Appointment Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_phone'          => array( 'label' => __( 'Phone', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_email'          => array( 'label' => __( 'Email', 'medical-college-manager' ), 'type' => 'email' ),
					'mcm_linkedin'       => array( 'label' => __( 'LinkedIn URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_orcid'          => array( 'label' => __( 'ORCID iD URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_scholar'        => array( 'label' => __( 'Google Scholar URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_pubmed'         => array( 'label' => __( 'PubMed Author URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_admission'  => array(
				'box_title' => __( 'Admission Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_program_name' => array( 'label' => __( 'Program Name', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_open_date'    => array( 'label' => __( 'Opening Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_close_date'   => array( 'label' => __( 'Closing Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_eligibility'  => array( 'label' => __( 'Eligibility', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_fee'          => array( 'label' => __( 'Application Fee', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_apply_url'    => array( 'label' => __( 'Apply URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_tender'     => array(
				'box_title' => __( 'Tender Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_tender_institute'   => array( 'label' => __( 'Institute / Facility Name', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_tender_ref'         => array( 'label' => __( 'Reference / Tender No.', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_tender_open'        => array( 'label' => __( 'Published Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_tender_close'       => array( 'label' => __( 'Submission Deadline (auto-hides after)', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_tender_bid'         => array( 'label' => __( 'Bid Opening Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_tender_fee'         => array( 'label' => __( 'Tender Document Fee', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_tender_earnest'     => array( 'label' => __( 'Earnest Money / Bid Security', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_tender_eligibility' => array( 'label' => __( 'Eligibility', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_tender_doc'         => array( 'label' => __( 'Tender Document URL (download)', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_tender_apply'       => array( 'label' => __( 'Submit / Portal URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_publication' => array(
				'box_title' => __( 'Publication Details (Vancouver)', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_pub_authors'     => array( 'label' => __( 'Authors (Vancouver order, comma separated)', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_pub_journal'     => array( 'label' => __( 'Journal / Publisher', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_year'        => array( 'label' => __( 'Year', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_volume'      => array( 'label' => __( 'Volume', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_issue'       => array( 'label' => __( 'Issue', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_pages'       => array( 'label' => __( 'Pages (e.g. 45-52)', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_type'        => array( 'label' => __( 'Publication Type', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'Original Article', 'Review', 'Systematic Review', 'Meta-Analysis', 'Case Report', 'Editorial', 'Letter', 'Book Chapter', 'Conference Abstract' ) ),
					'mcm_pub_doi'         => array( 'label' => __( 'DOI (e.g. 10.1234/abcd)', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_pmid'        => array( 'label' => __( 'PubMed ID (PMID)', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_url'         => array( 'label' => __( 'Full-text / Publisher URL', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_pub_pdf'         => array( 'label' => __( 'Paper (PDF)', 'medical-college-manager' ), 'type' => 'media', 'desc' => __( 'Upload or select the PDF. It can then be viewed and downloaded from the page.', 'medical-college-manager' ) ),
					'mcm_pub_supervisor'  => array( 'label' => __( 'Supervisor', 'medical-college-manager' ), 'type' => 'text', 'desc' => __( 'Supervising professor / consultant, if this was supervised work.', 'medical-college-manager' ) ),
					'mcm_pub_fellows'     => array( 'label' => __( 'Fellowship Members', 'medical-college-manager' ), 'type' => 'textarea', 'desc' => __( 'Students working under the supervisor — one per line, e.g. "Dr. Samina — 2nd Year MBBS".', 'medical-college-manager' ) ),
					'mcm_pub_open_access' => array( 'label' => __( 'Open Access', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'No', 'Yes' ) ),
					'mcm_pub_impact'      => array( 'label' => __( 'Impact Factor', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_pub_indexing'    => array( 'label' => __( 'Indexing (HEC W/X/Y, Scopus, PubMed…)', 'medical-college-manager' ), 'type' => 'text' ),
				),
			),
			'mcm_research'   => array(
				'box_title' => __( 'Research Project Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_res_pi'        => array( 'label' => __( 'Supervisor / Principal Investigator', 'medical-college-manager' ), 'type' => 'text', 'desc' => __( 'Name of the supervising professor / principal investigator.', 'medical-college-manager' ) ),
					'mcm_res_status'    => array( 'label' => __( 'Research Status', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'In Progress', 'Completed', 'Dropped' ) ),
					'mcm_res_start'     => array( 'label' => __( 'Start Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_res_completed' => array( 'label' => __( 'Date of Completion', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_res_students'  => array( 'label' => __( 'Assisting Students (Fellowship Members)', 'medical-college-manager' ), 'type' => 'textarea', 'desc' => __( 'Students assisting under the supervisor — one per line, e.g. "Dr. Farooq — Final Year MBBS".', 'medical-college-manager' ) ),
					'mcm_res_coinv'     => array( 'label' => __( 'Co-Investigators', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_res_funding'   => array( 'label' => __( 'Funding Body / Grant', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_res_doc'       => array( 'label' => __( 'Research Paper (PDF)', 'medical-college-manager' ), 'type' => 'media', 'desc' => __( 'Upload or select the paper. It can then be viewed and downloaded from the page.', 'medical-college-manager' ) ),
				),
			),
			'mcm_policy'     => array(
				'box_title' => __( 'Policy Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_policy_issuer'  => array( 'label' => __( 'Issued By (KMU / GMC / PMDC…)', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_policy_ref'     => array( 'label' => __( 'Reference No.', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_policy_date'    => array( 'label' => __( 'Effective Date', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_policy_doc'     => array( 'label' => __( 'Policy Document (PDF)', 'medical-college-manager' ), 'type' => 'media' ),
				),
			),
			'mcm_scholarship' => array(
				'box_title' => __( 'Scholarship Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_scholarship_amount'      => array( 'label' => __( 'Amount / Coverage', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_scholarship_eligibility' => array( 'label' => __( 'Eligibility', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_scholarship_deadline'    => array( 'label' => __( 'Application Deadline', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_scholarship_apply'       => array( 'label' => __( 'Apply / Info URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_notice'     => array(
				'box_title' => __( 'Notice Details', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_notice_priority'   => array( 'label' => __( 'Priority', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'Normal', 'Important', 'Urgent' ) ),
					'mcm_notice_pinned'     => array( 'label' => __( 'Pin to top', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'No', 'Yes' ) ),
					'mcm_notice_date'       => array( 'label' => __( 'Notice Date (defaults to publish date)', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_notice_expiry'     => array( 'label' => __( 'Expiry Date (auto-hides after)', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_notice_url'        => array( 'label' => __( 'Link URL (result page / form)', 'medical-college-manager' ), 'type' => 'url' ),
					'mcm_notice_attachment' => array( 'label' => __( 'Attachment / File URL', 'medical-college-manager' ), 'type' => 'url' ),
				),
			),
			'mcm_student'    => array(
				'box_title' => __( 'Student Record', 'medical-college-manager' ),
				'fields'    => array(
					'mcm_student_id'    => array( 'label' => __( 'Student ID', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_roll_no'       => array( 'label' => __( 'PMDC No.', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_email'         => array( 'label' => __( 'Email', 'medical-college-manager' ), 'type' => 'email' ),
					'mcm_phone'         => array( 'label' => __( 'Phone', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_program'       => array( 'label' => __( 'Program', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_batch'         => array( 'label' => __( 'Batch / Year', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_gender'        => array( 'label' => __( 'Gender', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'Male', 'Female', 'Other' ) ),
					'mcm_dob'           => array( 'label' => __( 'Date of Birth', 'medical-college-manager' ), 'type' => 'date' ),
					'mcm_status'        => array( 'label' => __( 'Status', 'medical-college-manager' ), 'type' => 'select', 'options' => array( 'Active', 'Graduated', 'On Leave', 'Suspended', 'Alumni' ) ),
					'mcm_blood_group'   => array( 'label' => __( 'Blood Group', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_address'       => array( 'label' => __( 'Address', 'medical-college-manager' ), 'type' => 'textarea' ),
					'mcm_guardian'      => array( 'label' => __( 'Guardian Name', 'medical-college-manager' ), 'type' => 'text' ),
					'mcm_guardian_phone'=> array( 'label' => __( 'Guardian Phone', 'medical-college-manager' ), 'type' => 'text' ),
				),
			),
		);
	}

	public function add_meta_boxes() {
		foreach ( self::fields() as $cpt => $cfg ) {
			add_meta_box(
				$cpt . '_meta',
				$cfg['box_title'],
				array( $this, 'render' ),
				$cpt,
				'normal',
				'high',
				array( 'cpt' => $cpt )
			);
		}
	}

	public function render( $post, $metabox ) {
		$cpt    = $metabox['args']['cpt'];
		$config = self::fields();
		if ( empty( $config[ $cpt ] ) ) {
			return;
		}
		wp_nonce_field( 'mcm_meta_save', 'mcm_meta_nonce' );

		echo '<p class="description" style="margin:0 0 12px;">' . esc_html__( 'Fill in the details below, then click Publish/Update. Only the Title (at the top) is required — leave any field blank to skip it.', 'medical-college-manager' ) . '</p>';

		echo '<div class="mcm-meta-grid">';
		foreach ( $config[ $cpt ]['fields'] as $key => $field ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<p class="mcm-meta-field">';
			printf( '<label for="%1$s"><strong>%2$s</strong></label><br/>', esc_attr( $key ), esc_html( $field['label'] ) );

			switch ( $field['type'] ) {
				case 'textarea':
					printf( '<textarea id="%1$s" name="%1$s" rows="3" style="width:100%%;">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
					break;

				case 'select':
					printf( '<select id="%1$s" name="%1$s" style="min-width:220px;">', esc_attr( $key ) );
					echo '<option value="">' . esc_html__( '— Select —', 'medical-college-manager' ) . '</option>';
					foreach ( $field['options'] as $opt ) {
						printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $opt ), selected( $value, $opt, false ) );
					}
					echo '</select>';
					break;

				case 'media':
					// Upload/choose a document instead of pasting a URL — the
					// review asked for upload, and staff should not have to know
					// what a media URL is.
					printf(
						'<input type="url" id="%1$s" name="%1$s" value="%2$s" class="mcm-media-field" style="width:100%%;max-width:420px;" placeholder="https://…" /> ',
						esc_attr( $key ),
						esc_attr( $value )
					);
					// 'accept' (optional) lists the MIME families the picker offers,
					// e.g. 'image,application/pdf'; documents (PDF) by default.
					$accept = ! empty( $field['accept'] ) ? $field['accept'] : 'application/pdf';
					echo '<button type="button" class="button mcm-media-pick" data-title="' . esc_attr__( 'Select document', 'medical-college-manager' ) . '" data-type="' . esc_attr( $accept ) . '">' . esc_html__( 'Upload / Select', 'medical-college-manager' ) . '</button> ';
					echo '<button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button>';
					echo '<span class="mcm-media-preview"></span>';
					break;

				default:
					printf(
						'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" style="width:100%%;max-width:420px;" />',
						esc_attr( $field['type'] ),
						esc_attr( $key ),
						esc_attr( $value )
					);
					break;
			}
			if ( ! empty( $field['desc'] ) ) {
				printf( '<br/><span class="description" style="color:#666;">%s</span>', esc_html( $field['desc'] ) );
			}
			echo '</p>';
		}
		echo '</div>';
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['mcm_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcm_meta_nonce'] ) ), 'mcm_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$config = self::fields();
		if ( empty( $config[ $post->post_type ] ) ) {
			return;
		}

		foreach ( $config[ $post->post_type ]['fields'] as $key => $field ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] );
			$clean = self::sanitize_value( $field['type'], $raw );
			update_post_meta( $post_id, $key, $clean );
		}
	}

	/* ----------------------------------------------------------------- *
	 *  Admin list-table columns (sortable for key meta).
	 * ----------------------------------------------------------------- */

	/**
	 * Which meta keys to surface as columns per CPT.
	 * Each: meta_key => array( label, sortable(bool), date(bool) ).
	 *
	 * @return array
	 */
	public static function admin_columns() {
		return array(
			'mcm_timetable'  => array(
				'mcm_day'         => array( __( 'Day', 'medical-college-manager' ), false, false ),
				'mcm_subject'     => array( __( 'Subject', 'medical-college-manager' ), false, false ),
				'mcm_room'        => array( __( 'Room', 'medical-college-manager' ), false, false ),
			),
			'mcm_program'    => array(
				'mcm_degree_level' => array( __( 'Level', 'medical-college-manager' ), false, false ),
				'mcm_duration'     => array( __( 'Duration', 'medical-college-manager' ), false, false ),
			),
			'mcm_course'     => array(
				'mcm_course_code' => array( __( 'Code', 'medical-college-manager' ), true, false ),
				'mcm_semester'    => array( __( 'Semester', 'medical-college-manager' ), false, false ),
			),
			'mcm_event'      => array(
				'mcm_event_date'  => array( __( 'Date', 'medical-college-manager' ), true, true ),
				'mcm_venue'       => array( __( 'Venue', 'medical-college-manager' ), false, false ),
			),
			'mcm_consultant' => array(
				'mcm_designation' => array( __( 'Designation', 'medical-college-manager' ), false, false ),
			),
			'mcm_team'       => array(
				'mcm_designation' => array( __( 'Designation / Role', 'medical-college-manager' ), false, false ),
			),
			'mcm_admission'  => array(
				'mcm_open_date'   => array( __( 'Opens', 'medical-college-manager' ), true, true ),
				'mcm_close_date'  => array( __( 'Closes', 'medical-college-manager' ), true, true ),
			),
			'mcm_student'    => array(
				'mcm_student_id'  => array( __( 'Student ID', 'medical-college-manager' ), true, false ),
				'mcm_roll_no'     => array( __( 'PMDC No.', 'medical-college-manager' ), false, false ),
				'mcm_status'      => array( __( 'Status', 'medical-college-manager' ), false, false ),
			),
		);
	}

	public function register_admin_columns() {
		foreach ( array_keys( self::admin_columns() ) as $cpt ) {
			add_filter( "manage_{$cpt}_posts_columns", array( $this, 'columns' ) );
			add_action( "manage_{$cpt}_posts_custom_column", array( $this, 'column_value' ), 10, 2 );
			add_filter( "manage_edit-{$cpt}_sortable_columns", array( $this, 'sortable_columns' ) );
		}
		add_action( 'pre_get_posts', array( $this, 'sort_by_meta' ) );
	}

	/** Insert our columns just before the built-in Date column. */
	public function columns( $columns ) {
		$cpt = get_current_screen() ? get_current_screen()->post_type : '';
		$map = self::admin_columns();
		if ( empty( $map[ $cpt ] ) ) {
			return $columns;
		}
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		foreach ( $map[ $cpt ] as $key => $def ) {
			$columns[ $key ] = $def[0];
		}
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public function column_value( $column, $post_id ) {
		$cpt = get_post_type( $post_id );
		$map = self::admin_columns();
		if ( empty( $map[ $cpt ][ $column ] ) ) {
			return;
		}
		$value = get_post_meta( $post_id, $column, true );
		if ( '' === $value || null === $value ) {
			echo '—';
			return;
		}
		// Date columns: localise for display.
		if ( ! empty( $map[ $cpt ][ $column ][2] ) ) {
			$ts = strtotime( $value );
			echo esc_html( $ts ? date_i18n( get_option( 'date_format' ), $ts ) : $value );
			return;
		}
		echo esc_html( $value );
	}

	public function sortable_columns( $columns ) {
		$cpt = get_current_screen() ? get_current_screen()->post_type : '';
		$map = self::admin_columns();
		if ( empty( $map[ $cpt ] ) ) {
			return $columns;
		}
		foreach ( $map[ $cpt ] as $key => $def ) {
			if ( ! empty( $def[1] ) ) {
				$columns[ $key ] = $key; // orderby handle = the meta key.
			}
		}
		return $columns;
	}

	/** Translate a sortable meta column click into a meta-ordered query. */
	public function sort_by_meta( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( ! is_string( $orderby ) || 0 !== strpos( $orderby, 'mcm_' ) ) {
			return;
		}
		$map = self::admin_columns();
		$cpt = $query->get( 'post_type' );
		if ( empty( $map[ $cpt ][ $orderby ] ) || empty( $map[ $cpt ][ $orderby ][1] ) ) {
			return;
		}
		$is_date = ! empty( $map[ $cpt ][ $orderby ][2] );
		$query->set( 'meta_key', $orderby );
		$query->set( 'orderby', 'meta_value' );
		if ( $is_date ) {
			$query->set( 'meta_type', 'DATE' );
		}
	}
}
