<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registration Successful</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .popup-box {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            text-align: center;
            animation: slideDown 0.5s ease;
        }

        .popup-box h2 {
            color: #2c3e50;
        }

        .popup-box p {
            color: #333;
            margin: 15px 0;
        }

        .popup-box .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }

        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="popup-box">
    <h2>Registration Successful!</h2>
    <div class="loader"></div>
    <p>Your account has been created successfully.</p>
    <p><strong>Please wait for admin approval.</strong></p>
    <p>You’ll be notified once approved. You may close this tab or return later to log in.</p>
    <a href="login.php" class="back-link">Back to Login</a>
</div>

</body>
</html>
