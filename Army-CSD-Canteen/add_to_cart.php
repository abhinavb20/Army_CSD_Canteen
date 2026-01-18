<?php
session_start();
include 'config.php'; // Required to get product price from DB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    // --- Stock check snippet start ---
    $product_id = $_POST['product_id'] ?? $product_id;
    $quantity = $_POST['quantity'] ?? 1;

    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock FROM products WHERE id=$product_id"));

    if ($product['stock'] == 0) {
        echo "This item is out of stock.";
        exit;
    }

    if ($quantity > $product['stock']) {
        echo "Only {$product['stock']} items available.";
        exit;
    }
    // --- Stock check snippet end ---

    // Fetch product price from database
    $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        http_response_code(404);
        echo "Product not found";
        exit;
    }

    $price = $product['price'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check if product already in cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'product_id' => $product_id,
            'price' => $price,
            'quantity' => $quantity
        ];
    }

    // echo "success";
    echo "success";
    exit;
}

http_response_code(400);
echo "Invalid Request";
?>

<!-- Add this script at the bottom of your index.php, after the product cards -->
<!-- <script>
$(document).ready(function() {
    $("form[action='add_to_cart.php']").submit(function(e) {
        e.preventDefault();
        var form = $(this);
        $.post("add_to_cart.php", form.serialize(), function(response) {
            if (response.trim() === "success") {
                alert("Product added to cart!");
            } else {
                alert(response);
            }
        });
    });
});
</script> -->