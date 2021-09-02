<?php
/**
 * Submission form template
 */

use IARAI\Settings;

$args         = array(
	'hide_empty' => false, // also retrieve terms which are not used yet
	'taxonomy'   => 'competition',
);
$competitions = get_terms( $args );

$object_id        = get_queried_object_id();
$competition_page = false;
if ( $object_id && is_tax( 'competition', $object_id ) ) {
	$competition_page = true;
}

$saved_title       = isset( $_POST['title'] ) ? $_POST['title'] : '';
$saved_email       = isset( $_POST['email'] ) ? $_POST['email'] : '';
$saved_competition = isset( $_POST['competition'] ) ? $_POST['competition'] : '';

?>

<!-- Form -->
<form method='post' action='' class='form-horizontal iarai-submission-form' enctype='multipart/form-data'>
    <div class="form-group">
        <label class="control-label col-sm-3" for="submission_name">Submission name*</label>
        <div class="col-sm-9">
            <input required type="text" name="title" id="submission_name" class="form-control submission_name"
                   placeholder="My submission name"
                   value="<?php echo esc_attr( $saved_title ); ?>">

        </div>
    </div>

	<?php
	if ( ! empty( $competition ) && ! is_user_logged_in() ) :
		$submission_option = get_term_meta( $competition, '_enable_submissions', true );
		if ( $submission_option === 'guests' ) :
			?>
            <div class="form-group">
                <label class="control-label col-sm-3" for="submission_name">Email*</label>
                <div class="col-sm-9">
                    <input required type="email" name="email" id="submission_email"
                           class="form-control submission_email"
                           placeholder="My Email"
                           value="<?php echo esc_attr( $saved_title ); ?>">
                </div>
            </div>
		<?php
		endif;
	endif;
	?>

	<?php if ( $competition_page ) : ?>
        <input class="submission_competition" name="competition" type="hidden"
               value="<?php echo esc_attr( $object_id ); ?>">
	<?php else : ?>
        <div class="form-group">
            <label class="control-label col-sm-3" for="submission_competition">Competition*</label>
            <div class="col-sm-9">
                <select required class="form-control submission_competition" name="competition"
                        id="submission_competition">
                    <option value="">Select competition</option>
					<?php foreach ( $competitions as $competition ) : ?>
                        <option
							<?php selected( $saved_competition, $competition->term_id ); ?>value="<?php echo esc_attr( $competition->term_id ); ?>">
							<?php echo esc_html( $competition->name ); ?>
                        </option>
					<?php endforeach; ?>
                </select>
            </div>
        </div>
	<?php endif; ?>

    <div class="form-group">
        <label class="control-label col-sm-3" for="submission_team">Team</label>
        <div class="col-sm-9">
            <div class="row">
                <div class="col-sm-6">
                    <input placeholder="Team name" name="team" type="text" class="form-control submission_team"
                           id="submission_team">
                </div>
                <div class="col-sm-6">
                    <input placeholder="Team password" name="pass" type="password" class="form-control submission_pass"
                           id="submission_pass">
                </div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-sm-3" for="submission_notes">Private notes</label>
        <div class="col-sm-9">
			<textarea name="notes" id="submission_notes" class="form-control submission_notes"
                      placeholder="Save your private submission notes here"></textarea>

        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-sm-3" for="submission_file">File upload*</label>
        <div class="col-sm-9">
            <input required name="file" type="file" class="form-control-file" id="submission_file">
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-sm-3" for="submission_notes"></label>
        <div class="col-sm-9">
            <input type="checkbox" name="tnc" id="submission_tnc" class="submission_tnc">
			<?php
			$terms_page = class_exists( Settings::class ) ? Settings::get_option( 'terms_page' ) : '';

			if ( ! empty( $terms_page ) ) {
				echo wp_kses_post(
					sprintf(
						__( 'I agree with the <a href="%s">terms and conditions</a>', 'competitions-leaderboard' ),
						get_permalink( $terms_page )
					)
				);
			}
			?>
        </div>
    </div>

    <input type="hidden" name="action" value="iarai_upload"/>
	<?php wp_nonce_field(
		'iarai-item-upload',
		'iarai-submissions-nonce'
	); ?>
    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
            <button type="submit" name="iarai_button_submit" class="btn btn-primary iarai-competion-submit">Submit
                data
            </button>
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
            <div class="response-wrapper"></div>
        </div>
    </div>

</form>
