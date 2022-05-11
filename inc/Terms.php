<?php
namespace CLead2;

use IARAI\Logging;

class Terms {

	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	public function __construct() {

		add_action( 'init', [ $this, 'set_cookie' ] );
		add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		add_action( 'admin_init', [ $this, 'page_init' ] );
		add_action( 'wp_ajax_iarai_save_user_terms', [ $this, 'ajax_terms_save' ] );
		add_action( 'wp_footer', [ $this, 'check_terms_version' ], 9999 );
		add_action( 'signup_extra_fields', [ $this, 'register_terms_field' ], 12 );
		add_action( 'wpmu_activate_user', [ $this, 'on_user_activate' ], 12, 3 );

		add_filter( 'manage_users_columns', [ $this, 'modify_user_table' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'modify_user_table_row' ], 10, 3 );
		add_filter( 'manage_users_sortable_columns', [ $this, 'terms_sortable_user_table' ] );
	}

	static function get_option( $name, $default = false ) {
		if ( ! isset( $name ) ) {
			return false;
		}

		$options = get_option( 'terms_option' );

		if ( isset( $options[ $name ] ) ) {
			return $options[ $name ];
		}

		return $default;

	}


	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			'Terms Settings',
			'Terms Settings',
			'manage_options',
			'terms-settings',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		// Set class property
		$this->options = get_option( 'terms_option' );
		?>
		<div class="wrap">
			<h1>Terms Settings</h1>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( 'terms_option_group' );
				do_settings_sections( 'terms-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'terms_option_group', // Option group
			'terms_option', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

        
		add_settings_section(
			'content_setting_section', // ID
			'Terms Page Settings', // Title
			null, // Callback
			'terms-settings' // Page
		);

		add_settings_field(
			'terms_page', // ID
			'Terms and conditions page', // Title
			array( $this, 'terms_callback' ), // Callback
			'terms-settings', // Page
			'content_setting_section' // Section
		);
		add_settings_field(
			'terms_version', // ID
			'Terms and conditions version', // Title
			array( $this, 'terms_version_callback' ), // Callback
			'terms-settings', // Page
			'content_setting_section' // Section
		);
		add_settings_field(
			'terms_text', // ID
			'Updated terms modal text', // Title
			array( $this, 'terms_text_callback' ), // Callback
			'terms-settings', // Page
			'content_setting_section' // Section
		);

	
	}

	public function sanitize( $input ) {
		$new_input = array();

		if ( isset( $input['terms_page'] ) ) {
			$new_input['terms_page'] = absint( $input['terms_page'] );
		}

		if ( isset( $input['terms_version'] ) ) {
			$new_input['terms_version'] = esc_html( $input['terms_version'] );
		}

		if ( isset( $input['terms_text'] ) ) {
			$new_input['terms_text'] = esc_html( $input['terms_text'] );
		}

		return $new_input;
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function terms_callback() {

		$pages = get_pages( 'sort_column=menu_order' );

		echo '<select id="redirect_page" name="terms_option[terms_page]">';
		echo '<option value="">' . __( '-- Choose page --', 'terms-dev' ) . '</option>';
		if ( $pages != null ) {
			foreach ( $pages as $page ) {
                $selected = isset($this->options['terms_page'] ) ? selected( esc_attr( $this->options['terms_page'] ), esc_attr( $page->ID ), false ) : '';

				echo '<option ' . $selected . ' value="' . esc_attr( $page->ID ) . '">' . esc_html( $page->post_title ) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function terms_version_callback() {

		echo '<input type="text" id="terms_version" name="terms_option[terms_version]" value="' . esc_attr( isset( $this->options['terms_version'] ) ? $this->options['terms_version'] : '' ) . '">';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function terms_text_callback() {
		$settings = array(
			'wpautop'       => true,
			'media_buttons' => false,
			'textarea_name' => 'terms_option[terms_text]',
			'textarea_rows' => get_option( 'default_post_edit_rows', 10 ),
			'tabindex'      => '',
			'editor_css'    => '',
			'editor_class'  => '',
			'teeny'         => true,
			//'dfw' => true,
			'tinymce'       => array(
				'theme_advanced_buttons1' => 'bold,italic,underline'
			),
			//'quicktags' => false
		);

		$terms_page  = self::get_option( 'terms_page' );
		$default     = '<h4>Welcome back!<br>We have updated our Terms and Conditions</h4>' .
		               'By pressing the Accept button below you accept the updated ' .
		               '<a target="_blank" href="' . esc_url( get_permalink( $terms_page ) ) . '">' .
		               'Terms and Conditions</a>.';
		$editor_text = html_entity_decode( stripcslashes( isset( $this->options['terms_text'] ) ? $this->options['terms_text'] : $default ) );

		wp_editor( $editor_text, 'terms_text', $settings );
		// echo '<textarea id="terms_text" name="terms_option[terms_text]">' . isset( $this->options['terms_text'] ) ? $this->options['terms_text'] : '' . '</textarea>';
	}

	public function ajax_terms_save() {

		//check_ajax_referer( 'iarai_terms_nonce', 'security' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			echo wp_json_encode( [ 'success' => false ] );
			exit;
		}
		$terms_version = self::get_option( 'terms_version' );

		self::save_user_terms( $terms_version, $user_id );

		echo wp_json_encode( [ 'success' => true ] );

		exit;
	}

	public static function save_user_terms( $user_terms_version, $user_id = null ) {

		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$current_meta = get_user_meta( $user_id, 'terms_version', true );

		if ( ! empty( $current_meta ) ) {
			if ( ! is_array( $current_meta ) ) {
				$new_meta[2] = $current_meta;
			} else {
				$new_meta = $current_meta;
			}
		} else {
			$new_meta = [];
		}

		$new_meta[ get_current_blog_id() ] = $user_terms_version;

		update_user_meta( $user_id, 'terms_version', $new_meta );

		//keep log for date-time
		$log = get_user_meta( $user_id, 'terms_version_log', true );

		if ( empty( $log ) ) {
			$log = [];
		}

		if ( ! isset( $log[ get_current_blog_id() ] ) ) {
			$log[ get_current_blog_id() ] = date( "Y-m-d H:i" );
			update_user_meta( $user_id, 'terms_version_log', $log );
		}
	}

	/**
	 * Set a cookie to remember last page before register
	 */
	public function set_cookie() {

		if ( is_user_logged_in() && isset( $_COOKIE['terms_accepted'] ) ) {

			$user_terms_version = esc_attr( $_COOKIE['terms_accepted'] );
			$user_id            = get_current_user_id();

			self::save_user_terms( $user_terms_version, $user_id );
			setcookie( 'terms_accepted', '', time() - 3600, '/' );

			//log error
			$title  = 'Terms & Conditions Pop-up AJAX failed request';
			$parent = 0;
			$type   = 'error';

			$user    = wp_get_current_user();
			$message = 'User preference was saved, this is just info for extra debugging<br>';
			$message .= 'Terms version: ' . $user_terms_version . '<br>';
			$message .= 'Username: ' . $user->user_login . '<br>';
			$message .= 'IP: ' . $_SERVER['REMOTE_ADDR'] . '<br>';
			$message .= 'Browser data: ' . $_SERVER['HTTP_USER_AGENT'] . '<br>';


			Logging::add( $title, $message, $parent, $type );
		}

	}



	/**
	 * Adds code to page for re-accepting terms and conditions pop-up
	 */
	public function check_terms_version() {

		/* Just for logged-in users */
		if ( ! is_user_logged_in() ) {
			return;
		}

		$terms_page = self::get_option( 'terms_page' );

		if ( ! $terms_page ) {
			return;
		}

		/* Bail out if is the terms and conditions page */
		if ( is_page( $terms_page ) ) {
			return;
		}

		$terms_version = self::get_option( 'terms_version' );
		if ( ! $terms_version ) {
			return;
		}

		$user_id            = get_current_user_id();
		$user_terms_version = self::get_user_terms( $user_id );

		// show terms modal again to accept terms
		if ( ! $user_terms_version || empty( $user_terms_version ) || version_compare( $user_terms_version, $terms_version, '<' ) ) {

			$terms_text = self::get_option( 'terms_text' );
			if ( ! $terms_text ) {
				$terms_text = '<h4>Welcome back!<br>We have updated our Terms and Conditions</h4>' .
				              'By pressing the Accept button below you accept the updated ' .
				              '<a target="_blank" href="' . esc_url( get_permalink( $terms_page ) ) . '">' .
				              'Terms and Conditions</a>.';
			} else {
				$terms_text = str_replace( "\n", '', html_entity_decode( wpautop( $terms_text ) ) );
			}

			?>
            <span class="element-with-popup"></span>
            <div style="display: none;">
                <div id="terms-modal-content">
                    <div class="terms-modal main-color text-center">
                        <div class="container">
                            <div class="row">
								<?php echo $terms_text; ?><br><br>
                                <a href="#" class="accept-terms-modal btn btn-primary">Accept</a>
                            </div>
                        </div>
                    </div>
                    <input class="iarai-terms-nonce" name="security_check" type="hidden"
                           value="<?php echo wp_create_nonce( 'iarai_terms_nonce' ); ?>">
                </div>
            </div>

            <style>
                .terms-modal {
                    max-width: 400px;
                    margin: 0 auto;
                    padding: 20px;
                    -webkit-border-radius: 2px;
                    -moz-border-radius: 2px;
                    border-radius: 2px;
                }

                .terms-modal h4 {
                    font-size: 20px;
                    line-height: 1.4;
                }
            </style>
            <script>
                (function ($) {
                    $('body').on('click', '.accept-terms-modal', function () {
                        var _this = $(this);

                        var data = {
                            'action': 'iarai_save_user_terms',
                            'security': $('.iarai-terms-nonce').val()
                        };

                        // save user terms version
                        $.ajax({
                            url: kleoFramework.ajaxurl, // AJAX handler
                            data: data,
                            type: 'POST',
                            beforeSend: function (xhr) {
                                _this.text('Please wait...');
                            },
                            success: function () {
                                $('.element-with-popup').magnificPopup('close');
                            },
                            error: function (xhr, status, error) {
                                var d = new Date();
                                d.setTime(d.getTime() + (30 * 24 * 60 * 60 * 1000));
                                var expires = "expires=" + d.toUTCString();
                                document.cookie = "terms_accepted=<?php echo $terms_version;?>; " + expires + "; path=/";
                                $('.element-with-popup').magnificPopup('close');
                            },
                        });

                        return false;
                    });

                    $('.element-with-popup').magnificPopup({
                        closeOnContentClick: false,
                        closeOnBgClick: false,
                        closeBtnInside: false,
                        showCloseBtn: false,
                        enableEscapeKey: false,

                        items: {
                            src: '#terms-modal-content',
                            type: 'inline'
                        }
                    }).magnificPopup('open');
                })(jQuery)

            </script>
			<?php
		}
	}

	public static function get_user_terms( $user_id = null ) {

		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$current_meta = get_user_meta( $user_id, 'terms_version', true );

		if ( ! is_array( $current_meta ) ) {
			if ( get_current_blog_id() === 2 ) {
				return $current_meta;
			}

			return false;
		}

		return $current_meta[ get_current_blog_id() ] ?? false;

	}

	/**
	 * Signup extra fields
	 * @param $errors
	 */
	public function register_terms_field( $errors ) {

		$terms_page = self::get_option( 'terms_page' );

		if ( ! $terms_page ) {
			return;
		}

		?>
        <label for="user_terms">
            <input type="checkbox" id="user_terms" value="1"/>&nbsp;
			<?php echo sprintf( __( 'By checking this box you agree to the <a target="_blank" href="%s">terms and conditions</a>', 'terms-dev' ),
				get_permalink( $terms_page ) ); ?>
        </label>
        <script>
            document.getElementById('setupform').addEventListener('submit', function (event) {
                if (document.getElementById('user_terms').checked == false) {
                    event.preventDefault();
                    alert("By signing up, you must agree to our terms and conditions!");
                    return false;
                }
            });
        </script>
		<?php
	}

	function modify_user_table( $column ) {

        $column['terms'] = 'Terms v.';

		return $column;
	}

	function modify_user_table_row( $val, $column_name, $user_id ) {
		switch ( $column_name ) {
			case 'terms' :
                return Terms::get_user_terms( $user_id );
				break;
			default:
		}

		return $val;
	}

	public function terms_sortable_user_table( $columns ) {
        $columns['registered'] = 'registered';

		return $columns;
	}

	public function on_user_activate( $user_id, $password, $meta ) {

		/* Set terms version */
        $terms_version = self::get_option( 'terms_version' );
        self::save_user_terms( $terms_version, $user_id );

	}

}