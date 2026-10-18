<?php
/**
 * CSV importer — bulk-create any CPT from an uploaded CSV file.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV import and export screen.
 */
class MCM_Import {

	/**
	 * Result notice shown at the top of the import page.
	 *
	 * @var string
	 */
	private $notice = '';

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		// Handle CSV export early (before the admin page outputs any HTML).
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
	}

	/**
	 * Columns for an export file — identical to what the importer reads, so an
	 * exported CSV can be edited and re-imported (round-trip).
	 *
	 * @return array
	 */
	/**
	 * Neutralise spreadsheet formula injection: a cell beginning with = + - @
	 * (or a leading tab/CR) is executed as a formula by Excel/Sheets on open.
	 * Prefix such values with an apostrophe so they are treated as text.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_safe( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Undo csv_safe() on import: drop ONE leading apostrophe when it guards a
	 * formula character, so "+92-300…" or "-5" survive export → re-import.
	 * A deliberate apostrophe before any other character is kept.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_unsafe( $value ) {
		$value = (string) $value;
		if ( strlen( $value ) > 1 && "'" === $value[0] && in_array( $value[1], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return substr( $value, 1 );
		}
		return $value;
	}

	/**
	 * Columns of an export file: exactly the columns the importer reads.
	 *
	 * @param string $type Post type.
	 */
	/**
	 * Meta key for a CSV column of a type: the schema's 'map' entry when there
	 * is one, otherwise mcm_<column>.
	 *
	 * @param string $type Post type.
	 * @param string $col  CSV column.
	 * @return string
	 */
	public static function meta_key_for( $type, $col ) {
		$schema = self::schema();
		return isset( $schema[ $type ]['map'][ $col ] ) ? $schema[ $type ]['map'][ $col ] : 'mcm_' . $col;
	}

	/**
	 * Columns of an export file: exactly the columns the importer reads.
	 *
	 * @param string $type Post type.
	 */
	private static function export_columns( $type ) {
		$schema = self::schema();
		// 'id' is first so a re-imported export updates the exact record it came
		// from instead of creating a duplicate (see import_file / find_existing).
		$cols = array( 'id', 'title', 'content', 'excerpt' );
		if ( isset( $schema[ $type ]['tax'] ) ) {
			$cols = array_merge( $cols, array_keys( $schema[ $type ]['tax'] ) );
		}
		if ( isset( $schema[ $type ]['meta'] ) ) {
			$cols = array_merge( $cols, $schema[ $type ]['meta'] );
		}
		return $cols;
	}

	/**
	 * Stream all entries of the chosen type as a CSV download. Runs on
	 * admin_init so headers can be sent before the page renders.
	 */
	public function maybe_export() {
		if ( empty( $_POST['mcm_export_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['mcm_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcm_export_nonce'] ) ), 'mcm_export' ) ) {
			return;
		}

		$type   = isset( $_POST['mcm_export_type'] ) ? sanitize_key( $_POST['mcm_export_type'] ) : '';
		$schema = self::schema();
		if ( ! isset( $schema[ $type ] ) ) {
			return;
		}

		$cols     = self::export_columns( $type );
		$tax_keys = isset( $schema[ $type ]['tax'] ) ? $schema[ $type ]['tax'] : array();

		$posts = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$types    = self::types();
		$label    = isset( $types[ $type ] ) ? sanitize_title( $types[ $type ]['label'] ) : sanitize_key( $type );
		$filename = 'mcm-' . $label . '-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		// UTF-8 BOM so Excel opens the file correctly.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download to php://output; WP_Filesystem has no stream API.
		fputcsv( $out, $cols, ',', '"', '\\' );

		foreach ( $posts as $p ) {
			$row = array();
			foreach ( $cols as $col ) {
				if ( 'id' === $col ) {
					$row[] = $p->ID;
				} elseif ( 'title' === $col ) {
					$row[] = $p->post_title;
				} elseif ( 'content' === $col ) {
					$row[] = $p->post_content;
				} elseif ( 'excerpt' === $col ) {
					$row[] = $p->post_excerpt;
				} elseif ( isset( $tax_keys[ $col ] ) ) {
					$names = wp_get_post_terms( $p->ID, $tax_keys[ $col ], array( 'fields' => 'names' ) );
					$row[] = is_wp_error( $names ) ? '' : implode( ', ', $names );
				} else {
					$row[] = MCM_Safe::meta( $p->ID, self::meta_key_for( $type, $col ) );
				}
			}
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), $row ), ',', '"', '\\' );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Column schema per CPT. `meta` headers become post meta `mcm_{header}`.
	 * `tax` maps a header to a taxonomy (term names, comma separated, auto-created).
	 * Core columns always supported: title (required), content, excerpt, status.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			'mcm_timetable'   => array(
				'meta' => array( 'day', 'start_time', 'end_time', 'subject', 'room', 'year' ),
				'tax'  => array(
					'program'    => 'mcm_program_link',
					'department' => 'mcm_department',
					'session'    => 'mcm_session',
					'teacher'    => 'mcm_teacher',
				),
			),
			'mcm_program'     => array(
				'meta' => array( 'degree_level', 'duration', 'fees', 'intake', 'apply_url' ),
				'tax'  => array(
					'program_category' => 'mcm_program_cat',
					'department'       => 'mcm_department',
				),
			),
			'mcm_course'      => array(
				'meta' => array( 'course_code', 'credit_hours', 'semester', 'course_lead' ),
				'tax'  => array(
					'program'         => 'mcm_program_link',
					'course_category' => 'mcm_course_cat',
					'department'      => 'mcm_department',
					'teacher'         => 'mcm_teacher',
					'session'         => 'mcm_session',
				),
			),
			'mcm_event'       => array(
				'meta' => array( 'event_date', 'start_time', 'end_time', 'venue', 'organizer', 'event_url', 'event_stream' ),
				'tax'  => array(
					'event_category' => 'mcm_event_cat',
					'department'     => 'mcm_department',
				),
			),
			'mcm_consultant'  => array(
				'meta' => array( 'designation', 'qualifications', 'experience', 'opd_days', 'opd_time', 'phone', 'email', 'linkedin', 'joining_date', 'orcid', 'scholar', 'pubmed', 'gender' ),
				'tax'  => array(
					'department'    => 'mcm_department',
					'specialty'     => 'mcm_specialty',
					'faculty'       => 'mcm_teacher',
					'research_area' => 'mcm_research_area',
				),
			),
			'mcm_team'        => array(
				'meta' => array( 'designation', 'qualifications', 'experience', 'phone', 'email', 'linkedin', 'joining_date', 'orcid', 'scholar', 'pubmed', 'gender' ),
				'tax'  => array(
					'department'    => 'mcm_department',
					'staff_type'    => 'mcm_staff_type',
					'specialty'     => 'mcm_specialty',
					'faculty'       => 'mcm_teacher',
					'research_area' => 'mcm_research_area',
				),
			),
			'mcm_admission'   => array(
				'meta' => array( 'program_name', 'open_date', 'close_date', 'eligibility', 'fee', 'apply_url' ),
				'tax'  => array(
					'program'    => 'mcm_program_link',
					'department' => 'mcm_department',
					'session'    => 'mcm_session',
				),
			),
			'mcm_tender'      => array(
				'meta' => array( 'tender_institute', 'tender_ref', 'tender_open', 'tender_close', 'tender_bid', 'tender_fee', 'tender_earnest', 'tender_eligibility', 'tender_doc', 'tender_apply', 'on_board' ),
				// Column => meta key where the key is not simply mcm_<column>.
				'map'  => array( 'on_board' => 'mcm_tender_on_board' ),
				'tax'  => array(
					'tender_category' => 'mcm_tender_cat',
					'department'      => 'mcm_department',
				),
			),
			'mcm_scholarship' => array(
				'meta' => array( 'scholarship_amount', 'scholarship_eligibility', 'scholarship_deadline', 'scholarship_apply' ),
				'tax'  => array(
					'scholarship_type' => 'mcm_scholarship_cat',
					'department'       => 'mcm_department',
				),
			),
			'mcm_publication' => array(
				'meta' => array( 'pub_authors', 'pub_journal', 'pub_year', 'pub_volume', 'pub_issue', 'pub_pages', 'pub_type', 'pub_doi', 'pub_pmid', 'pub_url', 'pub_pdf', 'pub_supervisor', 'pub_fellows', 'pub_open_access', 'pub_impact', 'pub_indexing' ),
				'tax'  => array(
					'research_area' => 'mcm_research_area',
					'department'    => 'mcm_department',
					'professor'     => 'mcm_teacher',
				),
			),
			'mcm_research'    => array(
				'meta' => array( 'res_pi', 'res_status', 'res_start', 'res_completed', 'res_students', 'res_coinv', 'res_funding', 'res_doc' ),
				'tax'  => array(
					'research_area' => 'mcm_research_area',
					'department'    => 'mcm_department',
					'professor'     => 'mcm_teacher',
				),
			),
			'mcm_job'         => array(
				'meta' => array( 'publish_date', 'deadline', 'deadline_time', 'extended_deadline', 'apply_url', 'apply_label', 'how_to_apply', 'document', 'voucher', 'posts', 'scale', 'type', 'contact', 'on_board' ),
				// Every job column is stored as mcm_job_<column>.
				'map'  => array(
					'publish_date'      => 'mcm_job_publish_date',
					'deadline'          => 'mcm_job_deadline',
					'deadline_time'     => 'mcm_job_deadline_time',
					'extended_deadline' => 'mcm_job_extended_deadline',
					'apply_url'         => 'mcm_job_apply_url',
					'apply_label'       => 'mcm_job_apply_label',
					'how_to_apply'      => 'mcm_job_how_to_apply',
					'document'          => 'mcm_job_document',
					'voucher'           => 'mcm_job_voucher',
					'posts'             => 'mcm_job_posts',
					'scale'             => 'mcm_job_scale',
					'type'              => 'mcm_job_type',
					'contact'           => 'mcm_job_contact',
					'on_board'          => 'mcm_job_on_board',
				),
				'tax'  => array(
					'job_category' => 'mcm_job_cat',
					'department'   => 'mcm_department',
				),
			),
			'mcm_policy'      => array(
				'meta' => array( 'policy_issuer', 'policy_ref', 'policy_date', 'policy_doc' ),
				'tax'  => array(
					'policy_category' => 'mcm_policy_cat',
					'department'      => 'mcm_department',
				),
			),
			'mcm_notice'      => array(
				'meta' => array( 'notice_priority', 'notice_pinned', 'notice_date', 'notice_expiry', 'notice_url', 'notice_attachment' ),
				'tax'  => array(
					'notice_category' => 'mcm_notice_cat',
					'department'      => 'mcm_department',
				),
			),
			'mcm_student'     => array(
				'meta' => array( 'student_id', 'roll_no', 'email', 'phone', 'program', 'batch', 'gender', 'dob', 'status', 'blood_group', 'address', 'guardian', 'guardian_phone' ),
				'tax'  => array(
					'program'    => 'mcm_program_link',
					'level'      => 'mcm_level',
					'department' => 'mcm_department',
					'session'    => 'mcm_session',
				),
			),
		);
	}

	/** Friendly labels + sample file names. */
	public static function types() {
		return array(
			'mcm_timetable'   => array(
				'label'  => __( 'Timetable', 'medical-college-manager' ),
				'sample' => 'sample-timetable.csv',
			),
			'mcm_program'     => array(
				'label'  => __( 'Programs', 'medical-college-manager' ),
				'sample' => 'sample-programs.csv',
			),
			'mcm_course'      => array(
				'label'  => __( 'Courses', 'medical-college-manager' ),
				'sample' => 'sample-courses.csv',
			),
			'mcm_event'       => array(
				'label'  => __( 'Events', 'medical-college-manager' ),
				'sample' => 'sample-events.csv',
			),
			'mcm_consultant'  => array(
				'label'  => __( 'Consultants', 'medical-college-manager' ),
				'sample' => 'sample-consultants.csv',
			),
			'mcm_team'        => array(
				'label'  => __( 'Team', 'medical-college-manager' ),
				'sample' => 'sample-team.csv',
			),
			'mcm_admission'   => array(
				'label'  => __( 'Admissions', 'medical-college-manager' ),
				'sample' => 'sample-admissions.csv',
			),
			'mcm_tender'      => array(
				'label'  => __( 'Tenders', 'medical-college-manager' ),
				'sample' => 'sample-tenders.csv',
			),
			'mcm_scholarship' => array(
				'label'  => __( 'Scholarships', 'medical-college-manager' ),
				'sample' => 'sample-scholarships.csv',
			),
			'mcm_publication' => array(
				'label'  => __( 'Publications', 'medical-college-manager' ),
				'sample' => 'sample-publications.csv',
			),
			'mcm_research'    => array(
				'label'  => __( 'Research Projects', 'medical-college-manager' ),
				'sample' => 'sample-research.csv',
			),
			'mcm_policy'      => array(
				'label'  => __( 'Policies', 'medical-college-manager' ),
				'sample' => 'sample-policies.csv',
			),
			'mcm_job'         => array(
				'label'  => __( 'Jobs', 'medical-college-manager' ),
				'sample' => 'sample-jobs.csv',
			),
			'mcm_notice'      => array(
				'label'  => __( 'Notices', 'medical-college-manager' ),
				'sample' => 'sample-notices.csv',
			),
			'mcm_student'     => array(
				'label'  => __( 'Students', 'medical-college-manager' ),
				'sample' => 'sample-students.csv',
			),
		);
	}

	/**
	 * Add the admin menu page.
	 */
	public function menu() {
		add_submenu_page(
			'mcm-settings',
			__( 'CSV Import / Export', 'medical-college-manager' ),
			__( 'CSV Import / Export', 'medical-college-manager' ),
			'manage_options',
			'mcm-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle an uploaded CSV. Returns a result array.
	 */
	private function process() {
		if ( empty( $_POST['mcm_import_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->notice = $this->error_html( __( 'You do not have permission to import.', 'medical-college-manager' ) );
			return;
		}
		if ( ! isset( $_POST['mcm_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcm_import_nonce'] ) ), 'mcm_import' ) ) {
			$this->notice = $this->error_html( __( 'Security check failed. Please try again.', 'medical-college-manager' ) );
			return;
		}

		$type   = sanitize_key( MCM_Safe::post( 'mcm_import_type' ) );
		$schema = self::schema();
		if ( ! isset( $schema[ $type ] ) ) {
			$this->notice = $this->error_html( __( 'Please choose a valid content type.', 'medical-college-manager' ) );
			return;
		}

		$status = 'draft' === MCM_Safe::post( 'mcm_import_status' ) ? 'draft' : 'publish';

		// Off by default: matching by title merged unrelated rows that happen
		// to share a name (e.g. every "Anatomy Lecture" in a timetable).
		$match_title = '1' === MCM_Safe::post( 'mcm_import_match_title' );

		// A single file only: an array here means a crafted multi-file field.
		$file = isset( $_FILES['mcm_csv'] ) && is_array( $_FILES['mcm_csv'] ) ? $_FILES['mcm_csv'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each part is validated below.
		if ( '' === MCM_Safe::scalar( $file, 'name' ) || '' === MCM_Safe::scalar( $file, 'tmp_name' ) ) {
			$this->notice = $this->error_html( __( 'No file was uploaded.', 'medical-college-manager' ) );
			return;
		}
		if ( 0 !== (int) MCM_Safe::scalar( $file, 'error', '0' ) ) {
			$this->notice = $this->error_html( __( 'File upload error. Check the file size limit and try again.', 'medical-college-manager' ) );
			return;
		}

		$tmp  = MCM_Safe::scalar( $file, 'tmp_name' );
		$name = sanitize_file_name( MCM_Safe::scalar( $file, 'name' ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( 'csv' !== $ext ) {
			$this->notice = $this->error_html( __( 'Please upload a .csv file.', 'medical-college-manager' ) );
			return;
		}
		if ( ! is_uploaded_file( $tmp ) ) {
			$this->notice = $this->error_html( __( 'Invalid upload.', 'medical-college-manager' ) );
			return;
		}

		// Defensive content sniff. CSVs are reported under several MIME types
		// across systems, so we allow the common ones rather than a single value.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo    = finfo_open( FILEINFO_MIME_TYPE );
			$detected = $finfo ? finfo_file( $finfo, $tmp ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			$allowed_mimes = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' );
			if ( $detected && ! in_array( $detected, $allowed_mimes, true ) ) {
				$this->notice = $this->error_html( __( 'The uploaded file does not look like a CSV.', 'medical-college-manager' ) );
				return;
			}
		}

		$result = $this->import_file( $tmp, $type, $schema[ $type ], $status, $match_title );

		$msg = sprintf(
			/* translators: 1: created, 2: updated, 3: skipped */
			__( 'Import complete: %1$d created, %2$d updated, %3$d skipped.', 'medical-college-manager' ),
			$result['created'],
			$result['updated'],
			$result['skipped']
		);
		if ( $result['same_title'] > 0 ) {
			$msg .= ' ' . sprintf(
				/* translators: %d: number of rows. */
				_n(
					'%d row has the same title as an existing entry – tick "Update existing entries with the same title" to update it instead.',
					'%d rows have the same title as existing entries – tick "Update existing entries with the same title" to update them instead.',
					$result['same_title'],
					'medical-college-manager'
				),
				$result['same_title']
			);
		}

		$warnings = isset( $result['warnings'] ) ? (array) $result['warnings'] : array();
		if ( $warnings ) {
			$msg .= ' ' . sprintf(
				/* translators: %d: number of warnings. */
				_n( '%d warning (the row was imported).', '%d warnings (the rows were imported).', count( $warnings ), 'medical-college-manager' ),
				count( $warnings )
			);
		}

		$html         = '<p>' . esc_html( $msg ) . '</p>';
		$html        .= self::message_list( $result['errors'], __( 'Skipped rows:', 'medical-college-manager' ) );
		$html        .= self::message_list( $warnings, __( 'Warnings:', 'medical-college-manager' ) );
		$class        = empty( $result['errors'] ) && empty( $warnings ) ? 'notice-success' : 'notice-warning';
		$this->notice = '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">' . $html . '</div>';
	}

	/**
	 * A titled list of import messages (first 20, then a count), or ''.
	 *
	 * @param string[] $items Messages.
	 * @param string   $title List heading.
	 * @return string
	 */
	private static function message_list( $items, $title ) {
		if ( empty( $items ) ) {
			return '';
		}
		$html = '<p><strong>' . esc_html( $title ) . '</strong></p><ul style="list-style:disc;padding-left:20px;">';
		foreach ( array_slice( $items, 0, 20 ) as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$more = count( $items ) - 20;
		if ( $more > 0 ) {
			/* translators: %d: number of further items not listed. */
			$html .= '<li>' . esc_html( sprintf( __( '…and %d more.', 'medical-college-manager' ), $more ) ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Stream the CSV and create posts.
	 *
	 * @param string $tmp         Uploaded file path.
	 * @param string $type        Post type.
	 * @param array  $map         Column schema for the type.
	 * @param string $status      Status for NEW posts.
	 * @param bool   $match_title Update an existing entry with the same title.
	 * @return array { created, updated, skipped, same_title, errors[], warnings[] }
	 */
	private function import_file( $tmp, $type, $map, $status, $match_title = false ) {
		$created    = 0;
		$updated    = 0;
		$skipped    = 0;
		$same_title = 0;
		$errors     = array();
		$warnings   = array(); // Row imported, but a value was not understood.
		// Posts created or updated by THIS file: rows of one file never merge
		// into each other, even when title matching is on.
		$touched = array();

		$empty = array(
			'created'    => 0,
			'updated'    => 0,
			'skipped'    => 0,
			'same_title' => 0,
			'errors'     => array(),
			'warnings'   => array(),
		);

		$handle = fopen( $tmp, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			$empty['errors'][] = __( 'Could not read the file.', 'medical-college-manager' );
			return $empty;
		}

		// Explicit args silence the PHP 8.4 deprecation and keep parsing stable.
		$header = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( false === $header || null === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$empty['errors'][] = __( 'The file is empty.', 'medical-college-manager' );
			return $empty;
		}

		// Normalize headers: lowercase, trim, strip BOM.
		$header = array_map(
			function ( $h ) {
				$h = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h );
				return strtolower( trim( $h ) );
			},
			$header
		);

		$meta_keys = isset( $map['meta'] ) ? $map['meta'] : array();
		$tax_keys  = isset( $map['tax'] ) ? $map['tax'] : array();

		$line = 1;
		while ( true ) {
			$row = fgetcsv( $handle, 0, ',', '"', '\\' );
			if ( false === $row ) {
				break;
			}
			++$line;
			if ( null === $row || ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) ) {
				continue; // blank line.
			}

			$data = array();
			foreach ( $header as $i => $col ) {
				$data[ $col ] = isset( $row[ $i ] ) ? self::csv_unsafe( trim( (string) $row[ $i ] ) ) : '';
			}

			$title = isset( $data['title'] ) ? $data['title'] : '';
			if ( '' === $title ) {
				++$skipped;
				/* translators: %d: CSV row number. */
				$errors[] = sprintf( __( 'Row %d skipped (no title).', 'medical-college-manager' ), $line );
				continue;
			}

			// Dates and times must be understood before anything is written: a
			// deadline the database cannot read would silently hide or misorder
			// the entry. The whole row is skipped and reported instead.
			$bad = '';
			foreach ( $meta_keys as $mk ) {
				$ftype = MCM_Meta::field_type( $type, self::meta_key_for( $type, $mk ) );
				if ( 'gender' === $ftype ) {
					continue; // Reported as a warning below; never a reason to skip the row.
				}
				if ( isset( $data[ $mk ] ) && '' !== $data[ $mk ] && ! MCM_Meta::is_valid( $ftype, $data[ $mk ] ) ) {
					$bad = 'date' === $ftype
						/* translators: 1: CSV row number, 2: column name, 3: the value. */
						? sprintf( __( 'Row %1$d skipped: "%2$s" value "%3$s" is not a valid date (use 15/10/2026 or 2026-10-15).', 'medical-college-manager' ), $line, $mk, $data[ $mk ] )
						/* translators: 1: CSV row number, 2: column name, 3: the value. */
						: sprintf( __( 'Row %1$d skipped: "%2$s" value "%3$s" is not a valid time (use 14:30 or 2:30 PM).', 'medical-college-manager' ), $line, $mk, $data[ $mk ] );
					break;
				}
			}
			if ( '' !== $bad ) {
				++$skipped;
				$errors[] = $bad;
				continue;
			}

			// Upsert by the 'id' column an export carries. Title matching only
			// when the admin ticked it, and never onto a post this same file
			// already wrote.
			$existing = self::find_existing( $type, $data, $title, $match_title, $touched );
			if ( ! $existing && ! $match_title && self::title_exists( $type, $title, $touched ) ) {
				++$same_title;
			}

			$postarr = array(
				'post_type'  => $type,
				'post_title' => sanitize_text_field( $title ),
			);
			// Only touch content/excerpt when the CSV actually has those columns,
			// so re-importing a file that omits them never wipes existing body text.
			if ( array_key_exists( 'content', $data ) ) {
				$postarr['post_content'] = wp_kses_post( $data['content'] );
			}
			if ( array_key_exists( 'excerpt', $data ) ) {
				$postarr['post_excerpt'] = sanitize_text_field( $data['excerpt'] );
			}

			if ( $existing ) {
				$postarr['ID'] = $existing;
				// Keep the existing status on update; the chosen import status
				// applies to newly created rows only.
				$post_id = wp_update_post( $postarr, true );
			} else {
				$postarr['post_status'] = $status;
				$post_id                = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) ) {
				++$skipped;
				/* translators: %d: CSV row number. */
				$errors[] = sprintf( __( 'Row %d failed.', 'medical-college-manager' ), $line );
				continue;
			}

			// Meta — sanitize by field nature for parity with the meta box.
			foreach ( $meta_keys as $mk ) {
				if ( ! array_key_exists( $mk, $data ) || '' === $data[ $mk ] ) {
					continue;
				}
				$meta_key = self::meta_key_for( $type, $mk );
				$ftype    = MCM_Meta::field_type( $type, $meta_key );
				if ( 'gender' === $ftype && '' === MCM_Meta::normalize_gender( $data[ $mk ] ) ) {
					// Like an empty cell: nothing is written, so an update keeps the
					// value already stored instead of losing it to a typo.
					$warnings[] = '' !== MCM_Safe::meta( $post_id, $meta_key )
						/* translators: 1: CSV row number, 2: column name, 3: the value. */
						? sprintf( __( 'Row %1$d: %2$s \'%3$s\' not recognised – existing value kept.', 'medical-college-manager' ), $line, $mk, $data[ $mk ] )
						/* translators: 1: CSV row number, 2: column name, 3: the value. */
						: sprintf( __( 'Row %1$d: %2$s \'%3$s\' not recognised – left blank.', 'medical-college-manager' ), $line, $mk, $data[ $mk ] );
					continue;
				}
				update_post_meta( $post_id, $meta_key, '' !== $ftype ? MCM_Meta::sanitize_value( $ftype, $data[ $mk ] ) : self::sanitize_meta_value( $mk, $data[ $mk ] ) );
			}

			// Taxonomies (term names, comma separated). Reuse an existing term
			// when one matches (by slug, then name) so the SAME entity across
			// different CSVs becomes one shared term — that shared term is what
			// links the content together. Only genuinely new terms are created.
			foreach ( $tax_keys as $col => $tax ) {
				if ( ! array_key_exists( $col, $data ) || '' === $data[ $col ] ) {
					continue;
				}
				$names = array_filter( array_map( 'trim', explode( ',', $data[ $col ] ) ) );
				if ( $names ) {
					$ids = self::resolve_term_ids( $names, $tax );
					if ( $ids ) {
						wp_set_object_terms( $post_id, $ids, $tax, false );
					}
				}
			}

			// Auto-connect people to their teaching: when a Consultant/Team row
			// has no explicit faculty, link it to an EXISTING Faculty term that
			// matches the person's name (created by their Timetable/Courses), so
			// their profile and classes connect. Never invents a faculty term —
			// non-teaching staff stay unlinked. Provide a "faculty" column (or
			// import teaching content first) to control this.
			if ( in_array( $type, array( 'mcm_consultant', 'mcm_team' ), true )
				&& empty( $data['faculty'] )
				&& apply_filters( 'mcm_import_autolink_faculty', true, $type, $post_id )
			) {
				$term = get_term_by( 'slug', sanitize_title( $title ), 'mcm_teacher' );
				if ( ! $term ) {
					$term = get_term_by( 'name', $title, 'mcm_teacher' );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'mcm_teacher', true );
				}
			}

			$touched[] = (int) $post_id;
			if ( $existing ) {
				++$updated;
			} else {
				++$created;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array(
			'created'    => $created,
			'updated'    => $updated,
			'skipped'    => $skipped,
			'same_title' => $same_title,
			'errors'     => $errors,
			'warnings'   => $warnings,
		);
	}

	/**
	 * Find an existing post to update for a CSV row.
	 *
	 * 1. The 'id' column an export carries (same type only).
	 * 2. Only when $match_title is on: an exact title match within the type,
	 *    excluding posts this same file already created or updated.
	 *
	 * @param string $type        Post type.
	 * @param array  $data        Row keyed by column.
	 * @param string $title       Row title.
	 * @param bool   $match_title Whether title matching is enabled.
	 * @param int[]  $touched     Post IDs already written by this file.
	 * @return int Existing post ID, or 0 when the row is new.
	 */
	private static function find_existing( $type, $data, $title, $match_title = false, $touched = array() ) {
		$id = isset( $data['id'] ) ? MCM_Safe::str( $data['id'] ) : '';
		if ( '' !== $id && ctype_digit( $id ) ) {
			$p = get_post( (int) $id );
			if ( $p && $p->post_type === $type ) {
				return (int) $p->ID;
			}
		}
		if ( $match_title ) {
			$found = self::posts_with_title( $type, $title, $touched );
			if ( $found ) {
				return (int) $found[0];
			}
		}
		return 0;
	}

	/**
	 * Does an entry of this type with this exact title exist that this file
	 * has not written itself? Used to tell the admin about likely duplicates.
	 *
	 * @param string $type    Post type.
	 * @param string $title   Title.
	 * @param int[]  $touched Post IDs already written by this file.
	 * @return bool
	 */
	private static function title_exists( $type, $title, $touched ) {
		return (bool) self::posts_with_title( $type, $title, $touched );
	}

	/**
	 * Post IDs of one type with an exact title, excluding $exclude.
	 *
	 * @param string $type    Post type.
	 * @param string $title   Title as it appears in the CSV.
	 * @param int[]  $exclude Post IDs to ignore.
	 * @return int[]
	 */
	private static function posts_with_title( $type, $title, $exclude ) {
		$title = sanitize_text_field( $title ); // As stored by the importer.
		if ( '' === $title ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'        => $type,
				'title'            => $title,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'post__not_in'     => array_map( 'intval', (array) $exclude ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded by the size of one import file.
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Resolve term names to IDs, reusing an existing term when one matches by
	 * slug (punctuation/case-insensitive via sanitize_title) or by exact name,
	 * and creating it only when none exists. This keeps the same entity as ONE
	 * term across separate CSV imports so relationships connect reliably.
	 *
	 * @param string[] $names Term names.
	 * @param string   $tax Taxonomy name.
	 * @return int[]
	 */
	private static function resolve_term_ids( $names, $tax ) {
		$ids = array();
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( $name ), $tax );
			if ( ! $term ) {
				$term = get_term_by( 'name', $name, $tax );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
				continue;
			}
			$new = wp_insert_term( $name, $tax );
			if ( ! is_wp_error( $new ) && isset( $new['term_id'] ) ) {
				$ids[] = (int) $new['term_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize a meta value according to the field's nature (URL / email /
	 * multi-line) so CSV imports match the admin meta-box behaviour.
	 *
	 * @param string $key Key.
	 * @param mixed  $val Value.
	 */
	private static function sanitize_meta_value( $key, $val ) {
		// Derive the type from the meta-box definitions rather than a hand-kept
		// list here — the two drifted apart before (pub_url, pub_pdf and
		// pub_authors were all being flattened to plain text on import, so
		// multi-line author lists lost their line breaks and URLs skipped
		// validation). One source of truth means new fields cannot drift again.
		$type = self::meta_type_for( $key );
		return MCM_Meta::sanitize_value( $type, $val );
	}

	/**
	 * Field type for a CSV meta column, looked up from the meta-box schema.
	 * CSV columns drop the mcm_ prefix, so both forms are accepted.
	 *
	 * @param string $key Short column name, e.g. "pub_url".
	 * @return string Field type, defaulting to text.
	 */
	private static function meta_type_for( $key ) {
		static $types = null;

		if ( null === $types ) {
			$types = array();
			foreach ( MCM_Meta::fields() as $cfg ) {
				if ( empty( $cfg['fields'] ) ) {
					continue;
				}
				foreach ( $cfg['fields'] as $meta_key => $field ) {
					$type               = isset( $field['type'] ) ? $field['type'] : 'text';
					$short              = ( 0 === strpos( $meta_key, 'mcm_' ) ) ? substr( $meta_key, 4 ) : $meta_key;
					$types[ $meta_key ] = $type;
					// Never let one CPT's short name overwrite another's.
					if ( ! isset( $types[ $short ] ) ) {
						$types[ $short ] = $type;
					}
				}
			}
		}

		return isset( $types[ $key ] ) ? $types[ $key ] : 'text';
	}

	/**
	 * An error notice.
	 *
	 * @param string $msg Message.
	 */
	private function error_html( $msg ) {
		return '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		$this->process();
		$types = self::types();
		?>
		<div class="wrap mcm-import-wrap">
			<h1><?php esc_html_e( 'Medical College Manager — CSV Import / Export', 'medical-college-manager' ); ?></h1>
			<?php echo wp_kses_post( $this->notice ); ?>

			<h2 class="title"><?php esc_html_e( 'Import', 'medical-college-manager' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Upload a CSV to bulk-create any content type. The first row must be the column headers. Departments, sessions, faculty and categories are created automatically from the values you provide (comma-separate multiple terms). Matching values are reused as one shared term, so content imported from different files links together automatically.', 'medical-college-manager' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Tip: import Timetable and Courses before Consultants/Team — a person whose name matches a Faculty entry is then auto-linked to the classes they teach. You can also add a "faculty" column to set this explicitly.', 'medical-college-manager' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'mcm_import', 'mcm_import_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mcm_import_type"><?php esc_html_e( 'Content Type', 'medical-college-manager' ); ?></label></th>
						<td>
							<select name="mcm_import_type" id="mcm_import_type" required>
								<?php foreach ( $types as $key => $cfg ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $cfg['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mcm_csv"><?php esc_html_e( 'CSV File', 'medical-college-manager' ); ?></label></th>
						<td><input type="file" name="mcm_csv" id="mcm_csv" accept=".csv" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mcm_import_status"><?php esc_html_e( 'Create As', 'medical-college-manager' ); ?></label></th>
						<td>
							<select name="mcm_import_status" id="mcm_import_status">
								<option value="publish"><?php esc_html_e( 'Published', 'medical-college-manager' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'medical-college-manager' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Matching', 'medical-college-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="mcm_import_match_title" value="1" />
								<?php esc_html_e( 'Update existing entries with the same title', 'medical-college-manager' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Rows with an "id" column (from an export) always update that exact entry. Leave this off when several entries legitimately share a title, such as timetable slots. Rows inside the same file are never merged.', 'medical-college-manager' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Import CSV', 'medical-college-manager' ), 'primary', 'mcm_import_submit' ); ?>
			</form>

			<hr/>
			<h2><?php esc_html_e( 'Sample CSV Templates', 'medical-college-manager' ); ?></h2>
			<p><?php esc_html_e( 'Download a ready-made sample for each type, fill it with your data, and upload it above.', 'medical-college-manager' ); ?></p>
			<ul class="mcm-sample-list">
				<?php foreach ( $types as $key => $cfg ) : ?>
					<li>
						<a href="<?php echo esc_url( MCM_URL . 'samples/' . $cfg['sample'] ); ?>" download>
							<?php echo esc_html( $cfg['label'] ); ?> &mdash; <?php echo esc_html( $cfg['sample'] ); ?>
						</a>
						<code><?php echo esc_html( $this->columns_hint( $key ) ); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>

			<hr/>
			<h2 class="title"><?php esc_html_e( 'Export', 'medical-college-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Download every entry of a content type as a CSV — including its meta fields and its Departments / Sessions / Faculty / Categories. The columns match the import format, so you can export, edit in a spreadsheet, and re-import.', 'medical-college-manager' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'mcm_export', 'mcm_export_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mcm_export_type"><?php esc_html_e( 'Content Type', 'medical-college-manager' ); ?></label></th>
						<td>
							<select name="mcm_export_type" id="mcm_export_type" required>
								<?php foreach ( $types as $key => $cfg ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $cfg['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Export CSV', 'medical-college-manager' ), 'secondary', 'mcm_export_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Comma-separated list of the columns a type's CSV may use.
	 *
	 * @param string $type Post type.
	 */
	private function columns_hint( $type ) {
		$schema = self::schema();
		$cols   = array( 'title', 'content' );
		if ( isset( $schema[ $type ]['tax'] ) ) {
			$cols = array_merge( $cols, array_keys( $schema[ $type ]['tax'] ) );
		}
		if ( isset( $schema[ $type ]['meta'] ) ) {
			$cols = array_merge( $cols, $schema[ $type ]['meta'] );
		}
		return implode( ', ', $cols );
	}
}
