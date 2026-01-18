<?php
session_start();

if (!isset($_POST['payment_method'])) {
    header("Location: payment.php");
    exit;
}

$payment_method = $_POST['payment_method'];

// Store payment method for order processing
$_SESSION['payment_method'] = $payment_method;

// Redirect to order confirmation
header("Location: payment_success.php");
exit;
?>
