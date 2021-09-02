<?php
echo '<ul class="list-group list-submissions">';
foreach ( $submissions as $submission ) {
	include CLEAD_PATH . 'templates/submission-item.php';
}
echo '</ul>';
