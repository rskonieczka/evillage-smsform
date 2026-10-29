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
		// W trakcie odliczania (flood z urządzenia) przycisk zostaje zablokowany
		button.disabled = loading || form.classList.contains('is-cooldown');
	}

	/* Urządzenie: zapamiętany odbiór kuponu --------------------------------- */

	var CLAIM_KEY = 'evsmsClaimedUntil';

	function markClaimed(days) {
		if (!days) {
			return;
		}
		try {
			localStorage.setItem(CLAIM_KEY, String(Date.now() + days * 86400000));
		} catch (e) {}
	}

	function isClaimed() {
		try {
			return Number(localStorage.getItem(CLAIM_KEY) || 0) > Date.now();
		} catch (e) {
			return false;
		}
	}

	/* Obdarowanie: udostępnij link do strony z formularzem -------------------- */

	function showGift(wrap) {
		var box = wrap.querySelector('.evsms-gift');
		if (box && evsmsData.gift && evsmsData.gift.enabled) {
			box.hidden = false;
		}
	}

	function shareUrl(wrap) {
		return wrap.getAttribute('data-share-url') || (location.origin + location.pathname);
	}

	function setGiftStatus(wrap, text) {
		wrap.querySelector('.evsms-gift-status').textContent = text;
	}

	document.addEventListener('click', function (e) {
		var button = e.target.closest('.evsms-gift-btn');
		if (!button) {
			return;
		}
		var wrap = button.closest('.evsms');
		var url = shareUrl(wrap);

		// Telefon: systemowe okno udostępniania; komputer: kopiowanie do schowka
		if (navigator.share) {
			navigator.share({ title: document.title, text: evsmsData.gift.shareText, url: url }).catch(function () {});
			return;
		}
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(function () {
				setGiftStatus(wrap, evsmsData.gift.copied);
			}, function () {
				setGiftStatus(wrap, url);
			});
			return;
		}
		setGiftStatus(wrap, url);
	});

	/* Konwersje ---------------------------------------------------------------- */

	var SOURCE_KEY = 'evsmsSource';

	// gclid i UTM znikają z adresu po pierwszej podstronie – zapamiętujemy je na czas sesji
	function readSource() {
		var params = new URLSearchParams(window.location.search);
		var found = {};
		['gclid', 'utm_source', 'utm_medium', 'utm_campaign'].forEach(function (key) {
			if (params.get(key)) {
				found[key] = params.get(key);
			}
		});
		try {
			if (Object.keys(found).length) {
				sessionStorage.setItem(SOURCE_KEY, JSON.stringify(found));
				return found;
			}
			return JSON.parse(sessionStorage.getItem(SOURCE_KEY) || '{}') || {};
		} catch (e) {
			return found;
		}
	}

	function addSource(data) {
		var source = readSource();
		data.set('src_page', window.location.pathname);
		Object.keys(source).forEach(function (key) {
			data.set('src_' + key, source[key]);
		});
	}

	// Wywoływane wyłącznie po sukcesie z serwera, czyli po wysłaniu SMS-a z kuponem
	function trackConversion(conv, form) {
		var cfg = evsmsData.tracking || {};
		if (!conv || !conv.transactionId) {
			return;
		}

		window.dataLayer = window.dataLayer || [];
		var layerEvent = { event: 'evsms_coupon_claimed', transaction_id: conv.transactionId };
		if (cfg.value > 0) {
			layerEvent.value = cfg.value;
			layerEvent.currency = cfg.currency || 'PLN';
		}
		window.dataLayer.push(layerEvent);

		// Układ z samym GTM (bez gtag): zdarzenie w dataLayer jest zgłoszeniem, które obsłuży kontener
		var reported = !!window.google_tag_manager;
		if (typeof window.gtag === 'function') {
			if (cfg.enhanced) {
				var digits = ((form.querySelector('.evsms-phone') || {}).value || '').replace(/\D+/g, '');
				if (digits.length === 9) {
					window.gtag('set', 'user_data', { phone_number: '+48' + digits });
				}
			}
			if (cfg.sendTo) {
				var payload = { send_to: cfg.sendTo, transaction_id: conv.transactionId };
				if (cfg.value > 0) {
					payload.value = cfg.value;
					payload.currency = cfg.currency || 'PLN';
				}
				window.gtag('event', 'conversion', payload);
				reported = true;
			}
			if (cfg.ga4Event) {
				window.gtag('event', cfg.ga4Event, { method: 'sms_coupon', transaction_id: conv.transactionId });
				reported = true;
			}
		}

		// Zapis sygnału przekazania zdarzenia do warstwy tagowania (nie potwierdza odbioru przez Google)
		if (reported) {
			post(new URLSearchParams({ action: 'evsms_conv', id: conv.id, token: conv.token })).catch(function () {});
		}
	}

	/* Stany końcowe ------------------------------------------------------------ */

	function showSuccess(form, data) {
		var wrap = form.closest('.evsms');
		var box = wrap.querySelector('.evsms-success');

		box.querySelector('.evsms-success-title').textContent = data.message || '';
		box.querySelector('.evsms-success-meta').textContent = data.meta || '';

		form.hidden = true;
		box.hidden = false;
		wrap.classList.add('is-done');
		if (data.gift) {
			showGift(wrap);
		}
		markClaimed(data.claimedDays);
		box.focus();
	}

	function showClaimed(wrap, focus) {
		var form = wrap.querySelector('.evsms-form');
		var box = wrap.querySelector('.evsms-claimed');
		if (!form || !box) {
			return;
		}
		form.hidden = true;
		box.hidden = false;
		wrap.classList.add('is-done');
		showGift(wrap);
		if (focus) {
			box.focus();
		}
	}

	/**
	 * Odliczanie po zbyt szybkiej kolejnej próbie z tego samego urządzenia.
	 */
	function startCooldown(form, seconds) {
		var button = form.querySelector('.evsms-submit');
		var left = Math.max(1, Math.ceil(seconds));

		form.classList.add('is-cooldown');
		button.disabled = true;

		(function tick() {
			if (left <= 0) {
				form.classList.remove('is-cooldown');
				button.disabled = false;
				setMessage(form, '');
				return;
			}
			setMessage(form, msg.deviceWait.split('{seconds}').join(left));
			left--;
			setTimeout(tick, 1000);
		})();
	}

	// Kupon już odebrany na tym urządzeniu – od razu pokazujemy stan końcowy zamiast formularza
	if (isClaimed()) {
		document.querySelectorAll('.evsms').forEach(function (wrap) {
			showClaimed(wrap, false);
		});
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
		// (przy okazji serwer wydaje ciasteczko urządzenia – bez niego wysyłka nie zostanie przyjęta)
		post(new URLSearchParams({ action: 'evsms_nonce' }))
			.then(function (res) {
				if (!res || !res.success) {
					return res; // np. zbyt wiele nowych urządzeń z tego IP
				}
				var data = new FormData(form);
				data.set('phone', nationalDigits(data.get('phone')));
				addSource(data);
				data.append('action', 'evsms_request');
				data.append('nonce', res.data.nonce);
				return post(data);
			})
			.then(function (res) {
				var data = (res && res.data) || {};
				var wrap = form.closest('.evsms');

				if (res && res.success) {
					showSuccess(form, data);
					trackConversion(data.conversion, form);
					return;
				}
				if (data.code === 'device_claimed') {
					markClaimed(evsmsData.deviceDays);
					showClaimed(wrap, true);
					return;
				}
				if (data.code === 'cooldown') {
					startCooldown(form, data.retry_after);
					return;
				}

				var text = data.message || msg.generic;
				if (data.field && setFieldError(form, data.field, text)) {
					fieldEl(form, data.field).querySelector('input').focus();
				} else {
					setMessage(form, text);
				}
				// Np. „ten numer otrzymał już kupon” – podpowiadamy, jak obdarować kogoś innego
				if (data.gift) {
					showGift(wrap);
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
