/**
 * EVILLAGE SMS Form – formatowanie numeru, walidacja inline i wysyłka przez admin-ajax.
 */
(function () {
	'use strict';

	if (typeof evsmsData === 'undefined') {
		return;
	}

	// Komunikaty edytowalne w panelu (Ustawienia → SMS Kupon → Komunikaty)
	var msg = evsmsData.messages;

	function post(params) {
		return fetch(evsmsData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: params
		}).then(function (res) {
			return res.json();
		});
	}

	/**
	 * Same cyfry numeru krajowego (max 9); obcina wklejone +48 / 0048.
	 */
	function nationalDigits(value) {
		var d = String(value).replace(/\D+/g, '');
		if (d.length > 9 && d.indexOf('0048') === 0) {
			d = d.slice(4);
		} else if (d.length > 9 && d.indexOf('48') === 0) {
			d = d.slice(2);
		}
		return d.slice(0, 9);
	}

	/**
	 * Formatuje pole do postaci "600 100 200", zachowując pozycję kursora.
	 */
	function formatPhone(input) {
		var caret = input.selectionStart === null ? input.value.length : input.selectionStart;
		var digitsBefore = input.value.slice(0, caret).replace(/\D+/g, '').length;
		var formatted = nationalDigits(input.value).replace(/(\d{3})(?=\d)/g, '$1 ');

		if (formatted === input.value) {
			return;
		}
		input.value = formatted;

		var pos = 0;
		var seen = 0;
		while (pos < formatted.length && seen < digitsBefore) {
			if (/\d/.test(formatted.charAt(pos))) {
				seen++;
			}
			pos++;
		}
		if (document.activeElement === input) {
			input.setSelectionRange(pos, pos);
		}
	}

	/**
	 * Zabroniony początek numeru (np. 70x – numery o podwyższonej opłacie) albo ''.
	 */
	function blockedPrefix(digits) {
		var list = evsmsData.blockedPrefixes || [];
		for (var i = 0; i < list.length; i++) {
			if (digits.indexOf(list[i]) === 0) {
				return list[i];
			}
		}
		return '';
	}

	function blockedMessage(prefix) {
		return msg.phoneBlocked.split('{prefix}').join(prefix);
	}

	function fieldEl(form, name) {
		return form.querySelector('.evsms-field[data-field="' + name + '"]');
	}

	function setFieldError(form, name, text) {
		var field = fieldEl(form, name);
		if (!field) {
			return false;
		}
		var input = field.querySelector('input');
		field.classList.toggle('is-invalid', !!text);
		field.querySelector('.evsms-error').textContent = text || '';
		if (text) {
			input.setAttribute('aria-invalid', 'true');
		} else {
			input.removeAttribute('aria-invalid');
		}
		return true;
	}

	function setMessage(form, text) {
		form.querySelector('.evsms-msg').textContent = text || '';
	}

	function validate(form) {
		var errors = {};
		var phone = form.querySelector('.evsms-phone');
		var consent = form.querySelector('input[name="consent"]');
		var digits = nationalDigits(phone.value);

		if (!digits) {
			errors.phone = msg.phoneEmpty;
		} else if (blockedPrefix(digits)) {
			errors.phone = blockedMessage(blockedPrefix(digits));
		} else if (digits.length !== 9) {
			errors.phone = msg.phoneLength;
		}
		if (consent && !consent.checked) {
			errors.consent = msg.consent;
		}
		return errors;
	}

	function setLoading(form, loading) {
		var button = form.querySelector('.evsms-submit');
		var label = button.querySelector('.evsms-submit-label');

		// Treść przycisku na czas wysyłki (edytowalna w panelu); stała szerokość, żeby pole obok nie skakało
		if (loading) {
			button.style.width = button.offsetWidth + 'px';
			label.setAttribute('data-idle', label.textContent);
			label.textContent = button.getAttribute('data-busy') || label.textContent;
		} else if (label.hasAttribute('data-idle')) {
			label.textContent = label.getAttribute('data-idle');
			button.style.width = '';
		}

		form.classList.toggle('is-loading', loading);
		form.setAttribute('aria-busy', loading ? 'true' : 'false');
		button.disabled = loading;
	}

	function showSuccess(form, data) {
		var wrap = form.closest('.evsms');
		var box = wrap.querySelector('.evsms-success');

		box.querySelector('.evsms-success-title').textContent = data.message || '';
		box.querySelector('.evsms-success-meta').textContent = data.meta || '';

		form.hidden = true;
		box.hidden = false;
		wrap.classList.add('is-done');
		box.focus();
	}

	// Maska: przy pisaniu przepuszczamy tylko cyfry (wklejanie czyści formatPhone)
	document.addEventListener('beforeinput', function (e) {
		if (!e.target.classList || !e.target.classList.contains('evsms-phone')) {
			return;
		}
		if (e.inputType === 'insertText' && e.data && /\D/.test(e.data)) {
			e.preventDefault();
		}
	});

	document.addEventListener('input', function (e) {
		var form = e.target.closest('.evsms-form');
		if (!form) {
			return;
		}
		if (e.target.classList.contains('evsms-phone')) {
			formatPhone(e.target);
			// Zabroniony początek sygnalizujemy od razu, a nie dopiero przy wysyłce
			var prefix = blockedPrefix(nationalDigits(e.target.value));
			setFieldError(form, 'phone', prefix ? blockedMessage(prefix) : '');
		}
		if (e.target.name === 'consent') {
			setFieldError(form, 'consent', '');
		}
		setMessage(form, '');
	});

	// Walidacja po opuszczeniu pola – tylko gdy coś już wpisano
	document.addEventListener('focusout', function (e) {
		if (!e.target.classList || !e.target.classList.contains('evsms-phone') || !e.target.value) {
			return;
		}
		var form = e.target.closest('.evsms-form');
		setFieldError(form, 'phone', validate(form).phone || '');
	});

	document.addEventListener('submit', function (e) {
		var form = e.target.closest('.evsms-form');
		if (!form) {
			return;
		}
		e.preventDefault();

		if (form.classList.contains('is-loading')) {
			return;
		}

		var errors = validate(form);
		setFieldError(form, 'phone', errors.phone || '');
		setFieldError(form, 'consent', errors.consent || '');
		setMessage(form, '');

		var firstInvalid = form.querySelector('[aria-invalid="true"]');
		if (firstInvalid) {
			firstInvalid.focus();
			return;
		}

		setLoading(form, true);

		// Najpierw świeży nonce (strona może pochodzić z cache), potem właściwe żądanie
		post(new URLSearchParams({ action: 'evsms_nonce' }))
			.then(function (res) {
				var data = new FormData(form);
				data.set('phone', nationalDigits(data.get('phone')));
				data.append('action', 'evsms_request');
				data.append('nonce', res && res.data ? res.data.nonce : '');
				return post(data);
			})
			.then(function (res) {
				var data = (res && res.data) || {};
				if (res && res.success) {
					showSuccess(form, data);
					return;
				}
				var text = data.message || msg.generic;
				if (data.field && setFieldError(form, data.field, text)) {
					fieldEl(form, data.field).querySelector('input').focus();
				} else {
					setMessage(form, text);
				}
			})
			.catch(function () {
				setMessage(form, msg.generic);
			})
			.finally(function () {
				setLoading(form, false);
			});
	});
})();
