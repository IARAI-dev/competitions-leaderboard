<?php

namespace CLead;

use IARAI\Logging;

class Submissions {

	/**
	 * @var null|\WP_User
	 */
	private $user = null;

	private $filename = null;

	private $competition = '';
	private $challenge = '';
	private $leaderboard = '';

	public function __construct() {

		add_action( 'wp_ajax_iarai_file_upload', [ $this, 'iarai_file_upload' ] );
		// add_action( 'wp_ajax_nopriv_iarai_file_upload', [ $this, 'iarai_file_upload' ] );
		add_action( 'wp_ajax_iarai_delete_submission', [ $this, 'ajax_delete_submission' ] );
		add_action( 'before_delete_post', [ $this, 'delete_submission_files' ] );
	}


	public function iarai_file_upload() {

		check_ajax_referer( 'iarai-submissions-nonce', 'security' );

		// Upload file
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'iarai_file_upload' ) {
			exit;
		}

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

		$challenge   = null;
		$leaderboard = null;

		// get 2.0 challenges.
		if ( isset( $_POST['challenge'], $_POST['leaderboard'] ) ) {

			$challenge   = sanitize_text_field( $_POST['challenge'] );
			$leaderboard = sanitize_text_field( $_POST['leaderboard'] );

			$challenge_data   = [];
			$leaderboard_data = [];

			$competition_challenges = carbon_get_term_meta( $competition, 'competition_challenges' );

			if ( $competition_challenges && ! empty( $competition_challenges ) ) {
				foreach ( $competition_challenges as $competition_challenge ) {
					if ( sanitize_title_with_dashes( $challenge ) === sanitize_title_with_dashes( $competition_challenge['name'] ) ) {
						$challenge_data         = $competition_challenge;
						$challenge_data['slug'] = sanitize_title_with_dashes( $competition_challenge['name'] );

						if ( isset( $competition_challenge['competition_leaderboards'] ) && ! empty( $competition_challenge['competition_leaderboards'] ) ) {
							foreach ( $competition_challenge['competition_leaderboards'] as $lb ) {
								if ( sanitize_title_with_dashes( $leaderboard ) === sanitize_title_with_dashes( $lb['name'] ) ) {
									$leaderboard_data         = $lb;
									$leaderboard_data['slug'] = sanitize_title_with_dashes( $lb['name'] );
									break 2;
								}
							}
						}
						break;
					}
				}
			}
		}

		// return error if limit exceeded
		if ( ! empty( $leaderboard_data ) ) {
			$limit = $leaderboard_data['competition_limit_submit'];
		} else {
			$limit = get_term_meta( $competition, '_competition_limit_submit', true );
		}


		if ( $limit && (int) $limit > 0 ) {

			$submissions = self::get_submissions( $competition, null, $challenge, $leaderboard );

			if ( $submissions ) {
				$total_submissions = count( $submissions );

				if ( $total_submissions >= (int) $limit ) {
					$errors['general'] = '<div class="alert alert-warning">You have exceeded the total submissions limit! Please remove existing submissions so you can add new ones.</div>';
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
		if ( ! empty( $leaderboard_data ) ) {
			$extension_restrict = $leaderboard_data['competition_file_types'];
		} else {
			$extension_restrict = get_term_meta( $competition, '_competition_file_types', true );
		}

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

			if ( isset( $challenge, $leaderboard ) ) {
				$this->challenge   = $challenge;
				$this->leaderboard = $leaderboard;
			}

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

				//Set Challenge term
				if ( ! empty( $challenge_data ) ) {
					wp_set_post_terms( $pid, $competition . '-' . $challenge_data['slug'], 'challenge' );
				}

				//Set Leaderboard term
				if ( ! empty( $leaderboard_data ) ) {
					wp_set_post_terms( $pid, $competition . '-' . $leaderboard_data['slug'], 'leaderboard' );
				}

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

				Logging::add( $title, $message, $parent, $types );

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
				Logging::add( 'Submission error', $message, $parent, $types );

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
		exit;
	}

	/**
	 * Remove files when deleting post from admin area
	 *
	 * @param $post_id
	 */
	public function delete_submission_files( $post_id ) {
		$this->delete_submission( $post_id );
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
		$score_path = self::get_score_path( $file_path );

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

		Logging::add( $title, $message, $parent, $types );

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

	/**
	 * Return submissions for user
	 *
	 * @param int $competition
	 * @param int $user_id
	 *
	 * @return bool|\WP_Post[]
	 */
	public static function get_submissions( $competition = null, $user_id = null, $challenge = null, $leaderboard = null ) {

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

		$args['tax_query'] = [];

		if ( $competition !== null ) {
			$args['tax_query'][] =
				[
					'taxonomy'         => 'competition',
					'field'            => 'term_id',
					'terms'            => $competition,
					'include_children' => false
				];
		}

		if ( $challenge !== null ) {
			$args['tax_query'][] =
				[
					'taxonomy' => 'challenge',
					'field'    => 'slug',
					'terms'    => $competition . '-' . $challenge,
				];
		}

		if ( $leaderboard !== null ) {
			$args['tax_query'][] =
				[
					'taxonomy' => 'leaderboard',
					'field'    => 'slug',
					'terms'    => $competition . '-' . $leaderboard,
				];
		}

		return get_posts( $args );
	}

	public static function get_score_path( $file_path ) {
		$score_path_parts = pathinfo( $file_path );
		if ( ! isset( $score_path_parts['dirname'] ) ) {
			return false;
		}

		return $score_path_parts['dirname'] . '/' . $score_path_parts['filename'] . '.score';
	}

	public function change_upload_dir( $dirs ) {

		$postfix = '';
		if ( $this->competition != '' ) {
			$postfix = '/' . $this->competition;
			if ( ! empty( $this->challenge ) && ! empty( $this->leaderboard ) ) {
				$postfix .= '/' . $this->challenge . '/' . $this->leaderboard;
			}
		}

		$dir = '/iarai-submissions';

		if ( defined( 'COMPETITION_DIR' ) && ! empty( COMPETITION_DIR ) ) {
			$dir = COMPETITION_DIR;
		}

		$dirs['subdir'] = $dir . $postfix;
		$dirs['path']   = $dirs['basedir'] . $dir . $postfix;
		$dirs['url']    = $dirs['baseurl'] . $dir . $postfix;

		return $dirs;
	}


}
