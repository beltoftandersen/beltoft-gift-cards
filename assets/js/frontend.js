/**
 * Beltoft Gift Cards for WooCommerce - Frontend JS
 */
(function($) {
	'use strict';

	$(function() {

		/* ── Product Page: Amount Buttons ─────────────────────── */

		var $amounts = $('.bgcw-amounts');
		if ($amounts.length) {
			// Select first predefined button on load.
			$amounts.find('.bgcw-amount-btn:not(.bgcw-custom-btn)').first().addClass('active');

			$amounts.on('click', '.bgcw-amount-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);

				$amounts.find('.bgcw-amount-btn').removeClass('active');
				$btn.addClass('active');

				if ($btn.hasClass('bgcw-custom-btn')) {
					$('.bgcw-custom-amount').slideDown(150);
					$('#bgcw_amount').val('');
				} else {
					$('.bgcw-custom-amount').slideUp(150);
					$('#bgcw_amount').val($btn.data('amount'));
					$('#bgcw_custom_amount').val('');
				}
			});

			// Sync custom amount input to hidden field as whole numbers only.
			$('#bgcw_custom_amount').on('input change', function() {
				var raw = $.trim($(this).val());
				if (raw === '') {
					$('#bgcw_amount').val('');
					return;
				}

				var amount = Math.round(parseFloat(raw));
				if (isNaN(amount) || amount <= 0) {
					$('#bgcw_amount').val('');
					return;
				}

				$(this).val(amount);
				$('#bgcw_amount').val(amount);
			});
		}

		/* ── Product Page: Amount Dropdown ────────────────────── */

		var $dropdown = $('#bgcw_amount_dropdown');
		if ($dropdown.length) {
			// Set initial value from first option and handle custom-only case.
			var initialVal = $dropdown.val();
			if (initialVal === 'custom') {
				$('.bgcw-custom-amount').show();
				$('#bgcw_amount').val('');
			} else {
				$('#bgcw_amount').val(initialVal);
			}

			$dropdown.on('change', function() {
				var val = $(this).val();

				if (val === 'custom') {
					$('.bgcw-custom-amount').slideDown(150);
					$('#bgcw_amount').val('');
				} else {
					$('.bgcw-custom-amount').slideUp(150);
					$('#bgcw_amount').val(val);
					$('#bgcw_custom_amount').val('');
				}
			});

			// Sync custom amount input to hidden field as whole numbers only.
			$('#bgcw_custom_amount').on('input change', function() {
				var raw = $.trim($(this).val());
				if (raw === '') {
					$('#bgcw_amount').val('');
					return;
				}

				var amount = Math.round(parseFloat(raw));
				if (isNaN(amount) || amount <= 0) {
					$('#bgcw_amount').val('');
					return;
				}

				$(this).val(amount);
				$('#bgcw_amount').val(amount);
			});
		}

		/* ── Dedicated Field: AJAX Apply / Remove ────────────── */

		var $field = $('.bgcw-apply-field');
		if ($field.length && typeof bgcw_params !== 'undefined') {

			// Apply gift card.
			$field.on('click', '.bgcw-apply-btn', function() {
				var $btn    = $(this);
				var $input  = $field.find('.bgcw-code-input');
				var $notice = $field.find('.bgcw-field-notice');
				var code    = $.trim($input.val());

				if (!code) {
					$notice.text(bgcw_params.i18n.enter_code).removeClass('success').addClass('error').show();
					return;
				}

				$btn.prop('disabled', true).text(bgcw_params.i18n.applying);
				$notice.hide();

				$.ajax({
					url: bgcw_params.ajax_url.replace('%%endpoint%%', 'bgcw_apply_card'),
					type: 'POST',
					data: {
						nonce: bgcw_params.nonce,
						code: code
					},
					success: function(res) {
						if (res.success) {
							$notice.text(res.data.message).removeClass('error').addClass('success').show();
							$input.val('');
							// Refresh page to update cart totals.
							location.reload();
						} else {
							$notice.text(res.data.message).removeClass('success').addClass('error').show();
						}
					},
					error: function() {
						$notice.text(bgcw_params.i18n.request_error).removeClass('success').addClass('error').show();
					},
					complete: function() {
						$btn.prop('disabled', false).text(bgcw_params.i18n.apply);
					}
				});
			});

			// Apply on Enter key.
			$field.on('keypress', '.bgcw-code-input', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					$field.find('.bgcw-apply-btn').trigger('click');
				}
			});

			// Remove gift card.
			$field.on('click', '.bgcw-ajax-remove', function() {
				var $btn    = $(this);
				var index   = $btn.data('index');
				var $notice = $field.find('.bgcw-field-notice');

				$btn.prop('disabled', true);

				$.ajax({
					url: bgcw_params.ajax_url.replace('%%endpoint%%', 'bgcw_remove_card'),
					type: 'POST',
					data: {
						nonce: bgcw_params.nonce,
						index: index
					},
					success: function(res) {
						if (res.success) {
							location.reload();
						} else {
							$notice.text(res.data.message).removeClass('success').addClass('error').show();
						}
					},
					error: function() {
						$notice.text(bgcw_params.i18n.request_error).removeClass('success').addClass('error').show();
					},
					complete: function() {
						$btn.prop('disabled', false);
					}
				});
			});
		}

		/* ── My Account: Toggle Transaction Rows ─────────────── */

		$('.bgcw-toggle-transactions').on('click', function() {
			var $btn   = $(this);
			var cardId = $btn.closest('tr').data('card-id');
			var $row   = $('.bgcw-transactions-row[data-card-id="' + cardId + '"]');

			$row.toggle();
			$btn.html($row.is(':visible') ? '&#9650;' : '&#9660;');
		});

	});
})(jQuery);
