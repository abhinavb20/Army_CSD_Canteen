<?php
session_start();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function back_with(string $type, string $text, array $old = []): void {
    $_SESSION['contact_flash'] = ['type' => $type, 'text' => $text];
    $_SESSION['contact_old']   = $old;
    header("Location: contact.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: contact.php");
    exit;
}

// 1. CSRF check
if (!hash_equals($_SESSION['contact_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    back_with('error', 'Your session expired. Please try again.');
}

// 2. Honeypot: silently accept and discard bot submissions
if (!empty($_POST['website'])) {
    back_with('success', 'Thank you. Your message has been sent.');
}

// 3. Read and clean input
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$old     = ['name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message];

// 4. Validate on the server
if ($name === '' || mb_strlen($name) > 100) {
    back_with('error', 'Please enter your name.', $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
    back_with('error', 'Please enter a valid email address.', $old);
}
if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
    back_with('error', 'Please enter a valid phone number.', $old);
}
if ($message === '' || mb_strlen($message) > 2000) {
    back_with('error', 'Please enter a message of up to 2000 characters.', $old);
}

// 5. Basic rate limit: one message every 60 seconds per session
if (isset($_SESSION['last_contact']) && time() - $_SESSION['last_contact'] < 60) {
    back_with('error', 'Please wait a minute before sending another message.', $old);
}

// 6. Store or send the message. Replace this block with your choice.
//    Option A: save to a table named contact_messages
//    Option B: email it to support@csdcanteen.in with mail()
include 'config.php';
$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $name, $email, $phone, $message);
$saved = $stmt->execute();
$stmt->close();

if (!$saved) {
    back_with('error', 'We could not send your message. Please try again later.', $old);
}

$_SESSION['last_contact'] = time();
unset($_SESSION['contact_csrf']);
back_with('success', 'Thank you. Your message has been sent and we will reply soon.');