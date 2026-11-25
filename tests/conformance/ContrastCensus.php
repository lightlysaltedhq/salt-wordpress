<?php
/**
 * The contrast census: every foreground and ground pair the stylesheets draw,
 * read from the CSS.
 *
 * FoundationsConformanceTest measures what this returns, in every display
 * mode, beside the contract's own contrast rules. Until this existed the gate
 * measured only the pairs it had been told about (the contract's, and a short
 * list of salt's own), so text on a ground outside them was never measured
 * (lightlysaltedhq/salt-wordpress#7). Nothing here is a list of pairs: a pair
 * exists because a rule draws it, and a rule added tomorrow is read tomorrow.
 *
 * HOW A PAIR IS READ
 * ------------------
 * A SCOPE is a ground the page can put under a rule, with the custom
 * properties that travel with it:
 *   - the page: the `body` (or `html`) rule's own background;
 *   - every rule that sets `--salt-section-*` and a ground, either as
 *     `--salt-section-bg` (the section tones, the footer's) or as its own
 *     `background` (the consent banner and panel, which bind the properties
 *     to the light roles). A rule that only re-points a property (the media
 *     band's `--salt-section-ink-muted`) draws no ground of its own and is
 *     not a scope; over a photograph no ratio can be stated at all.
 * Under `[data-theme=dark]` the dark rule of a tone replaces its light one
 * (the markup carries both `data-tone` and `data-tone-dark`, and the dark
 * rule is the more specific); a scope with no dark twin applies in both.
 *
 * Each rule that sets `color` or a border colour is then read in every scope:
 * `var()` is followed through the scope's own properties, to the fallback if
 * the property is unset there, to the `--color-*` role it ends at. The ground
 * is the rule's own `background`, when it sets one that resolves to a role,
 * and the scope's otherwise. So `.salt-eyebrow`'s
 * `var(--salt-section-ink-muted, var(--color-ink-muted))` is `ink-muted` on
 * `surface` on the page, `ink-muted` on `brand-tint` in that tone, and
 * `ink-muted-inverse` on `surface-inverse` in the inverted one. A rule that
 * reaches a role without going through a `--salt-section-*` property is read
 * in every scope as well: it does not follow the band, and it is drawn
 * wherever its element can sit.
 *
 * Where a rule's element can sit is read from the contract's fixture pages: a
 * rule whose classes are only ever printed outside a band (the header, the
 * post and archive views) is read on its own ground, or the page's, and every
 * other rule, and any rule no fixture prints, on every scope.
 *
 * The utilities Tailwind built into app.css (`@layer utilities`) are read
 * from the class lists the templates print instead: a utility names a colour
 * and nothing else, so its ground is the `bg-*` utility beside it, else one
 * the same file draws, else the page's.
 *
 * WHAT A PAIR MUST MEET
 * ---------------------
 * - `text`: `color`, 4.5:1 (WCAG 1.4.3).
 * - `boundary`: the border of a control or a structure, 3:1 (WCAG 1.4.11).
 * - `graphic`: `color` on an element only ever printed as an `svg` in the
 *   fixtures, an icon, 3:1 (WCAG 1.4.11). The contract's markup marks every
 *   icon `aria-hidden`, so this is the strict reading; a decorative icon is
 *   outside 1.4.11 and a ratio below 3:1 there is a decision, not a defect
 *   the census can settle.
 * - `hairline`: a border in a role the token layer calls a hairline, a
 *   decorative rule that 1.4.11 does not cover. It is named and its roles
 *   must resolve in every mode, and it is held to no ratio.
 *
 * WHAT IT REFUSES
 * ---------------
 * A rule it cannot measure fails by name rather than being passed over: a
 * literal colour (not a role), a role painted through a property it does not
 * read (`fill`, `text-decoration-color`), a colour under an at-rule that
 * could change it (`prefers-color-scheme`, `@container`), nested rules and
 * unbalanced braces.
 *
 * WHAT IT DOES NOT READ
 * ---------------------
 * `inherit`, `currentColor` and `transparent` (they draw the pair of whatever
 * they sit on, already counted); `outline`, `box-shadow` and `border-image`
 * (the focus indicator, held by the contract's focus pairs and the keyline
 * checks, and a header's band); text over a photograph, where no ratio can be
 * stated; colour a rule takes from outside the stylesheets (a status role
 * painted by a template, an uploaded image); and which element is nested in
 * which. A rule is measured on every ground it could sit on, never fewer.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContrastCensus {

	/**
	 * WCAG 1.4.3, normal text.
	 */
	public const TEXT_FLOOR = 4.5;

	/**
	 * WCAG 1.4.11, the boundary of a control or a structure.
	 */
	public const BOUNDARY_FLOOR = 3.0;

	/**
	 * A decorative hairline has no floor in WCAG. It is named and its roles
	 * must resolve, and a ratio is never below 1.
	 */
	public const HAIRLINE_FLOOR = 1.0;

	/**
	 * Properties that take a role and that the census leaves to other checks:
	 * the focus indicator (the contract's focus pairs and the keyline checks),
	 * and the gradient a case study header paints its band with.
	 */
	private const NOT_READ = array( 'outline', 'outline-color', 'box-shadow', 'border-image' );

	/**
	 * At-rules whose rules are never drawn on a page, and so never read.
	 */
	private const NOT_DRAWN = array( '@keyframes', '@font-face', '@property', '@counter-style', '@page' );

	/**
	 * Every style rule in a stylesheet, in source order, at any depth of
	 * @media, @layer or @supports.
	 *
	 * A colour-bearing rule under an at-rule this census does not know how to
	 * condition fails by name, since it could draw a pair in a state nothing
	 * here models. Width and motion queries, `scripting`, `hover` and layers
	 * condition nothing that changes a colour, and `forced-colors` replaces
	 * every colour with the system's.
	 *
	 * @param string $css  Stylesheet without comments.
	 * @param string $name Stylesheet name, for messages.
	 * @return list<array{selectors: list<string>, decl: array<string, string>, at: list<string>, file: string}>
	 */
	public static function rules( string $css, string $name ): array {
		$rules  = array();
		$stack  = array();
		$buffer = '';
		$length = strlen( $css );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( '"' === $char || "'" === $char ) {
				$end = $i + 1;
				while ( $end < $length && $css[ $end ] !== $char ) {
					$end += '\\' === $css[ $end ] ? 2 : 1;
				}
				$buffer .= substr( $css, $i, $end - $i + 1 );
				$i       = $end;
				continue;
			}

			if ( '{' === $char ) {
				$top = end( $stack );
				if ( false !== $top && ! str_starts_with( $top, '@' ) && '' !== trim( $buffer ) ) {
					self::fail( "{$name} nests a rule inside `{$top}`; this census reads flat rules only." );
				}
				$stack[] = trim( (string) preg_replace( '/\s+/', ' ', $buffer ) );
				$buffer  = '';
				continue;
			}

			if ( '}' === $char ) {
				if ( array() === $stack ) {
					self::fail( "{$name} has an unbalanced `}`." );
				}
				$prelude = (string) array_pop( $stack );
				if ( ! str_starts_with( $prelude, '@' ) ) {
					$at = array_values( array_filter( $stack, static fn( string $frame ): bool => str_starts_with( $frame, '@' ) ) );
					if ( array() === array_filter( $at, static fn( string $frame ): bool => in_array( strtolower( (string) strtok( $frame, ' ' ) ), self::NOT_DRAWN, true ) ) ) {
						$rules[] = array(
							'selectors' => self::split( $prelude, ',' ),
							'decl'      => self::declarations( $buffer ),
							'at'        => $at,
							'file'      => $name,
						);
					}
				}
				$buffer = '';
				continue;
			}

			if ( ';' === $char && ( array() === $stack || str_starts_with( (string) end( $stack ), '@' ) ) ) {
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		if ( array() !== $stack ) {
			self::fail( "{$name} has an unbalanced `{`." );
		}

		foreach ( $rules as $rule ) {
			$colours = array();
			foreach ( $rule['decl'] as $prop => $value ) {
				if ( str_starts_with( $prop, '--' ) ) {
					continue;
				}
				$where = "{$name} sets `{$prop}` in `{$rule['selectors'][0]}`";
				if ( self::is_text( $prop ) || self::is_border( $prop ) || self::is_ground( $prop ) ) {
					$colours[] = $prop;
					// Tailwind's preflight resets a form control's ground to a fully
					// transparent `#0000`, which paints no ground. Its `::placeholder`
					// text is half of the current colour, drawn only on a control that
					// prints a placeholder, which the test guards against.
					$painted = (string) preg_replace( '/#0{4}(?:0{4})?\b/', 'transparent', $value );
					if ( array( '::placeholder' ) !== $rule['selectors'] && 1 === preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark)\(/i', $painted ) ) {
						self::fail( "{$where} to `{$value}`, a literal colour. The census reads a colour only as a role (`var(--color-…)`), and a literal is no role to measure." );
					}
				} elseif ( 1 === preg_match( '/var\(\s*--(?:color-|salt-section-(?:bg|ink|border|boundary))/i', $value ) && ! in_array( $prop, self::NOT_READ, true ) ) {
					self::fail( "{$where} to `{$value}`, which paints a role through a property the census does not read (it reads color, a border's colour and a background). Teach it the property, or it measures nothing there." );
				}
			}
			if ( array() === $colours ) {
				continue;
			}
			foreach ( $rule['at'] as $frame ) {
				$known = 1 === preg_match( '/^@layer\b/i', $frame ) || 1 === preg_match( '/^@supports\b/i', $frame ) || ( 1 === preg_match( '/^@media\b/i', $frame ) && 1 === preg_match( '/width|prefers-reduced-motion|scripting|hover|pointer|forced-colors|print/i', $frame ) );
				if ( ! $known ) {
					self::fail( "{$name} sets a colour in `{$rule['selectors'][0]}` under `{$frame}`, a condition this census does not model." );
				}
			}
		}

		return $rules;
	}

	/**
	 * Every pair the rules draw, as one row per distinct pair.
	 *
	 * @param list<array{selectors: list<string>, decl: array<string, string>, at: list<string>, file: string}> $rules       rules(), every stylesheet.
	 * @param list<list<list<string>>>                                                                         $class_groups Per template file, each class list it prints, as its tokens.
	 * @param bool                                                                                           $dark        Whether `[data-theme=dark]` is in force.
	 * @param list<string>                                                                                   $hairlines   Colour properties that are decorative hairlines.
	 * @param list<string>                                                                                   $fixtures    The contract's fixture pages (HTML files).
	 * @return array<string, array{kind: string, fg: string, bg: string, min: float, from: list<string>}> Keyed `kind fg on bg`.
	 */
	public static function pairs( array $rules, array $class_groups, bool $dark, array $hairlines, array $fixtures ): array {
		$pairs = array();
		$add   = static function ( string $kind, string $fg, string $bg, string $from ) use ( &$pairs, $hairlines ): void {
			if ( 'border' === $kind ) {
				$kind = in_array( $fg, $hairlines, true ) ? 'hairline' : 'boundary';
			}
			$key = "{$kind} {$fg} on {$bg}";
			if ( ! isset( $pairs[ $key ] ) ) {
				$pairs[ $key ] = array(
					'kind' => $kind,
					'fg'   => $fg,
					'bg'   => $bg,
					'min'  => match ( $kind ) {
						'text'                => self::TEXT_FLOOR,
						'boundary', 'graphic' => self::BOUNDARY_FLOOR,
						default               => self::HAIRLINE_FLOOR,
					},
					'from' => array(),
				);
			}
			if ( count( $pairs[ $key ]['from'] ) < 3 && ! in_array( $from, $pairs[ $key ]['from'], true ) ) {
				$pairs[ $key ]['from'][] = $from;
			}
		};

		$drawn  = array_values( array_filter( $rules, static fn( array $rule ): bool => ! self::is_utility( $rule ) ) );
		$scopes = self::scopes( $drawn, $dark );
		$seen   = self::placements( $fixtures, array_merge( ...array_column( array_filter( $scopes, static fn( array $scope ): bool => array() !== $scope['classes'] ), 'classes' ) ) );

		foreach ( $drawn as $rule ) {
			$names = self::class_names( $rule['selectors'] );
			$known = array_values( array_filter( $names, static fn( string $name ): bool => isset( $seen[ $name ] ) ) );
			// Where the rule's element can sit. Every scope, unless the
			// contract's fixtures show it only ever outside a band; a rule
			// no fixture draws is read in every scope too.
			$inside  = array() === $known || array() !== array_filter( $known, static fn( string $name ): bool => $seen[ $name ]['band'] );
			$graphic = array() !== $known && array() === array_filter( $known, static fn( string $name ): bool => array() !== array_diff( array_keys( $seen[ $name ]['tags'] ), array( 'svg' ) ) );

			foreach ( $scopes as $scope ) {
				$ground = self::role( $scope['ground'], $scope['bind'] );
				if ( null === $ground ) {
					continue;
				}
				$own = self::ground_of( $rule['decl'], $scope['bind'] );

				foreach ( $rule['decl'] as $prop => $value ) {
					$kind = self::is_text( $prop ) ? ( $graphic ? 'graphic' : 'text' ) : ( self::is_border( $prop ) ? 'border' : null );
					$fg   = null === $kind ? null : self::role( $value, $scope['bind'] );
					if ( null === $fg || ( null === $own && ! $inside && ! str_contains( $value, '--salt-section-' ) && 'the page' !== $scope['name'] ) ) {
						continue;
					}
					$add( (string) $kind, $fg, $own ?? $ground, "{$rule['file']} {$rule['selectors'][0]} ({$prop}) in {$scope['name']}" );
				}
			}
		}

		// A utility is one declaration, so what it is drawn on is what the\n\t\t// class list beside it names: its own `bg-*`, else one the same file\n\t\t// draws elsewhere (a template's divider sits on whichever band it is\n\t\t// printed in), else the page's.\n       $utilities = array();
		foreach ( $rules as $rule ) {
			if ( self::is_utility( $rule ) && 1 === count( $rule['selectors'] ) && 1 === preg_match( '/^\.([\w\\\\-]+)$/', $rule['selectors'][0], $class ) ) {
				$utilities[ stripslashes( $class[1] ) ] = $rule['decl'];
			}
		}
		$page = array_values( array_filter( array_map( static fn( array $scope ): ?string => 'the page' === $scope['name'] ? self::role( $scope['ground'], $scope['bind'] ) : null, $scopes ) ) );
		foreach ( $class_groups as $lists ) {
			$read   = array();
			$nearby = array();
			foreach ( $lists as $tokens ) {
				$grounds = array();
				$colours = array();
				foreach ( array_intersect( $tokens, array_keys( $utilities ) ) as $token ) {
					foreach ( $utilities[ $token ] as $prop => $value ) {
						$role = self::role( $value, array() );
						if ( null === $role ) {
							continue;
						}
						if ( self::is_ground( $prop ) ) {
							$grounds[] = $role;
						} elseif ( self::is_text( $prop ) || self::is_border( $prop ) ) {
							$colours[] = array( self::is_text( $prop ) ? 'text' : 'border', $role, $token );
						}
					}
				}
				$read[] = array( $grounds, $colours );
				$nearby = array_merge( $nearby, $grounds );
			}
			foreach ( $read as list( $grounds, $colours ) ) {
				$on = array() !== $grounds ? $grounds : ( array() !== $nearby ? array_unique( $nearby ) : $page );
				foreach ( $colours as list( $kind, $fg, $token ) ) {
					foreach ( $on as $ground ) {
						$add( $kind, $fg, $ground, "{$token} in a template's class list" );
					}
				}
			}
		}

		ksort( $pairs );

		return $pairs;
	}

	/**
	 * The grounds the page can put under a rule, with the custom properties
	 * that travel with each (see the class comment).
	 *
	 * @param list<array{selectors: list<string>, decl: array<string, string>, at: list<string>, file: string}> $rules Drawn rules.
	 * @param bool                                                                                           $dark  Whether `[data-theme=dark]` is in force.
	 * @return list<array{name: string, classes: list<string>, bind: array<string, string>, ground: string}>
	 */
	public static function scopes( array $rules, bool $dark ): array {
		$found = array();

		foreach ( $rules as $rule ) {
			$selectors = array_map( static fn( string $selector ): string => str_replace( array( '"', "'" ), '', $selector ), $rule['selectors'] );

			if ( array() === array_diff( $selectors, array( 'body', 'html', ':root' ) ) && null !== self::ground_of( $rule['decl'], array() ) ) {
				$found['the page'] = array(
					'key'     => 'the page',
					'dark'    => false,
					'name'    => 'the page',
					'classes' => array(),
					'bind'    => array(),
					'ground'  => $rule['decl']['background-color'] ?? $rule['decl']['background'],
				);
				continue;
			}

			$bind = array_filter( $rule['decl'], static fn( string $prop ): bool => str_starts_with( $prop, '--salt-section-' ), ARRAY_FILTER_USE_KEY );
			if ( array() === $bind ) {
				continue;
			}
			$ground = $bind['--salt-section-bg'] ?? $rule['decl']['background-color'] ?? $rule['decl']['background'] ?? null;
			if ( null === $ground ) {
				continue;
			}

			$is_dark = array() === array_filter( $selectors, static fn( string $selector ): bool => ! str_starts_with( $selector, '[data-theme=dark]' ) );
			$twin    = array_map( static fn( string $selector ): string => str_replace( 'data-tone-dark', 'data-tone', (string) preg_replace( '/^\[data-theme=dark\]\s*/', '', $selector ) ), $selectors );
			sort( $twin );
			$name = implode( ', ', $selectors );
			$found[ ( $is_dark ? 'dark ' : 'light ' ) . implode( ',', $twin ) ] = array(
				'key'     => implode( ',', $twin ),
				'dark'    => $is_dark,
				'name'    => strlen( $name ) > 70 ? substr( $name, 0, 67 ) . '...' : $name,
				'classes' => self::class_names( $selectors ),
				'bind'    => $bind,
				'ground'  => $ground,
			);
		}

		$scopes = array();
		foreach ( $found as $scope ) {
			$replaced = $dark && ! $scope['dark'] && isset( $found[ 'dark ' . $scope['key'] ] );
			if ( ( $scope['dark'] && ! $dark ) || $replaced ) {
				continue;
			}
			$scopes[] = array(
				'name'    => $scope['name'],
				'classes' => $scope['classes'],
				'bind'    => $scope['bind'],
				'ground'  => $scope['ground'],
			);
		}

		return $scopes;
	}

	/**
	 * The `--color-*` property a value ends at, following `var()` through the
	 * scope's own properties and then through fallbacks; null for anything
	 * that is not a colour taken from a role (`inherit`, `transparent`, a
	 * gradient, a property nothing sets).
	 *
	 * @param string                $value A declared value, possibly a shorthand such as `1px solid var(--x)`.
	 * @param array<string, string> $bind  The scope's `--salt-section-*` properties.
	 * @param int                   $depth Guard against a cycle.
	 * @return string|null
	 */
	public static function role( string $value, array $bind, int $depth = 0 ): ?string {
		$at = stripos( $value, 'var(' );
		if ( $depth > 8 || false === $at ) {
			return null;
		}

		$inner = '';
		$open  = 0;
		for ( $i = $at + 3, $length = strlen( $value ); $i < $length; $i++ ) {
			$open += '(' === $value[ $i ] ? 1 : ( ')' === $value[ $i ] ? -1 : 0 );
			if ( 0 === $open ) {
				$inner = substr( $value, $at + 4, $i - $at - 4 );
				break;
			}
		}

		$parts    = self::split( $inner, ',', 1 );
		$name     = trim( $parts[0] );
		$fallback = $parts[1] ?? null;

		if ( isset( $bind[ $name ] ) ) {
			return self::role( $bind[ $name ], $bind, $depth + 1 );
		}
		if ( str_starts_with( $name, '--color-' ) ) {
			return $name;
		}

		return null === $fallback ? null : self::role( $fallback, $bind, $depth + 1 );
	}

	/**
	 * The role a rule's own `background` ends at, or null if it paints none.
	 *
	 * @param array<string, string> $decl Declarations.
	 * @param array<string, string> $bind The scope's properties.
	 * @return string|null
	 */
	private static function ground_of( array $decl, array $bind ): ?string {
		foreach ( array( 'background-color', 'background' ) as $prop ) {
			if ( isset( $decl[ $prop ] ) ) {
				return self::role( $decl[ $prop ], $bind );
			}
		}

		return null;
	}

	/**
	 * The class names in the rightmost compound of each selector: what the
	 * element a rule paints carries, without the ancestors it is read under.
	 *
	 * @param list<string> $selectors Selectors.
	 * @return list<string>
	 */
	private static function class_names( array $selectors ): array {
		$names = array();
		foreach ( $selectors as $selector ) {
			$compounds = preg_split( '/\s*[>+~]\s*|\s+(?![^(\[]*[)\]])/', trim( (string) preg_replace( '/:(?:is|where|not|has)\([^()]*\)/', '', $selector ) ) );
			preg_match_all( '/\.([\w-]+)/', (string) end( $compounds ), $found );
			$names = array_merge( $names, $found[1] );
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Where each class is printed in the contract's fixture pages: whether
	 * some element with it sits inside an element carrying a band's classes,
	 * and the tags it is printed on.
	 *
	 * @param list<string> $files        Fixture HTML files.
	 * @param list<string> $band_classes Class names of the bands (a section, the footer).
	 * @return array<string, array{band: bool, tags: array<string, true>}>
	 */
	private static function placements( array $files, array $band_classes ): array {
		static $cache = array();

		$key = md5( implode( "\n", $files ) . "\0" . implode( ',', $band_classes ) );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$seen  = array();
		$bands = array_flip( $band_classes );
		foreach ( $files as $file ) {
			$html = (string) file_get_contents( $file );
			if ( '' === trim( $html ) ) {
				continue;
			}
			$document = new DOMDocument();
			libxml_use_internal_errors( true );
			$document->loadHTML( $html );
			libxml_clear_errors();

			foreach ( ( new DOMXPath( $document ) )->query( '//*[@class]' ) as $element ) {
				$inside = false;
				for ( $up = $element->parentNode; $up instanceof DOMElement; $up = $up->parentNode ) {
					if ( array() !== array_intersect_key( $bands, array_flip( preg_split( '/\s+/', trim( $up->getAttribute( 'class' ) ) ) ) ) ) {
						$inside = true;
						break;
					}
				}
				foreach ( preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) ) as $name ) {
					$seen[ $name ]['band']                       = ( $seen[ $name ]['band'] ?? false ) || $inside;
					$seen[ $name ]['tags'][ $element->nodeName ] = true;
				}
			}
		}

		$cache[ $key ] = $seen;

		return $seen;
	}

	/**
	 * Whether a rule is one of the utilities Tailwind built.
	 *
	 * @param array{at: list<string>} $rule A rule.
	 * @return bool
	 */
	private static function is_utility( array $rule ): bool {
		return in_array( '@layer utilities', $rule['at'], true );
	}

	/**
	 * Whether a property sets text colour.
	 *
	 * @param string $prop Property.
	 * @return bool
	 */
	private static function is_text( string $prop ): bool {
		return 'color' === $prop;
	}

	/**
	 * Whether a property sets a border's colour: `border`, `border-color`, a
	 * side's or a logical side's, in either spelling. Width, style, radius and
	 * image carry no colour.
	 *
	 * @param string $prop Property.
	 * @return bool
	 */
	private static function is_border( string $prop ): bool {
		return 1 === preg_match( '/^border(?:-(?:top|right|bottom|left|block|inline)(?:-(?:start|end))?)?(?:-color)?$/', $prop );
	}

	/**
	 * Whether a property paints a ground.
	 *
	 * @param string $prop Property.
	 * @return bool
	 */
	private static function is_ground( string $prop ): bool {
		return 'background' === $prop || 'background-color' === $prop;
	}

	/**
	 * A declaration block as property => value, `!important` dropped, a
	 * property lower-cased unless it is a custom property.
	 *
	 * @param string $block Text between the braces.
	 * @return array<string, string>
	 */
	private static function declarations( string $block ): array {
		$out = array();
		foreach ( self::split( $block, ';' ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) ) {
				continue;
			}
			$prop = trim( $parts[0] );
			$out[ str_starts_with( $prop, '--' ) ? $prop : strtolower( $prop ) ] = trim( (string) preg_replace( '/\s*!\s*important\s*$/i', '', $parts[1] ) );
		}

		return $out;
	}

	/**
	 * $text cut at each $separator outside brackets and quotes, at most $limit
	 * cuts (0 for all), each piece trimmed and empty pieces dropped.
	 *
	 * @param string $text      Text.
	 * @param string $separator One character.
	 * @param int    $limit     Cuts to make, or 0.
	 * @return list<string>
	 */
	private static function split( string $text, string $separator, int $limit = 0 ): array {
		$pieces = array();
		$depth  = 0;
		$quote  = '';
		$start  = 0;

		for ( $i = 0, $length = strlen( $text ); $i < $length; $i++ ) {
			$char = $text[ $i ];
			if ( '' !== $quote ) {
				$quote = $char === $quote ? '' : $quote;
				$i    += '\\' === $char ? 1 : 0;
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char || '[' === $char ) {
				++$depth;
			} elseif ( ')' === $char || ']' === $char ) {
				--$depth;
			} elseif ( $separator === $char && 0 === $depth && ( 0 === $limit || count( $pieces ) < $limit ) ) {
				$pieces[] = substr( $text, $start, $i - $start );
				$start    = $i + 1;
			}
		}
		$pieces[] = substr( $text, $start );

		return array_values( array_filter( array_map( 'trim', $pieces ), static fn( string $piece ): bool => '' !== $piece ) );
	}

	/**
	 * Fail the test that called this.
	 *
	 * @param string $message Message.
	 * @return never
	 */
	private static function fail( string $message ): never {
		throw new PHPUnit\Framework\AssertionFailedError( $message );
	}
}
