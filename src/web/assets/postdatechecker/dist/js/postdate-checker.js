(function () {
	function checkAndShow() {
		if (typeof Craft === 'undefined' || !Craft.cp || typeof Garnish === 'undefined') {
			requestAnimationFrame(checkAndShow);
			return;
		}

		if (window.entryPostDateConflict) {
			showConflictModal(window.entryPostDateConflict);
			window.entryPostDateConflict = null;
		}
	}

	checkAndShow();
})();

function showConflictModal(conflict) {
	const payload = normalizeConflictPayload(conflict);
	const $modal = $('<div class="modal fitted" role="dialog" aria-modal="true"></div>').appendTo(Garnish.$bod);
	const $container = $('<div class="modal-content"></div>').appendTo($modal);
	const $body = $('<div></div>').css('padding', '20px').appendTo($container);

	$('<h1></h1>').css('margin-top', 0).text(payload.title).appendTo($body);
	$('<p></p>').text(payload.message).appendTo($body);

	if (payload.recommendation) {
		$('<p></p>').text(payload.recommendation).appendTo($body);
	}

	const $buttons = $('<div class="buttons"></div>').css('text-align', 'right').appendTo($body);
	const $button = $('<button type="button" class="btn submit"></button>')
		.css('margin-top', '5px')
		.text(payload.buttonLabel)
		.appendTo($buttons);

	const modal = new Garnish.Modal($modal, {
		onHide: () => {
			$modal.remove();
		}
	});

	$button.on('click', () => modal.hide());
}

function normalizeConflictPayload(conflict) {
	const defaults = window.entryPostDateCheckerDefaults || {};

	if (typeof conflict === 'string') {
		return {
			title: defaults.title || 'Warning',
			message: conflict.replace(/<br\s*\/?>/gi, '\n'),
			recommendation: '',
			buttonLabel: defaults.buttonLabel || 'Got it'
		};
	}

	return {
		title: conflict.title || defaults.title || 'Warning',
		message: conflict.message || '',
		recommendation: conflict.recommendation || '',
		buttonLabel: conflict.buttonLabel || defaults.buttonLabel || 'Got it'
	};
}
