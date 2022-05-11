<?php
global $wpdb;

use CLead2\Plugin;

if ( $competition == '' ) {
	return;
}

$leaderboard_option = get_term_meta( $competition, '_competition_leaderboard', true );
if ( $leaderboard_option !== 'yes' && ! ( current_user_can( 'author' ) && $leaderboard_option === 'editor' ) ) {
	return;
}

$sort_order  = 'DESC';
$sort_option = get_term_meta( $competition, '_competition_score_sort', true );
if ( $sort_option === 'asc' || $sort_option === 'desc' ) {
	$sort_order = strtoupper( $sort_option );
}

// Submissions
$submissions = Plugin::query_leaderboard( $competition, '', $sort_order );

?>
<style>
    .thead-bg {
        background: #46556e;
        color: #fff;
    }

    .search-leaderboard, .input-group-addon {
        -webkit-border-radius: 0;
        -moz-border-radius: 0;
        border-radius: 0;
    }

    .search-leaderboard-wrap {
        margin: 0 auto;
    }

    .search-leaderboard-form {
        background: #46556e;
        padding: 10px;
        border-bottom: 1px solid #5b6283;
        border-right: 1px solid #fff;
    }

    .popover h3 {
        font-size: 16px !important;
        color: #fff;
    }

    .filter-my-entries {
        color: #fff;
        font-size: 16px;
    }

    .submission-log {
        cursor: pointer;
    }
    tbody td {
        word-break: break-word;
    }

</style>

<form class="search-leaderboard-form" method="post" action="">
    <input type="hidden" class="leaderboard-competition" value="<?php echo esc_attr( $competition ); ?>">

    <div class="row">
        <div class="input-group search-leaderboard-wrap col-md-12">
            <span class="input-group-addon"><i class="icon icon-search"></i></span>
            <input type="text" class="form-control search-leaderboard" name="search_leaderboard"
                   placeholder="Search Leaderboard...">
        </div>
		<?php if ( is_user_logged_in() ) : ?>
            <div class="col-md-12 filter-my-entries">
                <labeL>
                    <input type="checkbox" class="leaderboad-just-me"> My submissions only
                </labeL>
            </div>
		<?php endif; ?>
    </div>


</form>

<table class="table table-responsive table-striped table-hover">
    <thead class="thead-dark">
    <tr class="thead-bg">
        <td>Pos.</td>
        <td>Name</td>
        <td>Team/User</td>
        <td>Score</td>
        <td>Date(UTC)</td>
    </tr>
    </thead>
    <tbody class="leaderboard-body">
	<?php

	if ( $submissions ) {
		$leaderboard_positions = [];
		$count                 = 0;
		foreach ( $submissions as $submission ): $count ++;
			$leaderboard_positions[ $submission->ID ] = $count;
			echo Plugin::get_leaderboard_row( $submission, $competition, $count );

		endforeach;

		set_transient( 'leaderboard_' . $competition, $leaderboard_positions );


	} else {
		echo '<tr><td colspan="5">' .
		     '<p class="lead text-center">Entries are updated hourly. Please check back later!</p>' .
		     '</td>' .
		     '</tr>';
	} ?>
    </tbody>

</table>
