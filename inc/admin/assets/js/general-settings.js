(function ($) {
	$(function () {
		var $input = $('#api_access_token');
		if (!$input.length) {
			return;
		}

		var $wrapper = $('<div class="eyeon-api-token-field"></div>');
		$input.after($wrapper);
		$wrapper.append($input);

		var $toggle = $(
			'<button type="button" class="button eyeon-api-token-toggle" aria-label="Show token" aria-pressed="false">' +
				'<span class="dashicons dashicons-visibility" aria-hidden="true"></span>' +
			'</button>'
		);
		$wrapper.append($toggle);

		var $copy = null;
		if ($input.val()) {
			$copy = $(
				'<button type="button" class="button eyeon-api-token-copy" aria-label="Copy token">' +
					'<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>' +
				'</button>'
			);
			$wrapper.append($copy);
		}

		function setVisible(visible) {
			$input.attr('type', visible ? 'text' : 'password');
			$toggle.attr('aria-label', visible ? 'Hide token' : 'Show token');
			$toggle.attr('aria-pressed', visible ? 'true' : 'false');
			$toggle.toggleClass('is-visible', visible);
			$toggle.find('.dashicons')
				.toggleClass('dashicons-visibility', !visible)
				.toggleClass('dashicons-hidden', visible);
		}

		setVisible(false);

		$toggle.on('click', function () {
			setVisible($input.attr('type') === 'password');
		});

		if ($copy) {
			$copy.on('click', function () {
				var token = $input.val();
				if (!token || !navigator.clipboard) {
					return;
				}

				navigator.clipboard.writeText(token).then(function () {
					$copy.addClass('is-copied');
					window.setTimeout(function () {
						$copy.removeClass('is-copied');
					}, 1500);
				});
			});
		}
	});
})(jQuery);
