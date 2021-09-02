<?php

use CLead\Plugin;

$notes = get_post_meta( $submission->ID, '_submission_notes', true );
$log   = '';
if ( Plugin::get_log_content( $submission->ID ) !== false ) {
	$log = '<br><i class="icon-info-circled"></i> ' . Plugin::get_log_content( $submission->ID );
}

echo '<li class="list-group-item">' .
     $submission->post_title .
     $log .
     '<div class="text-right">';

if ( $notes ) {
	echo '<span class="badge"><a style="color: #fff;" href="#acc-id-' . $submission->ID . '"data-toggle="collapse">' .
	     'Notes <span class="icon-closed icon-down-open-big"></span><span class="icon-opened icon-up-open-big hide"></span></a>' .
	     '</span>';
}

$score_number = 'pending score';
if ( Plugin::get_score_number( $submission->ID ) ) {
	$score_number = Plugin::get_score_number( $submission->ID );
}
echo '&nbsp;<span style="background: #46556D;" class="badge">' . esc_html( $score_number ) . '</span>';

$submission_option        = get_term_meta( $competition, '_enable_submissions', true );
$delete_submission_option = get_term_meta( $competition, '_enable_submission_deletion', true );

if ( empty( $delete_submission_option ) ) {
	$delete_submission_option = 'yes';
}

$can_submit = $submission_option === 'yes' || $submission_option === 'guests' || ( ( current_user_can( 'author' ) || current_user_can( 'editor' ) ) && $submission_option === 'editor' );
$can_delete = $delete_submission_option === 'yes' || ( current_user_can( 'author' ) && $delete_submission_option === 'editor' );

if ( $can_submit && $can_delete ) {
	echo '&nbsp;<span class="badge hover-tip" data-toggle="tooltip" title="Delete this submission" data-placement="top" style="background: red;">' .
	     '<a data-id="' . $submission->ID . '" class="delete-submission-item" href="#">' .
	     '<i class="icon-cancel"></i>' .
	     '</a>' .
	     '</span>';
}
echo '</div></li>';

if ( $notes ) {
	echo '<li><div id="acc-id-' . $submission->ID . '" class="panel-collapse collapse">
				<div class="panel-body">
				<p>' . $notes . '</p>
				</div>
			</div></li>';
}
