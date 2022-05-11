<?php
/*
Plugin Name: Competitions & Leaderboard V2.0
Description: Add competitions, submissions and leaderboard
Version: 2.1.0
Text Domain: competitions-leaderboard
*/

// Define Constants

if ( ! defined( 'CLEAD_VERSION_2' ) ) {
	define( 'CLEAD_VERSION_2', '2.1.0' );
}

// Plugin Folder Path
if ( ! defined( 'CLEAD_PATH_2' ) ) {
	define( 'CLEAD_PATH_2', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL
if ( ! defined( 'CLEAD_URL_2' ) ) {
	define( 'CLEAD_URL_2', plugin_dir_url( __FILE__ ) );
}

// Plugin Root File
if ( ! defined( 'CLEAD_FILE_2' ) ) {
	define( 'CLEAD_FILE_2', __FILE__ );
}
require CLEAD_PATH_2 . 'vendor/autoload.php';

if ( ! isset( $GLOBALS['wp_logs'] ) && ! class_exists( '\IARAI\Logging' ) ) {
	require_once CLEAD_PATH_2 . 'lib/Logging.php';
}

require_once CLEAD_PATH_2 . 'inc/Plugin.php';
\CLead2\Plugin::instance();
