<?php

namespace CLead;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use IARAI\Logging;

class Plugin {

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
	);

	public static $timeline_has_custom_text = array(
		'event',
		'conference_start_date',
		'conference_end_date',
		'leaderboard_opens',
		'submission_leaderboard_deadline',
		'dataset_available',
	);


	public function __construct() {

		new Crons();
		new Terms();
		new Submissions();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action(
			'wp',
			function() {

				global $post;

				if ( empty( $post ) ) {
					return;
				}

				if ( ! is_multisite() ) {
					return;
				}

				if ( has_shortcode( $post->post_content, 'competitions_app' ) ) {

					//Remove existing header
					remove_action( 'kleo_header', 'kleo_show_header' );

					// Disable sticky header
					add_filter(
						'body_class',
						function( $class ) {
							if ( ( $key = array_search( 'kleo-navbar-fixed', $class ) ) !== false ) {
								unset( $class[ $key ] );
							}
							return $class;
						}
					);

					// Replace header with main site header
					add_action(
						'kleo_header',
						function() {

							// delete_transient( 'main_page_header' );
							if ( $header = get_transient( 'main_page_header' ) ) {
								echo $header;
								return;
							}

							$request = wp_remote_get( network_site_url() );
							$html    = wp_remote_retrieve_body( $request );
							// preg_match('/<div id="header" class="header-color">(.*?)<\/div>/s', $html, $match);

							$dom = new \DOMDocument();
							libxml_use_internal_errors( true );

							$dom->loadHTML( $html );

							$xpath = new \DOMXPath( $dom );

							$div = $xpath->query( '//*[@id="header"]' );

							$div = $div->item( 0 );

							set_transient( 'main_page_header', $dom->saveHTML( $div ), 60 * 60 );

							echo $dom->saveHTML( $div );

						}
					);
				}
			}
		);

		add_action( 'wp_ajax_iarai_filter_leaderboard', array( $this, 'ajax_filter_leaderboard' ) );
		add_action( 'wp_ajax_nopriv_iarai_filter_leaderboard', array( $this, 'ajax_filter_leaderboard' ) );

		add_action(
			'posts_where',
			function ( $where, $wp_query ) {
				global $wpdb;
				if ( $search_term = $wp_query->get( '_title_filter' ) ) {
					$search_term           = $wpdb->esc_like( $search_term );
					$search_term           = ' \'%' . $search_term . '%\'';
					$title_filter_relation = ( strtoupper( $wp_query->get( '_title_filter_relation' ) ) === 'OR' ? 'OR' : 'AND' );
					$where                .= ' ' . $title_filter_relation . ' ' . $wpdb->posts . '.post_title LIKE ' . $search_term;
				}

				return $where;
			},
			10,
			2
		);

		// create submissions post type. Private, not public
		// create taxonomy competitions. Private, not public
		add_action( 'init', array( $this, 'register_post_types' ) );

		// remove the html filtering
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'term_description', 'wp_kses_data' );

		// add wysiwyg description
		add_action( 'admin_head', array( $this, 'remove_default_category_description' ) );
		// add_action( 'competition_add_form_fields', [ $this, 'competition_display_meta' ] );
		// add_action( 'competition_edit_form_fields', [ $this, 'competition_display_meta' ] );

		/* Metabox */
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'plugins_loaded', array( $this, 'register_custom_fields' ), 12 );

		add_filter( 'template_include', array( $this, 'research_display_type_template' ), 99 );

		// Export CSV
		add_action( 'admin_init', array( $this, 'export_csv' ) );

		// Email custom column
		add_filter( 'manage_edit-submission_columns', array( $this, 'custom_add_new_columns' ) );
		add_action( 'manage_submission_posts_custom_column', array( $this, 'custom_manage_new_columns' ), 10, 2 );

		// Show the taxonomy ID
		add_filter( 'manage_edit-competition_columns', array( $this, 'add_tax_col' ) );
		add_filter( 'manage_edit-competition_sortable_columns', array( $this, 'add_tax_col' ) );
		add_filter( 'manage_competition_custom_column', array( $this, 'show_tax_id' ), 10, 3 );

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'competition/v1',
					'/main',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'api_get_main_competition' ),
						'permission_callback' => function () {
							return true;
						},
					)
				);
			}
		);

		// add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ){
		// $wp_rewrite->rules = array_merge(
		// ['events/?$' => 'index.php?compage=events'],
		// $wp_rewrite->rules
		// );
		// $wp_rewrite->rules = array_merge(
		// ['challenge/?$' => 'index.php?compage=challenge'],
		// $wp_rewrite->rules
		// );
		// $wp_rewrite->rules = array_merge(
		// ['connect/?$' => 'index.php?compage=connect'],
		// $wp_rewrite->rules
		// );
		// } );

		// add_filter( 'query_vars', function( $query_vars ) {
		// $query_vars[] = 'events';
		// $query_vars[] = 'challenge';
		// $query_vars[] = 'connect';
		// return $query_vars;
		// } );

		// add_action( 'template_redirect', function() {
		// $page = get_query_var( 'compage' );
		// if ( $page ) {
		// add_filter('the_content', function() {
		// return do_shortcode['competitions_app'];
		// });
		// }
		// } );
	}

	public function api_get_main_competition() {

		$data = array();

		$sections = array(
			'home',
			'events',
			'challenge',
			'connect',
		);

		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], $sections, true ) ) {
			return new \WP_REST_Response( array( 'error' => 'Nothing here' ), 404 );
		}

		$page = sanitize_text_field( $_GET['page'] );

		$terms = get_terms(
			array(
				'taxonomy'   => 'competition',
				'hide_empty' => false,
				'meta_key'   => '_competition_is_main',
				'meta_value' => 'yes',
			)
		);

		if ( empty( $terms ) ) {
			return new \WP_REST_Response( array( 'error' => 'Nothing here' ), 404 );
		}

		$competition = $terms[0];

		$is_public = carbon_get_term_meta( $competition->term_id, 'competition_is_public' );

		// private competition
		if ( ! $is_public && ! is_user_logged_in() ) {
			return new \WP_REST_Response( array( 'error' => 'Forbidden' ), 401 );
		}

		if ( $page === 'events' ) {
			$events = array();

			$cat = ! empty( carbon_get_term_meta( $competition->term_id, 'competition_indico_category' ) ) ? carbon_get_term_meta( $competition->term_id, 'competition_indico_category' ) : 0;

			$data = wp_remote_retrieve_body(
				wp_remote_get( "https://indico.iarai.ac.at/export/categ/$cat.json" )
				// ?occ=yes
			);

			$data = @json_decode( $data, true );

			if ( ! empty( $data['results'] ) ) {
				foreach ( $data['results'] as $event ) {
					$events[] = array(
						'title'       => $event['title'],
						'description' => $event['description'],
						'startDate'   => $event['startDate'],
						'endDate'     => $event['endDate'],
						'location'    => $event['location'],
						'url'         => $event['url'],
					);
				}
			}

			return new \WP_REST_Response( $events, 200 );
		}

		$db_fields = array(
			'home'      => array(
				'competition_main_long_name',
				'competition_logo',
				'competition_main_bg_image',
				'competition_main_short_description',
				'competition_bullets',
				'competition_main_image',
				'competition_main_video',
			),
			'connect'   => array(
				'competition_connect_forum',
				'competition_connect_github',
				'competition_connect_scientific_committee',
				'competition_connect_organising_committee',
				'competition_connect_contact',
				'competition_connect_address',
			),
			'challenge' => array(
				'competition_long_description',
				'competition_secondary_image',
				'competition_data_description',
				'competition_data_image',
				'competition_data_link',
				'competition_data_github',
				'competition_challenges',
			),
		);

		if ( ! empty( $db_fields[ $page ] ) ) {

			if ( is_array( $db_fields[ $page ] ) ) {
				foreach ( $db_fields[ $page ] as $field ) {
					$data[ $field ] = carbon_get_term_meta( $competition->term_id, $field );
				}
			}
		}

		$data['competition_short_name'] = $competition->name;
		$data['competition_id']         = $competition->term_id;

		if ( $page === 'challenge' ) {
			if ( ! empty( $data['competition_challenges'] ) ) {

				foreach ( $data['competition_challenges'] as $k => $challenge ) {

					$data['competition_challenges'][ $k ]['slug'] = sanitize_title_with_dashes( $challenge['name'] );

					if ( ! empty( $challenge['timeline'] ) ) {
						foreach ( $challenge['timeline'] as $j => $timeline ) {

							$label = self::$timeline_types[ $timeline['type'] ];

							if ( in_array( $timeline['type'], self::$timeline_has_custom_text ) ) {
								$label = preg_replace( '/{(?!CompetitionName).*}/i', $timeline['extra_name'], $label );
								unset( $data['competition_challenges'][ $k ]['timeline'][ $j ]['extra_name'] );
							}
							$data['competition_challenges'][ $k ]['timeline'][ $j ]['label'] = $label;
						}
					}

					if ( ! empty( $challenge['competition_leaderboards'] ) ) {
						foreach ( $challenge['competition_leaderboards'] as $i => $leaderboard ) {
							$data['competition_challenges'][ $k ]['competition_leaderboards'][ $i ]['slug'] = sanitize_title_with_dashes( $leaderboard['name'] );
						}
					}
				}
			}

			$data['generalData'] = array(
				'timelineOptions' => self::$timeline_types,
			);
		}

		return new \WP_REST_Response( $data, 200 );
	}

	public function plugins_loaded() {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	public function init() {

		add_shortcode( 'competitions_app', array( $this, 'competitions_app_shortcode' ) );
		add_shortcode( 'iarai_submission_form', array( $this, 'shortcode_submission' ) );
		add_shortcode( 'iarai_leaderboard', array( $this, 'shortcode_leaderboard' ) );
	}

	public function add_tax_col( $new_columns ) {
		$new_columns['tax_id'] = 'ID';

		return $new_columns;
	}

	public function show_tax_id( $value, $name, $id ) {
		return 'tax_id' === $name ? $id : $value;
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

						Field::make( 'checkbox', 'competition_is_public', 'Public competition' )
							 ->set_help_text( 'Is the competition available to the public or just for logged in users' ),

						Field::make( 'text', 'competition_main_long_name', 'Competition long name' )
							 ->set_attribute( 'placeholder', 'Traffic Map Movie Forecasting 2021' )
							 ->set_required( true ),

						Field::make( 'image', 'competition_logo', 'Competition Logo' )
							 ->set_value_type( 'url' )
							 ->set_width( 50 )
							 ->set_required( true ),
						Field::make( 'image', 'competition_main_bg_image', 'Background image' )
							 ->set_value_type( 'url' )
							 ->set_width( 50 )
							 ->set_required( true ),

						Field::make( 'textarea', 'competition_main_short_description', 'Short description' )
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
						Field::make( 'textarea', 'competition_long_description', 'Long description' )
							 ->set_attribute( 'maxLength', 2500 )
							 ->set_help_text( 'Max 2500 characters' ),

						Field::make( 'image', 'competition_secondary_image', 'Secondary image' )
							 ->set_value_type( 'url' ),

						Field::make( 'textarea', 'competition_data_description', 'Data description' )
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

									Field::make( 'textarea', 'description', 'Challenge description' )
										 ->set_attribute( 'maxLength', 1500 )
										 ->set_help_text( 'Max 1500 characters' ),

									Field::make( 'complex', 'timeline', 'Timeline' )
									->setup_labels(
										array(
											'plural_name' => 'Timeline',
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
											Field::make( 'date_time', 'date', 'Date' )
												->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
												->set_picker_options(
													array(
														'time_24hr' => true,
														'enableSeconds' => false,
													)
												),
										)
									)
									  ->set_header_template( ' <%- date ? date : ($_index+1) %>' ),

									Field::make( 'complex', 'prizes', 'Prizes' )
									->setup_labels(
										array(
											'plural_name' => 'Prizes',
											'singular_name' => 'Prize',
										)
									)
										->set_layout( 'tabbed-horizontal' )
										->set_min( 1 )
										->set_max( 3 )
									->add_fields(
										array(
											Field::make( 'textarea', 'prize', 'Prize' )
												 ->set_default_value( 'Voucher or cash prize worth {AMOUNT}EUR to the participant/team and one free {CONFERENCE NAME AND YEAR} conference registration' )
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
											'plural_name' => 'Special Prizes',
											'singular_name' => 'Special Prize',
										)
									)
										->set_layout( 'tabbed-horizontal' )
										->set_min( 1 )
										->set_max( 3 )
									->add_fields(
										array(
											Field::make( 'textarea', 'prize', 'Prize' )
												 ->set_attribute( 'maxLength', 200 )
												 ->set_help_text( 'Max 200 characters' ),
										)
									),

									// special prizes. 1 text field
									Field::make( 'complex', 'awards', 'Winners' )
									->setup_labels(
										array(
											'plural_name' => 'Winners',
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
											'plural_name' => 'SP Winners',
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

									Field::make( 'select', 'enable_leaderboards', 'Leaderboard Visibility' )
									->add_options(
										array(
											'yes'    => 'Public',
											'editor' => 'Just for site Editors and Admins',
											'no'     => 'Closed',
										)
									),
									Field::make( 'text', 'external_submission', 'Submission of other files' )
										->set_help_text( 'Link to submit' ),

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
											'plural_name' => 'Leaderboards',
											'singular_name' => 'Leaderboard',
										)
									)
										->set_layout( 'tabbed-horizontal' )
										->set_min( 1 )
									->add_fields(
										array(

											Field::make( 'text', 'name', 'Name' ),

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

												)
											)
											   ->set_header_template( ' <%- name ? name : ($_index+1) %>' ),

											Field::make( 'select', 'enable_submissions', 'Enable Submissions' )
											->add_options(
												array(
													'yes' => 'Yes',
													'editor' => 'Just for site Editors and Admins',
													'guests' => 'Also for Guests',
													'no'  => 'No',
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
													'yes' => 'Yes',
													'editor' => 'Just for site Editors and Admins',
													'no'  => 'No',
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
											Field::make( 'date', 'competition_start_date', 'Leaderboard Start Date' )
											->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
												->set_picker_options(
													array(
														'time_24hr' => true,
														'enableSeconds' => false,
													)
												),
											Field::make( 'date', 'competition_end_date', 'Leaderboard End Date' )
											->set_input_format( 'Y-m-d H:i', 'Y-m-d H:i' )
												->set_picker_options(
													array(
														'time_24hr' => true,
														'enableSeconds' => false,
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
						Field::make( 'text', 'competition_connect_forum', 'Forum link' ),
						Field::make( 'text', 'competition_connect_contact', 'Contact email' ),
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
											'member'   => 'Member',
											'chair'    => 'Chair',
											'co-chair' => 'Co-Chair',

										)
									),
									Field::make( 'text', 'name', 'Name' ),
									Field::make( 'image', 'image', 'Image' )
											 ->set_value_type( 'url' ),
									Field::make( 'text', 'affiliation', 'Affiliation & Country' ),
									Field::make( 'textarea', 'description', 'Bio' ),
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
											'co-chair' => 'Team',
											'chair'    => 'Core Organizer',
										)
									),
									Field::make( 'text', 'affiliation', 'Affiliation' ),
									Field::make( 'text', 'role_affiliation', 'Role at affiliation' ),
									Field::make( 'text', 'name', 'Name' ),
									Field::make( 'image', 'image', 'Image' )
											 ->set_value_type( 'url' ),
									Field::make( 'textarea', 'description', 'Bio' ),
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

	public function register_meta_boxes() {

		// Moderators
		add_meta_box(
			'submission_files_metabox',
			__( 'Submission info', 'bbpress' ),
			array( $this, 'file_info_metabox' ),
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

		wp_register_style( 'competitions-react', CLEAD_URL . 'lib/react-competitions/build/static/main.css', array(), CLEAD_VERSION, 'all' );
		wp_register_script( 'competitions-react', CLEAD_URL . 'lib/react-competitions/build/static/main.js', array(), CLEAD_VERSION, true );

		wp_localize_script(
			'competitions-react',
			'wpApiSettings',
			array(
				'apiRoot' => esc_url_raw( rest_url() ),
				'appBase' => esc_url_raw( rtrim( is_multisite() ? get_blog_details()->path : '', '/\\' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_register_script( 'iarai-submissions', CLEAD_URL . 'assets/js/submissions.js', array( 'jquery' ), false, true );

		wp_localize_script(
			'iarai-submissions',
			'iaraiSubmissionsParams',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'iarai-submissions-nonce' ),
			)
		);
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
			'not_found_in_trash' => __( 'No submissions found in Trash.', 'competitions-leaderboard' ),
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
			'supports'            => array( 'title', 'author' ),
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

		$labels = array(
			'name'              => _x( 'Challenges', 'taxonomy general name', 'textdomain' ),
			'singular_name'     => _x( 'Challenge', 'taxonomy singular name', 'textdomain' ),
			'search_items'      => __( 'Search Challenges', 'textdomain' ),
			'all_items'         => __( 'All Challenges', 'textdomain' ),
			'parent_item'       => __( 'Parent Challenge', 'textdomain' ),
			'parent_item_colon' => __( 'Parent Challenge', 'textdomain' ),
			'edit_item'         => __( 'Edit Challenge', 'textdomain' ),
			'update_item'       => __( 'Update Challenge', 'textdomain' ),
			'add_new_item'      => __( 'Add New Challenge', 'textdomain' ),
			'new_item_name'     => __( 'New Challenge', 'textdomain' ),
			'menu_name'         => __( 'Challenges', 'textdomain' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => false,
			'show_admin_column' => true,
			'query_var'         => false,
			'show_in_rest'      => false,
			'public'            => false,
			'rewrite'           => false,
		);

		register_taxonomy( 'challenge', array( 'submission' ), $args );

		$labels = array(
			'name'              => _x( 'Leaderboard', 'taxonomy general name', 'textdomain' ),
			'singular_name'     => _x( 'Leaderboard', 'taxonomy singular name', 'textdomain' ),
			'search_items'      => __( 'Search Leaderboards', 'textdomain' ),
			'all_items'         => __( 'All Leaderboards', 'textdomain' ),
			'parent_item'       => __( 'Parent Leaderboard', 'textdomain' ),
			'parent_item_colon' => __( 'Parent Leaderboard', 'textdomain' ),
			'edit_item'         => __( 'Edit Leaderboard', 'textdomain' ),
			'update_item'       => __( 'Update Leaderboard', 'textdomain' ),
			'add_new_item'      => __( 'Add New Leaderboard', 'textdomain' ),
			'new_item_name'     => __( 'New Leaderboard', 'textdomain' ),
			'menu_name'         => __( 'Leaderboards', 'textdomain' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => false,
			'show_admin_column' => true,
			'query_var'         => false,
			'show_in_rest'      => false,
			'public'            => false,
			'rewrite'           => false,
		);

		register_taxonomy( 'leaderboard', array( 'submission' ), $args );
	}

	/**
	 * @param false $category
	 */
	public function competition_display_meta( $category = false ) {

		$description = '';
		if ( is_object( $category ) ) {
			$description = html_entity_decode( stripcslashes( $category->description ) );
		}

		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="cat_description"><?php esc_html_e( 'Description', 'competitions-leaderboard' ); ?></label>
			</th>
			<td>
				<div class="form-field term-meta-wrap">
					<?php

					$settings = array(
						'wpautop'       => true,
						'media_buttons' => true,
						'quicktags'     => true,
						'textarea_rows' => '15',
						'textarea_name' => 'description',
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
		if ( $current_screen->id === 'edit-competition' ) {
			?>
			<script type="text/javascript">
				jQuery(function ($) {
					$('textarea#tag-description, textarea#description').closest('.form-field').remove();
				})

			</script>
			<?php
		}
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

	public function competitions_app_shortcode( $atts = array() ) {
		add_action(
			'wp_footer',
			function () {
				wp_enqueue_script( 'competitions-react' );
				wp_print_styles( array( 'competitions-react' ) );
			}
		);

		return '<div id="competitions-wrap"></div>';

	}

	public function shortcode_submission( $atts = array() ) {
		extract(
			shortcode_atts(
				array(
					'competition' => '',
				),
				$atts
			)
		);

		$competition       = ! empty( $competition ) ? $competition : get_queried_object_id();
		$submission_option = get_term_meta( $competition, '_enable_submissions', true );

		if ( ! is_user_logged_in() && $submission_option !== 'guests' ) {
			echo '<p class="alert alert-warning submissions-no-user">Please ' .
				 '<a class="kleo-show-login" href="' . wp_login_url() . '">login</a> to submit data.</p>';

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
			<td><?php echo get_the_date( 'M j, Y H:i', $submission->ID ); ?></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param null   $competition
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
			$author_query = " AND {$wpdb->prefix}posts.post_author IN (" . get_current_user_id() . ')';
		}

		$search_query = '';
		if ( ! empty( $search_term ) ) {
			$search_query = ' AND (' .
							"(mt1.meta_key = '_submission_notes' AND mt1.meta_value LIKE '%%%s%%')" .
							"OR {$wpdb->prefix}posts.post_title LIKE '%%%s%%'" .
							')';
		}

		$submissions_query = "SELECT $wpdb->posts.* FROM $wpdb->posts" .
							 " LEFT JOIN {$wpdb->prefix}term_relationships ON ({$wpdb->posts}.ID = {$wpdb->prefix}term_relationships.object_id)" .
							 " INNER JOIN {$wpdb->prefix}postmeta ON ( {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id )" .
							 " INNER JOIN {$wpdb->prefix}postmeta AS mt1 ON ( {$wpdb->prefix}posts.ID = mt1.post_id )" .
							 ' WHERE' .
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
				$wpdb->esc_like( $search_term ),
				$wpdb->esc_like( $search_term )
			);
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
				wp_send_json_success( array( 'results' => $result ) );
				exit;
			}
		}
		wp_send_json_success( array( 'results' => false ) );
		exit;
	}

	public function shortcode_leaderboard( $atts = array() ) {
		extract(
			shortcode_atts(
				array(
					'competition' => '',
				),
				$atts
			)
		);

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

		add_action(
			'admin_head-edit.php',
			function () {
				global $current_screen;

				?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					jQuery(jQuery(".wrap h1")[0]).append("<a onclick=\"window.location='" + window.location.href + "&export-csv'\" id='iarai-export-csv' class='add-new-h2'>Export CSV</a>");
				});
			</script>
				<?php
			}
		);

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

		$args = array(
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'post_type'      => 'submission',
		);

		$submissions = get_posts( $args );

		foreach ( $submissions as $k => $submission ) {

			$terms            = get_the_terms( $submission->ID, 'team' );
			$competitions     = get_the_terms( $submission->ID, 'competition' );
			$terms_text       = '';
			$competition_text = '';
			$competition_id   = '';

			if ( $terms && ! is_wp_error( $terms ) ) {

				$terms_arr = array();

				foreach ( $terms as $term ) {
					$terms_arr[] = $term->name;
				}

				$terms_text = join( ', ', $terms_arr );
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
				get_the_date( 'Y-m-d H:i', $submission->ID ),
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
