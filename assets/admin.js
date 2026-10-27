/**
 * EVILLAGE SMS Form – panel: zakładki, licznik SMS, kolory i podgląd na żywo.
 */
(function ($) {
	'use strict';

	var wrap = document.querySelector('.evsms-admin');
	if (!wrap) {
		return;
	}

	function byId(id) {
		return document.getElementById(id);
	}

	/* Zakładki ---------------------------------------------------------- */

	var STORAGE_KEY = 'evsmsTab';
	var tabs = wrap.querySelectorAll('.evsms-tabs [data-tab]');
	var panels = wrap.querySelectorAll('[data-panel]');
	var submitRow = wrap.querySelector('.evsms-submit-row');

	function showTab(id) {
		var exists = wrap.querySelector('[data-panel="' + id + '"]');
		id = exists ? id : 'sms';

		tabs.forEach(function (tab) {
			var active = tab.getAttribute('data-tab') === id;
			tab.classList.toggle('nav-tab-active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		panels.forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-panel') !== id;
		});
		// Rejestr voucherów nie ma czego zapisywać
		submitRow.hidden = id === 'vouchers';

		try {
			sessionStorage.setItem(STORAGE_KEY, id);
		} catch (e) {}
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (e) {
			e.preventDefault();
			var id = tab.getAttribute('data-tab');
			showTab(id);
			history.replaceState(null, '', '#evsms-tab-' + id);
		});
	});

	wrap.classList.add('evsms-js');
	var initial = location.hash.replace('#evsms-tab-', '');
	if (!initial) {
		try {
			initial = sessionStorage.getItem(STORAGE_KEY) || '';
		} catch (e) {}
	}
	showTab(initial || 'sms');

	/* Kod kuponu: pokaż tylko pola wybranego trybu ---------------------- */

	function codeMode() {
		var checked = wrap.querySelector('input[name$="[code_mode]"]:checked');
		return checked ? checked.value : 'unique';
	}

	function toggleCodeRows() {
		var mode = codeMode();
		wrap.querySelectorAll('[data-code-mode]').forEach(function (row) {
			row.hidden = row.getAttribute('data-code-mode') !== mode;
		});
	}

	/* Licznik i podgląd SMS ---------------------------------------------- */

	var GSM = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
	var GSM_EXT = '^{}\\[~]|€';
	var PL = { 'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z',
		'Ą': 'A', 'Ć': 'C', 'Ę': 'E', 'Ł': 'L', 'Ń': 'N', 'Ó': 'O', 'Ś': 'S', 'Ź': 'Z', 'Ż': 'Z' };

	function sampleText() {
		var code = codeMode() === 'static'
			? (byId('evsms_static_code').value || 'KOD')
			: (byId('evsms_code_prefix').value + 'K7PX2MQA');

		var text = byId('evsms_message').value
			.split('{code}').join(code)
			.split('{voucher}').join(byId('evsms_voucher_prefix').value + '000123')
			.split('{site}').join(evsmsAdmin.site);

		if (byId('evsms_normalize').checked) {
			text = text.replace(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/g, function (ch) {
				return PL[ch];
			});
		}
		return text;
	}

	function smsStats(text) {
		var gsmLength = 0;
		var unicode = false;

		for (var i = 0; i < text.length; i++) {
			var ch = text.charAt(i);
			if (GSM.indexOf(ch) !== -1) {
				gsmLength += 1;
			} else if (GSM_EXT.indexOf(ch) !== -1) {
				gsmLength += 2;
			} else {
				unicode = true;
				break;
			}
		}

		var length = unicode ? text.length : gsmLength;
		var single = unicode ? 70 : 160;
		var multi = unicode ? 67 : 153;
		var parts = length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multi));

		return { length: length, parts: parts, unicode: unicode, limit: single };
	}

	function updateSms() {
		var text = sampleText();
		var stats = smsStats(text);
		var info = stats.length + ' / ' + stats.limit + ' znaków · ' + stats.parts + ' SMS';

		if (stats.unicode) {
			info += ' · znaki specjalne – limit 70 znaków na SMS';
		}
		if (stats.parts > 1) {
			info += ' · każda część jest płatna osobno';
		}

		byId('evsms-sms-bubble').textContent = text;
		var statsEl = byId('evsms-sms-stats');
		statsEl.textContent = info;
		statsEl.classList.toggle('is-warning', stats.parts > 1 || stats.unicode);
	}

	['evsms_message', 'evsms_code_prefix', 'evsms_static_code', 'evsms_voucher_prefix', 'evsms_normalize'].forEach(function (id) {
		var el = byId(id);
		if (el) {
			el.addEventListener('input', updateSms);
			el.addEventListener('change', updateSms);
		}
	});

	wrap.querySelectorAll('input[name$="[code_mode]"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			toggleCodeRows();
			updateSms();
		});
	});

	toggleCodeRows();
	updateSms();

	/* Wygląd: kolory + podgląd na żywo ----------------------------------- */

	var preview = wrap.querySelector('.evsms-look-preview .evsms');

	function setVar(name, value) {
		if (!preview) {
			return;
		}
		if (value) {
			preview.style.setProperty(name, value);
		} else {
			preview.style.removeProperty(name);
		}
	}

	$('.evsms-color').each(function () {
		var input = this;
		var name = input.getAttribute('data-var');

		$(input).wpColorPicker({
			palettes: evsmsAdmin.palette,
			change: function (e, ui) {
				setVar(name, ui.color.toString());
			},
			clear: function () {
				setVar(name, '');
			}
		});
		setVar(name, input.value);
	});

	function setColor(input, value) {
		var $input = $(input);
		if (value) {
			$input.wpColorPicker('color', value);
		} else {
			// Pusty = kolor motywu
			$input.val('');
			$input.closest('.wp-picker-container').find('.wp-color-result').css('background-color', '');
			setVar(input.getAttribute('data-var'), '');
		}
	}

	// Gotowe zestawy kolorów
	wrap.querySelectorAll('.evsms-scheme').forEach(function (button) {
		button.addEventListener('click', function () {
			var colors = evsmsAdmin.schemes[button.getAttribute('data-scheme')] || {};
			Object.keys(colors).forEach(function (key) {
				var input = byId('evsms_' + key);
				if (input) {
					setColor(input, colors[key]);
				}
			});
			wrap.querySelectorAll('.evsms-scheme').forEach(function (b) {
				b.classList.toggle('is-active', b === button);
			});
		});
	});

	// Zaokrąglenia ramki oraz pól/przycisku – suwaki z podglądem na żywo
	wrap.querySelectorAll('.evsms-radius').forEach(function (range) {
		var output = range.parentNode.querySelector('output');
		range.addEventListener('input', function () {
			setVar(range.getAttribute('data-var'), range.value + 'px');
			if (output) {
				output.textContent = range.value + ' px';
			}
		});
	});

	/* Ikona nagłówka ------------------------------------------------------ */

	var iconBox = preview && preview.querySelector('.evsms-icon');
	var iconUrl = byId('evsms_icon_url');
	var customRow = wrap.querySelector('[data-icon-custom]');

	function escapeAttr(value) {
		return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
	}

	function currentIcon() {
		var checked = wrap.querySelector('input[name$="[icon]"]:checked');
		return checked ? checked.value : 'ticket';
	}

	function updateIcon() {
		var icon = currentIcon();
		var html = '';

		if (icon === 'custom') {
			html = iconUrl.value ? '<img src="' + escapeAttr(iconUrl.value) + '" alt="" width="26" height="26">' : '';
		} else if (evsmsAdmin.icons[icon]) {
			html = evsmsAdmin.icons[icon];
		}

		customRow.hidden = icon !== 'custom';
		if (iconBox) {
			iconBox.innerHTML = html;
			iconBox.hidden = !html;
		}
	}

	wrap.querySelectorAll('input[name$="[icon]"]').forEach(function (radio) {
		radio.addEventListener('change', updateIcon);
	});
	iconUrl.addEventListener('input', updateIcon);

	var mediaFrame;
	byId('evsms-icon-pick').addEventListener('click', function () {
		if (!window.wp || !wp.media) {
			return;
		}
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: 'Wybierz ikonę formularza',
				button: { text: 'Użyj jako ikony' },
				library: { type: 'image' },
				multiple: false
			});
			mediaFrame.on('select', function () {
				var file = mediaFrame.state().get('selection').first().toJSON();
				iconUrl.value = file.url;

				var tile = wrap.querySelector('.evsms-icon-tile--custom');
				tile.innerHTML = '<img src="' + escapeAttr(file.url) + '" alt="" width="22" height="22">';

				wrap.querySelector('input[name$="[icon]"][value="custom"]').checked = true;
				updateIcon();
			});
		}
		mediaFrame.open();
	});

	updateIcon();

	// Teksty z zakładki Komunikaty od razu widoczne w podglądzie
	var textMap = {
		evsms_form_title: '.evsms-title',
		evsms_form_subtitle: '.evsms-subtitle',
		evsms_form_label: '.evsms-label',
		evsms_form_button: '.evsms-submit-label',
		evsms_consent: '.evsms-consent span'
	};

	Object.keys(textMap).forEach(function (id) {
		var input = byId(id);
		var target = preview && preview.querySelector(textMap[id]);
		if (!input || !target) {
			return;
		}
		input.addEventListener('input', function () {
			target.textContent = input.value || input.getAttribute('placeholder') || '';
		});
	});
})(jQuery);
