<?php
/**
 * The update flag (ID-3613): which of the child theme's declared sections
 * need re-verifying, shown beside the override manifest's re-verify step.
 *
 * The manifest's update procedure (docs/templates/override-manifest.md)
 * is carried out in the site's admin: update the parent, then re-verify the
 * overrides. So the flag is an admin notice on the Themes and Updates
 * screens, for users who can switch themes, built from the child theme's
 * salt-overrides.json (inc/overrides.php).
 *
 * What it can know, and so what it raises (OVERRIDES.md "The update flag"):
 *  - every `logic` entry, always (the contract: flagged on every update);
 *  - a `view` entry whose `verified` is not the declaration's own `contract`
 *    release. The contract asks for a view entry to be raised when its
 *    view-props file or _shared.json changed between the old pin and the
 *    new; salt-wordpress carries neither the contract's files nor a record
 *    of which contract release it conforms to, so it cannot compute that
 *    diff and does not pretend to. It raises every view entry the site has
 *    not re-verified against the release it says it is on;
 *  - an `imageSizes` row with its section;
 *  - a client section whose id Salt now uses;
 *  - every disagreement between the declaration and the child's files.
 * No network calls; nothing is stored.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The section views a child theme supplies: the slugs of its
 * template-parts/sections/*.php files.
 *
 * @param string $child_dir Child theme directory ('' = none).
 * @return string[] Sorted layout slugs.
 */
function salt_override_child_section_views( string $child_dir ): array {
	if ( '' === $child_dir ) {
		return array();
	}

	$slugs = array();
	foreach ( (array) glob( $child_dir . '/template-parts/sections/*.php' ) as $file ) {
		$slug = basename( (string) $file, '.php' );
		if ( 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			$slugs[] = $slug;
		}
	}
	sort( $slugs );
	return $slugs;
}

/**
 * The flag's rows (pure apart from file-existence checks in the child).
 *
 * @param array{state: string, declaration: array<string,mixed>|null, errors: string[], child_dir: string} $loaded      salt_overrides_load().
 * @param string[]                                                                                         $child_views salt_override_child_section_views().
 * @return array<int, array{section: string, message: string}> Plain text; escape at output.
 */
function salt_override_flag_items( array $loaded, array $child_views ): array {
	$items = array();
	$add   = static function ( string $section, string $message ) use ( &$items ): void {
		$items[] = array(
			'section' => $section,
			'message' => $message,
		);
	};

	if ( 'none' === $loaded['state'] ) {
		return array();
	}

	if ( 'invalid' === $loaded['state'] ) {
		$add(
			'',
			sprintf(
				/* translators: 1: file name. 2: validation errors. */
				__( '%1$s is not valid (%2$s). Until it is fixed no section counts as declared, so a section the site took over renders the parent\'s way.', 'salt' ),
				SALT_OVERRIDES_FILE,
				implode( '; ', array_slice( $loaded['errors'], 0, 3 ) )
			)
		);
	}

	$declaration = $loaded['declaration'];
	$map         = salt_contract_section_layouts();
	$raised      = array();

	foreach ( null === $declaration ? array() : $declaration['sections'] as $entry ) {
		$id      = $entry['section'];
		$layouts = $map[ $id ] ?? null;

		if ( null === $layouts ) {
			$raised[ $id ] = true;
			$add(
				$id,
				sprintf(
					/* translators: %s: section id. */
					__( '"%s" is not a salt-contract section this theme knows. Check the id against contract/sections.json.', 'salt' ),
					$id
				)
			);
			continue;
		}

		$missing = array_values( array_diff( $layouts, $child_views ) );

		if ( 'logic' === $entry['tier'] ) {
			$raised[ $id ] = true;
			$message       = sprintf(
				/* translators: 1: section id. 2: release. */
				__( '%1$s (logic, last verified against %2$s): the site took this section over, so nothing in a Salt release reaches it. Re-verify it on every update.', 'salt' ),
				$id,
				$entry['verified']
			);
			if ( array() === $layouts ) {
				$message .= ' ' . __( 'Salt for WordPress has no layout for this section yet.', 'salt' );
			} elseif ( array() !== $missing ) {
				$message .= ' ' . sprintf(
					/* translators: %s: template paths. */
					__( 'The child theme has no %s, so that layout renders nothing.', 'salt' ),
					implode( ', ', array_map( static fn( string $slug ): string => 'template-parts/sections/' . $slug . '.php', $missing ) )
				);
			}
			$add( $id, $message );
		} else {
			if ( $entry['verified'] !== $declaration['contract'] ) {
				$raised[ $id ] = true;
				$add(
					$id,
					sprintf(
						/* translators: 1: section id. 2: release the entry was verified against. 3: release the site's pin conforms to. */
						__( '%1$s (view): last verified against %2$s, but the site\'s contract is %3$s. Re-verify the view against this release\'s view props, then move "verified".', 'salt' ),
						$id,
						$entry['verified'],
						$declaration['contract']
					)
				);
			}
			if ( array() !== $layouts && count( $missing ) === count( $layouts ) ) {
				$raised[ $id ] = true;
				$add(
					$id,
					sprintf(
						/* translators: 1: section id. 2: template paths. */
						__( '%1$s (view): declared, but the child theme has no %2$s, so the parent\'s view is drawn.', 'salt' ),
						$id,
						implode( ', ', array_map( static fn( string $slug ): string => 'template-parts/sections/' . $slug . '.php', $layouts ) )
					)
				);
			}
		}

		foreach ( $entry['files'] as $file ) {
			$inside = '' !== $loaded['child_dir'] && ! str_starts_with( $file, '/' ) && ! str_contains( '/' . $file . '/', '/../' );
			if ( $inside && ! file_exists( $loaded['child_dir'] . '/' . $file ) ) {
				$add(
					$id,
					sprintf(
						/* translators: 1: section id. 2: file path. */
						__( '%1$s: the declared file %2$s is not in the child theme.', 'salt' ),
						$id,
						$file
					)
				);
			}
		}
	}

	$declared_ids = array_map( static fn( array $entry ): string => $entry['section'], null === $declaration ? array() : $declaration['sections'] );

	foreach ( null === $declaration ? array() : $declaration['imageSizes'] as $size ) {
		if ( isset( $raised[ $size['section'] ] ) || ! in_array( $size['section'], $declared_ids, true ) ) {
			$add(
				$size['section'],
				sprintf(
					/* translators: 1: section id. 2: image slot id. */
					__( '%1$s, image slot %2$s: the site declares its own sizes. Re-verify them with the section.', 'salt' ),
					$size['section'],
					$size['slot']
				)
			);
		}
	}

	foreach ( null === $declaration ? array() : $declaration['clientSections'] as $client ) {
		if ( isset( $map[ $client['id'] ] ) || null !== salt_section_resolver_file( $client['id'] ) ) {
			$add(
				$client['id'],
				sprintf(
					/* translators: %s: section id. */
					__( '%s (client section): Salt has a section of the same id. Rename the site\'s section, or adopt Salt\'s and declare it.', 'salt' ),
					$client['id']
				)
			);
		}
	}

	foreach ( $child_views as $slug ) {
		if ( null === salt_section_resolver_file( $slug ) || null !== salt_override_tier_in( $declaration, $slug ) ) {
			continue; // A child-only layout, or declared.
		}
		$add(
			implode( ', ', salt_contract_sections_for_layout( $slug ) ),
			sprintf(
				/* translators: 1: template path. 2: contract section id(s). 3: file name. */
				__( 'template-parts/sections/%1$s.php: the child theme swaps this view, but %3$s does not declare section %2$s (tier "view").', 'salt' ),
				$slug,
				implode( ', ', salt_contract_sections_for_layout( $slug ) ),
				SALT_OVERRIDES_FILE
			)
		);
	}

	return $items;
}

/**
 * The flag's notice HTML ('' when there is nothing to raise or the user
 * may not see it). Every value is escaped here.
 *
 * @param array<int, array{section: string, message: string}> $items   salt_override_flag_items().
 * @param bool                                                $can_see Whether the current user may see it.
 * @return string
 */
function salt_override_flag_notice( array $items, bool $can_see ): string {
	if ( ! $can_see || array() === $items ) {
		return '';
	}

	$rows = '';
	foreach ( $items as $item ) {
		$rows .= '<li>' . esc_html( $item['message'] ) . '</li>';
	}

	return sprintf(
		'<div class="notice notice-warning salt-override-flag"><p><strong>%1$s</strong> %2$s</p><ul class="ul-disc">%3$s</ul><p>%4$s</p></div>',
		esc_html__( 'salt theme:', 'salt' ),
		esc_html(
			sprintf(
				/* translators: %s: file name. */
				__( 'Re-verify these sections from the child theme\'s %s when you update the parent (override manifest, update procedure):', 'salt' ),
				SALT_OVERRIDES_FILE
			)
		),
		$rows,
		esc_html__( 'Salt cannot tell which sections\' view props a release changed, so it raises every swapped view not yet verified against the site\'s contract release. See docs/contracts/OVERRIDES.md in the parent theme.', 'salt' )
	);
}

/**
 * Print the flag on the Themes and Updates screens for users who can
 * switch themes.
 *
 * @return void
 */
function salt_override_flag_admin_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! is_object( $screen ) || ! in_array( $screen->id ?? '', array( 'themes', 'update-core' ), true ) ) {
		return;
	}
	if ( ! current_user_can( 'switch_themes' ) ) {
		return;
	}

	$loaded = salt_overrides_load();
	echo salt_override_flag_notice( salt_override_flag_items( $loaded, salt_override_child_section_views( $loaded['child_dir'] ) ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in salt_override_flag_notice().
}
add_action( 'admin_notices', 'salt_override_flag_admin_notice' );
