<?php
/*
Plugin Name: Competitions & Leaderboard V2.0
Description: Add competitions, submissions and leaderboard
Version: 2.0.0
Text Domain: competitions-leaderboard
*/

// Define Constants

if ( ! defined( 'CLEAD_VERSION' ) ) {
	define( 'CLEAD_VERSION', '2.0.0' );
}

// Plugin Folder Path
if ( ! defined( 'CLEAD_PATH' ) ) {
	define( 'CLEAD_PATH', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL
if ( ! defined( 'CLEAD_URL' ) ) {
	define( 'CLEAD_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin Root File
if ( ! defined( 'CLEAD_FILE' ) ) {
	define( 'CLEAD_FILE', __FILE__ );
}
require CLEAD_PATH . 'vendor/autoload.php';

if ( ! isset( $GLOBALS['wp_logs'] ) && ! class_exists( '\IARAI\Logging' ) ) {
	require_once CLEAD_PATH . 'lib/Logging.php';
}

require_once CLEAD_PATH . 'inc/Plugin.php';
new \Clead\Plugin();
