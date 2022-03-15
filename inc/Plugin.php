<?php

namespace CLead;

class Plugin {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	public function __construct() {

		new Options();
		new Crons();
		new Terms();
		new Submissions();

		add_action( 'init', array( $this, 'init' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_admin_css' ) );

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
				register_rest_route(
					'competition/v1',
					'/past',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'api_get_past_competitions' ),
						'permission_callback' => function () {
							return true;
						},
					)
				);
			}
		);

		add_filter(
			'generate_rewrite_rules',
			function ( $wp_rewrite ) {
				$wp_rewrite->rules = array_merge(
					array( 'events/?$' => 'index.php?compzone=events' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'challenge/?$' => 'index.php?compzone=challenge' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'connect/?$' => 'index.php?compzone=connect' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'organising-committee/?$' => 'index.php?compzone=orgcommittee' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'scientific-committee/?$' => 'index.php?compzone=scicommittee' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'contact/?$' => 'index.php?compzone=contact' ),
					$wp_rewrite->rules
				);
				$wp_rewrite->rules = array_merge(
					array( 'submit/?$' => 'index.php?compzone=submit' ),
					$wp_rewrite->rules
				);

				// Past competitions home page
				$wp_rewrite->rules = array_merge(
					array( 'competition\/([a-z0-9_-]+)\/?$' => 'index.php?compage=competition&compslug=$matches[1]' ),
					$wp_rewrite->rules
				);

				// Past competitions other pages
				$wp_rewrite->rules = array_merge(
					array( 'competition\/([a-z0-9_-]+)\/([a-z0-9_-]+)\/?$' => 'index.php?compage=competition&compslug=$matches[1]&compzone=$matches[2]' ),
					$wp_rewrite->rules
				);
			}
		);

		add_filter(
			'query_vars',
			function( $query_vars ) {
				$query_vars[] = 'compage';
				$query_vars[] = 'compslug';
				$query_vars[] = 'compzone';
				return $query_vars;
			}
		);

		/**
		 * Replace with competition template
		*/
		add_action(
			'template_redirect',
			function() {
				$competition      = get_query_var( 'compage' );
				$competition_zone = get_query_var( 'compzone' );

				global $post;
				$shortcode_on_page = ! empty( $post ) && has_shortcode( $post->post_content, 'competitions_app' );

				if ( $competition_zone || $competition || $shortcode_on_page ) {
					$file = CLEAD_PATH . 'templates/competition-page.php';
					if ( file_exists( $file ) ) {
						include $file;
						exit;
					}
				}
			}
		);

		add_action( 'wp', array( $this, 'general_layout_changes' ) );
	}

	public function general_layout_changes() {

		if ( ! is_multisite() ) {
			return;
		}

		// Replace header with main site header
		add_action( 'kleo_header', array( $this, 'main_site_header' ), 9 );

		// set logo back to regular one
		remove_all_filters( 'kleo_logo_href', 10 );

	}

	/**
	 * Get main site header and put it above this subsite header
	 *
	 * @return void
	 */
	public function main_site_header() {

		// delete_transient( 'main_page_header' );

		$replace_class    = 'alternate-color competition-main-site';
		$competition_zone = get_query_var( 'compzone' );

		// if main site.
		if ( trailingslashit( network_site_url() ) === trailingslashit( home_url() ) ) {
			return;
		}

		if ( $header = get_transient( 'main_page_header' ) ) {

			// disable sticky.
			if ( ! empty( $competition_zone ) ) {
				$header = str_replace( $replace_class, $replace_class . ' disable-sticky', $header );

				// Prevent duplicate header id
				$header = str_replace( 'id="header"', 'id="header-main-site"', $header );
			}

			echo $header;
			return;
		}

		$request = wp_remote_get( network_site_url() );
		$html    = wp_remote_retrieve_body( $request );

		if ( empty( $html ) ) {
			return;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );

		$dom->loadHTML( $html );

		$xpath = new \DOMXPath( $dom );

		$div = $xpath->query( '//*[@id="header"]' );

		$div = $div->item( 0 );
		$div = $dom->saveHTML( $div );

		$div = str_replace( 'class="header-color"', 'class="' . $replace_class . '"', $div );

		set_transient( 'main_page_header', $div, 60 * 60 );

		// disable sticky.
		if ( ! empty( $competition_zone ) ) {
			$div = str_replace( $replace_class, $replace_class . ' disable-sticky', $div );

			// Prevent duplicate header id
			$div = str_replace( 'id="header"', 'id="header-main-site"', $div );
		}

		echo $div;
	}

	/**
	 * Get all previous competitions data.
	 *
	 * @return \WP_REST_Response;
	 */
	public function api_get_past_competitions() {

		$terms = get_terms(
			array(
				'taxonomy'   => 'competition',
				'hide_empty' => false,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '_competition_is_main',
						'value'   => 'yes',
						'compare' => '!=',
					),
					array(
						'key'     => '_competition_is_main',
						'compare' => 'NOT EXISTS',
					),

				),
			)
		);

		if ( empty( $terms ) ) {
			return new \WP_REST_Response( array(), 200 );
		}

		$data = array();

		foreach ( $terms as $term ) {
			$link = 'competition/' . $term->slug;

			$v2       = carbon_get_term_meta( $term->term_id, 'competition_is_v2' );
			$old_link = carbon_get_term_meta( $term->term_id, 'competition_old_link' );

			if ( $v2 && $old_link ) {
				$link = $old_link;
			}

			$data[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'link' => $link,
			);
		}

		return new \WP_REST_Response( $data, 200 );

	}

	/**
	 * Get main competition data
	 *
	 * @return \WP_REST_Response
	 */
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

		$args = array(
			'taxonomy'   => 'competition',
			'hide_empty' => false,
			'meta_key'   => '_competition_is_main',
			'meta_value' => 'yes',
		);

		if ( isset( $_GET['competition'] ) && ! empty( $_GET['competition'] ) ) {
			$current_competition = sanitize_text_field( $_GET['competition'] );
			unset( $args['meta_key'] );
			unset( $args['meta_value'] );
		}

		$terms = get_terms( $args );

		if ( empty( $terms ) ) {
			return new \WP_REST_Response( array( 'error' => 'Nothing here' ), 404 );
		}

		if ( isset( $current_competition ) ) {
			foreach ( $terms as $term ) {
				if ( $term->slug === $current_competition ) {
					$competition = $term;
					break;
				}
			}
		} else {
			$competition = $terms[0];
		}

		$is_public = carbon_get_term_meta( $competition->term_id, 'competition_is_public' );

		// private competition.
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

					$path = sanitize_title_with_dashes( $challenge['name'] );
					if ( isset( $challenge['path'] ) && ! empty( $challenge['path'] ) ) {
						$path = $challenge['path'];
					}

					$data['competition_challenges'][ $k ]['slug'] = $path;

					if ( ! empty( $challenge['timeline'] ) ) {
						foreach ( $challenge['timeline'] as $j => $timeline ) {

							$label = Options::$timeline_types[ $timeline['type'] ];

							if ( in_array( $timeline['type'], Options::$timeline_has_custom_text ) ) {
								$label = preg_replace( '/{(?!CompetitionName).*}/i', $timeline['extra_name'], $label );
								unset( $data['competition_challenges'][ $k ]['timeline'][ $j ]['extra_name'] );
							}
							$data['competition_challenges'][ $k ]['timeline'][ $j ]['label'] = $label;
						}
					}

					if ( ! empty( $challenge['competition_leaderboards'] ) ) {
						foreach ( $challenge['competition_leaderboards'] as $i => $leaderboard ) {

							$path2 = sanitize_title_with_dashes( $leaderboard['name'] );
							if ( isset( $leaderboard['path'] ) && ! empty( $leaderboard['path'] ) ) {
								$path2 = $leaderboard['path'];
							}
							$data['competition_challenges'][ $k ]['competition_leaderboards'][ $i ]['slug'] = $path2;
						}
					}
				}
			}

			$data['generalData'] = array(
				'timelineOptions' => Options::$timeline_types,
			);
		}

		return new \WP_REST_Response( $data, 200 );
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

	public function register_meta_boxes() {

		// Moderators
		add_meta_box(
			'submission_files_metabox',
			__( 'Submission info', 'competitions-leaderboard' ),
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

		$score_full = self::get_score_number_full( $post->ID );
		if ( ! $score_full ) {
			$score_full = 'To be calculated';
		}

		echo '<p><strong>Score full:</strong><br>' . implode( '<br>', $score_full ) . '<br>';

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

	public function custom_admin_css() {
		wp_enqueue_style( 'admin-styles', CLEAD_URL . 'assets/css/admin.css' );
	}


	public function enqueue_scripts() {

		// general styles
		wp_enqueue_style( 'competitions', CLEAD_URL . 'assets/css/competition.css', array(), CLEAD_VERSION, 'all' );

		wp_register_style( 'competitions-react', CLEAD_URL . 'lib/react-competitions/build/static/main.css', array(), CLEAD_VERSION, 'all' );
		wp_register_script( 'competitions-react', CLEAD_URL . 'lib/react-competitions/build/static/main.js', array(), CLEAD_VERSION, true );

		$submit_nonce     = wp_create_nonce( 'iarai-submissions-nonce' );
		$competition_slug = get_query_var( 'compslug' );
		$competition_zone = get_query_var( 'compzone' );

		$localize_data = array(
			'apiRoot'     => esc_url_raw( rest_url() ),
			'appBase'     => esc_url_raw( rtrim( is_multisite() ? get_blog_details()->path : '', '/\\' ) ),
			'appPath'     => esc_url_raw( rtrim( is_multisite() ? get_blog_details()->path : '', '/\\' ) ),
			'appRoute'    => '/',
			'pluginBase'  => CLEAD_URL . 'lib/react-competitions/public',
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'nonceSubmit' => $submit_nonce,
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'loggedIn'    => is_user_logged_in(),
		);
		if ( ! empty( $competition_slug ) ) {
			$localize_data['appPath'] .= "/competition/$competition_slug";
		}
		if ( ! empty( $competition_zone ) ) {
			$localize_data['appRoute'] .= $competition_zone;
		}

		wp_localize_script(
			'competitions-react',
			'wpApiSettings',
			$localize_data
		);

		wp_register_script( 'iarai-submissions', CLEAD_URL . 'assets/js/submissions.js', array( 'jquery' ), false, true );

		wp_localize_script(
			'iarai-submissions',
			'iaraiSubmissionsParams',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => $submit_nonce,
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

	static function get_log_path( $id ) {
		$file_path = get_post_meta( $id, '_submission_file_path', true );

		if ( $file_path ) {
			$path_parts = pathinfo( $file_path );
			if ( ! isset( $path_parts['dirname'] ) ) {
				return false;
			}

			$log_path = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.log';

			if ( file_exists( $log_path ) ) {
				return $log_path;
			}
		}

		return false;
	}

	public static function get_log_url( $id ) {
		$log_path = self::get_log_path( $id );
		if ( ! $log_path ) {
			return false;
		}

		$arr = explode( 'wp-content', $log_path );
		return home_url( 'wp-content' . $arr[1] );
	}

	public static function get_log_content( $id ) {

		$log_path = self::get_log_path( $id );
		if ( ! $log_path ) {
			return false;
		}

		return file_get_contents( $log_path );
	}

	static function get_leadearboard_by_submission_id( $submission_id ) {
		$competition_term = get_the_terms( $submission_id, 'competition' );

		$challenge_term   = get_the_terms( $submission_id, 'challenge' );
		$leaderboard_term = get_the_terms( $submission_id, 'leaderboard' );

		if ( ! $competition_term || ! $leaderboard_term || ! $challenge_term ) {
			return false;
		}

		$challenge_slug   = str_replace( $competition_term[0]->term_id . '-', '', $challenge_term[0]->name );
		$leaderboard_slug = str_replace( $competition_term[0]->term_id . '-', '', $leaderboard_term[0]->name );

		if ( ! $challenge_slug || ! $leaderboard_slug ) {
			return false;
		}

		return self::get_leaderboard_settings_by_slug( $competition_term[0]->term_id, $challenge_slug, $leaderboard_slug );
	}

	public static function get_leaderboard_settings_by_slug( $competition_id, $challenge_slug, $leaderboard_slug ) {
		$competitions = carbon_get_term_meta( $competition_id, 'competition_challenges' );

		if ( ! $competitions ) {
			return false;
		}

		foreach ( $competitions as $competition ) {

			$competition_path = sanitize_title( $competition['name'] );
			if ( isset( $competition['path'] ) && ! empty( $competition['path'] ) ) {
				$competition_path = $competition['path'];
			}

			if ( $competition_path === $challenge_slug ) {

				if ( ! empty( $competition['competition_leaderboards'] ) ) {

					foreach ( $competition['competition_leaderboards'] as $leaderboard ) {

						$lb_path = sanitize_title( $leaderboard['name'] );
						if ( isset( $leaderboard['path'] ) && ! empty( $leaderboard['path'] ) ) {
							$lb_path = $leaderboard['path'];
						}

						if ( $lb_path === $leaderboard_slug ) {

							return $leaderboard;
						}
					}
				}
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

	static function get_score_number_full( $post_id ) {

		if ( $score = get_post_meta( $post_id, '_score_full', true ) ) {
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
	public static function query_leaderboard( $competition = null, $search_term = '', $sort_order = 'ASC', $user = null, $challenge_slug = null, $leaderboard_slug = null ) {

		global $wpdb;

		if ( empty( $competition ) ) {
			return false;
		}

		$author_query = '';

		if ( isset( $user ) ) {
			$author_query = " AND {$wpdb->prefix}posts.post_author IN (" . $user . ')';
		}

		$terms_query = " {$wpdb->prefix}term_relationships.term_taxonomy_id IN ($competition)";

		// Get from leaderbord, then challenge. we need just one term, not all in the logically
		if ( ! empty( $leaderboard_slug ) ) {

			$leaderboard = get_term_by( 'slug', "$competition-$leaderboard_slug", 'leaderboard' );
			$terms_query = " {$wpdb->prefix}term_relationships.term_taxonomy_id IN ($leaderboard->term_id)";
		} elseif ( ! empty( $challenge_slug ) ) {

			$challenge = get_term_by( 'slug', "$competition-$challenge_slug", 'challenge' );
			if ( ! empty( $challenge ) ) {
				$terms_query = " {$wpdb->prefix}term_relationships.term_taxonomy_id IN ($challenge->term_id)";
			}
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
							 $terms_query .
							 $author_query .
							// " AND ( {$wpdb->prefix}postmeta.meta_key = '_score' AND {$wpdb->prefix}postmeta.meta_value > '0' )" .
							 $search_query .
							 " AND {$wpdb->prefix}posts.post_type = 'submission'" .
							 " AND {$wpdb->prefix}posts.post_status = 'publish'" .
							 " GROUP BY {$wpdb->prefix}posts.ID";
							// " ORDER BY {$wpdb->prefix}postmeta.meta_value+0 " . $sort_order;

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

			$competition  = (int) $_POST['competition'];
			$current_user = null;

			if ( isset( $_POST['current_user'] ) && (int) $_POST['current_user'] !== 0 && is_user_logged_in() ) {
				$current_user = get_current_user_id();
			}

			// Submissions.
			$search_term = sanitize_text_field( $_POST['term'] );

			// v2 React.
			if ( isset( $_POST['challenge'], $_POST['leaderboard'] ) ) {

				$challenge   = sanitize_text_field( $_POST['challenge'] );
				$leaderboard = sanitize_text_field( $_POST['leaderboard'] );

				$submissions = self::query_leaderboard( $competition, $search_term, 'ASC', $current_user, $challenge, $leaderboard );

				if ( empty( $submissions ) ) {
					wp_send_json_success( array( 'results' => array() ), 200 );
					exit;
				}

				$leaderboard_settings = self::get_leaderboard_settings_by_slug( $competition, $challenge, $leaderboard );
				$result               = array();

				foreach ( $submissions as $submission ) {

					$user_id         = (int) $submission->post_author;
					$user            = get_user_by( 'id', $user_id );
					$name            = $user->display_name;
					$team            = wp_get_post_terms( $submission->ID, 'team' );
					$log             = ( self::get_log_url( $submission->ID ) ? self::get_log_url( $submission->ID ) : '' );
					$notes           = get_post_meta( $submission->ID, '_submission_notes', true ) ?? '';
					$is_current_user = ( is_user_logged_in() && $user_id === get_current_user_id() ) ? true : false;

					if ( $team && ! empty( $team ) ) {
						$name = $team[0]->name . ' - ' . $name;
					}

					$result[] = array(
						'name'            => $submission->post_name,
						'id'              => $submission->ID,
						'team'            => $name,
						'score'           => self::get_score_number( $submission->ID ),
						'extraScores'     => Submissions::get_score_lines( $submission->ID, $leaderboard_settings ),
						'date'            => get_the_date( 'Y-m-d H:i', $submission->ID ),
						'log'             => isset( $user ) ? $log : '',
						'notes'           => isset( $user ) ? $notes : '',
						'is_current_user' => $is_current_user,
					);
				}

				wp_send_json_success( array( 'results' => $result ), 200 );
				exit;

			}

			$submissions = self::query_leaderboard( $competition, $search_term, 'ASC', $current_user );

			// Old style leaderboard.
			$result = '';
			foreach ( $submissions as $submission ) {
				$result .= self::get_leaderboard_row( $submission, $competition );
			}
			wp_send_json_success( array( 'results' => $result ) );
			exit;
		}

		wp_send_json_error( array( 'results' => false ), 401 );
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
