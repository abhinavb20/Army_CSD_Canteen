<?php
session_start();
include 'config.php';
include 'header.php';

$cart = $_SESSION['cart'] ?? [];
$products = [];

if (!empty($cart)) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($cart));
    $cart_keys = array_keys($cart);
    $stmt->bind_param($types, ...$cart_keys);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[$row['id']] = $row;
    }
}

// Remove item from cart
if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
    header("Location: cart.php");
    exit;
}

// Update quantity
if (isset($_POST['update_cart']) && isset($_POST['qty'])) {
    foreach ($_POST['qty'] as $id => $q) {
        // Check stock before updating
        $id = (int)$id;
        $q = (int)$q;
        if (isset($products[$id])) {
            $stock = $products[$id]['stock'];
            if ($stock == 0) {
                $_SESSION['cart'][$id] = 0;
            } else {
                $_SESSION['cart'][$id] = min(max(1, $q), $stock);
            }
        }
    }
    header("Location: cart.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
    <style>
        .remove-btn {
            color: red;
            font-weight: bold;
            text-decoration: none;
        }
        .remove-btn:hover {
            color: darkred;
        }
        input[type="number"] {
            width: 60px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
    <h2>🛒 Your Cart</h2>
    <br>
    <div class="text-center mb-3">
        <a href="index.php" class="btn btn-primary">Continue Shopping</a>
    </div>

<?php if (empty($products)): ?>
    <p>Your cart is empty.</p>
<?php else: ?>
    <form action="cart.php" method="POST">
        <table class="table table-bordered text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th>❌</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $total = 0;
            foreach ($cart as $product_id => $qty):
                // If $qty is an array, extract the actual quantity
                if (is_array($qty)) {
                    $qty = $qty['qty'] ?? 1;
                }
                $product = $products[$product_id];
                $price = $product['price'];
                $stock = $product['stock'];
                $qty = min($qty, $stock); // Prevent showing more than stock
                $subtotal = $price * $qty;
                $total += $subtotal;
            ?>
                <tr>
                    <td><a href="cart.php?remove=<?= $product_id ?>" class="remove-btn">×</a></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
                        <?php if ($stock > 0): ?>
                            <input type="number" name="qty[<?= $product_id ?>]" value="<?= $qty ?>" min="1" max="<?= $stock ?>" onchange="updateSubtotal(this, <?= $price ?>)">
                            <span style="font-size:12px;color:#888;">(Stock: <?= $stock ?>)</span>
                        <?php else: ?>
                            <span style="color:red;">Out of Stock</span>
                        <?php endif; ?>
                    </td>
                    <td>₹<?= number_format($price, 2) ?></td>
                    <td class="subtotal">₹<?= number_format($subtotal, 2) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr>
                    <td colspan="4" class="text-end"><strong>Total</strong></td>
                    <td id="total-amount"><strong>₹<?= number_format($total, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="d-flex justify-content-between">
            <button type="submit" name="update_cart" class="btn btn-warning">Update Cart</button>
            <a href="checkout.php" class="btn btn-success">Proceed to Checkout</a>
        </div>
    </form>
<?php endif; ?>
</div>

<script>
function updateSubtotal(input, price) {
    const row = input.closest('tr');
    const qty = parseInt(input.value) || 1;
    const subtotal = qty * price;
    row.querySelector('.subtotal').textContent = '₹' + subtotal.toFixed(2);

    // Recalculate total
    let total = 0;
    document.querySelectorAll('.subtotal').forEach(cell => {
        const value = parseFloat(cell.textContent.replace(/[₹,]/g, ''));
        total += value || 0;
    });
    document.getElementById('total-amount').innerHTML = "<strong>₹" + total.toFixed(2) + "</strong>";
}
</script>
</body>
</html>
