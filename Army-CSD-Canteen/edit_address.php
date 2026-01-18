<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Address ID is required.');
}

$address_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $address_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Address not found or unauthorized access.");
}

$address = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Address</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="address_form.css" rel="stylesheet"> <!-- your custom styles -->
</head>
<body>
<div class="container">
    <h3>Edit Address</h3>
    <form action="update_address.php" method="POST" class="row g-3 mt-3">
        <input type="hidden" name="id" value="<?= $address['id'] ?>">

        <div class="col-md-6">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($address['name']) ?>">
        </div>

        <div class="col-md-6">
            <label>Mobile Number</label>
            <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($address['phone']) ?>">
        </div>

        <div class="col-md-6">
            <label>Pincode</label>
            <input type="text" name="pincode" class="form-control" required value="<?= htmlspecialchars($address['pincode']) ?>">
        </div>

        <div class="col-md-6">
            <label>Locality</label>
            <input type="text" name="locality" class="form-control" value="<?= htmlspecialchars($address['locality']) ?>">
        </div>

        <div class="col-md-12">
            <label>Address (Area and Street)</label>
            <textarea name="address_line" class="form-control" rows="2" required><?= htmlspecialchars($address['address_line']) ?></textarea>
        </div>

        <div class="col-md-6">
            <label>City/District/Town</label>
            <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($address['city']) ?>">
        </div>

        <div class="col-md-6">
            <label>State</label>
            <select name="state" class="form-select" required>
                <option value="">--Select State--</option>
                <?php
                $states = ['Kerala', 'Tamil Nadu', 'Maharashtra'];
                foreach ($states as $state) {
                    $selected = $address['state'] === $state ? 'selected' : '';
                    echo "<option value=\"$state\" $selected>$state</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-6">
            <label>Landmark (Optional)</label>
            <input type="text" name="landmark" class="form-control" value="<?= htmlspecialchars($address['landmark']) ?>">
        </div>

        <div class="col-md-6">
            <label>Alternate Phone (Optional)</label>
            <input type="text" name="alt_phone" class="form-control" value="<?= htmlspecialchars($address['alt_phone']) ?>">
        </div>

        <div class="col-md-12">
            <label>Address Type</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="address_type" value="Home" class="form-check-input"
                    <?= $address['address_type'] === 'Home' ? 'checked' : '' ?>>
                <label class="form-check-label">Home</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="address_type" value="Work" class="form-check-input"
                    <?= $address['address_type'] === 'Work' ? 'checked' : '' ?>>
                <label class="form-check-label">Work</label>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Update Address</button>
        </div>
    </form>
</div>
</body>
</html>
