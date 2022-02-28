<?php
/**
 * Template Name: Competition page
 * 
 * @package WordPress
 * @subpackage Kleo
 * @author SeventhQueen <themesupport@seventhqueen.com>
 * @since Kleo 1.0
 */

$competition_name = '';
$competition_slug = get_query_var( 'compslug' );

if ( ! empty( $competition_slug ) && get_term_by( 'slug', $competition_slug, 'competition' ) ) {
	$competition_name = get_term_by( 'slug', $competition_slug, 'competition' )->name;
} else {
	$args = array(
		'taxonomy'   => 'competition',
		'hide_empty' => false,
		'meta_key'   => '_competition_is_main',
		'meta_value' => 'yes',
	);

	$terms = get_terms( $args );

	if ( !  empty( $terms ) ) {
		$competition_name = $terms[0]->name;
	}
}

$zone = get_query_var( 'compzone' );
$title_arr = array();

if ( empty( $zone ) ) {
	remove_action( 'kleo_header', 'kleo_show_header' );
}

get_header(); ?>

<?php

// create full width template.
kleo_switch_layout( 'no' );
add_filter( 'kleo_main_container_class', 'kleo_ret_full_container' );


$title_arr['title'] = $competition_name;
$title_arr['extra'] = '';

if ( ! empty( $zone ) ) {
    echo kleo_title_section($title_arr);
}

?>

<?php get_template_part( 'page-parts/general-before-wrap' ); ?>

<?php
echo do_shortcode( '[competitions_app]' );

?>
		
<?php get_template_part( 'page-parts/general-after-wrap' ); ?>

<?php
get_footer();
