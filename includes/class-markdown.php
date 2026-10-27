<?php
/**
 * Minimalny konwerter Markdown → HTML dla zakładki „Dokumentacja” (README.md wtyczki).
 *
 * Obsługuje to, czego używa README: nagłówki (z kotwicami jak na GitHubie), akapity, listy,
 * tabele, bloki kodu, cytaty, linie poziome, `kod`, **pogrubienie** i [linki](url).
 * Cały tekst jest najpierw escapowany – surowy HTML z pliku nie trafia na stronę.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EVSMS_Markdown {

	/**
	 * Zamienia Markdown na bezpieczny HTML.
	 */
	public static function render( $markdown ) {
		$lines = preg_split( '/\R/u', (string) $markdown );
		$html  = '';
		$count = count( $lines );

		for ( $i = 0; $i < $count; $i++ ) {
			$line = $lines[ $i ];

			// Blok kodu ```
			if ( str_starts_with( ltrim( $line ), '```' ) ) {
				$code = array();
				for ( $i++; $i < $count && ! str_starts_with( ltrim( $lines[ $i ] ), '```' ); $i++ ) {
					$code[] = $lines[ $i ];
				}
				$html .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . "</code></pre>\n";
				continue;
			}

			if ( '' === trim( $line ) ) {
				continue;
			}

			// Nagłówek
			if ( preg_match( '/^(#{1,6})\s+(.*)$/u', $line, $m ) ) {
				$level = strlen( $m[1] );
				$html .= sprintf( "<h%d id=\"%s\">%s</h%d>\n", $level, esc_attr( self::slug( $m[2] ) ), self::inline( $m[2] ), $level );
				continue;
			}

			// Linia pozioma
			if ( preg_match( '/^(-{3,}|\*{3,})$/u', trim( $line ) ) ) {
				$html .= "<hr>\n";
				continue;
			}

			// Tabela
			if ( str_starts_with( ltrim( $line ), '|' ) ) {
				$rows = array();
				for ( ; $i < $count && str_starts_with( ltrim( $lines[ $i ] ), '|' ); $i++ ) {
					$rows[] = $lines[ $i ];
				}
				$i--;
				$html .= self::table( $rows );
				continue;
			}

			// Cytat
			if ( str_starts_with( $line, '>' ) ) {
				$quote = array();
				for ( ; $i < $count && str_starts_with( $lines[ $i ], '>' ); $i++ ) {
					$quote[] = ltrim( substr( $lines[ $i ], 1 ) );
				}
				$i--;
				$html .= '<blockquote><p>' . self::inline( implode( ' ', $quote ) ) . "</p></blockquote>\n";
				continue;
			}

			// Lista punktowana / numerowana (wiersze z wcięciem to ciąg dalszy punktu)
			if ( preg_match( '/^\s*([-*]|\d+\.)\s+/u', $line, $m ) ) {
				$tag   = ctype_digit( rtrim( $m[1], '.' ) ) ? 'ol' : 'ul';
				$items = array();
				for ( ; $i < $count; $i++ ) {
					if ( preg_match( '/^\s*([-*]|\d+\.)\s+(.*)$/u', $lines[ $i ], $item ) ) {
						$items[] = $item[2];
					} elseif ( '' !== trim( $lines[ $i ] ) && preg_match( '/^\s{2,}\S/u', $lines[ $i ] ) && $items ) {
						$items[ count( $items ) - 1 ] .= ' ' . trim( $lines[ $i ] );
					} else {
						break;
					}
				}
				$i--;
				$html .= "<{$tag}>";
				foreach ( $items as $item ) {
					$html .= '<li>' . self::inline( $item ) . '</li>';
				}
				$html .= "</{$tag}>\n";
				continue;
			}

			// Akapit – do pustej linii albo początku innego bloku
			$para = array( trim( $line ) );
			for ( $i++; $i < $count; $i++ ) {
				$next = $lines[ $i ];
				if ( '' === trim( $next ) || preg_match( '/^(#{1,6}\s|\s*([-*]|\d+\.)\s|\||>|```)/u', $next ) ) {
					break;
				}
				$para[] = trim( $next );
			}
			$i--;
			$html .= '<p>' . self::inline( implode( ' ', $para ) ) . "</p>\n";
		}

		return $html;
	}

	/**
	 * Kotwica nagłówka zgodna z GitHubem: małe litery, bez interpunkcji, spacja → myślnik.
	 */
	public static function slug( $text ) {
		$text = mb_strtolower( wp_strip_all_tags( preg_replace( '/[`*]/u', '', $text ) ) );
		$text = preg_replace( '/[^\p{L}\p{N}\s_-]/u', '', $text );
		return str_replace( ' ', '-', trim( $text ) );
	}

	private static function table( $rows ) {
		$cells = function ( $row ) {
			return array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) );
		};

		$head = $cells( array_shift( $rows ) );
		if ( $rows && preg_match( '/^[\s|:-]+$/u', $rows[0] ) ) {
			array_shift( $rows ); // wiersz |---|---|
		}

		$html = '<table class="widefat striped"><thead><tr>';
		foreach ( $head as $cell ) {
			$html .= '<th>' . self::inline( $cell ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $cells( $row ) as $cell ) {
				$html .= '<td>' . self::inline( $cell ) . '</td>';
			}
			$html .= '</tr>';
		}

		return $html . "</tbody></table>\n";
	}

	/**
	 * Formatowanie w linii: `kod`, [link](url), **pogrubienie**. Tekst jest escapowany przed formatowaniem.
	 */
	private static function inline( $text ) {
		$codes = array();
		$text  = preg_replace_callback( '/`([^`]+)`/u', function ( $m ) use ( &$codes ) {
			$codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
			return "\x00" . ( count( $codes ) - 1 ) . "\x00";
		}, $text );

		$text = esc_html( $text );

		$text = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/u', function ( $m ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES );
			if ( str_starts_with( $url, '#' ) ) {
				return '<a href="#' . esc_attr( substr( $url, 1 ) ) . '">' . $m[1] . '</a>';
			}
			// Tylko http(s) i mailto – inne protokoły (np. javascript:) zostają zwykłym tekstem
			if ( ! preg_match( '#^(https?:|mailto:)#i', $url ) ) {
				return $m[1];
			}
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
		}, $text );

		$text = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text );

		return preg_replace_callback( "/\x00(\d+)\x00/", function ( $m ) use ( $codes ) {
			return $codes[ (int) $m[1] ];
		}, $text );
	}
}
