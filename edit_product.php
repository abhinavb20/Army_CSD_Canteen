<?php
require_once 'admin_guard.php';

$pageTitle = 'Edit product';

const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const ALLOWED_IMAGES  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$uploadDir = __DIR__ . '/uploads/';

// Product ID
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: product_dashboard.php?msg=" . urlencode("Invalid product."));
    exit;
}

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['admin_csrf'];

// Load the product
$stmt = $conn->prepare(
    "SELECT id, name, description, price, stock, image, category_id
     FROM products WHERE id = ? AND is_active = 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: product_dashboard.php?msg=" . urlencode("Product not found."));
    exit;
}

$categories  = rows($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categoryIds = array_map(fn($c) => (int)$c['id'], $categories);

$errors = [];
$old = [
    'name'         => (string)$product['name'],
    'description'  => (string)$product['description'],
    'price'        => (string)$product['price'],
    'category'     => (string)($product['category_id'] ?? ''),
    'new_category' => '',
];
$currentImage = (string)($product['image'] ?? '');

// ---------- Save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    // 2. Read input
    $old['name']         = trim((string)($_POST['name'] ?? ''));
    $old['description']  = trim((string)($_POST['description'] ?? ''));
    $old['price']        = trim((string)($_POST['price'] ?? ''));
    $old['category']     = (string)($_POST['category'] ?? '');
    $old['new_category'] = trim((string)($_POST['new_category'] ?? ''));

    // 3. Validate
    $name = $old['name'];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Product name must be between 2 and 150 characters.';
    }
    if (mb_strlen($old['description']) > 1000) {
        $errors[] = 'Description can be up to 1000 characters.';
    }

    $price = null;
    if (!is_numeric($old['price']) || (float)$old['price'] <= 0 || (float)$old['price'] > 9999999.99) {
        $errors[] = 'Enter a valid price greater than 0.';
    } else {
        $price = round((float)$old['price'], 2);
    }

    // Category: a typed new name wins, then the dropdown, otherwise keep the current one
    $newCat = $old['new_category'];
    $catId  = $product['category_id'] !== null ? (int)$product['category_id'] : null;

    if ($newCat !== '') {
        if (mb_strlen($newCat) < 2 || mb_strlen($newCat) > 100) {
            $errors[] = 'New category name must be between 2 and 100 characters.';
        }
    } elseif ($old['category'] !== '') {
        if (!ctype_digit($old['category']) || !in_array((int)$old['category'], $categoryIds, true)) {
            $errors[] = 'The selected category does not exist.';
        } else {
            $catId = (int)$old['category'];
        }
    }

    // Image: optional. Only checked when a new file is chosen.
    $tmpPath  = null;
    $imgExt   = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The image could not be uploaded. Please try again.';
        } elseif ($f['size'] > MAX_IMAGE_BYTES) {
            $errors[] = 'Image must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!isset(ALLOWED_IMAGES[$mime]) || getimagesize($f['tmp_name']) === false) {
                $errors[] = 'Image must be a JPG, PNG or WebP file.';
            } else {
                $tmpPath = $f['tmp_name'];
                $imgExt  = ALLOWED_IMAGES[$mime];
            }
        }
    }

    if ($tmpPath !== null && (!is_dir($uploadDir) || !is_writable($uploadDir))) {
        $errors[] = 'The uploads folder is missing or not writable.';
    }

    // 4. Save
    $newImageName = null;
    if (empty($errors) && $tmpPath !== null) {
        $newImageName = bin2hex(random_bytes(12)) . '.' . $imgExt;
        if (!move_uploaded_file($tmpPath, $uploadDir . $newImageName)) {
            $errors[] = 'The image could not be saved. Please try again.';
            $newImageName = null;
        }
    }

    if (empty($errors)) {
        $finalImage = $newImageName ?? $currentImage;

        $conn->begin_transaction();
        try {
            // Create a new category if one was typed (reuse it if the name already exists)
            if ($newCat !== '') {
                $s = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $s->bind_param("s", $newCat);
                $s->execute();
                $existing = $s->get_result()->fetch_row();
                $s->close();

                if ($existing) {
                    $catId = (int)$existing[0];
                } else {
                    $s = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                    $s->bind_param("s", $newCat);
                    $s->execute();
                    $catId = (int)$conn->insert_id;
                    $s->close();
                }
            }

            $s = $conn->prepare(
                "UPDATE products
                 SET name = ?, description = ?, price = ?, image = ?, category_id = ?
                 WHERE id = ? AND is_active = 1"
            );
            $s->bind_param("ssdsii", $name, $old['description'], $price, $finalImage, $catId, $id);
            $s->execute();
            $s->close();

            $conn->commit();

            // Remove the old image only after the save succeeded
            if ($newImageName !== null && $currentImage !== '') {
                $oldPath = $uploadDir . basename($currentImage);
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            header("Location: product_dashboard.php?msg=" . urlencode("Product saved."));
            exit;

        } catch (Throwable $ex) {
            $conn->rollback();
            if ($newImageName !== null && is_file($uploadDir . $newImageName)) {
                unlink($uploadDir . $newImageName);
            }
            error_log('Edit product failed: ' . $ex->getMessage());
            $errors[] = 'We could not save the product. Please try again.';
        }
    }
}

require_once 'admin_header.php';
?>

<style>
    .form-wrap { display:grid; grid-template-columns:1fr 300px; gap:18px; align-items:start; }
    .panel { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; margin-bottom:18px; }
    .panel h2 { font-size:15px; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .panel h2 i { color:var(--g7); }
    .form-label { font-size:13px; font-weight:600; margin-bottom:6px; }
    .form-label .opt { color:var(--mu); font-weight:500; }
    .form-control, .form-select { border-radius:9px; border-color:var(--line); padding:10px 12px; font-size:14px; }
    .form-control:focus, .form-select:focus { border-color:var(--g7); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }
    .form-control[readonly] { background:var(--bg); color:var(--mu); }
    .form-text { font-size:12px; }

    .stock-box { display:flex; justify-content:space-between; align-items:center; gap:12px; background:var(--bg); border-radius:12px; padding:14px 16px; }
    .stock-box small { color:var(--mu); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .stock-box b { font-size:22px; font-weight:800; display:block; }
    .stock-box a { color:var(--g7); font-weight:600; font-size:13px; white-space:nowrap; }

    .current { display:flex; gap:12px; align-items:center; margin-bottom:12px; }
    .thumb-lg { width:96px; height:96px; border-radius:12px; background:#f6f8f6; border:1px solid var(--line); display:grid; place-items:center; overflow:hidden; flex-shrink:0; }
    .thumb-lg img { width:100%; height:100%; object-fit:contain; }
    .thumb-lg i { color:#9aa69e; font-size:24px; }
    .current small { color:var(--mu); font-size:12.5px; }

    .drop { border:1.5px dashed #b9ccbe; border-radius:12px; padding:16px; text-align:center; background:#fafcfa; position:relative; display:block; cursor:pointer; }
    .drop:hover { border-color:var(--g7); background:var(--g1); }
    .drop input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; }
    .drop i { font-size:24px; color:var(--g7); }
    .drop small { display:block; color:var(--mu); font-size:12px; margin-top:4px; }

    .alert-x { border-radius:10px; font-size:13.5px; background:#fde8e6; color:var(--bad); padding:12px 16px; margin-bottom:18px; }
    .alert-x ul { margin:0; padding-left:18px; }

    .actions { display:flex; gap:10px; justify-content:flex-end; }
    .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--g9); color:#fff; border:0; border-radius:10px; padding:11px 22px; font-weight:700; font-size:14px; }
    .btn-save:hover { background:var(--g7); }
    .btn-save:disabled { background:#c9d3cc; }
    .btn-cancel { display:inline-flex; align-items:center; gap:6px; border:1px solid #c5d6c9; background:#fff; color:var(--g7); border-radius:10px; padding:11px 20px; font-weight:600; font-size:14px; }
    .btn-cancel:hover { background:var(--g1); color:var(--g9); }

    @media (max-width:900px) { .form-wrap { grid-template-columns:1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Edit product</h1>
        <p>Update the details for <?= e($product['name']) ?>.</p>
    </div>
    <a href="product_dashboard.php" class="btn-outline"><i class="bi bi-arrow-left"></i>Back to products</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert-x">
        <strong><i class="bi bi-exclamation-circle me-1"></i>Please fix the following:</strong>
        <ul class="mt-1">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="editForm" novalidate>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-wrap">

        <div>
            <section class="panel">
                <h2><i class="bi bi-info-circle"></i>Product details</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Product name</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="150"
                               value="<?= e($old['name']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description <span class="opt">(optional)</span></label>
                        <textarea id="description" name="description" class="form-control" rows="4" maxlength="1000"><?= e($old['description']) ?></textarea>
                        <div class="form-text" id="descCount">0 / 1000</div>
                    </div>
                    <div class="col-md-6">
                        <label for="price" class="form-label">Price (₹)</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01"
                               value="<?= e($old['price']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Current stock</label>
                        <div class="stock-box">
                            <div>
                                <small>Units in stock</small>
                                <b><?= (int)$product['stock'] ?></b>
                            </div>
                            <a href="update_stock.php?product_id=<?= $id ?>">Adjust stock</a>
                        </div>
                        <div class="form-text">Stock changes are made on the stock page so they are logged.</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2><i class="bi bi-tags"></i>Category</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="category" class="form-label">Existing category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">Keep current category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $old['category'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="new_category" class="form-label">New category <span class="opt">(optional)</span></label>
                        <input type="text" id="new_category" name="new_category" class="form-control" maxlength="100"
                               placeholder="e.g. Stationery" value="<?= e($old['new_category']) ?>">
                    </div>
                </div>
                <div class="form-text mt-2">A typed new category is used instead of the dropdown.</div>
            </section>
        </div>

        <div>
            <section class="panel">
                <h2><i class="bi bi-image"></i>Product image</h2>

                <div class="current">
                    <div class="thumb-lg" id="previewBox">
                        <?php if ($currentImage !== ''): ?>
                            <img src="uploads/<?= e(basename($currentImage)) ?>" alt="" id="previewImg">
                        <?php else: ?>
                            <i class="bi bi-image" id="previewIcon"></i>
                            <img src="" alt="" id="previewImg" style="display:none">
                        <?php endif; ?>
                    </div>
                    <small><?= $currentImage !== '' ? 'Current image' : 'No image yet' ?></small>
                </div>

                <label class="drop">
                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <div class="fw-semibold mt-1">Replace image</div>
                    <small>JPG, PNG or WebP, up to 2 MB</small>
                </label>
            </section>

            <div class="actions">
                <a href="product_dashboard.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check2"></i>Save changes</button>
            </div>
        </div>

    </div>
</form>

<script>
    // Image preview when a new file is chosen
    const imageInput = document.getElementById('image');
    const previewImg = document.getElementById('previewImg');
    const previewIcon = document.getElementById('previewIcon');
    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Image must be 2 MB or smaller.');
            this.value = '';
            return;
        }
        previewImg.src = URL.createObjectURL(file);
        previewImg.style.display = 'block';
        if (previewIcon) previewIcon.style.display = 'none';
    });

    // Description counter
    const desc = document.getElementById('description');
    const descCount = document.getElementById('descCount');
    const updateCount = () => descCount.textContent = desc.value.length + ' / 1000';
    desc.addEventListener('input', updateCount);
    updateCount();

    // Submit guard
    document.getElementById('editForm').addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
    });
</script>

<?php require_once 'admin_footer.php'; ?>