<?php

namespace CLead;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Plugin {

	private $filename = null;

	private $competition = '';
	/**
	 * @var null|\WP_User
	 */
	private $user = null;

	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_iarai_file_upload', [ $this, 'iarai_file_upload' ] );
		add_action( 'wp_ajax_nopriv_iarai_file_upload', [ $this, 'iarai_file_upload' ] );
		add_action( 'wp_ajax_iarai_delete_submission', [ $this, 'ajax_delete_submission' ] );

		add_action( 'wp_ajax_iarai_filter_leaderboard', [ $this, 'ajax_filter_leaderboard' ] );
		add_action( 'wp_ajax_nopriv_iarai_filter_leaderboard', [ $this, 'ajax_filter_leaderboard' ] );

		add_action( 'posts_where', function ( $where, $wp_query ) {
			global $wpdb;
			if ( $search_term = $wp_query->get( '_title_filter' ) ) {
				$search_term           = $wpdb->esc_like( $search_term );
				$search_term           = ' \'%' . $search_term . '%\'';
				$title_filter_relation = ( strtoupper( $wp_query->get( '_title_filter_relation' ) ) === 'OR' ? 'OR' : 'AND' );
				$where                 .= ' ' . $title_filter_relation . ' ' . $wpdb->posts . '.post_title LIKE ' . $search_term;
			}

			return $where;
		}, 10, 2 );


		add_action( 'before_delete_post', [ $this, 'delete_submission_files' ] );

		// create submissions post type. Private, not public
		// create taxonomy competitions. Private, not public
		add_action( 'init', [ $this, 'register_post_types' ] );

		// remove the html filtering
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'term_description', 'wp_kses_data' );
		add_action( 'admin_head', [ $this, 'remove_default_category_description' ] );
		add_action( 'competition_add_form_fields', [ $this, 'competition_display_meta' ] );
		add_action( 'competition_edit_form_fields', [ $this, 'competition_display_meta' ] );

		/* Metabox */
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'plugins_loaded', [ $this, 'register_custom_fields' ], 12 );

		add_filter( 'template_include', [ $this, 'research_display_type_template' ], 99 );

		// Cron for scores
		add_action( 'wp', [ $this, 'register_cron_activation' ] );
		add_action( 'iarai_cron_calculate_score_event', [ $this, 'do_cron_scores' ] );
		add_filter( 'cron_schedules', [ $this, 'custom_cron_schedules' ] );

		// Export CSV
		add_action( 'admin_init', [ $this, 'export_csv' ] );

		// Email custom column
		add_filter( 'manage_edit-submission_columns', [ $this, 'custom_add_new_columns' ] );
		add_action( 'manage_submission_posts_custom_column', [ $this, 'custom_manage_new_columns' ], 10, 2 );
	}

	public function plugins_loaded() {

		\Carbon_Fields\Carbon_Fields::boot();
	}

	public function init() {

		add_shortcode( 'iarai_submission_form', [ $this, 'shortcode_submission' ] );
		add_shortcode( 'iarai_leaderboard', [ $this, 'shortcode_leaderboard' ] );
	}

	public function custom_cron_schedules( $schedules ) {
		if ( ! isset( $schedules["5minutes"] ) ) {
			$schedules["5minutes"] = array(
				'interval' => 5 * 60,
				'display'  => __( 'Once every 5 minutes' )
			);
		}
		if ( ! isset( $schedules["10minutes"] ) ) {
			$schedules["10minutes"] = array(
				'interval' => 10 * 60,
				'display'  => __( 'Once every 10 minutes' )
			);
		}
		if ( ! isset( $schedules["15minutes"] ) ) {
			$schedules["15minutes"] = array(
				'interval' => 15 * 60,
				'display'  => __( 'Once every 15 minutes' )
			);
		}
		if ( ! isset( $schedules["20minutes"] ) ) {
			$schedules["20minutes"] = array(
				'interval' => 20 * 60,
				'display'  => __( 'Once every 20 minutes' )
			);
		}

		if ( ! isset( $schedules["30minutes"] ) ) {
			$schedules["30minutes"] = array(
				'interval' => 30 * 60,
				'display'  => __( 'Once every 30 minutes' )
			);
		}

		return $schedules;
	}

	public function do_cron_scores() {
		$competitions = get_terms(
			[
				'taxonomy'   => 'competition',
				'hide_empty' => false,
			]
		);
		foreach ( $competitions as $competition ) {
			$id  = $competition->term_id;
			$tag = $competition->slug;

			$frequency         = (int) get_term_meta( $id, '_competition_cron_frequency', true );
			$previousFrequency = (int) get_term_meta( $id, 'competition_previous_cron_frequency', true );
			// if the previous frequency is empty, this means that it's a new competition and we should initialize it
			// if the previous frequency is not equal to the current one, this means that we have changed the frequency of the competition
			if (
				! metadata_exists( 'term', $id, 'competition_previous_cron_frequency' ) ||
				$frequency !== $previousFrequency
			) {
				update_term_meta( $id, 'competition_previous_cron_frequency', $frequency );
			}
			$passed = get_term_meta( $id, 'competition_time_passed_since_update', true );
			// if the passed time is empty, this means that it's a new competition and we should initialize it
			// if we changed the frequency of the competition, reset the passed time to 0
			if (
				! metadata_exists( 'term', $id, 'competition_time_passed_since_update' ) ||
				$frequency !== $previousFrequency
			) {
				update_term_meta( $id, 'competition_time_passed_since_update', 0 );
				$passed = 0;
			}
			// another iteration of the cron has passed, increment the passed time
			$passed += 10;
			update_term_meta( $id, 'competition_time_passed_since_update', $passed );
			// if the passed time is equal to the frequency, execute the function
			if ( $passed === $frequency ) {
				// since the cron executed for this competition, reset the passed time to 0
				update_term_meta( $id, 'competition_time_passed_since_update', 0 );
				$taxQuery    = [
					[
						'taxonomy' => 'competition',
						'field'    => 'slug',
						'terms'    => $tag
					],
					'relation' => 'AND'
				];
				$submissions = get_posts( [
					'post_status'    => 'publish',
					'posts_per_page' => - 1,
					'post_type'      => 'submission',
					'tax_query'      => $taxQuery
				] );
				foreach ( $submissions as $submission ) {
					$file_path  = get_post_meta( $submission->ID, '_submission_file_path', true );
					$score_path = $this->get_score_path( $file_path );
					if ( file_exists( $score_path ) ) {
						$score = file_get_contents( $score_path );
						$score = $score - 0;
						if ( $score > 0 ) {
							update_post_meta( $submission->ID, '_score', $score );
						} else {
							$message = 'Submission ID: ' . $submission->ID . '<br>';
							$message .= 'Score path: ' . $score_path . '<br>';
							$message .= 'Score: ' . $score . '<br>';
							//Logging::add( 'Score processing value error', $message, 0, 'submission' );
						}
					}
				}
			}
		}
	}

	public function register_cron_activation() {
		if ( ! wp_next_scheduled( 'iarai_cron_calculate_score_event' ) ) {
			wp_schedule_event( time(), '30minutes', 'iarai_cron_calculate_score_event' );
		}
	}

	/**
	 * Remove files when deleting post from admin area
	 *
	 * @param $post_id
	 */
	public function delete_submission_files( $post_id ) {
		$this->delete_submission( $post_id );
	}

	/**
	 * Enable leaderboard option Y/N
	 * Require file upload option Y/N
	 *
	 */
	public function register_custom_fields() {

		if ( class_exists( '\Carbon_Fields\Container' ) ) {

			$log_tag_options = function () {
				$data  = [ '' => '--Select tag--' ];
				$terms = get_terms(
					[
						'taxonomy'   => 'wp_log_type',
						'hide_empty' => false,
					]
				);
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$data[ $term->slug ] = $term->name;
					}
				}

				return $data;
			};
			Container::make( 'term_meta', __( 'Term Options', 'competitions-leaderboard' ) )
			         ->where( 'term_taxonomy', '=', 'competition' )// only show our new field for categories
			         ->add_fields( array(
					Field::make( 'rich_text', 'competition_pre_text', 'Competition Before Text' ),
					Field::make( 'select', 'competition_leaderboard', 'Enable Leaderboard' )
					     ->add_options( array(
						     'yes'    => 'Yes',
						     'editor' => 'Just for site Editors and Admins',
						     'no'     => 'No',
					     ) ),
					Field::make( 'select', 'enable_submissions', 'Enable Submissions' )
					     ->add_options( array(
						     'yes'    => 'Yes',
						     'editor' => 'Just for site Editors and Admins',
						     'guests' => 'Also for Guests',
						     'no'     => 'No',
					     ) ),
					Field::make( 'text', 'competition_limit_submit', 'Limit submissions number' )
					     ->set_attribute( 'type', 'number' )
					     ->set_conditional_logic( array(
						     array(
							     'field'   => 'enable_submissions',
							     'value'   => 'no',
							     'compare' => '!=',
						     )
					     ) ),
					Field::make( 'text', 'competition_file_types', 'Allow specific file types' )
					     ->set_help_text( 'Comma separated allowed file extensions(Ex: jpg,png,gif,pdf)' )
					     ->set_conditional_logic( array(
						     array(
							     'field'   => 'enable_submissions',
							     'value'   => 'no',
							     'compare' => '!=',
						     )
					     ) ),
					Field::make( 'select', 'enable_submission_deletion', 'Enable Submission Deletion' )
					     ->add_options( array(
						     'yes'    => 'Yes',
						     'editor' => 'Just for site Editors and Admins',
						     'no'     => 'No',
					     ) )
					     ->set_conditional_logic( array(
						     array(
							     'field'   => 'enable_submissions',
							     'value'   => 'no',
							     'compare' => '!=',
						     )
					     ) ),
					Field::make( 'date', 'competition_start_date', 'Competition Start Date' ),
					Field::make( 'date', 'competition_end_date', 'Competition End Date' ),
					Field::make( 'select', 'competition_log_tag' )
					     ->add_options( $log_tag_options ),
					Field::make( 'select', 'competition_stats_type', 'Download Statistics Method' )
					     ->add_options( array(
						     'local'     => 'Local Log',
						     'analytics' => 'Google Analytics',
					     ) ),
					Field::make( 'text', 'competition_google_label', 'Analytics Event Label' )
					     ->set_conditional_logic( array(
						     array(
							     'field'   => 'competition_stats_type',
							     'value'   => 'analytics',
							     'compare' => '=',
						     )
					     ) ),
					/*Field::make( 'text', 'competition_score_decimals', 'Score decimals' )
					     ->set_attribute( 'type', 'number' )*/
					Field::make( 'select', 'competition_score_sort', 'Leaderboard Score Sorting' )
					     ->add_options( array(
						     'asc'  => 'Ascending',
						     'desc' => 'Descending',
						     'abs'  => 'Absolute Zero',
					     ) ),
					Field::make( 'select', 'competition_cron_frequency', "Cron Frequency" )
					     ->add_options( [
						     '10' => '10 minutes',
						     '20' => '20 minutes',
						     '30' => '30 minutes'
					     ] )
				) );
		}
	}

	public function register_meta_boxes() {

		// Moderators
		add_meta_box(
			'submission_files_metabox',
			__( 'Submission info', 'bbpress' ),
			[ $this, 'file_info_metabox' ],
			'submission',
			'advanced',
			'default'
		);
	}

	/**
	 * @param \WP_Post $post
	 */
	public function file_info_metabox( $post ) {

		$score = self::get_score_number( $post->ID );
		if ( ! $score ) {
			$score = 'To be calculated';
		}
		echo '<p><strong>Score:</strong> ' . $score . '<br>';

		if ( get_post_meta( $post->ID, '_submission_file_path', true ) ) {
			echo '<p><strong>File location</strong>:<br>';
			echo get_post_meta( $post->ID, '_submission_file_path', true );
			echo '</p>';
		} else {
			echo '<p><strong>File location</strong> - NOT FOUND</p>';
		}

		if ( get_post_meta( $post->ID, '_submission_file_original_name', true ) ) {
			echo '<p><strong>Original file name</strong>:<br>';
			echo get_post_meta( $post->ID, '_submission_file_original_name', true );
			echo '</p>';
		} else {
			echo '<p><strong>Original file name:</strong> - NOT FOUND</p>';
		}
		if ( get_post_meta( $post->ID, '_submission_notes', true ) ) {
			echo '<p><strong>Notes</strong>:<br>';
			echo get_post_meta( $post->ID, '_submission_notes', true );
			echo '</p>';
		}
	}


	public function enqueue_scripts() {
		wp_register_script( 'iarai-submissions', CLEAD_URL . 'assets/js/submissions.js', [ 'jquery' ], false, true );

		wp_localize_script( 'iarai-submissions', 'iaraiSubmissionsParams', array(
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'ajaxNonce' => wp_create_nonce( 'iarai-submissions-nonce' )
		) );
	}

	public function register_post_types() {
		$labels = array(
			'name'               => _x( 'Submissions', 'post type general name', 'competitions-leaderboard' ),
			'singular_name'      => _x( 'Submission', 'post type singular name', 'competitions-leaderboard' ),
			'menu_name'          => _x( 'Submissions', 'admin menu', 'competitions-leaderboard' ),
			'name_admin_bar'     => _x( 'Submission', 'add new on admin bar', 'competitions-leaderboard' ),
			'add_new'            => _x( 'Add New', 'publication', 'competitions-leaderboard' ),
			'add_new_item'       => __( 'Add New Submission', 'competitions-leaderboard' ),
			'new_item'           => __( 'New Submission', 'competitions-leaderboard' ),
			'edit_item'          => __( 'Edit Submission', 'competitions-leaderboard' ),
			'view_item'          => __( 'View Submission', 'competitions-leaderboard' ),
			'all_items'          => __( 'All Submissions', 'competitions-leaderboard' ),
			'search_items'       => __( 'Search Submissions', 'competitions-leaderboard' ),
			'parent_item_colon'  => __( 'Parent Submissions:', 'competitions-leaderboard' ),
			'not_found'          => __( 'No submissions found.', 'competitions-leaderboard' ),
			'not_found_in_trash' => __( 'No submissions found in Trash.', 'competitions-leaderboard' )
		);

		$args = array(
			'labels'              => $labels,
			'description'         => '',
			'public'              => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author' )
		);

		register_post_type( 'submission', $args );

		$labels = array(
			'name'              => _x( 'Competitions', 'taxonomy general name', 'textdomain' ),
			'singular_name'     => _x( 'Competition', 'taxonomy singular name', 'textdomain' ),
			'search_items'      => __( 'Search Competitions', 'textdomain' ),
			'all_items'         => __( 'All Competitions', 'textdomain' ),
			'parent_item'       => __( 'Parent Competition', 'textdomain' ),
			'parent_item_colon' => __( 'Parent Competition', 'textdomain' ),
			'edit_item'         => __( 'Edit Competition', 'textdomain' ),
			'update_item'       => __( 'Update Competition', 'textdomain' ),
			'add_new_item'      => __( 'Add New Competition', 'textdomain' ),
			'new_item_name'     => __( 'New Competition', 'textdomain' ),
			'menu_name'         => __( 'Competitions', 'textdomain' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'show_in_rest'      => false,
			'rewrite'           => array( 'slug' => 'competitions' ),
		);

		register_taxonomy( 'competition', array( 'submission' ), $args );

		$labels = array(
			'name'              => _x( 'Teams', 'taxonomy general name', 'textdomain' ),
			'singular_name'     => _x( 'Team', 'taxonomy singular name', 'textdomain' ),
			'search_items'      => __( 'Search Teams', 'textdomain' ),
			'all_items'         => __( 'All Teams', 'textdomain' ),
			'parent_item'       => __( 'Parent Team', 'textdomain' ),
			'parent_item_colon' => __( 'Parent Team', 'textdomain' ),
			'edit_item'         => __( 'Edit Team', 'textdomain' ),
			'update_item'       => __( 'Update Team', 'textdomain' ),
			'add_new_item'      => __( 'Add New Team', 'textdomain' ),
			'new_item_name'     => __( 'New Team', 'textdomain' ),
			'menu_name'         => __( 'Teams', 'textdomain' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => false,
			'show_in_rest'      => false,
			'public'            => false,
			'rewrite'           => false,
		);

		register_taxonomy( 'team', array( 'submission' ), $args );
	}

	public function competition_display_meta( $category = false ) {

		$description = '';
		if ( is_object( $category ) ) {
			$description = html_entity_decode( stripcslashes( $category->description ) );
		}

		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="cat_description"><?php esc_html_e( 'Description', 'competitions-leaderboard' ); ?></label></th>
			<td>
				<div class="form-field term-meta-wrap">
					<?php

					$settings = array(
						'wpautop'       => true,
						'media_buttons' => true,
						'quicktags'     => true,
						'textarea_rows' => '15',
						'textarea_name' => 'description'
					);
					wp_editor( wp_kses_post( $description ), 'cat_description', $settings );

					?>
				</div>
			</td>
		</tr>

		<?php

	}

	public function remove_default_category_description() {
		global $current_screen;
		if ( $current_screen->id == 'edit-competition' ) {
			?>
			<script type="text/javascript">
                jQuery(function ($) {
                    $('textarea#tag-description, textarea#description').closest('.form-field').remove();
                });
			</script>
			<?php
		}
	}

	public function change_upload_dir( $dirs ) {

		$postfix = '';
		if ( $this->competition != '' ) {
			$postfix = '/' . $this->competition;
		}

		$dirs['subdir'] = '/iarai-submissions' . $postfix;
		$dirs['path']   = $dirs['basedir'] . '/iarai-submissions' . $postfix;
		$dirs['url']    = $dirs['baseurl'] . '/iarai-submissions' . $postfix;

		return $dirs;
	}

	/**
	 * Return submissions for user
	 *
	 * @param int $competition
	 * @param int $user_id
	 *
	 * @return bool|\WP_Post[]
	 */
	public static function get_submissions( $competition = null, $user_id = null ) {

		if ( $user_id === null && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id === null ) {
			return false;
		}

		$args = [
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'post_type'      => 'submission',
			'author'         => $user_id,
		];


		if ( $competition !== null ) {
			$args['tax_query'] = [
				[
					'taxonomy'         => 'competition',
					'field'            => 'term_id',
					'terms'            => $competition,
					'include_children' => false
				]
			];

		}

		return get_posts( $args );
	}

	static function get_log_content( $id ) {
		$file_path = get_post_meta( $id, '_submission_file_path', true );
		if ( $file_path ) {
			$path_parts = pathinfo( $file_path );
			if ( ! isset( $path_parts['dirname'] ) ) {
				return false;
			}

			$log_path = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.log';
			if ( file_exists( $log_path ) ) {
				return file_get_contents( $log_path );
			}
		}

		return false;
	}

	static function get_score_number( $post_id ) {

		if ( $score = get_post_meta( $post_id, '_score', true ) ) {
			return $score;
		}

		return false;
	}

	private function get_score_path( $file_path ) {
		$score_path_parts = pathinfo( $file_path );
		if ( ! isset( $score_path_parts['dirname'] ) ) {
			return false;
		}

		return $score_path_parts['dirname'] . '/' . $score_path_parts['filename'] . '.score';
	}

	public function ajax_delete_submission() {
		check_ajax_referer( 'iarai-submissions-nonce', 'security' );

		if ( ! is_user_logged_in() ) {
			return;
		}

		$deleted = false;
		$item    = (int) $_POST['item_id'];

		if ( isset( $_POST['action'] ) && $_POST['action'] === 'iarai_delete_submission' ) {
			$submissions = self::get_submissions();

			foreach ( $submissions as $k => $submission ) {
				if ( $submission->ID == $item ) {

					if ( wp_delete_post( $submission->ID ) ) {

						unset( $submissions[ $k ] );
						$deleted = true;
						break;
					}
				}
			}

			if ( $deleted === true ) {

				echo wp_json_encode( [ 'success' => true, 'message' => 'Submission deleted successfully!' ] );
				exit;
			}
		}

		echo wp_json_encode( [ 'success' => false, 'message' => 'There was a problem deleting your data' ] );

		exit;

	}

	/**
	 * Delete entry from database. Delete associated files
	 *
	 * @param int $id
	 *
	 */
	private function delete_submission( $id ) {
		$file_path  = get_post_meta( $id, '_submission_file_path', true );
		$score_path = $this->get_score_path( $file_path );

		if ( $file_path ) {
			wp_delete_file( $file_path );
		}

		if ( file_exists( $score_path ) ) {
			wp_delete_file( $score_path );
		}

		delete_post_meta( $id, '_submission_notes' );
		delete_post_meta( $id, '_submission_file_url' );
		delete_post_meta( $id, '_submission_file_path' );
		delete_post_meta( $id, '_submission_file_original_name' );

		// For log
		$user   = wp_get_current_user();
		$parent = 0;
		$type   = 'delete-submission';
		$types  = [ $type ];

		$title   = 'Deleted submission ' . $id;
		$message = 'Username: ' . $user->user_login . '<br>';
		$message .= 'IP: ' . $_SERVER['REMOTE_ADDR'] . '<br>';
		$message .= 'Browser data: ' . $_SERVER['HTTP_USER_AGENT'] . '<br>';

		//Logging::add( $title, $message, $parent, $types );

	}

	public function iarai_file_upload() {

		check_ajax_referer( 'iarai-submissions-nonce', 'security' );

		// Upload file
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'iarai_file_upload' ) {

			// Save new submissions && get ID
			$errors = [];

			if ( isset ( $_POST['title'] ) && '' != $_POST['title'] ) {
				$title = $_POST['title'];
			} else {
				$errors['title'] = 'Please enter a submission name';
			}

			if ( isset ( $_POST['competition'] ) && '' != $_POST['competition'] ) {
				$competition = $_POST['competition'];
			} else {
				$errors['competition'] = 'Please select a competition';
			}

			if ( isset( $competition ) && ! get_term( $competition, 'competition' ) ) {
				$errors['competition'] = 'Something is wrong with the submitted form. Please try refreshing the page';
			}

			if ( isset( $competition ) ) {
				$submission_option = get_term_meta( $competition, '_enable_submissions', true );
				$competition_open  = $submission_option === 'yes' || $submission_option === 'guests' || ( ( current_user_can( 'author' ) || current_user_can( 'editor' ) ) && $submission_option === 'editor' );
				if ( ! $competition_open ) {
					$errors['general'] = 'Competitions is closed for new submissions!';
				}
			}

			if ( ! is_user_logged_in() && $submission_option !== 'guests' ) {
				return;
			}

			if ( ! is_user_logged_in() && $submission_option === 'guests' ) {

				if ( ! isset ( $_POST['email'] ) || empty( $_POST['email'] ) ) {
					$errors['email'] = 'Please enter your email';
				} else {
					$email = sanitize_text_field( $_POST['email'] );

					$user = get_user_by( 'email', $email );
					if ( ! $user ) {
						// create user
						$username        = $this->random_unique_username( 's4cu' );
						$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
						$user_id         = wp_create_user( $username, $random_password, $email );
					} else {
						$user_id = $user->ID;
					}
				}
			} else {
				$user_id = get_current_user_id();
			}

			// Intermediate stop to ensure basic data is set
			if ( ! empty( $errors ) ) {
				echo wp_json_encode( [ 'errors' => $errors ] );
				exit;
			}

			$this->user = get_user_by( 'id', $user_id );

			// return error if limit exceeded
			$limit = get_term_meta( $competition, '_competition_limit_submit', true );
			if ( $limit && (int) $limit > 0 ) {

				$submissions = self::get_submissions( $competition );

				if ( $submissions ) {
					$total_submissions = count( $submissions );
					if ( $total_submissions >= $limit ) {
						$errors['general'] = '<div class="alert alert-warning">You have exceeded the total submissions limit!</div>';
						echo wp_json_encode( [ 'errors' => $errors ] );
						exit;
					}
				}

			}

			if ( $_FILES['file']['name'] == '' ) {
				$errors['file'] = 'You need to submit a file for this competition';
			}

			// Check file upload
			$uploaded_file = $_FILES['file'];
			$file_type     = explode( '.', $uploaded_file['name'] );
			$file_type     = strtolower( $file_type[ count( $file_type ) - 1 ] );

			//Get allowed file extensions
			$extension_restrict = get_term_meta( $competition, '_competition_file_types', true );

			if ( $extension_restrict && '' !== $extension_restrict ) {
				$extension_restrict = explode( ',', $extension_restrict );
				$extension_restrict = array_map( 'trim', $extension_restrict );

				if ( ! in_array( $file_type, $extension_restrict ) ) {
					$errors['file'] = 'Your uploaded file type is not allowed.';
				}
			}

			if ( empty( $errors ) ) {

				$new_post = array(
					'post_title'   => $title,
					'post_author'  => $this->user->ID,
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'submission'  // Use a custom post type if you want to
				);

				//save the new post and return its ID
				$pid = wp_insert_post( $new_post );

				$this->competition = $competition;

				// Actually try to upload the file
				add_filter( 'upload_dir', [ $this, 'change_upload_dir' ] );
				$upload_overrides = [
					'action'                   => 'iarai_file_upload',
					'test_type'                => false,
					'unique_filename_callback' => [ $this, 'custom_filename' ]
				];

				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				$data_file = wp_handle_upload( $uploaded_file, $upload_overrides );

				// For log
				$user    = wp_get_current_user();
				$parent  = 0;
				$type    = 'submission';
				$types   = [ $type ];
				$tag_log = get_term_meta( $competition, '_competition_log_tag', true );

				if ( $tag_log ) {
					$types[] = $tag_log;
				}

				// If file uploaded
				if ( $data_file && ! isset( $data_file['error'] ) ) {

					// Add notes.
					$notes = isset( $_POST['notes'] ) && $_POST['notes'] !== '' ? esc_html( $_POST['notes'] ) : false;
					if ( $notes ) {
						add_post_meta( $pid, '_submission_notes', $notes );
					}

					// Add team & password.
					if ( isset( $_POST['team'] ) && $_POST['team'] !== '' ) {
						$team = esc_html( $_POST['team'] );
						$pass = isset( $_POST['pass'] ) && $_POST['pass'] !== '' ? esc_html( $_POST['pass'] ) : false;

						if ( $pass ) {

							// Check existing team
							$existing_team = get_term_by( 'slug', sanitize_title( $team ), 'team' );
							if ( $existing_team ) {
								$existing_pass = get_term_meta( $existing_team->term_id, '_team_pass', true );

								// Team and pass match
								if ( $existing_pass && $existing_pass == $pass ) {
									wp_set_post_terms( $pid, [ $existing_team->term_id ], 'team' );
								} else {
									$errors['pass'] = 'This team already exists but the password doesn\'t match.' .
									                  'Please retry entering the correct password or pick a different team name.';
								}
							} else {
								// New team.
								$new_team = wp_insert_term( $team, 'team' );
								add_term_meta( $new_team['term_id'], '_team_pass', $pass, true );

								wp_set_post_terms( $pid, [ $new_team['term_id'] ], 'team' );
							}

						} else {
							$errors['pass'] = 'Please enter a password for your team';
						}
					}

					// Set competition term.
					wp_set_post_terms( $pid, $competition, 'competition' );

					// Save file name to the submission
					add_post_meta( $pid, '_submission_file_url', $data_file['url'] );
					add_post_meta( $pid, '_submission_file_path', $data_file['file'] );
					add_post_meta( $pid, '_submission_file_original_name', $uploaded_file['name'] );

					//log error
					$title = 'Successful submission ' . $pid;

					$message = 'Submission ID: ' . admin_url( 'post.php?post=' . $pid . '&action=edit' ) . '<br>';
					$message .= 'Username: ' . $user->user_login . '<br>';
					$message .= 'IP: ' . $_SERVER['REMOTE_ADDR'] . '<br>';
					$message .= 'Browser data: ' . $_SERVER['HTTP_USER_AGENT'] . '<br>';

					//Logging::add( $title, $message, $parent, $types );

					// $file_type = wp_check_filetype( basename( $data_file['file'] ), null );
					// Prepare an array of post data for the attachment.
					/*$attachment = array(
						'guid'           => $data_file['url'],
						'post_mime_type' => $file_type['type'],
						'post_title'     => preg_replace( '/\\.[^.]+$/', '', basename( $uploaded_file['name'] ) ),
						'post_content'   => '',
						'post_status'    => 'inherit'
					);
					wp_insert_attachment( $attachment, $data_file['file'], $pid );*/

				} else {

					$message = $data_file['error'] . '<br>';
					$message .= 'Username: ' . $user->user_login . '<br>';
					$message .= 'IP: ' . $_SERVER['REMOTE_ADDR'] . '<br>';
					$message .= 'Browser data: ' . $_SERVER['HTTP_USER_AGENT'] . '<br>';
					//Logging::add( 'Submission error', $message, $parent, $types );

					$errors['file'] = 'There was an error uploading your file';
				}

				remove_filter( 'upload_dir', [ $this, 'change_upload_dir' ] );

			}

			// Last check for errors.
			if ( ! empty( $errors ) ) {

				//Delete previous post and data
				wp_delete_post( $pid );

				echo wp_json_encode( [ 'errors' => $errors ] );
			} else {

				$submission = get_post( $pid ); //used in submission-item.php
				ob_start();
				include CLEAD_PATH . 'templates/submission-item.php';
				$data = ob_get_clean();

				echo wp_json_encode( [
					'success' => true,
					'message' => '<div class="alert alert-success">Thank you. Your form has been successfully submitted!</div>',
					'data'    => $data
				] );
			}

		}
		exit;
	}

	private function random_unique_username( $prefix = '' ) {
		$user_exists = 1;
		do {
			$rnd_str     = sprintf( "%06d", mt_rand( 1, 999999 ) );
			$user_exists = username_exists( $prefix . $rnd_str );
		} while ( $user_exists > 0 );

		return $prefix . $rnd_str;
	}

	public function custom_filename( $dir, $name, $ext ) {

		$this->filename = time() . '-' . $this->user->ID . '-' . rand( 0, 100000 ) . $ext;

		return $this->filename;
	}

	public function shortcode_submission( $atts = [] ) {
		extract( shortcode_atts( array(
			'competition' => '',
		), $atts ) );

		$competition       = ! empty( $competition ) ? $competition : get_queried_object_id();
		$submission_option = get_term_meta( $competition, '_enable_submissions', true );

		if ( ! is_user_logged_in() && $submission_option !== 'guests' ) {
			echo '<p class="alert alert-warning submissions-no-user">Please <a class="kleo-show-login" href="#">login</a> to submit data.</p>';

			return '';
		}

		wp_enqueue_script( 'iarai-submissions' );

		ob_start();

		require_once CLEAD_PATH . 'templates/submission-form.php';

		return ob_get_clean();
	}

	static function get_leaderboard_row( $submission, $competition, $count = false ) {

		$user_id         = (int) $submission->post_author;
		$user            = get_user_by( 'id', $user_id );
		$name            = $user->display_name;
		$team            = wp_get_post_terms( $submission->ID, 'team' );
		$is_current_user = ( is_user_logged_in() && $user_id === get_current_user_id() ) ? true : false;

		if ( $team && ! empty( $team ) ) {
			$name = $team[0]->name . ' - ' . $name;
		}

		if ( $count === false ) {
			$saved_positions = get_transient( 'leaderboard_' . $competition );
			$saved_positions = (array) $saved_positions;
			$count           = isset( $saved_positions[ $submission->ID ] ) ? $saved_positions[ $submission->ID ] : '';
		}

		ob_start();
		?>
		<tr>
			<td class="submission-count"><?php echo $count; ?></td>
			<td><?php echo esc_html( get_the_title( $submission ) ); ?></td>
			<td><?php echo esc_html( $name ); ?></td>
			<td>
				<?php echo get_post_meta( $submission->ID, '_score', true ); ?>
				<?php if ( $is_current_user && self::get_log_content( $submission->ID ) !== false ) { ?>
					<span data-placement="top" class="submission-log click-pop" data-toggle="popover"
					      data-title="Submission info"
					      data-content="<?php echo esc_attr( self::get_log_content( $submission->ID ) ); ?>">
							<i class="icon-info-circled"></i>
						</span>


				<?php } ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param null $competition
	 * @param string $search_term
	 * @param string $sort_order
	 *
	 * @return array|false|object
	 */
	public static function query_leaderboard( $competition = null, $search_term = '', $sort_order = 'ASC' ) {

		global $wpdb;

		if ( empty( $competition ) ) {
			return false;
		}

		$author_query = '';
		if ( is_user_logged_in() && isset( $_POST['current_user'] ) && (bool) $_POST['current_user'] === true ) {
			$author_query = " AND {$wpdb->prefix}posts.post_author IN (" . get_current_user_id() . ")";
		}

		$search_query = '';
		if ( ! empty( $search_term ) ) {
			$search_query = " AND (" .
			                "(mt1.meta_key = '_submission_notes' AND mt1.meta_value LIKE '%%%s%%')" .
			                "OR {$wpdb->prefix}posts.post_title LIKE '%%%s%%'" .
			                ")";
		}

		$submissions_query = "SELECT $wpdb->posts.* FROM $wpdb->posts" .
		                     " LEFT JOIN {$wpdb->prefix}term_relationships ON ({$wpdb->posts}.ID = {$wpdb->prefix}term_relationships.object_id)" .
		                     " INNER JOIN {$wpdb->prefix}postmeta ON ( {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id )" .
		                     " INNER JOIN {$wpdb->prefix}postmeta AS mt1 ON ( {$wpdb->prefix}posts.ID = mt1.post_id )" .
		                     " WHERE" .
		                     " {$wpdb->prefix}term_relationships.term_taxonomy_id IN ($competition)" .
		                     $author_query .
		                     " AND ( {$wpdb->prefix}postmeta.meta_key = '_score' AND {$wpdb->prefix}postmeta.meta_value > '0' )" .
		                     $search_query .
		                     " AND {$wpdb->prefix}posts.post_type = 'submission'" .
		                     " AND {$wpdb->prefix}posts.post_status = 'publish'" .
		                     " GROUP BY {$wpdb->prefix}posts.ID" .
		                     " ORDER BY {$wpdb->prefix}postmeta.meta_value+0 " . $sort_order;

		if ( $search_term ) {
			$submissions_query = $wpdb->prepare(
				$submissions_query,
				$wpdb->esc_like( $search_term ), $wpdb->esc_like( $search_term ) );
		}

		return $wpdb->get_results( $submissions_query );
	}

	public function ajax_filter_leaderboard() {

		check_ajax_referer( 'iarai-submissions-nonce', 'security' );

		if ( isset( $_POST['action'] ) && $_POST['action'] === 'iarai_filter_leaderboard' ) {

			global $wpdb;
			$competition = (int) $_POST['competition'];

			// Submissions

			$search_term = $_POST['term'];
			$submissions = self::query_leaderboard( $competition, $search_term );

			if ( $submissions ) {
				$result = '';
				foreach ( $submissions as $submission ) {
					$result .= self::get_leaderboard_row( $submission, $competition );
				}
				wp_send_json_success( [ 'results' => $result ] );
				exit;
			}

		}
		wp_send_json_success( [ 'results' => false ] );
		exit;
	}

	public function shortcode_leaderboard( $atts = [] ) {
		extract( shortcode_atts( array(
			'competition' => '',
		), $atts ) );

		wp_enqueue_script( 'iarai-submissions' );

		ob_start();

		require_once CLEAD_PATH . 'templates/submission-leaderboard.php';

		return ob_get_clean();
	}

	/**
	 * Determines if the current user has permission to upload a file based on their current role and the values
	 * of the security nonce.
	 *
	 * @param string $nonce The WordPress-generated nonce.
	 * @param string $action The developer-generated action name.
	 *
	 * @return bool              True if the user has permission to save; otherwise, false.
	 */
	private function user_can_save( $nonce, $action ) {
		$is_nonce_set   = isset( $_POST[ $nonce ] );
		$is_valid_nonce = false;
		if ( $is_nonce_set ) {
			$is_valid_nonce = wp_verify_nonce( $_POST[ $nonce ], $action );
		}

		return ( $is_nonce_set && $is_valid_nonce );
	}

	public function research_display_type_template( $template ) {

		$file = CLEAD_PATH . 'templates/archive-competition.php';
		if ( is_tax( 'competition' ) && file_exists( $file ) ) {
			return $file;
		}

		return $template;
	}

	public function export_csv() {

		if ( ! ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'submission' ) ) {
			return;
		}

		add_action( 'admin_head-edit.php', function () {
			global $current_screen;

			?>
			<script type="text/javascript">
                jQuery(document).ready(function ($) {
                    jQuery(jQuery(".wrap h1")[0]).append("<a onclick=\"window.location='" + window.location.href + "&export-csv'\" id='iarai-export-csv' class='add-new-h2'>Export CSV</a>");
                });
			</script>
			<?php
		} );

		if ( ! isset( $_GET['export-csv'] ) ) {
			return;
		}

		$filename   = 'Submissions_' . time() . '.csv';
		$header_row = array(
			'Title',
			'User ID',
			'Username',
			'Email',
			'Team',
			'Competition ID',
			'Competition',
			'Score',
			'Submission ID',
			'Log',
			'Date',
		);
		$data_rows  = array();

		$args = [
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'post_type'      => 'submission',
		];

		$submissions = get_posts( $args );

		foreach ( $submissions as $k => $submission ) {

			$terms            = get_the_terms( $submission->ID, 'team' );
			$competitions     = get_the_terms( $submission->ID, 'competition' );
			$terms_text       = '';
			$competition_text = '';
			$competition_id   = '';

			if ( $terms && ! is_wp_error( $terms ) ) {

				$terms_arr = [];

				foreach ( $terms as $term ) {
					$terms_arr[] = $term->name;
				}

				$terms_text = join( ", ", $terms_arr );
			}

			if ( $competitions && ! is_wp_error( $competitions ) ) {

				foreach ( $competitions as $competition ) {
					$competition_text = $competition->name;
					$competition_id   = $competition->term_id;
					break;
				}
			}

			$sub_id     = '';
			$path       = get_post_meta( $submission->ID, '_submission_file_path', true );
			$path_array = explode( '/', $path );
			if ( count( $path_array ) > 1 ) {
				$sub_id = str_replace( '.zip', '', end( $path_array ) );
			}

			$user        = get_user_by( 'id', $submission->post_author );
			$row         = array(
				$submission->post_title,
				$submission->post_author,
				$user->user_login,
				$user->user_email,
				$terms_text,
				$competition_id,
				$competition_text,
				self::get_score_number( $submission->ID ),
				$sub_id,
				( self::get_log_content( $submission->ID ) ? self::get_log_content( $submission->ID ) : '' ),
				get_the_date( 'Y-m-d H:i', $submission->ID )
			);
			$data_rows[] = $row;
		}
		ob_end_clean();
		$fh = @fopen( 'php://output', 'w' );
		header( "Content-Disposition: attachment; filename={$filename}" );
		fputcsv( $fh, $header_row );
		foreach ( $data_rows as $data_row ) {
			fputcsv( $fh, $data_row );
		}

		exit();
	}

	/**
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function custom_add_new_columns( $columns ) {
		$columns['author_email'] = 'Email';

		return $columns;
	}

	/**
	 * @param $column_name
	 * @param $id
	 *
	 * @return void
	 */
	public function custom_manage_new_columns( $column_name, $id ) {
		if ( 'author_email' === $column_name ) {
			$current_item = get_post( $id );
			$author_id    = $current_item->post_author;
			$author_email = get_the_author_meta( 'user_email', $author_id );
			echo $author_email;
		}
	}

}
