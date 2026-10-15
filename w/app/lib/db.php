<?php
/**
 * Database access (PDO). All queries use prepared statements.
 * SQL is kept portable (no MySQL-only functions in queries; timestamps come from PHP)
 * so the same code is tested against SQLite in development and runs on MySQL in production.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!defined('DB_NAME') && !defined('DB_DSN')) {
        throw new RuntimeException('Database is not configured yet. Run install.php.');
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (defined('DB_DSN')) {                       // development/test override
        $pdo = new PDO(DB_DSN, null, null, $opts);
        if (str_starts_with(DB_DSN, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        // Do not chain the original exception: its trace can contain the password.
        throw new RuntimeException('Database connection failed (' . $e->getCode() . '). Check app/config.php.');
    }
    return $pdo;
}

function db_all(string $sql, array $args = []): array
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function db_one(string $sql, array $args = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($args);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $args = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($args);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $args = []): int
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
}

/** Insert a row from an associative array; returns the new id. */
function db_insert(string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_exec($sql, array_values($row));
    return (int)db()->lastInsertId();
}

/** Update a row by id from an associative array. */
function db_update(string $table, int $id, array $row): void
{
    $sets = implode(',', array_map(fn($c) => "$c=?", array_keys($row)));
    db_exec("UPDATE $table SET $sets WHERE id=?", [...array_values($row), $id]);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
