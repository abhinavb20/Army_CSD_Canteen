<?php
session_start();
include 'config.php';

// Set to false while testing if you don't want to require a delivered order
const REQUIRE_PURCHASE = true;

function back_to_product(int $pid, string $msg): void {
    header("Location: product_details.php?id=" . $pid . "&msg=" . urlencode($msg) . "#reviews");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$pid     = (int)($_POST['product_id'] ?? 0);

if ($pid <= 0) {
    header("Location: index.php");
    exit;
}

// 1. CSRF check
if (!hash_equals($_SESSION['review_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    back_to_product($pid, "Your session expired. Please try again.");
}

// 2. Input checks
$rating = (int)($_POST['rating'] ?? 0);
$review = trim((string)($_POST['review'] ?? ''));

if ($rating < 1 || $rating > 5) {
    back_to_product($pid, "Please choose a rating from 1 to 5.");
}
if (mb_strlen($review) < 5) {
    back_to_product($pid, "Please write at least 5 characters in your review.");
}
if (mb_strlen($review) > 1000) {
    back_to_product($pid, "Reviews can be up to 1000 characters.");
}

// 3. Product must exist and be active
$stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $pid);
$stmt->execute();
$productExists = $stmt->get_result()->fetch_row();
$stmt->close();

if (!$productExists) {
    header("Location: index.php");
    exit;
}

// 4. One review per user per product
$stmt = $conn->prepare("SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $pid, $user_id);
$stmt->execute();
$alreadyReviewed = (bool)$stmt->get_result()->fetch_row();
$stmt->close();

if ($alreadyReviewed) {
    back_to_product($pid, "You have already reviewed this product.");
}

// 5. Only customers with a delivered order containing this product can review
if (REQUIRE_PURCHASE) {
    $stmt = $conn->prepare(
        "SELECT 1
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'Delivered'
         LIMIT 1"
    );
    $stmt->bind_param("ii", $user_id, $pid);
    $stmt->execute();
    $bought = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$bought) {
        back_to_product($pid, "Only customers who have received this product can write a review.");
    }
}

// 6. Save the review
try {
    $stmt = $conn->prepare(
        "INSERT INTO reviews (product_id, user_id, rating, review, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("iiis", $pid, $user_id, $rating, $review);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $ex) {
    // 1062 = duplicate entry, which the unique key raises on a double submit
    if ($ex->getCode() === 1062) {
        back_to_product($pid, "You have already reviewed this product.");
    }
    back_to_product($pid, "We could not save your review. Please try again.");
}

// Rotate the token so the same form can't be resubmitted
unset($_SESSION['review_csrf']);

back_to_product($pid, "Thank you. Your review has been posted.");