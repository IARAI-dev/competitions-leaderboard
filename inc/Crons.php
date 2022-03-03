<?php

namespace CLead;

use IARAI\Logging;

class Crons {

	public function __construct() {

		// Cron for scores.
		add_action( 'wp', array( $this, 'register_cron_activation' ) );
		add_action( 'iarai_cron_calculate_score_event', array( $this, 'do_cron_scores' ) );
		add_filter( 'cron_schedules', array( $this, 'custom_cron_schedules' ) );

	}

	public function register_cron_activation() {
		if ( ! wp_next_scheduled( 'iarai_cron_calculate_score_event' ) ) {
			wp_schedule_event( time(), '30minutes', 'iarai_cron_calculate_score_event' );
		}
	}

	public function do_cron_scores() {
		$competitions = get_terms(
			array(
				'taxonomy'   => 'competition',
				'hide_empty' => false,
			)
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
				
				$taxQuery    = array(
					array(
						'taxonomy' => 'competition',
						'field'    => 'slug',
						'terms'    => $tag,
					),
					'relation' => 'AND',
				);
				$submissions = get_posts(
					array(
						'post_status'    => 'publish',
						'posts_per_page' => - 1,
						'post_type'      => 'submission',
						'tax_query'      => $taxQuery,
					)
				);

				foreach ( $submissions as $submission ) {
					$file_path  = get_post_meta( $submission->ID, '_submission_file_path', true );
					$score_path = Submissions::get_score_path( $file_path );

					if ( file_exists( $score_path ) ) {
						$score = file_get_contents( $score_path );

						// Check if we have multiple scores.
						$leaderboard = Plugin::get_leadearboard_by_submission_id( $submission->ID );
						if ( $leaderboard && $leaderboard['competition_score_has_multiple'] !== 'no' ) {

							$lines       = $leaderboard['competition_score_multiple'];
							$line_number = 1;

							if ( $lines ) {
								foreach ( $lines as $k => $line ) {
									if ( $line['score_line'] ) {
										$line_number = $k;
									}
								}
							}

							$scores = explode( "\n", $score );
							if ( isset( $scores[ $line_number ] ) ) {
								$score = trim( $scores[ $line_number ] );
								update_post_meta( $submission->ID, '_score_full', $scores );
							}
						}

						// Save the score in post meta.
						$score = $score - 0;
						if ( $score > 0 ) {
							update_post_meta( $submission->ID, '_score', $score );
						} else {
							$message  = 'Submission ID: ' . $submission->ID . '<br>';
							$message .= 'Score path: ' . $score_path . '<br>';
							$message .= 'Score: ' . $score . '<br>';
							Logging::add( 'Score processing value error', $message, 0, 'submission' );
						}
					}
				}
			}
		}
	}

	public function custom_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['5minutes'] ) ) {
			$schedules['5minutes'] = array(
				'interval' => 5 * 60,
				'display'  => __( 'Once every 5 minutes' ),
			);
		}
		if ( ! isset( $schedules['10minutes'] ) ) {
			$schedules['10minutes'] = array(
				'interval' => 10 * 60,
				'display'  => __( 'Once every 10 minutes' ),
			);
		}
		if ( ! isset( $schedules['15minutes'] ) ) {
			$schedules['15minutes'] = array(
				'interval' => 15 * 60,
				'display'  => __( 'Once every 15 minutes' ),
			);
		}
		if ( ! isset( $schedules['20minutes'] ) ) {
			$schedules['20minutes'] = array(
				'interval' => 20 * 60,
				'display'  => __( 'Once every 20 minutes' ),
			);
		}

		if ( ! isset( $schedules['30minutes'] ) ) {
			$schedules['30minutes'] = array(
				'interval' => 30 * 60,
				'display'  => __( 'Once every 30 minutes' ),
			);
		}

		return $schedules;
	}
}
