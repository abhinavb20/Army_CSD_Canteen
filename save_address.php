<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('add_address.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_address.php");
    exit;
}

$allowedNext = ['manage_addresses.php', 'checkout.php'];
$next = in_array($_POST['next'] ?? '', $allowedNext, true) ? $_POST['next'] : 'manage_addresses.php';

$states = [
    'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
    'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
    'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
    'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
    'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim',
    'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal'
];
const MAX_ADDRESSES = 10;

// Save what was typed and send the user back with an error
function back_with(string $text, array $old, string $next): void {
    $_SESSION['addr_flash'] = ['type' => 'error', 'text' => $text];
    $_SESSION['addr_old']   = $old;
    header("Location: add_address.php?next=" . urlencode($next));
    exit;
}

// 1. CSRF check
if (!hash_equals($_SESSION['addr_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die("Invalid request. Please reload the page and try again.");
}

$user_id = (int)$_SESSION['user_id'];

// 2. Read and trim input
$fields = ['name', 'phone', 'alt_phone', 'address_line', 'locality', 'landmark',
           'city', 'state', 'pincode', 'address_type'];
$in = [];
foreach ($fields as $f) {
    $in[$f] = trim((string)($_POST[$f] ?? ''));
}
$old = $in;

// 3. Validate
if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 100) {
    back_with("Please enter the full name (2 to 100 characters).", $old, $next);
}
if (!preg_match('/^[6-9]\d{9}$/', $in['phone'])) {
    back_with("Please enter a valid 10-digit mobile number.", $old, $next);
}
if ($in['alt_phone'] !== '' && !preg_match('/^[6-9]\d{9}$/', $in['alt_phone'])) {
    back_with("Please enter a valid alternate phone number, or leave it empty.", $old, $next);
}
if (mb_strlen($in['address_line']) < 5 || mb_strlen($in['address_line']) > 255) {
    back_with("Please enter the flat, street and area (5 to 255 characters).", $old, $next);
}
if (mb_strlen($in['locality']) > 100) {
    back_with("Locality is too long.", $old, $next);
}
if (mb_strlen($in['landmark']) > 150) {
    back_with("Landmark is too long.", $old, $next);
}
if (mb_strlen($in['city']) < 2 || mb_strlen($in['city']) > 100) {
    back_with("Please enter the city, district or town.", $old, $next);
}
if (!in_array($in['state'], $states, true)) {
    back_with("Please select a valid state.", $old, $next);
}
if (!preg_match('/^\d{6}$/', $in['pincode'])) {
    back_with("Please enter a valid 6-digit pincode.", $old, $next);
}
if (!in_array($in['address_type'], ['Home', 'Work'], true)) {
    back_with("Please choose Home or Work.", $old, $next);
}

// 4. Limit saved addresses per user
$stmt = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$count = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();

if ($count >= MAX_ADDRESSES) {
    $_SESSION['addr_flash'] = ['type' => 'error', 'text' => "You can save up to " . MAX_ADDRESSES . " addresses. Delete one to add another."];
    header("Location: manage_addresses.php");
    exit;
}

// 5. Save. Empty optional fields are stored as NULL.
$alt      = $in['alt_phone'] !== '' ? $in['alt_phone'] : null;
$locality = $in['locality']  !== '' ? $in['locality']  : null;
$landmark = $in['landmark']  !== '' ? $in['landmark']  : null;

$stmt = $conn->prepare(
    "INSERT INTO addresses
        (user_id, name, phone, alt_phone, pincode, locality, address_line, city, state, landmark, address_type)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "issssssssss",
    $user_id,
    $in['name'],
    $in['phone'],
    $alt,
    $in['pincode'],
    $locality,
    $in['address_line'],
    $in['city'],
    $in['state'],
    $landmark,
    $in['address_type']
);

if (!$stmt->execute()) {
    $stmt->close();
    back_with("We could not save your address. Please try again.", $old, $next);
}
$stmt->close();

// 6. Done: clear the form and the token, then go to the next page
unset($_SESSION['addr_old'], $_SESSION['addr_flash']);
$_SESSION['addr_csrf'] = bin2hex(random_bytes(16)); // new token for the next form

header("Location: " . $next . "?msg=" . urlencode("Address saved."));
exit;