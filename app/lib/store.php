<?php
/**
 * Core store — app tables in MariaDB (core/db.php, database/schema.sql).
 *
 * Reads:  store_rows($table) loads one table per request (lazy, cached), rows shaped exactly
 *         like the app expects (ints, bools, JSON lists decoded, unknown keys from `extra`).
 * Writes: store_mutate($mutator) runs inside a transaction holding a global row lock
 *         (app_meta 'lock' FOR UPDATE): it loads every table, lets the mutator change the
 *         state array, then writes only the difference (INSERT / UPDATE / DELETE by id).
 *         store_insert / store_update / store_delete build on it. Every write also opens today's
 *         empty daily sheets for students who do not have them yet (daily.php).
 * Meta:   app_meta keeps schema, seedDate (= the last day the daily sheets were opened), version
 *         (bumped on every write — the client polls it to refresh), updatedAt and nextId (global id
 *         sequence for new rows).
 *
 * Data is NEVER created, reset or deleted automatically — no demo data, no reseed. An empty database
 * is set up with `php database/install.php` (school + first admin account).
 * Callers must authorise first (permissions.php); the store only reads/writes rows.
 */
declare(strict_types=1);

final class ValidationException extends InvalidArgumentException
{
}

final class PermissionDeniedException extends RuntimeException
{
}

const MK_STORE_SCHEMA = 7; // data format version (recorded in app_meta; table changes go through database/schema.sql)

/** Tables read newest first (the app prepends new rows to them). */
const MK_STORE_DESC = ['statuses' => true];

/** Every app table stored in the database (core/schema.php). */
function store_tables(): array
{
    return array_keys(mk_tables());
}

/** app_meta as typed values (schema, seedDate, version, updatedAt, nextId). */
function store_load_meta(PDO $pdo): array
{
    $meta = $pdo->query('SELECT k, v FROM app_meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'schema'    => (int) ($meta['schema'] ?? 0),
        'seedDate'  => (string) ($meta['seedDate'] ?? ''),
        'version'   => (int) ($meta['version'] ?? 0),
        'updatedAt' => (string) ($meta['updatedAt'] ?? ''),
        'nextId'    => (int) ($meta['nextId'] ?? 1),
    ];
}

/**
 * Current meta (cached per request). The first call of a request seeds an empty database or
 * rolls yesterday's data forward, so every page sees today's state.
 */
function store_state(?array $replace = null): array
{
    static $meta = null;
    if ($replace !== null) {
        return $meta = $replace;
    }
    if ($meta !== null) {
        return $meta;
    }
    $meta = store_load_meta(mk_db()); // cached first, so nested calls during the write below do not recurse
    if ($meta['schema'] !== MK_STORE_SCHEMA || $meta['seedDate'] !== today()) {
        mk_log('Store', 'new day (open daily sheets) or new data format', ['schema' => $meta['schema'], 'seedDate' => $meta['seedDate']]);
        store_mutate(fn (array &$s) => null); // replaces $meta through store_state($state)
    }
    return $meta;
}

/** Row cache per table; store_rows_cache(null) clears it after a write. */
function store_rows_cache(?string $table = null, ?array $rows = null, bool $clear = false): ?array
{
    static $cache = [];
    if ($clear) {
        $cache = [];
        return null;
    }
    if ($rows !== null) {
        $cache[$table] = $rows;
    }
    return $cache[$table] ?? null;
}

/** Raw (UNSCOPED) rows of a table — use scoped() from data-filter.php for anything shown to users. */
function store_rows(string $table): array
{
    $cached = store_rows_cache($table);
    if ($cached !== null) {
        return $cached;
    }
    store_state(); // open today's daily sheets first
    return store_rows_cache($table, store_select($table));
}

/** SELECT a whole table, converted to app rows. */
function store_select(string $table): array
{
    $db = mk_db_table($table);
    $columns = mk_db_columns()[$db] ?? null;
    if ($columns === null) {
        error_log('[Mykid][Store][store_select] ERROR table missing ' . json_encode(['table' => $table, 'db' => $db]));
        throw new RuntimeException('Table missing: ' . $db);
    }
    $order = isset(MK_STORE_DESC[$table]) ? 'DESC' : 'ASC';
    $rows = mk_db()->query('SELECT * FROM ' . mk_db_quote($db) . ' ORDER BY id ' . $order)->fetchAll();
    return array_map(fn (array $row) => store_from_db($row, $columns), $rows);
}

/** Database row → app row. */
function store_from_db(array $row, array $columns): array
{
    $extra = isset($row['extra']) ? (json_decode($row['extra'], true) ?: []) : [];
    unset($row['extra']);
    foreach ($row as $key => $value) {
        if ($value === null) {
            continue;
        }
        $row[$key] = match ($columns[$key] ?? 'string') {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => (bool) $value,
            'json'  => json_decode((string) $value, true),
            default => $value,
        };
    }
    return $row + $extra;
}

/** App row → [column => value] for SQL. Keys without a column go to `extra` (JSON). */
function store_to_db(string $table, array $row, array $columns): array
{
    static $warned = [];
    $out = [];
    $extra = [];
    foreach ($row as $key => $value) {
        $kind = $columns[$key] ?? null;
        if ($kind === null || $key === 'extra') {
            $extra[$key] = $value;
            if (!isset($warned[$table][$key])) {
                $warned[$table][$key] = true;
                mk_log('Store', 'store_to_db: key has no column, kept in extra', ['table' => $table, 'key' => $key]);
            }
            continue;
        }
        if ($value === '' && in_array($kind, ['int', 'float', 'bool', 'date', 'datetime'], true)) {
            $value = null;
        }
        $out[$key] = match (true) {
            $value === null                => null,
            $kind === 'json'               => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $kind === 'bool'               => $value ? 1 : 0,
            is_bool($value)                => $value ? 1 : 0,
            is_array($value)               => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default                        => $value,
        };
    }
    if (isset($columns['extra'])) {
        $out['extra'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }
    return $out;
}

/** Atomically load → modify → save. Returns ['state' => ..., 'result' => mutator result]. */
function store_mutate(callable $mutator): array
{
    mk_log('Store', 'store_mutate START');
    store_state(); // open today's sheets in its own transaction before this write
    $pdo = mk_db();
    $pdo->beginTransaction();
    try {
        $pdo->query("SELECT v FROM app_meta WHERE k = 'lock' FOR UPDATE")->fetchColumn(); // serialises writers (like flock)

        $original = [];
        foreach (store_tables() as $table) {
            $original[$table] = store_select($table);
        }
        $state = store_load_meta($pdo) + ['tables' => $original];

        $state['schema'] = MK_STORE_SCHEMA; // never reseeds: table changes are migrations in schema.sql
        $result = $mutator($state);
        daily_sheets_fill($state); // new day / new student → today's empty records
        $state['seedDate'] = today();
        $state['version']++;
        $state['updatedAt'] = date('c');

        $changes = store_write_diff($pdo, $original, $state['tables']);
        $meta = $pdo->prepare('REPLACE INTO app_meta (k, v) VALUES (?, ?)');
        foreach (['schema', 'seedDate', 'version', 'updatedAt', 'nextId'] as $key) {
            $meta->execute([$key, (string) $state[$key]]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (!$e instanceof ValidationException && !$e instanceof PermissionDeniedException) {
            error_log('[Mykid][Store][store_mutate] ERROR ' . $e->getMessage());
        }
        throw $e;
    }

    store_rows_cache(clear: true);
    store_state(array_diff_key($state, ['tables' => true]));
    mk_log('Store', 'store_mutate END', ['version' => $state['version']] + $changes);
    return ['state' => $state, 'result' => $result];
}

/** Write the difference between two table sets. Returns counts of inserted / updated / deleted rows. */
function store_write_diff(PDO $pdo, array $before, array $after): array
{
    $count = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];
    $norm = fn ($v) => $v === null ? null : (string) $v;

    foreach (store_tables() as $table) {
        $db = mk_db_table($table);
        $columns = mk_db_columns()[$db];
        $old = [];
        foreach ($before[$table] ?? [] as $row) {
            $old[$row['id']] = array_map($norm, store_to_db($table, $row, $columns));
        }

        $inserts = [];
        foreach ($after[$table] ?? [] as $row) {
            $new = store_to_db($table, $row, $columns);
            $id = $row['id'];
            if (!isset($old[$id])) {
                $inserts[] = $new;
                continue;
            }
            $changed = array_filter($new, fn ($v, $k) => $norm($v) !== ($old[$id][$k] ?? null), ARRAY_FILTER_USE_BOTH);
            unset($old[$id]);
            if ($changed) {
                $set = implode(', ', array_map(fn ($k) => mk_db_quote($k) . ' = ?', array_keys($changed)));
                $pdo->prepare('UPDATE ' . mk_db_quote($db) . " SET $set WHERE id = ?")->execute([...array_values($changed), $id]);
                $count['updated']++;
            }
        }

        foreach (array_chunk(array_keys($old), 500) as $ids) { // rows no longer in the state
            $pdo->prepare('DELETE FROM ' . mk_db_quote($db) . ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')')->execute($ids);
            $count['deleted'] += count($ids);
        }

        foreach (array_chunk($inserts, 200) as $chunk) {
            $names = array_keys($columns);
            $values = [];
            $tuples = [];
            foreach ($chunk as $row) {
                $tuples[] = '(' . implode(', ', array_map(function ($name) use ($row, &$values) {
                    if (!array_key_exists($name, $row)) {
                        return 'DEFAULT'; // key not set by the app → column default
                    }
                    $values[] = $row[$name];
                    return '?';
                }, $names)) . ')';
            }
            $sql = 'INSERT INTO ' . mk_db_quote($db) . ' (' . implode(', ', array_map('mk_db_quote', $names)) . ') VALUES ' . implode(', ', $tuples);
            $pdo->prepare($sql)->execute($values);
            $count['inserted'] += count($chunk);
        }
    }
    return $count;
}

function store_find(array $state, string $table, int $id): ?int
{
    foreach ($state['tables'][$table] ?? [] as $index => $row) {
        if (($row['id'] ?? null) === $id) {
            return $index;
        }
    }
    return null;
}

function store_insert(string $table, array $row): int
{
    mk_log('Store', 'store_insert', ['table' => $table, 'row' => $row]);
    return store_mutate(function (array &$state) use ($table, $row) {
        $row = ['id' => $state['nextId']++] + $row;
        $state['tables'][$table][] = $row;
        return $row['id'];
    })['result'];
}

/** Update a row; $guard(oldRow) must return true (scope check against the stored row). */
function store_update(string $table, int $id, array $changes, callable $guard): void
{
    mk_log('Store', 'store_update', ['table' => $table, 'id' => $id, 'changes' => $changes]);
    store_mutate(function (array &$state) use ($table, $id, $changes, $guard) {
        $index = store_find($state, $table, $id);
        if ($index === null) {
            throw new ValidationException('ไม่พบข้อมูลนี้ อาจถูกลบไปแล้ว');
        }
        $guard($state['tables'][$table][$index]);
        $state['tables'][$table][$index] = array_merge($state['tables'][$table][$index], $changes);
    });
}

function store_delete(string $table, int $id, callable $guard): void
{
    mk_log('Store', 'store_delete', ['table' => $table, 'id' => $id]);
    store_mutate(function (array &$state) use ($table, $id, $guard) {
        $index = store_find($state, $table, $id);
        if ($index === null) {
            throw new ValidationException('ไม่พบข้อมูลนี้ อาจถูกลบไปแล้ว');
        }
        $guard($state['tables'][$table][$index]);
        array_splice($state['tables'][$table], $index, 1);
    });
}
