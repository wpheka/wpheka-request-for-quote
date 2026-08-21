<?php
/**
 * Custom table definition and installation.
 *
 * Extracted from wpheka-seo-os src/Database/Schema.php, and extended with the
 * per-site versus network scope that version lacks — it uses $wpdb->prefix
 * unconditionally, which is wrong for any table belonging to a network
 * (ADR-012).
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Declares a plugin's custom tables and creates them with dbDelta.
 *
 * **Scope is explicit per table.** `$wpdb->prefix` is per-site (`wp_2_`);
 * `$wpdb->base_prefix` is network-wide (`wp_`). Choosing wrongly either
 * scatters network data across sites or leaks one site's rows into another,
 * and on a single-site install both prefixes are identical so the mistake is
 * invisible until a customer runs a network.
 */
final class Schema {

	/** Table lives on each site. Uses $wpdb->prefix. */
	public const SCOPE_SITE = 'site';

	/** One table for the whole network. Uses $wpdb->base_prefix. */
	public const SCOPE_NETWORK = 'network';

	/**
	 * Table definitions, keyed by unprefixed table name.
	 *
	 * Each entry is array{ columns: string, scope?: string }, where `columns`
	 * is the body of the CREATE TABLE statement.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $tables;

	/**
	 * Construct a schema from table definitions.
	 *
	 * @param array<string, array<string, string>> $tables Keyed by unprefixed name.
	 */
	public function __construct( array $tables ) {
		$this->tables = $tables;
	}

	/**
	 * Fully-prefixed name for one table.
	 *
	 * @param string $name Unprefixed table name.
	 * @return string
	 */
	public function table( string $name ): string {
		global $wpdb;

		$scope = $this->tables[ $name ]['scope'] ?? self::SCOPE_SITE;

		return ( self::SCOPE_NETWORK === $scope ? $wpdb->base_prefix : $wpdb->prefix ) . $name;
	}

	/**
	 * Every table name this schema declares, unprefixed.
	 *
	 * @return string[]
	 */
	public function names(): array {
		return array_keys( $this->tables );
	}

	/**
	 * Create or upgrade every table.
	 *
	 * Idempotent, which it must be: it runs on activation, on every site of a
	 * network, on sites created later, and on upgrade (ADR-012). dbDelta only
	 * creates what is absent and alters what has changed, so repeat runs are
	 * cheap and safe.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		foreach ( $this->tables as $name => $definition ) {
			$columns = $definition['columns'] ?? '';

			if ( '' === $columns ) {
				continue;
			}

			$table = $this->table( $name );

			dbDelta( sprintf( 'CREATE TABLE %s (%s) %s', $table, $columns, $charset ) );

			/*
			 * A repository may already have asked this table for its columns in
			 * this request — during activation it usually has, and the answer was
			 * "no such table". Repository::where() and ::count() fail closed on an
			 * empty column list, so leaving that answer in place means every
			 * filtered read returns nothing against a table that now exists.
			 */
			Repository::forget_columns( $table );
		}
	}

	/**
	 * Tables this schema declares that do not exist in the database.
	 *
	 * This exists because of a real production failure in wpheka-seo-os: a
	 * table was lost to a failed migration, and because the stored schema
	 * version still matched, nothing ever re-ran dbDelta. The table stayed
	 * missing for weeks while the feature silently produced nothing and kept
	 * consuming API quota on every run. A version gate alone cannot detect
	 * that; only asking the database can.
	 *
	 * @return string[] Unprefixed names of missing tables.
	 */
	public function missing(): array {
		global $wpdb;

		$missing = array();

		foreach ( array_keys( $this->tables ) as $name ) {
			$table = $this->table( $name );

			/*
			 * Escaped: table names are full of underscores, which LIKE treats as
			 * single-character wildcards, so an alphabetically earlier near-match
			 * would report an existing table as missing.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema inspection, deliberately uncached.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			if ( $found !== $table ) {
				$missing[] = $name;
			}
		}

		return $missing;
	}

	/**
	 * Drop the tables this schema declares at one scope.
	 *
	 * For uninstall only, and **scope is the whole point**. A network-scoped
	 * declaration resolves against `$wpdb->base_prefix`, so it names one table
	 * shared by every site on the network. The documented uninstall path iterates
	 * sites via `Core\Lifecycle::for_each_site()`, so a version of this method
	 * that dropped every declared table would destroy every other site's data the
	 * first time one site was uninstalled — unrecoverable, and silent at the
	 * moment it happened. It did exactly that until this signature existed
	 * (ADR-026).
	 *
	 * So: call this per site for the site-scoped tables, and `drop_network()`
	 * **once**, from the network-level uninstall, for the shared ones.
	 *
	 * @param string $scope self::SCOPE_SITE or self::SCOPE_NETWORK.
	 * @return void
	 */
	public function drop( string $scope = self::SCOPE_SITE ): void {
		global $wpdb;

		$wanted = self::SCOPE_NETWORK === $scope ? self::SCOPE_NETWORK : self::SCOPE_SITE;

		foreach ( $this->tables as $name => $definition ) {
			// Normalised the same way table() normalises it, so a declaration
			// carrying an unrecognised scope is dropped by the same call that
			// would have created it rather than by neither.
			$declared = self::SCOPE_NETWORK === ( $definition['scope'] ?? '' )
				? self::SCOPE_NETWORK
				: self::SCOPE_SITE;

			if ( $declared !== $wanted ) {
				continue;
			}

			$table = $this->table( $name );

			// Table names cannot be parameterised; the value comes from our own
			// declarations and the prefix from $wpdb, never from user input.
			// SchemaChange is suppressed for this line only: dropping tables is the
			// documented purpose of this method, called from uninstall.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

			// The table is gone, so any remembered column list for it is a lie.
			Repository::forget_columns( $table );
		}
	}

	/**
	 * Drop the network-shared tables this schema declares.
	 *
	 * Call **once per network**, never from inside a per-site loop. Named rather
	 * than left as an argument so that "this destroys data belonging to every
	 * site" is visible at the call site and in review.
	 *
	 * @return void
	 */
	public function drop_network(): void {
		$this->drop( self::SCOPE_NETWORK );
	}
}
