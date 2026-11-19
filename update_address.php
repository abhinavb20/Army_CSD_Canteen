<?php
session_start();
include 'config.php';
require_once 'address_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('manage_addresses.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_addresses.php");
    exit;
}

// CSRF check
if (!hash_equals($_SESSION['addr_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die('Invalid request. Please reload the page and try again.');
}

$user_id = (int)$_SESSION['user_id'];
$id      = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_addresses.php?msg=" . urlencode("Invalid address."));
    exit;
}

// Go back to the edit page with the message and the typed values
function back_to_edit(int $id, array $data, string $text): void {
    $_SESSION['addr_flash']    = ['type' => 'error', 'text' => $text];
    $_SESSION['addr_edit_old'] = ['id' => $id, 'data' => $data];
    header("Location: edit_address.php?id=" . $id);
    exit;
}

$in = address_read($_POST);

// Validate on the server
if ($err = address_error($in)) {
    back_to_edit($id, $in, $err);
}

// Make sure the address belongs to this user
$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_row();
$stmt->close();

if (!$exists) {
    header("Location: manage_addresses.php?msg=" . urlencode("Address not found."));
    exit;
}

$alt      = address_nullable($in['alt_phone']);
$locality = address_nullable($in['locality']);
$landmark = address_nullable($in['landmark']);

$stmt = $conn->prepare(
    "UPDATE addresses
     SET name = ?, phone = ?, alt_phone = ?, pincode = ?, locality = ?,
         address_line = ?, city = ?, state = ?, landmark = ?, address_type = ?
     WHERE id = ? AND user_id = ?"
);
$stmt->bind_param(
    "ssssssssssii",
    $in['name'],
    $in['phone'],
    $alt,
    $in['pincode'],
    $locality,
    $in['address_line'],
    $in['city'],
    $in['state'],
    $landmark,
    $in['address_type'],
    $id,
    $user_id
);

if (!$stmt->execute()) {
    error_log('Update address failed: ' . $stmt->error);
    $stmt->close();
    back_to_edit($id, $in, "We could not save your changes. Please try again.");
}
$stmt->close();

// Clear the form data and go back to the list
unset($_SESSION['addr_edit_old'], $_SESSION['addr_flash']);
header("Location: manage_addresses.php?msg=" . urlencode("Address saved."));
exit;