<?php
session_start();
include 'config.php';
if (!defined('LOW_STOCK_LIMIT')) define('LOW_STOCK_LIMIT', 5);

// Only logged-in admins can view admin pages
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php?msg=admin_required");
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Single value from a query, with a fallback if the query fails
// (for example, when a column has not been added yet)
function scalar_value(mysqli $conn, string $sql, $default = 0) {
    try {
        $res = $conn->query($sql);
        $row = $res ? $res->fetch_row() : null;
        return $row[0] ?? $default;
    } catch (mysqli_sql_exception $ex) {
        error_log('Admin query failed: ' . $ex->getMessage());
        return $default;
    }
}

// All rows from a query, or an empty list if it fails
function rows(mysqli $conn, string $sql): array {
    try {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (mysqli_sql_exception $ex) {
        error_log('Admin query failed: ' . $ex->getMessage());
        return [];
    }
}