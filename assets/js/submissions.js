jQuery(function ($) {
	"use strict";

	function removeWarning(form) {
		$(form).closest('.form-group').removeClass('has-warning');
		$(form).closest('.form-group').find('.help-block').remove();
		$(form).find('.response-wrapper').html('');
	}

	$('.iarai-submission-form select, .iarai-submission-form input[type="file"]').on('change', function () {
		removeWarning(this);
	});

	$('.iarai-submission-form input[type="text"]').on('keyup', function () {
		removeWarning(this);
	});

	$('body').on('click', '.delete-submission-item', function (e) {

		e.preventDefault();

		var result = confirm("Are you sure you want to delete this submission?");
		if (!result) {
			return false;
		}

		var _this = $(this);
		var form_data = new FormData();

		form_data.append('action', 'iarai_delete_submission');
		form_data.append('item_id', _this.data('id'));
		form_data.append('competition', $(this).find('.submission_competition').val());
		form_data.append('security', iaraiSubmissionsParams.ajaxNonce);

		$.ajax({
			url: iaraiSubmissionsParams.ajaxurl,
			type: 'POST',
			data: form_data,
			contentType: false,
			processData: false,
			beforeSend: function (xhr) {
			},
			complete: function (response) {
				try {
					var data = JSON.parse(response);
					if (data.hasOwnProperty('message')) {
						alert(data.message);
					}
				} catch (e) {

				}
				_this.closest('li').fadeOut('slow');
				var accContent = $('#acc-id-' + _this.data('id'));
				if (accContent.length > 0) {
					accContent.closest('li').fadeOut('slow');
				}
			},
			error: function() {
				alert('There was something wrong with the request');
			}
		});
		return false;
	});


	$('.iarai-submission-form').on('submit', function (e) {

		e.preventDefault();

		var _this = $(this);
		var form_data = new FormData();

		form_data.append('action', 'iarai_file_upload');
		form_data.append('security', iaraiSubmissionsParams.ajaxNonce);
		form_data.append('title', $(this).find('.submission_name').val());
		form_data.append('competition', $(this).find('.submission_competition').val());
		form_data.append('notes', $(this).find('.submission_notes').val());
		form_data.append('team', $(this).find('.submission_team').val());
		form_data.append('pass', $(this).find('.submission_pass').val());

		if ($(this).find('.submission_name').length > 0 ) {
			form_data.append('email', $(this).find('.submission_email').val());
		}

		var file_data = $(this).find('#submission_file').prop('files')[0];
		if (file_data !== undefined) {
			form_data.append('file', file_data);
		}

		if (! _this.find('.submission_tnc').is(':checked')) {
			_this.find('.response-wrapper').html('<span class="alert alert-warning">' +
				'You must agree with the terms and conditions!</span>');
			return;
		}

		$.ajax({
			url: iaraiSubmissionsParams.ajaxurl,
			type: 'POST',
			contentType: false,
			processData: false,
			data: form_data,
			beforeSend: function (xhr) {
				_this.find('.btn').text('Uploading...').attr('disabled', 'disabled'); // change the button text
				_this.find('.response-wrapper').html('');
				_this.find('.form-group').removeClass('has-warning');
				_this.find('.help-block').remove();

			},
			success: function (response) {
				if (isJsonString(response)) {
					var data = JSON.parse(response);
					if (data.hasOwnProperty('success')) {
						_this.find('.response-wrapper').html(data.message);
						$('.submissions-no-data').hide();
						$('.list-submissions').prepend(data.data);

						_this.trigger("reset");
					} else {
						// Show form error messages
						$.each(data.errors, function (index, value) {
							var el = _this.find('input[name="' + index + '"], select[name="' + index + '"]');
							if (el.length > 0) {
								el.closest('.col-sm-9').append('<span class="help-block">' + value + '</span>');
								el.closest('.form-group').addClass('has-warning');
							} else {
								_this.find('.response-wrapper').html(value);
							}
						});
					}
				} else {
					_this.find('.response-wrapper').html('<span class="alert alert-warning">' +
						'Something went wrong with the request. Please try again</span>');
				}

				_this.find('.btn').text('Submit data').attr('disabled', false);

			},
			error: function() {
				alert('There was something wrong with the request.');
			},
			xhr: function () {
				var jqXHR = null;
				if ( window.ActiveXObject ) {
					jqXHR = new window.ActiveXObject( "Microsoft.XMLHTTP" );
				} else {
					jqXHR = new window.XMLHttpRequest();
				}
				//Upload progress
				jqXHR.upload.addEventListener( "progress", function ( evt ) {
					if ( evt.lengthComputable ) {
						var percentComplete = Math.round( (evt.loaded * 100) / evt.total );
						_this.find('.btn').text('Uploading ('+ percentComplete +'%)...');
					} else {
						_this.find('.btn').text('Uploading...');
					}
				}, false );

				return jqXHR;
			},
		});

		return false;
	});

	$('.leaderboad-just-me').on('change', function () {
		$(this).closest('form').submit();
	});

	$('.search-leaderboard-form').on('submit', function (e) {

		e.preventDefault();

		var data = {
			'action': 'iarai_filter_leaderboard',
			'term': $(this).find('.search-leaderboard').val(),
			'current_user': $(this).find('.leaderboad-just-me').is(':checked') ? 1 : 0,
			'competition': $(this).find('.leaderboard-competition').val(),
			'security': iaraiSubmissionsParams.ajaxNonce
		};

		$.ajax({ // you can also use $.post here
			url: iaraiSubmissionsParams.ajaxurl, // AJAX handler
			data: data,
			type: 'POST',
			beforeSend: function (xhr) {
			},
			success: function (response) {

				if (response.data.results !== false) {
					$('.leaderboard-body').html(response.data.results);
				} else {
					$('.leaderboard-body').html('<tr><td colspan="3">Nothing found. Try a different search.</td></tr>');
					$('[data-toggle="popover"]').popover({placement: 'top'});
				}
			},
			error: function() {
				alert('There was something wrong with the request');
			}
		});

		return false;
	});


	function isJsonString(str) {
		try {
			JSON.parse(str);
		} catch (e) {
			return false;
		}
		return true;
	}
});
