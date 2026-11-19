<?php
session_start();
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration received | Army CSD Canteen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f4f6f4;
            color: #18251d;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card-x {
            background: #fff;
            border: 1px solid #e4ebe5;
            border-radius: 16px;
            padding: 36px;
            max-width: 460px;
            width: 100%;
            text-align: center;
        }

        .ic {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #eaf3ec;
            color: #17623b;
            display: grid;
            place-items: center;
            font-size: 30px;
            margin: 0 auto 16px;
        }

        h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 8px;
        }

        p {
            color: #6b7a70;
            line-height: 1.6;
            margin: 0 0 22px;
        }

        .btn-x {
            display: inline-block;
            background: #103d25;
            color: #fff;
            border-radius: 10px;
            padding: 11px 22px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-x:hover {
            background: #17623b;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="card-x">
        <div class="ic"><i class="bi bi-hourglass-split"></i></div>
        <h1>Registration received</h1>
        <p>Thank you. Your account is waiting for approval from the canteen office. You can log in once it has been approved.</p>
        <a href="login.php" class="btn-x">Back to log in</a>
    </div>
</body>

</html>