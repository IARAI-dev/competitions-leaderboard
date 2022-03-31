<?php

namespace CLead;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * Register competition fields
 */
class Options {

	/**
	 * @var array
	 */
	public static $timeline_types = array(
		'symposium'                       => '{CompetitionName} Symposium',
		'event'                           => '{CompetitionName} {Event name}',
		'special_session'                 => '{CompetitionName} Special Session',
		'workshop'                        => '{CompetitionName} Workshop',
		'conference_start_date'           => '{ConferenceName} conference start date',
		'conference_end_date'             => '{ConferenceName} conference end date',
		'abstract_code_deadline'          => 'Abstract and code submission deadline',
		'abstract_deadline'               => 'Abstract submission deadline',
		'announcement_winners'            => 'Announcement of the winners',
		'code_submission'                 => 'Code submission deadline',
		'competition_starts'              => 'Competition starts',
		'competition_ends'                => 'Competition ends ',
		'forums_open'                     => 'Forums open',
		'leaderboard_opens'               => 'Leaderboard {LeaderboadName} opens',
		'submission_leaderboard_deadline' => 'Submission to {LeaderboadName} leaderboard deadline',
		'paper_reviews_start'             => 'Paper reviews start',
		'dataset_available'               => '{DatasetName} available',
		'custom'                          => '{Event name}',
	);

	public static $timeline_has_custom_text = array(
		'event',
		'conference_start_date',
		'conference_end_date',
		'leaderboard_opens',
		'submission_leaderboard_deadline',
		'dataset_available',
		'custom'
	);

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Options();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		add_action( 'plugins_loaded', array( $this, 'register_custom_fields' ), 12 );

	}

	public function plugins_loaded() {
		\Carbon_Fields\Carbon_Fields::boot();
	}


	/**
	 * Enable leaderboard option Y/N
	 * Require file upload option Y/N
	 */
	public function register_custom_fields() {

		if ( class_exists( '\Carbon_Fields\Container' ) ) {

			$log_tag_options = function () {
				$data  = array( '' => '--Select tag--' );
				$terms = get_terms(
					array(
						'taxonomy'   => 'wp_log_type',
						'hide_empty' => false,
					)
				);
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$data[ $term->slug ] = $term->name;
					}
				}

				return $data;
			};

			$competition_fields =
				Container::make( 'term_meta', __( 'Term Options', 'competitions-leaderboard' ) )
				         ->where( 'term_taxonomy', '=', 'competition' );

			$competition_fields
				->add_tab(
					__( 'Main page' ),
					array(
						Field::make( 'checkbox', 'competition_is_main', 'Current competition' )
						     ->set_help_text( 'Is this the current competition?' ),

						Field::make( 'radio', 'competition_is_public', 'Public/Internal competition' )
						     ->add_options(
							     array(
								     ''    => 'Internal',
								     'yes' => 'Public',
							     )
						     )
						     ->set_help_text( 'Is the competition available to the public or just for logged in users' ),

						Field::make( 'text', 'competition_main_long_name', 'Competition long name' )
						     ->set_attribute( 'placeholder', 'Traffic Map Movie Forecasting 2021' )
						     ->set_required( true ),

						Field::make( 'image', 'competition_logo', 'Competition Logo' )
						     ->set_value_type( 'url' )
						     ->set_width( 50 ),
						Field::make( 'image', 'competition_main_bg_image', 'Background image' )
						     ->set_value_type( 'url' )
						     ->set_width( 50 ),

						Field::make( 'rich_text', 'competition_main_short_description', 'Short description' )
						     ->set_attribute( 'maxLength', 1500 )
						     ->set_help_text( 'Max 1500 characters' )
						     ->set_required( true ),

						Field::make( 'complex', 'competition_bullets', 'Bullet points' )
						     ->setup_labels(
							     array(
								     'plural_name'   => 'Bullet Points',
								     'singular_name' => 'Bullet Point',
							     )
						     )
						     ->set_layout( 'tabbed-horizontal' )
						     ->set_min( 1 )
						     ->set_max( 5 )
						     ->add_fields(
							     array(
								     Field::make( 'text', 'bullet', 'Bullet point' )
								          ->set_attribute( 'placeholder', 'Study high-resolution multi-channel traffic movies of entire cities with road maps.' )
								          ->set_attribute( 'maxLength', 120 )
								          ->set_help_text( 'Max 120 characters' ),
							     )
						     )
						     ->set_help_text( 'Max 5 entries' )
						     ->set_required( true ),

						Field::make( 'image', 'competition_main_image', 'Main image' )
						     ->set_value_type( 'url' ),

						Field::make( 'text', 'competition_main_video', 'Youtube Video Link ' ),

					)
				)
				->add_tab(
					__( 'Competition' ),
					array(
						Field::make( 'rich_text', 'competition_long_description', 'Long description' )
						     ->set_attribute( 'maxLength', 2500 )
						     ->set_help_text( 'Max 2500 characters' ),

						Field::make( 'image', 'competition_secondary_image', 'Secondary image' )
						     ->set_value_type( 'url' ),

						Field::make( 'rich_text', 'competition_data_description', 'Data description' )
						     ->set_attribute( 'maxLength', 2500 )
						     ->set_help_text( 'Max 2500 characters' ),

						Field::make( 'image', 'competition_data_image', 'Data image' )
						     ->set_value_type( 'url' ),

						Field::make( 'text', 'competition_data_github', 'Github/Gitlab URL' )
						     ->set_attribute( 'type', 'url' )
						     ->set_attribute( 'placeholder', 'https://' ),

					)
				)
				->add_tab(
					__( 'Challenges' ),
					array(
						Field::make( 'complex', 'competition_challenges', 'Challenges' )
						     ->setup_labels(
							     array(
								     'plural_name'   => 'Challenges',
								     'singular_name' => 'Challenge',
							     )
						     )
						     ->set_layout( 'tabbed-horizontal' )
						     ->set_min( 1 )
						     ->add_fields(
							     array(
								     Field::make( 'text', 'name', 'Challenge name' )
								          ->set_attribute( 'maxLength', 80 )
								          ->set_help_text( 'Max 80 characters' )
								          ->set_attribute( 'placeholder', 'IEEE Big Data - Stage 1 (challenge 2 would be IEEE Big Data - Stage 2)' ),

								     Field::make( 'text', 'path', 'Challenge server path' )
								          ->set_help_text( 'The path on the server inside the competition folder.' ),

								     Field::make( 'rich_text', 'description', 'Challenge description' )
								          ->set_attribute( 'maxLength', 1500 )
								          ->set_help_text( 'Max 1500 characters' ),

								     Field::make( 'complex', 'timeline', 'Timeline' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'Timeline',
										          'singular_name' => 'Timeline',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->set_min( 1 )
								          ->add_fields(
									          array(
										          Field::make( 'select', 'type', 'Timeline type' )
										               ->add_options( self::$timeline_types ),
										          Field::make( 'text', 'extra_name', 'Custom name' )
										               ->set_help_text( 'Custom name to appear in the timeline name placeholder' )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'type',
													               'value'   => self::$timeline_has_custom_text,
													               'compare' => 'IN',
												               ),
											               )
										               ),
										          Field::make( 'image', 'custom_icon', 'Custom icon' )
										               ->set_value_type( 'url' )
										               ->set_help_text( 'Custom icon for front-end' )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'type',
													               'value'   => 'custom',
													               'compare' => '=',
												               ),
											               )
										               ),
										          Field::make( 'date_time', 'date', 'Date' )
										               ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
										               ->set_width( 50 )
										               ->set_picker_options(
											               array(
												               'time_24hr'     => true,
												               'enableSeconds' => false,
											               )
										               ),
										          Field::make( 'select', 'timezone', 'Timezone' )
										               ->set_width( 50 )
										               ->add_options(
											               array(
												               'CET' => 'CET',
												               'AoE' => 'AoE',
											               )
										               ),
									          )
								          )
								          ->set_header_template( ' <%- date ? date : ($_index+1) %>' ),

								     Field::make( 'complex', 'prizes', 'Prizes' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'Prizes',
										          'singular_name' => 'Prize',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->set_min( 1 )
								          ->set_max( 3 )
								          ->add_fields(
									          array(
										          Field::make( 'rich_text', 'prize', 'Prize' )
										               ->set_default_value( 'Voucher or cash prize worth {AMOUNT} EUR to the participant/team and one free {CONFERENCE NAME AND YEAR} conference registration' )
										               ->set_attribute( 'maxLength', 200 )
										               ->set_help_text( 'Max 200 characters' ),
										          Field::make( 'text', 'amount', 'Prize amount' )
										               ->set_attribute( 'maxLength', 6 )
										               ->set_default_value( '3000' )
										               ->set_help_text( 'Prize amount in EUR. Max 6 characters' ),
										          Field::make( 'text', 'conference_name', 'Conference name' ),
										          Field::make( 'text', 'conference_year', 'Conference year' ),

									          )
								          ),

								     Field::make( 'complex', 'special_prizes', 'Special Prizes' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'Special Prizes',
										          'singular_name' => 'Special Prize',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->add_fields(
									          array(
										          Field::make( 'text', 'name', 'Name' )
										               ->set_default_value( 'For a surprising, pure network-theoretical solution' ),
										          Field::make( 'text', 'prize', 'Prize' )
										               ->set_attribute( 'maxLength', 200 )
										               ->set_help_text( 'Max 200 characters' )
										               ->set_default_value( 'Voucher or cash prize of {AMOUNT} EUR' ),
										          Field::make( 'text', 'amount', 'Amount' )
										               ->set_default_value( '2000' ),
									          )
								          ),

								     // special prizes. 1 text field
								     Field::make( 'complex', 'awards', 'Winners' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'Winners',
										          'singular_name' => 'Winner',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->set_min( 1 )
								          ->set_max( 3 )
								          ->add_fields(
									          array(
										          Field::make( 'text', 'team_name', 'Team Name' ),
										          Field::make( 'text', 'team_members', 'Team members' ),
										          Field::make( 'text', 'affiliations', 'Affiliations' ),
										          Field::make( 'text', 'award', 'Prize' ),
									          )
								          )
								          ->set_header_template( ' <%- team_name ? team_name : ($_index+1) %>' ),

								     Field::make( 'complex', 'special_prizes_awards', 'Special Prizes Winners' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'SP Winners',
										          'singular_name' => 'SP Winner',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->set_min( 1 )
								          ->set_max( 3 )
								          ->add_fields(
									          array(
										          Field::make( 'text', 'team_name', 'Team Name' ),
										          Field::make( 'text', 'title', 'Title' ),
										          Field::make( 'text', 'affiliations', 'Affiliations' ),
										          Field::make( 'text', 'award', 'Prize' ),
									          )
								          )
								          ->set_header_template( ' <%- team_name ? team_name : ($_index+1) %>' ),

								     // External submissions.
								     Field::make( 'complex', 'external_submissions', 'External submissions' )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'External submissions',
										          'singular_name' => 'External submission',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->add_fields(
									          array(
										          Field::make( 'text', 'name', 'Submission Type' ),
										          Field::make( 'text', 'link', 'Link' ),
										          Field::make( 'date_time', 'start_date', 'Start Date' )
										               ->set_width( 50 )
										               ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
										               ->set_picker_options(
											               array(
												               'time_24hr'     => true,
												               'enableSeconds' => false,
											               )
										               ),

										          Field::make( 'select', 'start_date_tz', 'Timezone' )
										               ->set_width( 50 )
										               ->add_options( array( 'CET' => 'CET', 'AoE' => 'AoE' ) ),

										          Field::make( 'date_time', 'end_date', 'End Date' )
										               ->set_width( 50 )
										               ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
										               ->set_picker_options(
											               array(
												               'time_24hr'     => true,
												               'enableSeconds' => false,
											               )
										               ),
										          Field::make( 'select', 'end_date_tz', 'Timezone' )
										               ->set_width( 50 )
										               ->add_options( array( 'CET' => 'CET', 'AoE' => 'AoE' ) ),
										          Field::make( 'select', 'ui', 'How to show the external submission?' )
										               ->add_options(
											               array(
												               'popup' => 'Popup',
												               'page'  => 'On another page',
											               )
										               )
									          )
								          ),

								     // Leaderboards
								     Field::make( 'select', 'enable_leaderboards', 'Leaderboard Visibility' )
								          ->add_options(
									          array(
										          'yes'    => 'Public',
										          'editor' => 'Just for site Editors and Admins',
										          'no'     => 'Closed',
									          )
								          ),
								     Field::make( 'complex', 'competition_leaderboards', 'Leaderboards' )
								          ->set_conditional_logic(
									          array(
										          array(
											          'field'   => 'enable_leaderboards',
											          'value'   => 'no',
											          'compare' => '!=',
										          ),
									          )
								          )
								          ->setup_labels(
									          array(
										          'plural_name'   => 'Leaderboards',
										          'singular_name' => 'Leaderboard',
									          )
								          )
								          ->set_layout( 'tabbed-horizontal' )
								          ->set_min( 1 )
								          ->add_fields(
									          array(

										          Field::make( 'text', 'name', 'Name' ),
										          Field::make( 'text', 'path', 'Leaderboard server path' )
										               ->set_help_text( 'The path on the server inside the competition folder.' ),

										          Field::make( 'complex', 'data', 'Data' )
										               ->setup_labels(
											               array(
												               'plural_name'   => 'Data',
												               'singular_name' => 'Data',
											               )
										               )
										               ->set_layout( 'tabbed-horizontal' )
										               ->add_fields(
											               array(
												               Field::make( 'text', 'name', 'Name' ),
												               Field::make( 'text', 'link', 'Link' )
												                    ->set_attribute( 'placeholder', 'https://' ),


												               Field::make( 'date_time', 'release_date', 'Release Data Date' )
												                    ->set_width( 50 )
												                    ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
												                    ->set_picker_options(
													                    array(
														                    'time_24hr'     => true,
														                    'enableSeconds' => false,
													                    )
												                    ),

												               Field::make( 'select', 'release_date_tz', 'Timezone' )
												                    ->set_width( 50 )
												                    ->add_options( array(
													                    'CET' => 'CET',
													                    'AoE' => 'AoE'
												                    ) ),

												               Field::make( 'date_time', 'hide_date', 'Hide Data Date' )
												                    ->set_width( 50 )
												                    ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
												                    ->set_picker_options(
													                    array(
														                    'time_24hr'     => true,
														                    'enableSeconds' => false,
													                    )
												                    ),
												               Field::make( 'select', 'hide_date_tz', 'Timezone' )
												                    ->set_width( 50 )
												                    ->add_options( array(
													                    'CET' => 'CET',
													                    'AoE' => 'AoE'
												                    ) ),
											               )

										               )
										               ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),

										          Field::make( 'select', 'enable_submissions', 'Enable Submissions' )
										               ->add_options(
											               array(
												               'yes'    => 'Yes',
												               'editor' => 'Just for site Editors and Admins',
												               'guests' => 'Also for Guests',
												               'no'     => 'No',
											               )
										               ),
										          Field::make( 'text', 'competition_limit_submit', 'Limit submissions number' )
										               ->set_attribute( 'type', 'number' )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'enable_submissions',
													               'value'   => 'no',
													               'compare' => '!=',
												               ),
											               )
										               ),
										          Field::make( 'radio', 'competition_file_types', 'Allow specific file types' )
										               ->add_options(
											               array(
												               'zip'    => 'zip',
												               'json'   => 'json',
												               'custom' => 'Custom',
											               )
										               )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'enable_submissions',
													               'value'   => 'no',
													               'compare' => '!=',
												               ),
											               )
										               ),
										          Field::make( 'text', 'competition_file_types_custom', 'Custom extension' )
										               ->set_help_text( 'Enter the file extension without dot. Eq: rar' )
										               ->set_attribute( 'placeholder', 'rar' )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'competition_file_types',
													               'value'   => 'custom',
													               'compare' => '=',
												               ),
											               )
										               ),
										          Field::make( 'select', 'enable_submission_deletion', 'Enable Submission Deletion' )
										               ->add_options(
											               array(
												               'yes'    => 'Yes',
												               'editor' => 'Just for site Editors and Admins',
												               'no'     => 'No',
											               )
										               )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'enable_submissions',
													               'value'   => 'no',
													               'compare' => '!=',
												               ),
											               )
										               ),
										          Field::make( 'date_time', 'competition_start_date', 'Leaderboard Start Date' )
										               ->set_width( 50 )
										               ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
										               ->set_picker_options(
											               array(
												               'time_24hr'     => true,
												               'enableSeconds' => false,
											               )
										               ),

										          Field::make( 'select', 'timezone_start_date', 'Timezone' )
										               ->set_width( 50 )
										               ->add_options( array( 'CET' => 'CET', 'AoE' => 'AoE' ) ),

										          Field::make( 'date_time', 'competition_end_date', 'Leaderboard End Date' )
										               ->set_width( 50 )
										               ->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
										               ->set_picker_options(
											               array(
												               'time_24hr'     => true,
												               'enableSeconds' => false,
											               )
										               ),
										          Field::make( 'select', 'timezone_end_date', 'Timezone' )
										               ->set_width( 50 )
										               ->add_options( array( 'CET' => 'CET', 'AoE' => 'AoE' ) ),

										          Field::make( 'text', 'competition_score_error', 'Score error number is' ),
										          Field::make( 'select', 'competition_score_has_multiple', 'Is there more than one score?' )
										               ->add_options(
											               array(
												               'no'  => 'No',
												               'yes' => 'Yes',
											               )
										               ),

										          Field::make( 'complex', 'competition_score_multiple', 'Score lines' )
										               ->setup_labels(
											               array(
												               'plural_name'   => 'Score Line',
												               'singular_name' => 'Score Lines',
											               )
										               )
										               ->set_layout( 'tabbed-vertical' )
										               ->set_min( 1 )
										               ->add_fields(
											               array(
												               Field::make( 'checkbox', 'score_line', 'Is this the score line?' ),
												               Field::make( 'text', 'line', 'Line name' )
												                    ->set_conditional_logic(
													                    array(
														                    array(
															                    'field'   => 'score_line',
															                    'value'   => '',
															                    'compare' => '=',
														                    ),
													                    )
												                    ),
											               )
										               )
										               ->set_conditional_logic(
											               array(
												               array(
													               'field'   => 'competition_score_has_multiple',
													               'value'   => 'yes',
													               'compare' => '=',
												               ),
											               )
										               ),

										          Field::make( 'select', 'competition_score_sort', 'Leaderboard Score Sorting' )
										               ->add_options(
											               array(
												               'asc'  => 'Ascending',
												               'desc' => 'Descending',
												               'abs'  => 'Absolute Zero',
											               )
										               ),
										          Field::make( 'select', 'competition_cron_frequency', 'Cron Frequency' )
										               ->add_options(
											               array(
												               '10' => '10 minutes',
												               '20' => '20 minutes',
												               '30' => '30 minutes',
											               )
										               ),
									          )
								          )
								          ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),

							     )
						     )
						     ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),
					)
				)
				->add_tab(
					__( 'Connect' ),
					array(
						// Field::make( 'text', 'competition_connect_forum', 'Forum link' ),
						Field::make( 'text', 'competition_connect_contact', 'Contact email' )
						     ->set_attribute( 'placeholder', 'traffic4cast@iarai.ac.at' ),
						Field::make( 'complex', 'competition_connect_scientific_committee', 'Scientific Committee' )
						     ->set_layout( 'tabbed-horizontal' )
						     ->set_min( 1 )
							// ->set_max( 3 )
							 ->add_fields(
								array(
									Field::make( 'select', 'title', 'Title' )
									     ->add_options(
										     array(
											     ''      => 'None',
											     'Dr'    => 'Dr',
											     'Prof.' => 'Prof.',
											     'MSc'   => 'MSc',
											     'PhD'   => 'PhD',

										     )
									     ),
									Field::make( 'select', 'role', 'Role in competition' )
									     ->add_options(
										     array(
											     'Member'   => 'Member',
											     'Chair'    => 'Chair',
											     'Co-Chair' => 'Co-Chair',

										     )
									     ),
									Field::make( 'text', 'name', 'Name' ),
									Field::make( 'image', 'image', 'Image' )
									     ->set_value_type( 'url' ),
									Field::make( 'text', 'affiliation', 'Affiliation & Country' )
									     ->set_attribute( 'placeholder', 'Affiliation, Country' ),
									Field::make( 'rich_text', 'description', 'Bio' ),
								)
							)
						     ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),
						Field::make( 'complex', 'competition_connect_organising_committee', 'Organising Committee' )
						     ->set_layout( 'tabbed-horizontal' )
						     ->set_min( 1 )
							// ->set_max( 3 )
							 ->add_fields(
								array(
									Field::make( 'select', 'title', 'Title' )
									     ->add_options(
										     array(
											     ''      => 'None',
											     'Dr'    => 'Dr',
											     'Prof.' => 'Prof.',
											     'MSc'   => 'MSc',
											     'PhD'   => 'PhD',
										     )
									     ),
									Field::make( 'select', 'role', 'Role in competition' )
									     ->add_options(
										     array(
											     'Team'           => 'Team',
											     'Core Organizer' => 'Core Organizer',
										     )
									     ),
									Field::make( 'text', 'affiliation', 'Affiliation' ),
									Field::make( 'text', 'role_affiliation', 'Role at affiliation' ),
									Field::make( 'text', 'name', 'Name' ),
									Field::make( 'image', 'image', 'Image' )
									     ->set_value_type( 'url' ),
									Field::make( 'rich_text', 'description', 'Bio' ),
								)
							)
						     ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),

					)
				)
				->add_tab(
					__( 'Events' ),
					array(
						Field::make( 'text', 'competition_indico_category', 'Indico Events Category ID' )
						     ->set_help_text( 'Enter the Category ID from Indico where we should get the events from' ),

					)
				)
				->add_tab(
					__( 'Deprecated' ),
					array(
						Field::make( 'html', 'deprecated_html' )->set_html( '<h2>Please ignore all these fields.</h2>' ),
						Field::make( 'checkbox', 'competition_is_v2', 'New template' )
						     ->set_default_value( 'yes' )
						     ->set_help_text( 'Is this using the new 2.0 template?' ),
						Field::make( 'text', 'competition_old_link', 'Old link to competition' )
						     ->set_conditional_logic(
							     array(
								     array(
									     'field'   => 'competition_is_v2',
									     'value'   => 'yes',
									     'compare' => '!=',
								     ),
							     )
						     ),

						Field::make( 'rich_text', 'competition_pre_text', 'Before Text(Deprecated)' ),
						Field::make( 'select', 'competition_leaderboard', 'Enable Leaderboard' )
						     ->add_options(
							     array(
								     'yes'    => 'Yes',
								     'editor' => 'Just for site Editors and Admins',
								     'no'     => 'No',
							     )
						     ),
						Field::make( 'select', 'enable_submissions', 'Enable Submissions' )
						     ->add_options(
							     array(
								     'yes'    => 'Yes',
								     'editor' => 'Just for site Editors and Admins',
								     'guests' => 'Also for Guests',
								     'no'     => 'No',
							     )
						     ),
						Field::make( 'text', 'competition_limit_submit', 'Limit submissions number' )
						     ->set_attribute( 'type', 'number' )
						     ->set_conditional_logic(
							     array(
								     array(
									     'field'   => 'enable_submissions',
									     'value'   => 'no',
									     'compare' => '!=',
								     ),
							     )
						     ),
						Field::make( 'text', 'competition_file_types', 'Allow specific file types' )
						     ->set_help_text( 'Comma separated allowed file extensions(Ex: jpg,png,gif,pdf)' )
						     ->set_conditional_logic(
							     array(
								     array(
									     'field'   => 'enable_submissions',
									     'value'   => 'no',
									     'compare' => '!=',
								     ),
							     )
						     ),
						Field::make( 'select', 'enable_submission_deletion', 'Enable Submission Deletion' )
						     ->add_options(
							     array(
								     'yes'    => 'Yes',
								     'editor' => 'Just for site Editors and Admins',
								     'no'     => 'No',
							     )
						     )
						     ->set_conditional_logic(
							     array(
								     array(
									     'field'   => 'enable_submissions',
									     'value'   => 'no',
									     'compare' => '!=',
								     ),
							     )
						     ),
						Field::make( 'date', 'competition_start_date', 'Competition Start Date' ),
						Field::make( 'date', 'competition_end_date', 'Competition End Date' ),
						Field::make( 'select', 'competition_log_tag' )
						     ->add_options( $log_tag_options ),
						Field::make( 'select', 'competition_stats_type', 'Download Statistics Method' )
						     ->add_options(
							     array(
								     'local'     => 'Local Log',
								     'analytics' => 'Google Analytics',
							     )
						     ),
						Field::make( 'text', 'competition_google_label', 'Analytics Event Label' )
						     ->set_conditional_logic(
							     array(
								     array(
									     'field'   => 'competition_stats_type',
									     'value'   => 'analytics',
									     'compare' => '=',
								     ),
							     )
						     ),
						/*
						Field::make( 'text', 'competition_score_decimals', 'Score decimals' )
							 ->set_attribute( 'type', 'number' )*/
						Field::make( 'select', 'competition_score_sort', 'Leaderboard Score Sorting' )
						     ->add_options(
							     array(
								     'asc'  => 'Ascending',
								     'desc' => 'Descending',
								     'abs'  => 'Absolute Zero',
							     )
						     ),
						Field::make( 'select', 'competition_cron_frequency', 'Cron Frequency' )
						     ->add_options(
							     array(
								     '10' => '10 minutes',
								     '20' => '20 minutes',
								     '30' => '30 minutes',
							     )
						     ),
					)
				);

		}
	}
}
