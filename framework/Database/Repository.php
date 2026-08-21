<?php
/**
 * Base repository with generic CRUD over one custom table.
 *
 * Extracted from wpheka-seo-os src/Database/Repository.php, which has run in
 * production since 2026, and rebased onto Schema so table scope is resolved
 * per table rather than assuming $wpdb->prefix (ADR-012).
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Generic CRUD over a single custom table.
 *
 * Deliberately thin. It removes the repetitive parts of `$wpdb` work —
 * prefixing, JSON encoding, timestamp stamping, prepared WHERE building — and
 * gets out of the way for anything real. A repository that tries to become an
 * ORM is a repository that will be fought.
 */
abstract class Repository {

	/**
	 * The schema this table belongs to.
	 *
	 * @var Schema
	 */
	protected Schema $schema;

	/**
	 * Unprefixed table name.
	 *
	 * @var string
	 */
	protected string $table = '';

	/**
	 * Columns stored as JSON, encoded on write and decoded on read.
	 *
	 * @var string[]
	 */
	protected array $json_columns = array();

	/**
	 * Construct a repository bound to a schema.
	 *
	 * @param Schema $schema Owning schema.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Fully-prefixed table name, honouring the table's declared scope.
	 *
	 * @return string
	 */
	public function table(): string {
		return $this->schema->table( $this->table );
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New row id, or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$encoded = $this->encode( $data );

		// A JSON column that cannot be encoded fails the write rather than
		// storing a partial row. See encode().
		if ( null === $encoded ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $this->table(), $encoded );

		/*
		 * The return value is checked, not just insert_id. wpdb::query() bails
		 * before touching insert_id when a query fails, so it still holds the id
		 * of the previous successful insert in this request — a failed insert
		 * would hand back another row's id and read as success.
		 */
		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a row by id.
	 *
	 * **True means the statement ran, not that a row changed.** MySQL reports
	 * zero affected rows both when no such id exists and when every value already
	 * equalled what was passed, and those two cannot be told apart from the
	 * return value. Reporting failure for zero was considered and rejected: it
	 * would make a no-op update — saving a form nobody edited — read as a
	 * database error, which is the more common case by far and the more damaging
	 * one to get wrong.
	 *
	 * A caller needing to know whether the row exists should ask before writing.
	 *
	 * @param int                  $id   Row id.
	 * @param array<string, mixed> $data Column values.
	 * @return bool False when the write failed or the data could not be encoded.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$encoded = $this->encode( $data );

		// A JSON column that cannot be encoded fails the write rather than
		// leaving the stored value silently unchanged. See encode().
		if ( null === $encoded ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( $this->table(), $encoded, array( 'id' => $id ) );
	}

	/**
	 * Delete a row by id.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	/**
	 * Find one row by id.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $this->decode( $row ) : null;
	}

	/**
	 * Query rows by equality on one or more columns.
	 *
	 * Column names are validated against the table's real columns before being
	 * interpolated, since identifiers cannot be bound as parameters. Values are
	 * always bound.
	 *
	 * @param array<string, mixed> $where    Column => value.
	 * @param string               $order_by Validated ORDER BY clause.
	 * @param int                  $limit    Maximum rows.
	 * @param int                  $offset   Rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function where( array $where = array(), string $order_by = 'id DESC', int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();
		$built = $this->build_where( $where );

		if ( null === $built ) {
			return array();
		}

		$clauses = $built['clauses'];
		$values  = $built['values'];

		$sql = "SELECT * FROM {$table}";

		if ( $clauses ) {
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}

		$sql .= ' ORDER BY ' . $this->safe_order_by( $order_by );
		$sql .= ' LIMIT %d OFFSET %d';

		/*
		 * Clamped at zero, not at one. A negative value here is a caller bug —
		 * typically an offset computed from a page number that went below one —
		 * and it produces invalid SQL rather than a wrong answer, so the failure
		 * is a logged database error and an empty result that reads as "no rows".
		 *
		 * `LIMIT 0` is deliberately preserved rather than raised to 1. It is
		 * valid SQL meaning "no rows", and a caller asking for none must not be
		 * handed one.
		 */
		$values[] = max( 0, $limit );
		$values[] = max( 0, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		return array_map( array( $this, 'decode' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Count rows matching an equality filter.
	 *
	 * @param array<string, mixed> $where Column => value.
	 * @return int
	 */
	public function count( array $where = array() ): int {
		global $wpdb;

		$table = $this->table();
		$built = $this->build_where( $where );

		if ( null === $built ) {
			return 0;
		}

		$clauses = $built['clauses'];
		$values  = $built['values'];

		$sql = "SELECT COUNT(*) FROM {$table}";

		if ( $clauses ) {
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}

		// No user input in this branch: the only interpolation is the table name,
		// which comes from our own schema and cannot be bound as a parameter.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Build the WHERE clauses and bound values for an equality filter.
	 *
	 * Shared by `where()` and `count()`, which is the point. They carried
	 * byte-identical copies of this loop, so the same defect existed twice and a
	 * fix to one would have left `count()` answering a different question than
	 * `where()` for the same filter.
	 *
	 * Only the placeholder type was ever decided here, `%d` for an int and `%s`
	 * for everything else, which quietly mishandled three shapes:
	 *
	 * - `null` bound as `''`, so the clause asked `col = ''` where the caller
	 *   meant `col IS NULL` — a plausible-looking wrong answer rather than a
	 *   failure.
	 * - `true` / `false` bound as `'1'` and `''`, which match nothing in an
	 *   integer or boolean column.
	 * - An array or object reached `prepare()` and errored.
	 *
	 * @param array<string, mixed> $where Column => value.
	 * @return array{clauses: string[], values: array<int, mixed>}|null Null when the filter cannot be honoured.
	 */
	private function build_where( array $where ): ?array {
		$clauses = array();
		$values  = array();

		foreach ( $where as $column => $value ) {
			/*
			 * Fail closed. Skipping an unrecognised column drops that condition
			 * and answers a *wider* question than was asked — and columns() can
			 * legitimately come back empty, when the table is missing or a
			 * migration failed, at which point every condition disappears and a
			 * filtered read returns the entire table. Returning nothing is the
			 * safe direction: visibly wrong rather than quietly too permissive.
			 */
			if ( ! $this->is_column( $column ) ) {
				return null;
			}

			// NULL never equals anything in SQL, NULL included, so `col = %s` with
			// a null bound can only ever match zero rows however the column reads.
			if ( null === $value ) {
				$clauses[] = "`{$column}` IS NULL";

				continue;
			}

			if ( is_bool( $value ) ) {
				$value = (int) $value;
			}

			// Same fail-closed reasoning as an unknown column: there is no honest
			// scalar reading of a structure, and guessing one answers a question
			// nobody asked.
			if ( ! is_scalar( $value ) ) {
				return null;
			}

			// Floats stay on %s deliberately: %f would impose a fixed precision on
			// a value the caller chose, and MySQL coerces the string for a numeric
			// column anyway.
			$clauses[] = "`{$column}` = " . ( is_int( $value ) ? '%d' : '%s' );
			$values[]  = $value;
		}

		return array(
			'clauses' => $clauses,
			'values'  => $values,
		);
	}

	/**
	 * Column names per fully-prefixed table, for the life of the request.
	 *
	 * A class property rather than a `static` inside `columns()`, so
	 * `forget_columns()` can reach it after the schema changes underneath.
	 *
	 * @var array<string, string[]>
	 */
	private static array $columns_cache = array();

	/**
	 * Real column names for this table.
	 *
	 * An empty or failed answer is **not** cached. `SHOW COLUMNS` comes back empty
	 * when the table is absent — mid-activation, or after a failed migration — and
	 * remembering that for the rest of the request compounds the fail-closed
	 * behaviour of `where()` and `count()`: every filtered read answers nothing,
	 * on a table that exists by the time it is queried.
	 *
	 * @return string[]
	 */
	protected function columns(): array {
		global $wpdb;

		$table = $this->table();

		if ( isset( self::$columns_cache[ $table ] ) ) {
			return self::$columns_cache[ $table ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

		if ( ! is_array( $found ) || array() === $found ) {
			return array();
		}

		self::$columns_cache[ $table ] = $found;

		return $found;
	}

	/**
	 * Discard remembered column names.
	 *
	 * Called by `Schema::install()`: dbDelta can create or alter a table within
	 * the same request that already asked about it, and a repository holding the
	 * pre-install answer would keep failing closed against a table that is now
	 * correct.
	 *
	 * @param string|null $table Fully-prefixed table name, or null for every table.
	 * @return void
	 */
	public static function forget_columns( ?string $table = null ): void {
		if ( null === $table ) {
			self::$columns_cache = array();

			return;
		}

		unset( self::$columns_cache[ $table ] );
	}

	/**
	 * Whether a name is a real column on this table.
	 *
	 * @param string $column Candidate column name.
	 * @return bool
	 */
	protected function is_column( string $column ): bool {
		return in_array( $column, $this->columns(), true );
	}

	/**
	 * Validate an ORDER BY clause against real columns.
	 *
	 * Identifiers cannot be bound, so anything reaching ORDER BY is validated
	 * rather than escaped. Unrecognised input falls back to `id DESC` instead
	 * of being passed through.
	 *
	 * @param string $order_by Requested clause, e.g. "created_at DESC".
	 * @return string
	 */
	protected function safe_order_by( string $order_by ): string {
		$parts = preg_split( '/\s+/', trim( $order_by ) );

		if ( ! is_array( $parts ) ) {
			$parts = array();
		}

		$column    = $parts[0] ?? 'id';
		$direction = strtoupper( $parts[1] ?? 'DESC' );

		if ( ! $this->is_column( $column ) ) {
			return '`id` DESC';
		}

		return sprintf( '`%s` %s', $column, 'ASC' === $direction ? 'ASC' : 'DESC' );
	}

	/**
	 * Encode JSON columns and stamp timestamps on write.
	 *
	 * Returns null when a JSON column cannot be encoded, which the callers turn
	 * into a refused write. See the note on the failure path below for why this
	 * does not simply store what it managed to encode.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return array<string, mixed>|null Encoded row, or null when it cannot be encoded.
	 */
	protected function encode( array $data ): ?array {
		$columns = $this->columns();

		foreach ( $this->json_columns as $column ) {
			if ( isset( $data[ $column ] ) && ! is_string( $data[ $column ] ) ) {
				$encoded = wp_json_encode( $data[ $column ] );

				/*
				 * `wp_json_encode()` returns false for INF and NAN, for nesting
				 * past its depth limit, and for circular references. Casting that
				 * to string wrote `''`, which decode() cannot tell from a
				 * legitimately stored empty string — so the row looked fine and
				 * the data was gone.
				 *
				 * **Not malformed UTF-8**, which an earlier version of this note
				 * claimed. `wp_json_encode()` catches `json_encode()` failing and
				 * retries through `_wp_json_sanity_check()`, which substitutes the
				 * offending bytes — so it returns a valid string and this branch
				 * never sees it. Confirmed against WordPress 6.9: the value
				 * arrives stored, with the bad byte replaced by `?`, and nothing
				 * reports that it changed. That is core's behaviour and this guard
				 * does not alter it; a caller that must preserve arbitrary bytes
				 * has to encode them itself before they reach a JSON column.
				 *
				 * Refusing the write is deliberate, over the two quieter
				 * alternatives. Dropping the column leaves an insert taking the
				 * column default and an update keeping the old value, both of
				 * which report success while silently storing something other
				 * than what was asked for. A repository that reports a write it
				 * did not perform is the defect family this framework has
				 * already been bitten by in OrderMeta::set_many().
				 */
				if ( false === $encoded ) {
					return null;
				}

				$data[ $column ] = $encoded;
			}
		}

		// GMT, not site time: rows outlive timezone settings.
		if ( ! isset( $data['created_at'] ) && in_array( 'created_at', $columns, true ) ) {
			$data['created_at'] = current_time( 'mysql', true );
		}

		if ( ! isset( $data['updated_at'] ) && in_array( 'updated_at', $columns, true ) ) {
			$data['updated_at'] = current_time( 'mysql', true );
		}

		return $data;
	}

	/**
	 * Decode JSON columns on read.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	protected function decode( array $row ): array {
		foreach ( $this->json_columns as $column ) {
			if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) ) {
				$decoded        = json_decode( $row[ $column ], true );
				$row[ $column ] = null === $decoded ? $row[ $column ] : $decoded;
			}
		}

		return $row;
	}
}
