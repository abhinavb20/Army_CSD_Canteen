<?php
session_start();
$conn = new mysqli("localhost", "root", "", "csd_canteen");
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ph_no = $_POST['ph_no'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, is_approved, role FROM users WHERE ph_no = ?");
    $stmt->bind_param("s", $ph_no);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $hashed_pw, $is_approved, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_pw)) {
            if ($is_approved == 1) {
                $_SESSION['user_id'] = $id;
                $_SESSION['role'] = $role;
                if ($role == 'admin') {
                    header("Location: admin_panel.php");
                } else {
                    header("Location: index.php");
                }
            } else {
                echo "⏳ Account not approved by admin.";
            }
        } else {
            echo "❌ Invalid password.";
        }
    } else {
        echo "❌ User not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
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
    <h2 class="text-center mb-4">Login</h2>
    <?php if ($message): ?>
        <div class="alert alert-danger text-center"><?php echo $message; ?></div>
    <?php endif; ?>
    <form method="POST" action="" class="mx-auto" style="max-width: 400px;">
        <div class="mb-3">
            <label for="ph_no">Phone Number:</label>
            <input type="text" id="ph_no" name="ph_no" class="form-control" 
                placeholder="Enter your 10-digit phone number" 
                       pattern="[0-9]{10}" maxlength="10" required>
        </div>
        <div class="mb-3">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" class="form-control" 
            placeholder="Password" minlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    
    <div class="text-center mt-3">
        <small>Don't have an account? <a href="register.php">Register here</a></small>
    </div>
</div>
</body>
</html>
