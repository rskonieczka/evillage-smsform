# EVILLAGE.PL – SMS Form (kupon rabatowy)

Wtyczka WordPress z formularzem, w którym klient podaje numer telefonu i dostaje **kupon rabatowy SMS-em** przez [SMSAPI.pl](https://www.smsapi.pl/). Po udanej wysyłce administrator dostaje e-mail z kolejnym **numerem vouchera** (np. `EV-000123`), a każde pobranie trafia do rejestru w bazie.

| | |
|---|---|
| Wersja | 1.2.1 |
| Wymagany WordPress | 6.0+ |
| Wymagane PHP | 8.0+ |
| Bramka SMS | SMSAPI.pl (konto i token OAuth) |
| Obsługiwane numery | polskie numery komórkowe (+48) |
| Język interfejsu | polski |
| Autor | [Rafał Skonieczka / EVILLAGE.PL](https://evillage.pl) |

## Spis treści

- [Funkcje](#funkcje)
- [Wymagania](#wymagania)
- [Instalacja](#instalacja)
- [Szybki start](#szybki-start)
- [Wstawianie formularza](#wstawianie-formularza)
- [Panel ustawień](#panel-ustawień)
- [Znaczniki w treściach](#znaczniki-w-treściach)
- [Zabronione numery](#zabronione-numery)
- [Ścieżka klienta](#ścieżka-klienta)
- [Jak to działa po stronie serwera](#jak-to-działa-po-stronie-serwera)
- [Bezpieczeństwo i ochrona przed nadużyciami](#bezpieczeństwo-i-ochrona-przed-nadużyciami)
- [Dla programistów](#dla-programistów)
- [Usługi zewnętrzne](#usługi-zewnętrzne)
- [Prywatność i RODO](#prywatność-i-rodo)
- [Dane w bazie i odinstalowanie](#dane-w-bazie-i-odinstalowanie)
- [Ograniczenia](#ograniczenia)
- [FAQ / rozwiązywanie problemów](#faq--rozwiązywanie-problemów)
- [Struktura plików](#struktura-plików)
- [Historia zmian](#historia-zmian)
- [Licencja](#licencja)

## Funkcje

- Formularz jako **blok „SMS Kupon”** (edytor blokowy) lub **shortcode** `[evillage_smsform]`.
- Wysyłka SMS przez **SMSAPI.pl** (REST, OAuth Bearer) z automatycznym przełączeniem na zapasowy endpoint przy awarii.
- **Tryb testowy** – SMSAPI waliduje żądanie, ale nie wysyła SMS-a i nie pobiera punktów (domyślnie włączony).
- Kod kuponu **unikalny** (8 znaków bez mylących 0/O, 1/I, z prefiksem) albo **stały** dla wszystkich.
- **Numer vouchera z autonumeracji** bazy – atomowy, bez duplikatów także przy równoległych żądaniach.
- **E-mail HTML** do administratora po każdym wysłanym kuponie.
- Rejestr voucherów w panelu: liczniki wysłanych / nieudanych i 50 ostatnich wpisów.
- Ochrona: jeden kupon na numer, blokada numerów premium / infolinii / VoIP / stacjonarnych, limit prób z IP, honeypot, nonce odporny na cache strony, blokada równoległych wysyłek na ten sam numer.
- Wszystkie teksty widoczne dla klienta edytowalne w panelu.
- **15 gotowych zestawów kolorów** (kontrast min. 4.5:1, WCAG AA), 8 kolorów do ręcznej zmiany, ikona nagłówka, zaokrąglenia, podgląd na żywo.
- Dostępność: etykiety, `aria-invalid`, komunikaty błędów powiązane z polami, `role="alert"`, przenoszenie fokusu na potwierdzenie.
- Responsywny układ – w wąskim kontenerze (telefon, sidebar) przycisk przechodzi pod pole.
- Zasoby (CSS/JS) ładowane tylko na stronach, na których jest formularz.

## Wymagania

- WordPress 6.0 lub nowszy, PHP 8.0 lub nowszy.
- MySQL / MariaDB (wtyczka używa `GET_LOCK()` do blokady równoległych wysyłek).
- Konto w [SMSAPI.pl](https://www.smsapi.pl/) z doładowanymi punktami i **tokenem OAuth** z uprawnieniem do wysyłki SMS.
- Działająca wysyłka poczty z WordPressa (`wp_mail`) – zalecana wtyczka SMTP, inaczej e-maile o voucherach mogą nie dochodzić.
- Opcjonalnie: zarejestrowane w SMSAPI **pole nadawcy** (nazwa zamiast numeru).

## Instalacja

1. Wgraj katalog `evillage-smsform` do `wp-content/plugins/` albo zainstaluj archiwum ZIP przez **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
2. Włącz wtyczkę w **Wtyczki**. Aktywacja tworzy tabelę `{prefix}evsms_vouchers` i zapisuje domyślne ustawienia.
3. Przejdź do **Ustawienia → SMS Kupon**.

Po aktualizacji wtyczki bez ponownej aktywacji schemat tabeli aktualizuje się sam (porównanie `evsms_db_version`).

## Szybki start

1. W SMSAPI wygeneruj token OAuth (Panel klienta → API → Tokeny API) z uprawnieniem do SMS.
2. **Ustawienia → SMS Kupon → SMS**: wklej token, ustaw treść SMS-a (np. `{site}: Twoj kod rabatowy to {code}`).
3. **Kupon i ochrona**: wybierz kod unikalny lub stały, prefiks kodu i numeru vouchera.
4. **Powiadomienia**: wpisz adres(y), na które ma przychodzić e-mail o pobranym voucherze.
5. Wstaw blok **„SMS Kupon”** na stronę i wyślij próbne zgłoszenie – w **trybie testowym** SMS nie wychodzi, ale voucher i e-mail są tworzone normalnie (e-mail ma adnotację o trybie testowym).
6. Gdy wszystko działa, **wyłącz tryb testowy**. Dopóki jest włączony, w panelu administratora widać ostrzeżenie.

### Token poza bazą danych (zalecane)

Zamiast zapisywać token w ustawieniach, dodaj do `wp-config.php`:

```php
define( 'EVSMS_SMSAPI_TOKEN', 'twoj-token-oauth' );
```

Stała ma pierwszeństwo przed polem w panelu, a panel pokazuje wtedy informację, że token pochodzi z `wp-config.php`.

## Wstawianie formularza

### Blok

W edytorze blokowym dodaj blok **„SMS Kupon”** (kategoria *Widżety*, słowa kluczowe: sms, kupon, rabat, voucher). W panelu bocznym możesz nadpisać tytuł, podtytuł, treść przycisku i kolor przycisku. Puste pola = wartości z panelu ustawień. Podgląd w edytorze jest renderowany po stronie serwera, więc wygląda jak na stronie.

### Shortcode

```text
[evillage_smsform]
[evillage_smsform title="Odbierz -10%" subtitle="Kod przyjdzie SMS-em" button="Wyślij kod" color="#e11d48"]
```

| Atrybut | Opis |
|---|---|
| `title` | tytuł formularza |
| `subtitle` | podtytuł |
| `button` | treść przycisku |
| `color` | kolor przycisku i akcentów (HEX) |

Pominięty lub pusty atrybut = wartość z panelu ustawień. Na jednej stronie może być kilka formularzy.

## Panel ustawień

**Ustawienia → SMS Kupon** (wymaga uprawnienia `manage_options`). Z listy wtyczek prowadzi tam też link „Ustawienia”.

| Zakładka | Zawartość |
|---|---|
| SMS | token, pole nadawcy, tryb testowy, treść SMS z podglądem „dymka” i licznikiem znaków/części, zamiana polskich znaków (`normalize=1` – SMS mieści 160 znaków zamiast 70) |
| Kupon i ochrona | kod unikalny/stały, prefiks kodu, prefiks numeru vouchera, jeden kupon na numer, zabronione numery (gotowe grupy + własne początki), limit prób z jednego IP na godzinę |
| Komunikaty | wszystkie teksty dla klienta: tytuł, podtytuł, etykieta, treść przycisku (także w trakcie wysyłki), zgoda, ekran sukcesu, każdy komunikat błędu |
| Wygląd | 15 zestawów kolorów, 8 kolorów do ręcznej zmiany, ikona nagłówka (10 wbudowanych, własny obrazek z biblioteki mediów albo brak), zaokrąglenie ramki oraz pól i przycisku (0–40 px), podgląd na żywo |
| Powiadomienia | adresy e-mail, temat e-maila |
| Vouchery | liczniki wysłanych/nieudanych i 50 ostatnich voucherów |

Uwagi:

- Puste pole komunikatu przywraca treść domyślną.
- Puste pole tekstu zgody **wyłącza checkbox zgody** w formularzu.
- Puste pole koloru = kolor z palety motywu. Wtyczka korzysta ze zmiennych CSS motywu Blocksy (`--theme-palette-color-*`), a w innych motywach używa własnych kolorów zapasowych. Zestaw „Kolory motywu” czyści wszystkie kolory.
- Limit prób z IP = `0` wyłącza limit.

### Ustawienia domyślne

| Ustawienie | Wartość domyślna |
|---|---|
| Tryb testowy | włączony |
| Zamiana polskich znaków | włączona |
| Treść SMS | `{site}: Twoj kod rabatowy to {code}. Voucher nr {voucher}. Podaj kod przy zamowieniu.` |
| Kod | unikalny, prefiks `RABAT-` |
| Prefiks vouchera | `EV-` |
| E-mail powiadomień | adres administratora witryny |
| Jeden kupon na numer | włączone |
| Zabronione numery | wszystkie grupy |
| Limit prób z IP | 3 na godzinę |

## Znaczniki w treściach

| Gdzie | Znaczniki |
|---|---|
| Treść SMS | `{code}` – kod kuponu, `{voucher}` – numer vouchera, `{site}` – nazwa witryny |
| Temat e-maila | `{code}`, `{voucher}`, `{site}` |
| Opis ekranu sukcesu | `{phone}` – zamaskowany numer, np. `+48 600 *** 200` |
| Błąd zabronionego numeru | `{prefix}` – dopasowany początek numeru, np. `70` |

## Zabronione numery

Domyślnie włączone są wszystkie grupy:

- **70x** – numery o podwyższonej opłacie (700, 701, 703…),
- **80x** – infolinie (800 bezpłatne, 801 wspólnych kosztów…),
- **39x** – numery VoIP / nomadyczne,
- **numery stacjonarne** – 49 stref numeracyjnych (12, 22, 32, 42, 58, 61, 71…), które nie odbierają SMS-ów.

Numery komórkowe (45, 50, 51, 53, 57, 60, 66, 69, 72, 73, 78, 79, 88) nie należą do żadnej grupy. Można dopisać własne początki numerów (bez `+48`). Blokada działa w przeglądarce (komunikat już podczas wpisywania) i na serwerze.

## Ścieżka klienta

1. Pole z nieedytowalnym **+48** i maską `600 100 200` (tylko cyfry; wklejone `+48…` / `0048…` jest czyszczone).
2. Walidacja przy polu, błąd zabronionego początku numeru pojawia się już przy pisaniu.
3. Wysyłka: przycisk zmienia treść na „Wysyłanie…” ze spinnerem, zachowuje szerokość i blokuje podwójne kliknięcie.
4. Sukces: formularz zamienia się w potwierdzenie z zamaskowanym numerem.
5. Błędy pokazują się przy właściwym polu (telefon, zgoda) albo pod formularzem.

## Jak to działa po stronie serwera

1. Formularz pobiera **świeży nonce przez AJAX** (`evsms_nonce`), więc działa także na stronach z pamięci podręcznej (LiteSpeed Cache, WP Rocket itp.).
2. Żądanie `evsms_request` przechodzi kontrole: nonce → honeypot → normalizacja numeru do `48XXXXXXXXX` → zabronione początki → zgoda → limit prób z IP.
3. `GET_LOCK` na numer telefonu (brak podwójnej wysyłki), opcjonalnie sprawdzenie „jeden kupon na numer”.
4. Zapis wiersza `pending` – **numer vouchera = AUTO_INCREMENT** tabeli, formatowany jako prefiks + 6 cyfr.
5. `POST https://api.smsapi.pl/sms.do` (`format=json`, `encoding=utf-8`, `skip_foreign=1`, opcjonalnie `normalize=1`, `from`, `test=1`). Przy błędzie transportu lub odpowiedzi 5xx – ponowienie na `https://api2.smsapi.pl/sms.do`.
6. Sukces → status `sent`, e-mail do administratora, akcja `evsms_voucher_sent`.
   Błąd → status `failed` z opisem błędu (numer zostaje w rejestrze, więc luka w numeracji jest audytowalna), klient widzi komunikat „SMS nie został wysłany”.

## Bezpieczeństwo i ochrona przed nadużyciami

Każdy wysłany SMS kosztuje, dlatego formularz ma kilka warstw ochrony:

- **nonce** WordPressa dla każdego zgłoszenia,
- **honeypot** – bot, który wypełni ukryte pole, dostaje fałszywy sukces, a SMS nie jest wysyłany,
- **limit prób z jednego IP** w oknie godzinowym (adres IP zapisywany jako HMAC-SHA256, nie jawnie),
- **jeden kupon na numer** (liczą się tylko vouchery o statusie `sent`),
- **blokada numerów** premium, infolinii, VoIP i stacjonarnych,
- **blokada MySQL** na numer – dwa równoległe żądania na ten sam numer nie wyślą dwóch SMS-ów,
- `skip_foreign=1` – SMSAPI odrzuca numery zagraniczne,
- wszystkie dane wyjściowe są escapowane, ustawienia sanityzowane, panel dostępny tylko dla `manage_options`.

Zalecenia: trzymaj token w `wp-config.php`, ustaw w SMSAPI limit dzienny / alert stanu konta, a przy dużym ruchu dodaj CAPTCHA lub ochronę typu WAF na `admin-ajax.php`.

> Uwaga: za serwerem proxy / CDN (Cloudflare, load balancer) `REMOTE_ADDR` może być adresem proxy – wtedy limit z IP obejmuje wszystkich klientów naraz. Skonfiguruj serwer tak, żeby przekazywał prawdziwy adres klienta (np. `mod_remoteip`, `real_ip` w nginx).

## Dla programistów

### Akcja `evsms_voucher_sent`

Wywoływana po udanym wysłaniu SMS-a i e-maila. Dobre miejsce na integrację, np. utworzenie kuponu WooCommerce, zapis do CRM czy wysłanie zdarzenia do analityki.

```php
/**
 * @param int    $id     Autonumer vouchera (ID wiersza).
 * @param string $number Sformatowany numer vouchera, np. EV-000123.
 * @param string $phone  Numer telefonu w formacie 48XXXXXXXXX.
 * @param string $code   Kod kuponu wysłany SMS-em.
 */
add_action( 'evsms_voucher_sent', function ( $id, $number, $phone, $code ) {
	// Przykład: kupon WooCommerce -10% na jedno użycie (dla trybu „kod unikalny”)
	if ( ! class_exists( 'WC_Coupon' ) || wc_get_coupon_id_by_code( $code ) ) {
		return;
	}
	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->set_usage_limit( 1 );
	$coupon->set_description( 'Voucher ' . $number );
	$coupon->save();
}, 10, 4 );
```

### Stałe

| Stała | Opis |
|---|---|
| `EVSMS_SMSAPI_TOKEN` | token OAuth SMSAPI (w `wp-config.php`), ma pierwszeństwo przed ustawieniem w panelu |

### Endpointy AJAX

| Akcja | Metoda | Opis |
|---|---|---|
| `evsms_nonce` | `admin-ajax.php` | zwraca świeży nonce (nagłówki `nocache`) |
| `evsms_request` | `admin-ajax.php`, POST | pola: `nonce`, `phone`, `consent`, `website` (honeypot); odpowiedź JSON `{ success, data: { message, meta \| field } }` |

Kody HTTP błędów: `400` – walidacja, `403` – nonce, `409` – trwa wysyłka na ten numer, `429` – limit prób.

### Blok

Nazwa bloku: `evillage/smsform` (render po stronie serwera, atrybuty `title`, `subtitle`, `button`, `color`).

### Style

Wygląd opiera się na zmiennych CSS w klasie `.evsms`, więc można go nadpisać w motywie:

```css
.evsms {
	--evsms-accent: #e11d48;
	--evsms-radius: 8px;
	--evsms-card-radius: 12px;
}
```

Dostępne zmienne: `--evsms-accent`, `--evsms-on-accent`, `--evsms-text`, `--evsms-bg`, `--evsms-input-bg`, `--evsms-border`, `--evsms-error`, `--evsms-success`, `--evsms-radius`, `--evsms-card-radius`.

## Usługi zewnętrzne

Wtyczka łączy się z usługą zewnętrzną **SMSAPI.pl** (LINK Mobility Poland sp. z o.o.) wyłącznie w momencie, gdy klient wyśle formularz:

- **Co jest wysyłane:** numer telefonu klienta, treść SMS-a (z kodem kuponu i numerem vouchera), opcjonalnie pole nadawcy oraz token API.
- **Dokąd:** `https://api.smsapi.pl/sms.do`, zapasowo `https://api2.smsapi.pl/sms.do`.
- **Po co:** dostarczenie kuponu SMS-em.
- Regulamin: <https://www.smsapi.pl/regulamin>
- Polityka prywatności: <https://www.smsapi.pl/polityka-prywatnosci>

Wtyczka nie wysyła żadnych innych danych na zewnątrz, nie ładuje zewnętrznych skryptów ani czcionek i nie zbiera statystyk.

## Prywatność i RODO

Wtyczka przetwarza dane osobowe (numer telefonu). Administrator strony odpowiada za:

- podstawę prawną i **treść zgody** (zakładka Komunikaty) – dostosuj ją do swojej polityki prywatności, możesz w niej wstawić link (dozwolony HTML),
- informację w polityce prywatności, że numer telefonu jest przekazywany do SMSAPI.pl w celu wysyłki SMS-a,
- umowę powierzenia przetwarzania danych z SMSAPI,
- okres przechowywania danych w rejestrze voucherów.

Co jest zapisywane w bazie: numer telefonu **jawnie** (potrzebny do blokady „jeden kupon na numer”), kod kuponu, status i identyfikator wysyłki SMSAPI, opis błędu, **skrót HMAC adresu IP** (bez możliwości odczytania adresu) i data. Numer telefonu trafia także do e-maila powiadomienia.

Wtyczka nie integruje się (jeszcze) z narzędziami eksportu / usuwania danych osobowych WordPressa – żądania usunięcia danych trzeba obsłużyć ręcznie w tabeli `{prefix}evsms_vouchers`.

## Dane w bazie i odinstalowanie

| Miejsce | Zawartość |
|---|---|
| tabela `{prefix}evsms_vouchers` | rejestr voucherów (`id`, `phone`, `code`, `status`, `sms_id`, `error`, `ip_hash`, `created_at`) |
| opcja `evsms_db_version` | wersja schematu tabeli |
| opcja z ustawieniami wtyczki | wszystkie ustawienia z panelu |
| transienty `evsms_rl_*` | liczniki limitu prób z IP (wygasają po godzinie) |

Wyłączenie ani usunięcie wtyczki **nie usuwa tabeli voucherów ani ustawień** – rejestr ma wartość księgową. Aby usunąć dane całkowicie, po odinstalowaniu usuń tabelę i opcje ręcznie (np. w phpMyAdmin lub WP-CLI: `wp db query "DROP TABLE wp_evsms_vouchers"`, `wp option delete evsms_db_version`).

## Ograniczenia

- Tylko **polskie numery komórkowe** (+48); wysyłka zagraniczna jest celowo zablokowana.
- Tylko bramka **SMSAPI.pl**.
- Brak realizacji / unieważniania kuponu i integracji z WooCommerce „z pudełka” – można je dopiąć przez akcję `evsms_voucher_sent`.
- Interfejs i komunikaty tylko po polsku (teksty dla klienta można zmienić w panelu); wtyczka nie ma jeszcze plików tłumaczeń.
- Rejestr w panelu pokazuje 50 ostatnich voucherów (bez eksportu CSV i wyszukiwania).
- Brak CAPTCHA – ochronę przed botami zapewniają honeypot, limit z IP i blokady numerów.

## FAQ / rozwiązywanie problemów

**SMS nie przychodzi, a formularz pokazuje sukces.**
Sprawdź, czy **tryb testowy** jest wyłączony – w trybie testowym SMSAPI nie wysyła wiadomości. Dopóki jest włączony, w panelu widać ostrzeżenie.

**Klient widzi „Nie udało się wysłać SMS-a”.**
Szczegółowy błąd SMSAPI jest zapisany przy voucherze w zakładce **Vouchery** (np. brak punktów, błędny token, niezarejestrowane pole nadawcy).

**„Sesja formularza wygasła”.**
Strona jest z cache, a żądanie `admin-ajax.php?action=evsms_nonce` jest blokowane lub cache'owane. Wyklucz `admin-ajax.php` z cache i z reguł firewalla.

**Wszyscy dostają „Zbyt wiele prób”.**
Serwer widzi adres proxy zamiast adresu klienta – zob. uwagę w sekcji [Bezpieczeństwo](#bezpieczeństwo-i-ochrona-przed-nadużyciami) albo zwiększ / wyłącz limit (0).

**Nie przychodzi e-mail o voucherze.**
Sprawdź adres w zakładce **Powiadomienia** i konfigurację poczty (zalecana wtyczka SMTP). Nieudana wysyłka jest zapisywana w logu błędów PHP.

**Polskie znaki w SMS-ie.**
Z włączoną opcją „zamiana polskich znaków” jeden SMS mieści 160 znaków; bez niej – 70 (kodowanie Unicode). Licznik w panelu pokazuje liczbę części.

## Struktura plików

```text
evillage-smsform/
├── evillage-smsform.php        # nagłówek wtyczki, autoloader, aktywacja
├── includes/
│   ├── class-settings.php      # panel ustawień, wartości domyślne, sanityzacja
│   ├── class-form.php          # shortcode, blok, AJAX, wydawanie voucherów, e-mail
│   ├── class-smsapi.php        # klient SMSAPI.pl
│   └── class-vouchers.php      # tabela i rejestr voucherów
└── assets/
    ├── smsform.js / .css       # formularz na stronie
    ├── block.js                # blok „SMS Kupon”
    └── admin.js / .css         # panel ustawień i podgląd na żywo
```

## Historia zmian

### 1.2.1

- Aktualna wersja: blok i shortcode, wysyłka przez SMSAPI z zapasowym endpointem, rejestr voucherów z autonumeracją, e-mail do administratora, zabronione numery, 15 zestawów kolorów, ikona nagłówka, podgląd na żywo.

## Licencja

GPLv2 lub nowsza – <https://www.gnu.org/licenses/gpl-2.0.html>.

Copyright © Rafał Skonieczka, [EVILLAGE.PL](https://evillage.pl).
