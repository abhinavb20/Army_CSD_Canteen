<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;


if (isset($_POST['address_id'])) {
    $address_id = intval($_POST['address_id']);
} elseif (isset($_GET['address_id'])) {
    $address_id = intval($_GET['address_id']);
} else {
    die("Address not selected.");
}

$result = mysqli_query($conn, "SELECT * FROM addresses WHERE id=$address_id AND user_id=$user_id");
if (mysqli_num_rows($result) == 0) {
    die("Invalid address.");
}
$address = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Method - CSD Canteen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .address-box {
            background: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type="radio"] {
            margin-right: 8px;
        }
        .pay-btn {
            margin-top: 20px;
            background: #27ae60;
            color: white;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .pay-btn:hover {
            background: #219150;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Choose Payment Method</h2>

    <div class="address-box">
        <strong>Delivery Address:</strong><br>
        <?= htmlspecialchars($address['name']) ?><br>
        <?= htmlspecialchars($address['address_line']) ?>, <?= htmlspecialchars($address['city']) ?><br>
        <?= htmlspecialchars($address['state']) ?> - <?= htmlspecialchars($address['pincode']) ?><br>
        Phone: <?= htmlspecialchars($address['phone']) ?>
    </div>

    <form action="payment_process.php" method="POST">
        <input type="hidden" name="address_id" value="<?= $address_id ?>">
        <label>
            <input type="radio" name="payment_method" value="UPI" required>
            Pay via UPI (Google Pay / PhonePe / Paytm)
        </label>
        <label>
            <input type="radio" name="payment_method" value="Card">
            Credit / Debit Card
        </label>
        <label>
            <input type="radio" name="payment_method" value="COD">
            Cash on Delivery (COD)
        </label>

        <button type="submit" class="pay-btn">Proceed to Pay</button>
    </form>
</div>

</body>
</html>
