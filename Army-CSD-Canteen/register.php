<?php
$conn = new mysqli("localhost", "root", "", "csd_canteen");
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $army_id = $_POST['army_id'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check for duplicate phone or army_id
    $dup_stmt = $conn->prepare("SELECT id FROM users WHERE ph_no = ? OR army_id = ?");
    $dup_stmt->bind_param("ss", $phone, $army_id);
    $dup_stmt->execute();
    $dup_stmt->store_result();

    if ($dup_stmt->num_rows > 0) {
        echo "<script>alert('Phone number or Army ID already registered!');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, ph_no, army_id, password, is_approved, role) VALUES (?, ?, ?, ?, 0, 'user')");
        $stmt->bind_param("ssss", $name, $phone, $army_id, $password);

        if ($stmt->execute()) {
            echo "<script>window.location.href='wait_approval.php';</script>";
            exit;
        } else {
            echo "❌ Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Army CSD Canteen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <style>
        body { font-family: Arial; background-color: #f4f4f4; }
        .container { width: 400px; margin: auto; padding: 20px; margin-top: 60px; background: white; border-radius: 5px; border: 1px solid #ccc; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; }
        .message { text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    
   <div class="container mt-5">
    <h2 class="text-center mb-4">Register</h2>
    <?php if ($message): ?>
        <div class="alert alert-info text-center"><?php echo $message; ?></div>
    <?php endif; ?>
    <form method="POST" class="mx-auto" style="max-width: 500px;">
                    <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" 
                       placeholder="Enter your full name" required>
            </div>

         <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-control" 
                       placeholder="Enter your 10-digit phone number" 
                       pattern="[0-9]{10}" maxlength="10" required>
            </div>
                     <div class="mb-3">
                <label for="army_id" class="form-label">Army ID</label>
                <input type="text" id="army_id" name="army_id" class="form-control" 
                       placeholder="Enter your Army ID" required>
            </div>

        <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Create a strong password" minlength="6" required>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="form-control" placeholder="Re-enter your password" required>
            </div>
        <button type="submit" class="btn btn-success w-100">Register</button>
    </form>
        <div class="mb-3 text-center mt-3">
            <?php if (isset($success)) echo "<p style='color: green;'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
            <p><a href="login.php">Already have an account? Login here</a></p>
            <a href="index.php" class="btn btn-secondary mt-2">Go to Home</a>
        </div>
    </div>
    <script>
function validateForm() {
    let phone = document.getElementById("phone").value;
    let pass = document.getElementById("password").value;
    let confirmPass = document.getElementById("confirm_password").value;

    // Validate phone number (10 digits only)
    if (!/^[0-9]{10}$/.test(phone)) {
        alert("Phone number must be exactly 10 digits.");
        return false;
    }

    // Validate password match
    if (pass !== confirmPass) {
        alert("Passwords do not match!");
        return false;
    }

    return true;
}
</script>
</body>
</html>