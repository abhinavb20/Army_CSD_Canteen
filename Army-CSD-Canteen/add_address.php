<html>
    <head>
        <link rel="stylesheet" href="styles.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
<body>
<div class="container mt-5">
    <form action="save_address.php" method="POST" class="row g-3 justify-content-center">
        <div class="col-md-4">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Mobile Number</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Pincode</label>
            <input type="text" name="pincode" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Locality</label>
            <input type="text" name="locality" class="form-control">
        </div>
        <div class="col-md-4">
            <label>City/District/Town</label>
            <input type="text" name="city" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>State</label>
            <select name="state" class="form-select" required>
                <option value="">--Select State--</option>
                <option value="Kerala">Kerala</option>
                <option value="Tamil Nadu">Tamil Nadu</option>
                <option value="Maharashtra">Maharashtra</option>
                <!-- Add more -->
            </select>
        </div>
        <div class="col-md-4">
            <label>Landmark (Optional)</label>
            <input type="text" name="landmark" class="form-control">
        </div>
        <div class="col-md-4">
            <label>Alternate Phone (Optional)</label>
            <input type="text" name="alt_phone" class="form-control">
        </div>
        <div class="col-md-4">
            <label>Address Type</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="address_type" value="Home" class="form-check-input" checked>
                <label class="form-check-label">Home</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="address_type" value="Work" class="form-check-input">
                <label class="form-check-label">Work</label>
            </div>
        </div>
        <div class="col-md-12">
            <label>Address (Area and Street)</label>
            <textarea name="address_line" class="form-control" rows="2" required></textarea>
        </div>
        <div class="col-12 d-grid">
            <button class="btn btn-primary" type="submit">Save Address</button>
        </div>
    </form>
</div>
</body>
</html>