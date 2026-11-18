<?php
/**
 * Creates or drops the harness's throwaway database. HARNESS ONLY.
 *
 * Usage: php db.php create|drop|snapshot|restore <name>
 * snapshot copies every wp_ table to base_wp_<table>; restore puts every
 * wp_ table back as its copy was, row for row and with the same next ID, so
 * each conformance case starts from the same site (view.php).
 * Connection: SALT_HARNESS_DB_HOST (host, host:port or localhost:/socket),
 * SALT_HARNESS_DB_USER, SALT_HARNESS_DB_PASSWORD. Only names matching
 * salt_harness_[a-z0-9]+ are accepted, so this can never touch another
 * database.
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$salt_harness_action = $argv[1] ?? '';
$salt_harness_name   = $argv[2] ?? '';

if ( ! in_array( $salt_harness_action, array( 'create', 'drop', 'snapshot', 'restore' ), true ) || ! preg_match( '/^salt_harness_[a-z0-9]+$/', $salt_harness_name ) ) {
	fwrite( STDERR, "usage: db.php create|drop|snapshot|restore salt_harness_<id>\n" );
	exit( 2 );
}

$salt_harness_host   = (string) getenv( 'SALT_HARNESS_DB_HOST' );
$salt_harness_socket = null;
$salt_harness_port   = null;
if ( preg_match( '/^([^:]+):(\/.+)$/', $salt_harness_host, $salt_harness_m ) ) {
	$salt_harness_host   = $salt_harness_m[1];
	$salt_harness_socket = $salt_harness_m[2];
} elseif ( preg_match( '/^([^:]+):(\d+)$/', $salt_harness_host, $salt_harness_m ) ) {
	$salt_harness_host = $salt_harness_m[1];
	$salt_harness_port = (int) $salt_harness_m[2];
}

// WordPress is not installed yet (this creates its database), so there is no $wpdb.
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- No WordPress here.
try {
	$salt_harness_db = new mysqli( $salt_harness_host, (string) getenv( 'SALT_HARNESS_DB_USER' ), (string) getenv( 'SALT_HARNESS_DB_PASSWORD' ), '', (int) $salt_harness_port, $salt_harness_socket ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- No WordPress here.
	if ( in_array( $salt_harness_action, array( 'create', 'drop' ), true ) ) {
		$salt_harness_db->query(
			'create' === $salt_harness_action
				? "CREATE DATABASE `{$salt_harness_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci"
				: "DROP DATABASE IF EXISTS `{$salt_harness_name}`"
		);
	} else {
		$salt_harness_db->select_db( $salt_harness_name );
		$salt_harness_tables = array_column( $salt_harness_db->query( "SHOW TABLES LIKE 'wp\\_%'" )->fetch_all(), 0 );
		foreach ( $salt_harness_tables as $salt_harness_table ) {
			if ( 'snapshot' === $salt_harness_action ) {
				$salt_harness_db->query( "DROP TABLE IF EXISTS `base_{$salt_harness_table}`" );
				$salt_harness_db->query( "CREATE TABLE `base_{$salt_harness_table}` LIKE `{$salt_harness_table}`" );
				$salt_harness_db->query( "INSERT INTO `base_{$salt_harness_table}` SELECT * FROM `{$salt_harness_table}`" );
			} else {
				// TRUNCATE resets the counter; the copied rows then set the next ID to the highest restored
				// ID plus one. That is not always the next ID the snapshot had, but it is the same on every restore.
				$salt_harness_db->query( "TRUNCATE TABLE `{$salt_harness_table}`" );
				$salt_harness_db->query( "INSERT INTO `{$salt_harness_table}` SELECT * FROM `base_{$salt_harness_table}`" );
			}
		}
	}
} catch ( mysqli_sql_exception $e ) {
	fwrite( STDERR, $salt_harness_action . ' ' . $salt_harness_name . ' failed: ' . $e->getMessage() . "\n" );
	exit( 1 );
}
