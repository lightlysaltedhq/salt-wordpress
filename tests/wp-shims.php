<?php
/**
 * WordPress shims for the unit/snapshot suites and CLI tooling.
 *
 * The suite tests salt's shared logic without booting WordPress. Every shim
 * mirrors documented WordPress behaviour for the inputs our code uses; where
 * a shim is a deliberate simplification (wp_kses), the limits are noted at
 * the shim. Owned by the foundation — section agents must NOT edit this
 * file (add tests/shims/{domain}.php instead).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! defined( 'SALT_TESTING' ) ) {
	define( 'SALT_TESTING', true );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

// ---------------------------------------------------------------------------
// Test-state registry + reset.
// ---------------------------------------------------------------------------

/**
 * Reset all shim state between tests.
 *
 * @return void
 */
function salt_test_reset(): void {
	$GLOBALS['salt_test_hooks']      = array();
	$GLOBALS['salt_test_options']    = array();
	$GLOBALS['salt_test_fields']     = array();
	$GLOBALS['salt_test_posts']      = array();
	$GLOBALS['salt_test_query_vars'] = array();
	$GLOBALS['salt_test_enqueued']   = array(
		'styles'  => array(),
		'scripts' => array(),
	); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray
	$GLOBALS['salt_test_logs']       = array();
	$GLOBALS['salt_test_user']       = array(
		'logged_in' => false,
		'caps'      => array(),
	);
}
salt_test_reset();

// ---------------------------------------------------------------------------
// Hooks.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register a hook callback.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @param int      $args      Accepted args.
	 * @return bool
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $args = 1 ): bool {
		$GLOBALS['salt_test_hooks'][ $hook_name ][ $priority ][] = array( $callback, $args );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback (same registry).
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @param int      $args      Accepted args.
	 * @return bool
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return add_action( $hook_name, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Run registered filter callbacks in priority order.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Value being filtered.
	 * @param mixed  ...$extra  Additional args.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$extra ) {
		$callbacks = $GLOBALS['salt_test_hooks'][ $hook_name ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $batch ) {
			foreach ( $batch as [ $callback, $accepted ] ) {
				$call_args = array_slice( array_merge( array( $value ), $extra ), 0, max( 1, $accepted ) );
				$value     = $callback( ...$call_args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Run registered action callbacks in priority order.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Args passed to callbacks.
	 * @return void
	 */
	function do_action( string $hook_name, ...$args ): void {
		// Fidelity: real do_action() passes an empty-string argument to every
		// callback when the action carries no explicit args. A callback that
		// type-hints its first parameter as non-string (e.g. ?array) would
		// then TypeError-fatal in production — reproduce that here so such
		// bugs surface in CI, not on a live front end.
		if ( array() === $args ) {
			$args = array( '' );
		}
		$callbacks = $GLOBALS['salt_test_hooks'][ $hook_name ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $batch ) {
			foreach ( $batch as [ $callback, $accepted ] ) {
				$callback( ...array_slice( $args, 0, max( 0, $accepted ) ) );
			}
		}
	}
}

/**
 * Back-compat alias used by the slug extractor.
 *
 * @param string $hook_name Hook name.
 * @return void
 */
function salt_test_do_action( string $hook_name ): void {
	do_action( $hook_name );
}

// ---------------------------------------------------------------------------
// i18n.
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation shim — returns the source string.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain (unused).
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Contextual translation shim.
	 *
	 * @param string $text    Text.
	 * @param string $context Context (unused).
	 * @param string $domain  Domain (unused).
	 * @return string
	 */
	function _x( string $text, string $context, string $domain = 'default' ): string {
		unset( $context, $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Plural translation shim.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Count.
	 * @param string $domain Domain (unused).
	 * @return string
	 */
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		unset( $domain );
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Escaped translation shim.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Escaped attribute translation shim.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Echoing escaped translation shim.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return void
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html__( $text, $domain );
	}
}

// ---------------------------------------------------------------------------
// Escaping & sanitisation.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * esc_html shim.
	 *
	 * @param string|int|float|null $text Raw text.
	 * @return string
	 */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * esc_attr shim.
	 *
	 * @param string|int|float|null $text Raw text.
	 * @return string
	 */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * esc_url shim — strips dangerous protocols, entity-encodes.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	function esc_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '/^\s*(javascript|data|vbscript):/i', $url ) ) {
			return '';
		}
		return str_replace( array( '"', "'", '<', '>' ), array( '%22', '%27', '%3C', '%3E' ), $url );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * sanitize_title shim — lowercase slug, [a-z0-9-], like WP for ASCII input.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	function sanitize_title( string $title ): string {
		$title = strtolower( strip_tags( $title ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		$title = preg_replace( '/&[a-z0-9#]+;/', '', $title ) ?? '';
		$title = preg_replace( '/[^a-z0-9\s_-]/', '', $title ) ?? '';
		$title = preg_replace( '/[\s_]+/', '-', trim( $title ) ) ?? '';
		$title = preg_replace( '/-+/', '-', $title ) ?? '';
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * sanitize_key shim.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * absint shim.
	 *
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	/**
	 * get_query_var shim — reads $GLOBALS['salt_test_query_vars'].
	 *
	 * @param string $name          Query var.
	 * @param mixed  $default_value Value when unset.
	 * @return mixed
	 */
	function get_query_var( string $name, $default_value = '' ) {
		return $GLOBALS['salt_test_query_vars'][ $name ] ?? $default_value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * wp_json_encode shim.
	 *
	 * @param mixed $data  Data.
	 * @param int   $flags JSON flags.
	 * @return string|false
	 */
	function wp_json_encode( $data, int $flags = 0 ) {
		return json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * wp_parse_args shim.
	 *
	 * @param array<string,mixed> $args     Args.
	 * @param array<string,mixed> $defaults Defaults.
	 * @return array<string,mixed>
	 */
	function wp_parse_args( array $args, array $defaults = array() ): array {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * wp_strip_all_tags shim.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function wp_strip_all_tags( string $text ): string {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) ?? '';
		return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * wp_kses shim — DOM-based emulation of WordPress kses for test fixtures.
	 *
	 * Fidelity notes (deliberate simplifications, adequate for our fixtures):
	 * disallowed elements are UNWRAPPED (tag removed, children kept), which
	 * matches kses stripping tags while keeping inner text; disallowed
	 * attributes are dropped; javascript:/data: URLs in href/src are removed.
	 *
	 * @param string                     $content           Raw HTML.
	 * @param array<string,array|true[]> $allowed_html      Allow-list in WP format.
	 * @param string[]                   $allowed_protocols Allowed protocols (simplified).
	 * @return string
	 */
	function wp_kses( string $content, array $allowed_html, array $allowed_protocols = array( 'http', 'https', 'mailto', 'tel' ) ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><body>' . $content . '</body>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( null === $body ) {
			return '';
		}

		salt_test_kses_walk( $body, array_change_key_case( $allowed_html, CASE_LOWER ), $allowed_protocols );

		$out = '';
		foreach ( iterator_to_array( $body->childNodes ) as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return $out;
	}

	/**
	 * Recursive kses walker (shim internal).
	 *
	 * @param DOMNode                    $node              Node.
	 * @param array<string,array|true[]> $allowed           Allow-list (lowercased keys).
	 * @param string[]                   $allowed_protocols Allowed protocols.
	 * @return void
	 */
	function salt_test_kses_walk( DOMNode $node, array $allowed, array $allowed_protocols ): void {
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			/** @var DOMElement $child */
			$tag = strtolower( $child->nodeName );

			if ( ! isset( $allowed[ $tag ] ) ) {
				// Unwrap: keep children, drop the element.
				while ( $child->firstChild ) {
					$node->insertBefore( $child->firstChild, $child );
				}
				$node->removeChild( $child );
				// Children were re-parented; re-walk this node.
				salt_test_kses_walk( $node, $allowed, $allowed_protocols );
				return;
			}

			$allowed_attrs = is_array( $allowed[ $tag ] ) ? array_change_key_case( $allowed[ $tag ], CASE_LOWER ) : array();
			foreach ( iterator_to_array( $child->attributes ) as $attr ) {
				$name = strtolower( $attr->nodeName );
				if ( empty( $allowed_attrs[ $name ] ) ) {
					$child->removeAttribute( $attr->nodeName );
					continue;
				}
				if ( in_array( $name, array( 'href', 'src' ), true ) ) {
					$value = trim( $attr->nodeValue ?? '' );
					if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $value, $m ) && ! in_array( strtolower( $m[1] ), $allowed_protocols, true ) ) {
						$child->removeAttribute( $attr->nodeName );
					}
				}
			}

			salt_test_kses_walk( $child, $allowed, $allowed_protocols );
		}
	}
}

// ---------------------------------------------------------------------------
// Options / ACF fields.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * get_option shim (fixture-backed).
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( string $option, $default_value = false ) {
		return $GLOBALS['salt_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * update_option shim (fixture-backed).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function update_option( string $option, $value ): bool {
		$GLOBALS['salt_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * ACF get_field shim (fixture-backed).
	 *
	 * Fixtures: $GLOBALS['salt_test_fields'][ $context ][ $selector ] where
	 * $context is 'option' or a post ID.
	 *
	 * @param string     $selector Field selector.
	 * @param int|string $post_id  Context.
	 * @return mixed
	 */
	function get_field( string $selector, $post_id = 0 ) {
		$context = 0 === $post_id ? 'current' : (string) $post_id;
		return $GLOBALS['salt_test_fields'][ $context ][ $selector ] ?? null;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * wp_timezone shim — reads the timezone_string option, defaults UTC.
	 *
	 * @return DateTimeZone
	 */
	function wp_timezone(): DateTimeZone {
		$tz = get_option( 'timezone_string' );
		return new DateTimeZone( is_string( $tz ) && '' !== $tz ? $tz : 'UTC' );
	}
}

// ---------------------------------------------------------------------------
// Posts & queries.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post fixture class.
	 */
	#[\AllowDynamicProperties]
	class WP_Post {
 // phpcs:ignore Generic.Files.OneObjectStructurePerFile

		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_type = 'post';
		/** @var string */
		public $post_status = 'publish';
		/** @var string */
		public $post_title = '';
		/** @var string */
		public $post_excerpt = '';
		/** @var string */
		public $post_date = '2026-01-01 00:00:00';
		/** @var int */
		public $menu_order = 0;
		/** @var int[] Term IDs per taxonomy (fixture extension). */
		public $salt_terms = array();

		/**
		 * Build from an array of properties.
		 *
		 * @param array<string,mixed> $props Properties.
		 */
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * get_posts shim — filters/sorts the fixture set for the query shapes
	 * salt_resolve_query() produces (post_type, post_status, post__in,
	 * orderby, order, numberposts, single-taxonomy tax_query).
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return WP_Post[]
	 */
	function get_posts( array $args ): array {
		$posts = $GLOBALS['salt_test_posts'];

		$post_type = $args['post_type'] ?? 'post';
		$posts     = array_filter( $posts, static fn( WP_Post $p ): bool => $p->post_type === $post_type );

		$status = $args['post_status'] ?? 'publish';
		if ( 'any' !== $status ) {
			$posts = array_filter( $posts, static fn( WP_Post $p ): bool => $p->post_status === $status );
		}

		if ( ! empty( $args['post__in'] ) ) {
			$ids   = array_map( 'intval', (array) $args['post__in'] );
			$posts = array_filter( $posts, static fn( WP_Post $p ): bool => in_array( $p->ID, $ids, true ) );
		}

		if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			foreach ( $args['tax_query'] as $clause ) {
				if ( ! is_array( $clause ) || empty( $clause['terms'] ) ) {
					continue;
				}
				$terms = array_map( 'intval', (array) $clause['terms'] );
				$posts = array_filter(
					$posts,
					static fn( WP_Post $p ): bool => array() !== array_intersect( $terms, $p->salt_terms )
				);
			}
		}

		$posts   = array_values( $posts );
		$orderby = $args['orderby'] ?? 'date';
		$order   = strtoupper( $args['order'] ?? 'DESC' );

		if ( 'post__in' === $orderby && ! empty( $args['post__in'] ) ) {
			$rank = array_flip( array_map( 'intval', (array) $args['post__in'] ) );
			usort( $posts, static fn( WP_Post $a, WP_Post $b ): int => ( $rank[ $a->ID ] ?? PHP_INT_MAX ) <=> ( $rank[ $b->ID ] ?? PHP_INT_MAX ) );
		} else {
			$key = match ( $orderby ) {
				'menu_order' => 'menu_order',
				'title'      => 'post_title',
				default      => 'post_date',
			};
			usort( $posts, static fn( WP_Post $a, WP_Post $b ): int => $a->$key <=> $b->$key );
			if ( 'DESC' === $order ) {
				$posts = array_reverse( $posts );
			}
		}

		$limit = (int) ( $args['numberposts'] ?? -1 );
		if ( $limit > 0 ) {
			$posts = array_slice( $posts, 0, $limit );
		}

		return $posts;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * get_post shim (fixture-backed).
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( int $post_id ): ?WP_Post {
		foreach ( $GLOBALS['salt_test_posts'] as $post ) {
			if ( $post->ID === $post_id ) {
				return $post;
			}
		}
		return null;
	}
}

// ---------------------------------------------------------------------------
// Templates & rendering.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'get_template_directory' ) ) {
	/**
	 * Repo root as the parent theme directory.
	 *
	 * @return string
	 */
	function get_template_directory(): string {
		return dirname( __DIR__ );
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	/**
	 * Fixture URI for the parent theme.
	 *
	 * @return string
	 */
	function get_template_directory_uri(): string {
		return 'https://example.test/wp-content/themes/salt-wordpress';
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	/**
	 * locate_template shim — parent theme only (no child in unit context).
	 *
	 * @param string|string[] $template_names Template file names.
	 * @return string Path or empty string.
	 */
	function locate_template( $template_names ): string {
		foreach ( (array) $template_names as $name ) {
			$path = get_template_directory() . '/' . ltrim( $name, '/' );
			if ( is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'get_template_part' ) ) {
	/**
	 * get_template_part shim — loads the parent file, passing $args.
	 *
	 * @param string              $slug Template slug.
	 * @param string|null         $name Optional variant.
	 * @param array<string,mixed> $args Args exposed to the template.
	 * @return void|false
	 */
	function get_template_part( string $slug, ?string $name = null, array $args = array() ) {
		$candidates = array();
		if ( null !== $name && '' !== $name ) {
			$candidates[] = "{$slug}-{$name}.php";
		}
		$candidates[] = "{$slug}.php";

		$located = locate_template( $candidates );
		if ( '' === $located ) {
			return false;
		}
		load_template( $located, false, $args );
	}
}

if ( ! function_exists( 'load_template' ) ) {
	/**
	 * load_template shim — exposes $args like WordPress does.
	 *
	 * @param string              $template_file Path.
	 * @param bool                $load_once     Whether to require_once.
	 * @param array<string,mixed> $args          Template args.
	 * @return void
	 */
	function load_template( string $template_file, bool $load_once = true, array $args = array() ): void {
		if ( $load_once ) {
			require_once $template_file;
		} else {
			require $template_file;
		}
	}
}

// ---------------------------------------------------------------------------
// Conditional / user context.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * is_admin shim — always front-end in tests.
	 *
	 * @return bool
	 */
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * is_user_logged_in shim (fixture-controlled).
	 *
	 * @return bool
	 */
	function is_user_logged_in(): bool {
		return (bool) $GLOBALS['salt_test_user']['logged_in'];
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * current_user_can shim (fixture-controlled).
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( string $capability ): bool {
		return in_array( $capability, $GLOBALS['salt_test_user']['caps'], true );
	}
}

// ---------------------------------------------------------------------------
// Enqueue capture.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * wp_enqueue_style capture shim.
	 *
	 * @param string           $handle Handle.
	 * @param string           $src    Source URI.
	 * @param string[]         $deps   Dependencies.
	 * @param string|bool|null $ver    Version.
	 * @param string           $media  Media.
	 * @return void
	 */
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): void {
		$GLOBALS['salt_test_enqueued']['styles'][ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * wp_enqueue_script capture shim.
	 *
	 * @param string              $handle Handle.
	 * @param string              $src    Source URI.
	 * @param string[]            $deps   Dependencies.
	 * @param string|bool|null    $ver    Version.
	 * @param array<string,mixed> $args   Loading strategy args.
	 * @return void
	 */
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, array $args = array() ): void {
		$GLOBALS['salt_test_enqueued']['scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * wp_parse_url shim.
	 *
	 * @param string $url       URL.
	 * @param int    $component PHP_URL_* component.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * home_url shim.
	 *
	 * @param string $path Path to append.
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * is_singular shim (fixture-controlled; defaults true).
	 *
	 * With a $post_types argument it additionally matches against the
	 * fixture post type ($GLOBALS['salt_test_singular_post_type'],
	 * default 'page' — the FC-page fixtures).
	 *
	 * @param string|string[] $post_types Post type(s) to match ('' = any).
	 * @return bool
	 */
	function is_singular( $post_types = '' ): bool {
		$singular = (bool) ( $GLOBALS['salt_test_is_singular'] ?? true );

		if ( ! $singular || '' === $post_types || array() === $post_types ) {
			return $singular;
		}

		$current = (string) ( $GLOBALS['salt_test_singular_post_type'] ?? 'page' );

		return in_array( $current, (array) $post_types, true );
	}
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * wp_salt shim — stable per suite run.
	 *
	 * @param string $scheme Salt scheme.
	 * @return string
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return 'salt-test-' . $scheme;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Nonce shims — deterministic HMAC pair (create/verify agree).
	 *
	 * @param string $action Action.
	 * @return string
	 */
	function wp_create_nonce( string $action = '-1' ): string {
		return substr( hash_hmac( 'sha256', $action, wp_salt( 'nonce' ) ), 0, 10 );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * wp_verify_nonce shim.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Action.
	 * @return int|false
	 */
	function wp_verify_nonce( string $nonce, string $action = '-1' ) {
		return hash_equals( wp_create_nonce( $action ), $nonce ) ? 1 : false;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * sanitize_textarea_field shim — strips tags, keeps newlines.
	 *
	 * @param string $str Raw value.
	 * @return string
	 */
	function sanitize_textarea_field( string $str ): string {
		$filtered = wp_strip_all_tags( $str );
		return trim( preg_replace( '/[ \t]+/', ' ', $filtered ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * sanitize_text_field shim.
	 *
	 * @param string $str Raw value.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( $str ) ) ?? '' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * esc_url_raw shim.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {
		return esc_url( $url );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * wp_unslash shim.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * admin_url shim.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * add_query_arg shim — the two salt call shapes only.
	 *
	 * @param mixed  $key   Key or array.
	 * @param string $value Value (when key is a string; doubles as URL).
	 * @param string $url   URL when key/value used.
	 * @return string
	 */
	function add_query_arg( $key = array(), $value = '', $url = '' ) {
		if ( is_array( $key ) ) {
			return '' !== $value ? $value : '/current-page/';
		}
		$base = '' !== $url ? $url : '/current-page/';
		return $base . ( str_contains( $base, '?' ) ? '&' : '?' ) . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * is_email shim — WP-like: filter_var plus CR/LF rejection.
	 *
	 * @param string $email Candidate.
	 * @return string|false The email when valid, false otherwise.
	 */
	function is_email( string $email ) {
		if ( preg_match( '/[\r\n]/', $email ) ) {
			return false;
		}
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}
