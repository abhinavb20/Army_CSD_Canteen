<?php
session_start();
include 'config.php';

// Save selected address_id to session
if (isset($_POST['address_id'])) {
    $_SESSION['address_id'] = $_POST['address_id'];
} else if (!isset($_SESSION['address_id'])) {
    die("No address selected.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Payment Method</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script>
        function showCardForm() {
            document.getElementById('card-form').style.display = 'block';
            setCardFieldsRequired(true);
        }
        function hideCardForm() {
            document.getElementById('card-form').style.display = 'none';
            setCardFieldsRequired(false);
        }
        function setCardFieldsRequired(isRequired) {
            document.querySelector('input[name="card_number"]').required = isRequired;
            document.querySelector('input[name="expiry"]').required = isRequired;
            document.querySelector('input[name="cvv"]').required = isRequired;
            document.querySelector('input[name="card_name"]').required = isRequired;
        }

        // Card number formatting and validation
        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '').substring(0,16);
            value = value.replace(/(.{4})/g, '$1 ').trim();
            input.value = value;
        }

        // Expiry date formatting and validation
        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '').substring(0,4);
            if (value.length > 2) {
                value = value.substring(0,2) + '/' + value.substring(2,4);
            }
            input.value = value;
        }

        // CVV validation (only 3 digits)
        function formatCVV(input) {
            input.value = input.value.replace(/\D/g, '').substring(0,3);
        }

        // Final validation before submit
        function validateCardForm(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (paymentMethod && paymentMethod.value === 'online') {
                const cardNumber = document.querySelector('input[name="card_number"]').value.replace(/\s/g, '');
                const expiry = document.querySelector('input[name="expiry"]').value;
                const cvv = document.querySelector('input[name="cvv"]').value;

                if (!/^\d{16}$/.test(cardNumber)) {
                    alert('Card number must be 16 digits.');
                    e.preventDefault();
                    return false;
                }
                if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                    alert('Expiry date must be in MM/YY format.');
                    e.preventDefault();
                    return false;
                }
                if (!/^\d{3}$/.test(cvv)) {
                    alert('CVV must be 3 digits.');
                    e.preventDefault();
                    return false;
                }
            }
        }

        window.onload = function() {
            document.getElementById('paymentForm').addEventListener('submit', validateCardForm);
            const cardNumberInput = document.querySelector('input[name="card_number"]');
            const expiryInput = document.querySelector('input[name="expiry"]');
            const cvvInput = document.querySelector('input[name="cvv"]');
            if (cardNumberInput) cardNumberInput.addEventListener('input', function() { formatCardNumber(this); });
            if (expiryInput) expiryInput.addEventListener('input', function() { formatExpiry(this); });
            if (cvvInput) cvvInput.addEventListener('input', function() { formatCVV(this); });
        }
    </script>
</head>
<body class="bg-light">
    <div class="container py-5">
        <h2>Select Payment Method</h2>
        <form method="POST" action="payment_success.php" id="paymentForm">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="payment_method" value="cod" id="cod" required onclick="hideCardForm()">
                <label class="form-check-label" for="cod">Cash on Delivery <span class="text-muted">(Pay when you receive your order)</span></label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="payment_method" value="online" id="online" required onclick="showCardForm()">
                <label class="form-check-label" for="online">Online Payment</label>
            </div>
            <div id="card-form" style="display:none; margin-top:20px;">
                <div class="card p-4">
                    <h5 class="mb-3">Credit / Debit Card</h5>
                    <div class="mb-3">
                        <input type="text" class="form-control" name="card_number" placeholder="Card number" maxlength="19">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <input type="text" class="form-control" name="expiry" placeholder="Expiry date (MM/YY)" maxlength="5">
                        </div>
                        <div class="col">
                            <input type="text" class="form-control" name="cvv" placeholder="CVC / CVV" maxlength="3">
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" name="card_name" placeholder="Name on card">
                    </div>
                    <div>
                        <img src="https://img.icons8.com/color/48/000000/visa.png" height="24"/>
                        <img src="https://img.icons8.com/color/48/000000/mastercard.png" height="24"/>
                        <img src="https://img.icons8.com/color/48/000000/amex.png" height="24"/>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-success mt-3">Proceed to Pay & Place Order</button>
        </form>
    </div>
</body>
</html>