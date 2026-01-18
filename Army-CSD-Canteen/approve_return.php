<?php
include 'config.php';

if (isset($_GET['return_id'])) {
    $return_id = $_GET['return_id'];

    // Fetch return details
    $sql = "SELECT r.*, p.name 
            FROM returns r 
            JOIN products p ON r.product_id = p.id 
            WHERE r.id = '$return_id'";
    $result = mysqli_query($conn, $sql);
    $return = mysqli_fetch_assoc($result);

    if ($return) {
        $product_id = $return['product_id'];
        $product_name = $return['name'];
        $quantity = $return['quantity'];
        $reason = $return['reason'];

        // Insert into returned_stock
        $insert = "INSERT INTO returned_stock (product_id, product_name, quantity, return_reason) 
                   VALUES ('$product_id', '$product_name', '$quantity', '$reason')";
        mysqli_query($conn, $insert);

        // Update return status to 'Accepted'
        $update = "UPDATE returns SET status='Accepted' WHERE id='$return_id'";
        mysqli_query($conn, $update);

        echo "<script>alert('Return accepted and added to Returned Stock table.'); window.location.href='manage_returns.php';</script>";
    }
}
?>
