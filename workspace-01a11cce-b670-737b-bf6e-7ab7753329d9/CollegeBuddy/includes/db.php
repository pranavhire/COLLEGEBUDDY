<?php
/*
 * includes/db.php
 * Database connection (MySQL through mysqli) and small helper functions.
 *
 * XAMPP default: user "root" with an EMPTY password.
 * Change the values below ONLY if you have set a MySQL password.
 */

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'college_buddy';

// Keep false for normal use. Set to true only while developing to see PHP errors on screen.
const APP_DEBUG = false;

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');          // errors go to the Apache error log instead
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

// Make mysqli throw exceptions, so every database error is handled in one place
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Returns the shared database connection (created the first time it is needed). */
function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '+05:30'"); // keep MySQL timestamps in Indian time
    }
    return $conn;
}

/*
 * Prepared-statement helpers.
 * $types = one letter per placeholder: s = string, i = integer, d = decimal.
 * Values are always passed as parameters, never pasted into the SQL text (prevents SQL injection).
 */

/** SELECT: returns all rows as an array of associative arrays. */
function db_select(string $sql, string $types = '', array $params = []): array
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** SELECT: returns the first row, or null if nothing matched. */
function db_select_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_select($sql, $types, $params);
    return $rows[0] ?? null;
}

/** SELECT COUNT(*): returns the number in the first column. */
function db_count(string $sql, string $types = '', array $params = []): int
{
    $row = db_select_one($sql, $types, $params);
    return $row ? (int) reset($row) : 0;
}

/** UPDATE / DELETE: returns the number of rows changed. */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/** INSERT: returns the new row's id (AUTO_INCREMENT value). */
function db_insert(string $sql, string $types = '', array $params = []): int
{
    db_exec($sql, $types, $params);
    return (int) db()->insert_id;
}
