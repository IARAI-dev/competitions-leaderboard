<?php
/**
 * Template Name: Competition page
 *
 * @package WordPress
 * @subpackage Kleo
 * @author SeventhQueen <themesupport@seventhqueen.com>
 * @since Kleo 1.0
 */

use CLead\Plugin;

$competition_name = '';
$competition_link = '';
$competition_slug = get_query_var( 'compslug' );
$zone             = get_query_var( 'compzone' );
$zone_name        = str_replace( str_replace( array( 'https://', 'http://' ), '', home_url( '/' ) ), '', $_SERVER['HTTP_HOST'] . explode( '?', $_SERVER['REQUEST_URI'], 2 )[0] );

// Set the competition name.
if ( ! empty( $competition_slug ) && get_term_by( 'slug', $competition_slug, 'competition' ) ) {
	$competition_name = get_term_by( 'slug', $competition_slug, 'competition' )->name;
	$competition_link = 'competition/' . $competition_slug;
} else {
	$args = array(
		'taxonomy'   => 'competition',
		'hide_empty' => false,
		'meta_key'   => '_competition_is_main',
		'meta_value' => 'yes',
	);

	$terms = get_terms( $args );

	if ( ! empty( $terms ) ) {
		$competition_name = $terms[0]->name;
		$competition_slug = $terms[0]->slug;
	}
}

// Add main site header.
add_action( 'kleo_header', array( Plugin::instance(), 'main_site_header' ), 9 );

// Set logo back to regular one. This is filtered in the iarai-dev/loader.php.
remove_all_filters( 'kleo_logo_href', 10 );

// If on homepage. Remove site header.
if ( empty( $zone ) ) {
	remove_action( 'kleo_header', 'kleo_show_header' );
}

get_header(); ?>

<?php

// create full width template.
kleo_switch_layout( 'no' );
add_filter( 'kleo_main_container_class', 'kleo_ret_full_container' );

// Setup title area.
$breadcrumb = '<div class="kleo_framework breadcrumb" itemscope="" itemtype="http://schema.org/BreadcrumbList">
<span itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
	<a itemprop="item" href="https://www.iarai.ac.at" title="IARAI">
		<span itemprop="name">Home</span>
	</a>
	<meta itemprop="position" content="1">
</span>

<span class="sep"> </span> 

<span class="active" itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
	<a itemprop="item" href="' . home_url( $competition_link ) . '" title="' . $competition_name . '">
		<span itemprop="name">' . $competition_name . '</span>
	</a>
	<meta itemprop="position" content="2">
</span>

</div>';

$title_arr           = array();
$title_arr['title']  = ucfirst( str_replace( array( '-', '_', '/' ), ' ', $zone_name ) );
$title_arr['extra']  = '';
$title_arr['output'] = "<section class='{class} border-bottom breadcrumbs-container'>
<div class='container'>
	{title_data}
	<div class='breadcrumb-extra'>
		" . $breadcrumb . '{extra}
	</div></div></section>';

// If not on homepage. Show title/breadcrumb.
if ( ! empty( $zone ) ) {
	echo kleo_title_section( $title_arr );
}
?>

<?php get_template_part( 'page-parts/general-before-wrap' ); ?>

<?php
echo do_shortcode( '[competitions_app]' );

?>
		
<?php get_template_part( 'page-parts/general-after-wrap' ); ?>

<?php
get_footer();
