<?php
/**
 * Self-hosted fonts service (spec §13).
 *
 * Admin-uploaded font files (woff2/woff/ttf) from the media library become
 * @font-face rules under the fixed families 'salt-heading'/'salt-body' plus
 * the role-token overrides (--font-heading/--font-body) that app.css
 * consumes. NOTHING loads from a font CDN — every src is validated as
 * same-origin before it may print (PRIV01; the theme-vars emitter prints
 * the built CSS unescaped inside a nonce-stamped <style>).
 *
 * Upload posture mirrors SVG (SEC04): font mime support is only ADDED for
 * users with 'manage_options', and the wp_check_filetype_and_ext fix (PHP
 * fileinfo frequently sniffs fonts as application/octet-stream) is gated on
 * the same capability.
 *
 * Runtime seams consumed elsewhere: salt_fonts_runtime_css() is composed by
 * salt_output_theme_vars_css() (inc/setup.php, wp_head 20);
 * salt_output_font_preloads() prints early preload hints (wp_head 2).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The font extension => mime map salt supports (spec §13).
 *
 * @return array<string,string>
 */
function salt_font_mime_map(): array {
	return array(
		'woff2' => 'font/woff2',
		'woff'  => 'font/woff',
		'ttf'   => 'font/ttf',
	);
}

/**
 * 'upload_mimes' callback: allow font uploads for admins only.
 *
 * Mirrors salt_svg_upload_mimes() (SEC04 posture): support is ADDED, never
 * removed, and only for 'manage_options'.
 *
 * @param array<string,string> $mimes Allowed mime map (ext => type).
 * @return array<string,string>
 */
function salt_font_upload_mimes( array $mimes ): array {
	if ( current_user_can( 'manage_options' ) ) {
		foreach ( salt_font_mime_map() as $ext => $type ) {
			$mimes[ $ext ] = $type;
		}
	}

	return $mimes;
}
add_filter( 'upload_mimes', 'salt_font_upload_mimes' );

/**
 * Re-validate a font upload's filetype BY EXTENSION (PURE — unit-tested).
 *
 * PHP's fileinfo often sniffs font binaries as application/octet-stream,
 * which makes wp_check_filetype_and_ext() blank the ext/type pair and
 * reject the upload even though the mime map allows fonts. When the
 * filename carries a supported font extension and core could not resolve
 * ext/type, fill both from the extension. Anything else passes through
 * untouched (a .php or .svg file is never "fixed" here).
 *
 * @param array<string,mixed> $data     Core's result (ext, type, proper_filename).
 * @param string              $filename Original filename.
 * @return array<string,mixed>
 */
function salt_font_filetype_fix( array $data, string $filename ): array {
	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}

	$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	$map = salt_font_mime_map();

	if ( ! isset( $map[ $ext ] ) ) {
		return $data;
	}

	$data['ext']  = $ext;
	$data['type'] = $map[ $ext ];

	return $data;
}

/**
 * 'wp_check_filetype_and_ext' wrapper: apply the font fix for admins only.
 *
 * @param array<string,mixed> $data     Core's result (ext, type, proper_filename).
 * @param string              $file     Full path to the upload (unused).
 * @param string              $filename Original filename.
 * @return array<string,mixed>
 */
function salt_font_filetype_filter( array $data, string $file, string $filename ): array {
	unset( $file );

	if ( ! current_user_can( 'manage_options' ) ) {
		return $data;
	}

	return salt_font_filetype_fix( $data, $filename );
}
add_filter( 'wp_check_filetype_and_ext', 'salt_font_filetype_filter', 10, 3 );

/**
 * Read the uploaded-font options into a normalized list of faces.
 *
 * Each face: role ('heading'|'body'), url (attachment URL), format (from
 * the file extension), weight (int), style ('normal'|'italic'). Broken
 * attachment ids and unsupported extensions are skipped LOUDLY via
 * salt_log() — an empty repeater simply keeps the compiled system-font
 * defaults (FAIL03).
 *
 * @return array<int,array{role:string,url:string,format:string,weight:int,style:string}>
 */
function salt_fonts_config(): array {
	if ( ! salt_acf_active() ) {
		return array();
	}

	$roles = array(
		'heading' => array(
			'field'          => 'font_heading_files',
			'default_weight' => 700,
		),
		'body'    => array(
			'field'          => 'font_body_files',
			'default_weight' => 400,
		),
	);

	$map   = salt_font_mime_map();
	$faces = array();

	foreach ( $roles as $role => $config ) {
		$rows = salt_option( $config['field'] );

		if ( ! is_array( $rows ) ) {
			continue;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$file_id = absint( $row['file'] ?? 0 );

			if ( 0 === $file_id ) {
				salt_log( sprintf( 'salt_fonts_config(): %s row without a font file — skipped.', $role ) );
				continue;
			}

			$url = wp_get_attachment_url( $file_id );

			if ( ! is_string( $url ) || '' === $url ) {
				salt_log( sprintf( 'salt_fonts_config(): broken attachment id %d for %s font — skipped.', $file_id, $role ) );
				continue;
			}

			$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
			$format = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

			if ( ! isset( $map[ $format ] ) ) {
				salt_log( sprintf( 'salt_fonts_config(): unsupported font extension "%s" (attachment %d) — skipped.', $format, $file_id ) );
				continue;
			}

			$weight = absint( $row['weight'] ?? 0 );

			$faces[] = array(
				'role'   => $role,
				'url'    => $url,
				'format' => $format,
				'weight' => ( $weight >= 1 && $weight <= 1000 ) ? $weight : $config['default_weight'],
				'style'  => ( 'italic' === ( $row['style'] ?? '' ) ) ? 'italic' : 'normal',
			);
		}
	}

	return $faces;
}

/**
 * Build the @font-face + role-token CSS from normalized faces (PURE).
 *
 * Faces are grouped by role+weight+style into one @font-face each, family
 * 'salt-heading'/'salt-body', src ordered with $preferred_format first and
 * font-display:swap on every rule. SECURITY: this string prints UNESCAPED
 * inside the theme-vars <style>, so every URL must start with $home_url
 * (same-origin only — PRIV01) and must be free of quotes, backslashes and
 * control characters; violators are skipped and noted via salt_log().
 *
 * When at least one heading face survives, the --font-heading role token is
 * pointed at the uploaded family (likewise body → --font-body); app.css
 * headings/html pick the override up (FAIL03: no faces, no override).
 *
 * @param array<int,array<string,mixed>> $faces            Normalized faces (salt_fonts_config() shape).
 * @param string                         $preferred_format Format listed first ('woff2'|'woff'|'ttf').
 * @param string                         $home_url         Site origin every src must start with.
 * @return string CSS, or '' when no usable face remains.
 */
function salt_fonts_css( array $faces, string $preferred_format, string $home_url ): string {
	$families = array(
		'heading' => 'salt-heading',
		'body'    => 'salt-body',
	);
	$hints    = array(
		'woff2' => 'woff2',
		'woff'  => 'woff',
		'ttf'   => 'truetype',
	);

	$groups = array();
	$roles  = array();

	// "Starts with the origin" alone would let example.test.evil.example
	// through — require the path separator right after the origin.
	$origin = rtrim( $home_url, '/' );

	foreach ( $faces as $face ) {
		$role   = (string) ( $face['role'] ?? '' );
		$url    = (string) ( $face['url'] ?? '' );
		$format = (string) ( $face['format'] ?? '' );

		if ( ! isset( $families[ $role ] ) || ! isset( $hints[ $format ] ) ) {
			salt_log( sprintf( 'salt_fonts_css(): face with unknown role/format "%s"/"%s" — skipped.', $role, $format ) );
			continue;
		}

		if ( '' === $origin || ! str_starts_with( $url, $origin . '/' ) ) {
			salt_log( sprintf( 'salt_fonts_css(): cross-origin font URL rejected (PRIV01): %s', $url ) );
			continue;
		}

		if ( 1 === preg_match( '/["\'\\\\]|[\x00-\x1F\x7F]/', $url ) ) {
			salt_log( 'salt_fonts_css(): font URL with quote/backslash/control character rejected.' );
			continue;
		}

		$weight = absint( $face['weight'] ?? 400 );
		$style  = ( 'italic' === ( $face['style'] ?? '' ) ) ? 'italic' : 'normal';
		$key    = $role . '|' . $weight . '|' . $style;

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'family'  => $families[ $role ],
				'weight'  => $weight,
				'style'   => $style,
				'sources' => array(),
			);
		}

		$groups[ $key ]['sources'][] = array(
			'url'    => $url,
			'format' => $format,
		);

		$roles[ $role ] = true;
	}

	if ( array() === $groups ) {
		return '';
	}

	$css = '';

	foreach ( $groups as $group ) {
		$sources = $group['sources'];

		usort(
			$sources,
			static fn( array $a, array $b ): int =>
				(int) ( $b['format'] === $preferred_format ) <=> (int) ( $a['format'] === $preferred_format )
		);

		$src = implode(
			',',
			array_map(
				static fn( array $source ): string => sprintf( 'url("%s") format("%s")', $source['url'], $hints[ $source['format'] ] ),
				$sources
			)
		);

		$css .= sprintf(
			'@font-face{font-family:"%s";font-style:%s;font-weight:%d;font-display:swap;src:%s}',
			$group['family'],
			$group['style'],
			$group['weight'],
			$src
		);
	}

	foreach ( array( 'heading', 'body' ) as $role ) {
		if ( ! empty( $roles[ $role ] ) ) {
			$css .= ':root{--font-' . $role . ':"salt-' . $role . '",var(--font-sans)}';
		}
	}

	return $css;
}

/**
 * Runtime fonts CSS — the seam salt_output_theme_vars_css() composes.
 *
 * @return string CSS, or '' when no fonts are configured (FAIL03).
 */
function salt_fonts_runtime_css(): string {
	if ( ! salt_acf_active() ) {
		return '';
	}

	$format = (string) ( salt_option( 'font_format' ) ?? '' );

	if ( ! isset( salt_font_mime_map()[ $format ] ) ) {
		$format = 'woff2';
	}

	return salt_fonts_css( salt_fonts_config(), $format, home_url( '' ) );
}

/**
 * Print preload hints for the most critical font files (wp_head 2).
 *
 * woff2 faces only (preloading a format the browser then skips wastes
 * bandwidth), HARD CAP of two hints: the first body face and the first
 * heading face. crossorigin is required on font preloads even same-origin
 * (fonts are always fetched in CORS mode). No faces, no output.
 *
 * @return void
 */
function salt_output_font_preloads(): void {
	$faces = salt_fonts_config();

	if ( array() === $faces ) {
		return;
	}

	$picks = array();

	foreach ( array( 'body', 'heading' ) as $role ) {
		foreach ( $faces as $face ) {
			if ( $role === $face['role'] && 'woff2' === $face['format'] ) {
				$picks[] = $face['url'];
				break;
			}
		}
	}

	foreach ( array_slice( array_unique( $picks ), 0, 2 ) as $url ) {
		printf(
			'<link rel="preload" as="font" type="font/woff2" crossorigin href="%s">' . "\n",
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', 'salt_output_font_preloads', 2 );
