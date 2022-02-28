<?php
/**
 * The template for displaying Archive pages
 *
 * Used to display archive-type pages if nothing more specific matches a query.
 * For example, puts together date-based pages if no date.php file exists.
 *
 * If you'd like to further customize these archive views, you may create a
 * new template file for each specific one. For example, Twenty Fourteen
 * already has tag.php for Tag archives, category.php for Category archives,
 * and author.php for Author archives.
 *
 * @link http://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Kleo
 * @since Kleo 1.0
 */

$competition = get_queried_object_id();

get_header();
if ( function_exists( 'kleo_switch_layout' ) ) {
	kleo_switch_layout( 'full' );
}

?>
<style>
    .hr-title {
        margin: 0 0 30px 0;
        font-size: 22px;
    }

    .list-submissions {
        list-style-type: none;
    }

    .delete-submission-item {
        color: #fff !important;
    }

    .iarai-submission-form {
        margin-bottom: 40px;
    }

    .panel-primary > .panel-heading {
        color: #fff;
        background-color: #46556e;
        border-color: #46556e;
    }


</style>
<?php get_template_part( 'page-parts/general-title-section' ); ?>

<?php get_template_part( 'page-parts/general-before-wrap' ); ?>

<div class="col-md-9">

	<?php
	if ( $pre_text = get_term_meta( $competition, '_competition_pre_text', true ) ) {
		echo '<div style="margin-bottom: 30px;">' . wp_kses_post( wpautop( $pre_text ) ) . '</div>';
	}
	?>

	<?php if ( isset( $_GET['leaderboard'] ) ): ?>

		<?php
		$leaderboard_option = get_term_meta( $competition, '_competition_leaderboard', true );
		if ( $leaderboard_option === 'yes' || ( current_user_can( 'author' ) && $leaderboard_option === 'editor' ) ) : ?>
            <div id="leaderboard" class="hr-title hr-full hr-left"><abbr>
                    Leaderboard
                </abbr></div>

			<?php echo do_shortcode( '[iarai_leaderboard competition="' . $competition . '"]' ); ?>
		<?php endif; ?>

	<?php elseif ( isset( $_GET['submissions'] ) ):

		$submission_option = get_term_meta( $competition, '_enable_submissions', true );

		if ( ! is_user_logged_in() && $submission_option !== 'guests' ) {
			echo '<p class="alert alert-warning submissions-no-user">' .
                 'Please <a class="kleo-show-login" href="'. wp_login_url() .'">' .
                 'login/create account</a> to submit or view your submitted data.</p>';
		} else {
			?>

			<?php
			if ( $submission_option === 'yes' || $submission_option === 'guests' || ( ( current_user_can( 'author' ) || current_user_can( 'editor' ) ) && $submission_option === 'editor' ) ) : ?>
                <div id="submit-data" class="hr-title hr-full hr-left"><abbr>
                        Submit your data
                    </abbr></div>

				<?php echo do_shortcode( '[iarai_submission_form]' ); ?>

			<?php else: ?>

                <h5><?php esc_html_e( 'This competition is closed for new submissions.', 'competitions-leaderboard' ); ?></h5>
                <br>
                <br>
			<?php endif; ?>

            <div id="my-submissions" class="hr-title hr-full hr-left"><abbr>My submissions</abbr></div>

			<?php
			$submissions = \CLead\Submissions::get_submissions( $competition, get_current_user_id() );

            echo '<!-- '.  count( $submissions ) .'-->';
			if ( $submissions && count( $submissions ) > 0 ) {
				wp_enqueue_script( 'iarai-submissions' );
				include CLEAD_PATH . 'templates/submissions-list.php';
			} else {
				echo '<p class="submissions-no-data">You haven\'t submitted any data for this competition.</p>';
				echo '<ul class="list-group list-submissions"></ul>';
			}
		}
		?>

	<?php else: ?>

		<?php if ( category_description() ) : ?>
            <div id="competition" class="hr-title hr-full hr-left"><abbr>
                    Details
                </abbr></div>
            <div class="archive-description"><?php echo do_shortcode( category_description() ); ?></div>

		<?php endif; ?>

	<?php endif; ?>


</div>
<div class="col-md-3">
    <div class="panel panel-default">
        <div class="panel-heading">Menu</div>
        <div class="panel-body">

            <ul class="competition-menu kleo-scroll-to">
                <li><a href="<?php echo esc_url( get_term_link( $competition ) ); ?>">Competition</li>

				<?php
				$leaderboard_option = get_term_meta( $competition, '_competition_leaderboard', true );
				if ( $leaderboard_option === 'yes' || ( current_user_can( 'author' ) && $leaderboard_option === 'editor' ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( 'leaderboard', '', get_term_link( $competition ) ) ); ?>">Leaderboard
                    </li>
				<?php endif; ?>

                <li>
                    <a href="<?php echo esc_url( add_query_arg( 'submissions', '', get_term_link( $competition ) ) ); ?>">Submit
                </li>
            </ul>


        </div>
    </div>
</div>

<?php do_action( 'kleo_after_archive_content' ); ?>

<?php get_template_part( 'page-parts/general-after-wrap' ); ?>

<?php get_footer(); ?>
