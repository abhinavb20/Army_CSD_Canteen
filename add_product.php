<?php
require_once 'admin_guard.php';

$pageTitle = 'Add product';

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['admin_csrf'];

const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const ALLOWED_IMAGES  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$uploadDir = __DIR__ . '/uploads/';

$errors = [];
$old = [
    'name' => '', 'description' => '', 'price' => '', 'stock' => '',
    'category' => '', 'new_category' => '',
];

// Categories for the dropdown
$categories = rows($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categoryIds = array_map(fn($c) => (int)$c['id'], $categories);

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
    $old['stock']        = trim((string)($_POST['stock'] ?? ''));
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

    $stock = null;
    if (!ctype_digit($old['stock']) || (int)$old['stock'] > 1000000) {
        $errors[] = 'Stock must be a whole number from 0 to 1,000,000.';
    } else {
        $stock = (int)$old['stock'];
    }

    // Category: either pick one, or type a new one
    $newCat = $old['new_category'];
    $catId  = null;
    if ($newCat !== '') {
        if (mb_strlen($newCat) < 2 || mb_strlen($newCat) > 100) {
            $errors[] = 'New category name must be between 2 and 100 characters.';
        }
    } elseif ($old['category'] !== '' && ctype_digit($old['category'])) {
        $catId = (int)$old['category'];
        if (!in_array($catId, $categoryIds, true)) {
            $errors[] = 'The selected category does not exist.';
        }
    } else {
        $errors[] = 'Choose a category or enter a new one.';
    }

    // Image: optional, but checked strictly when provided
    $tmpPath = null;
    $imgExt  = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The image could not be uploaded. Please try again.';
        } elseif ($f['size'] > MAX_IMAGE_BYTES) {
            $errors[] = 'Image must be 2 MB or smaller.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!isset(ALLOWED_IMAGES[$mime]) || getimagesize($f['tmp_name']) === false) {
                $errors[] = 'Image must be a JPG, PNG or WebP file.';
            } else {
                $tmpPath = $f['tmp_name'];
                $imgExt  = ALLOWED_IMAGES[$mime];
            }
        }
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        if ($tmpPath !== null) {
            $errors[] = 'The uploads folder is missing or not writable.';
        }
    }

    // 4. Save
    if (empty($errors)) {
        $imageName = '';
        $movedFile = null;

        if ($tmpPath !== null) {
            $imageName = bin2hex(random_bytes(12)) . '.' . $imgExt;
            $movedFile = $uploadDir . $imageName;
            if (!move_uploaded_file($tmpPath, $movedFile)) {
                $errors[] = 'The image could not be saved. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Use an existing category if the typed name matches one (ignoring case)
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
                "INSERT INTO products (name, description, price, image, category_id, stock, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            $s->bind_param("ssdsii", $name, $old['description'], $price, $imageName, $catId, $stock);
            $s->execute();
            $s->close();

            $conn->commit();

            unset($_SESSION['admin_csrf']);
            header("Location: product_dashboard.php?msg=" . urlencode("Product saved."));
            exit;

        } catch (Throwable $ex) {
            $conn->rollback();
            if ($movedFile && file_exists($movedFile)) {
                unlink($movedFile);
            }
            error_log('Add product failed: ' . $ex->getMessage());
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
    .form-text { font-size:12px; }
    .or { display:flex; align-items:center; gap:10px; color:var(--mu); font-size:12px; margin:12px 0; }
    .or::before, .or::after { content:''; flex:1; height:1px; background:var(--line); }

    .drop { border:1.5px dashed #b9ccbe; border-radius:12px; padding:18px; text-align:center; background:#fafcfa; position:relative; }
    .drop:hover { border-color:var(--g7); background:var(--g1); }
    .drop input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; }
    .drop i { font-size:28px; color:var(--g7); }
    .drop small { display:block; color:var(--mu); font-size:12px; margin-top:4px; }
    .preview { width:100%; aspect-ratio:1/1; border-radius:12px; background:#f6f8f6; border:1px solid var(--line); display:grid; place-items:center; overflow:hidden; margin-bottom:12px; }
    .preview img { width:100%; height:100%; object-fit:contain; }
    .preview span { color:#9aa69e; font-size:13px; }

    .tips { font-size:12.5px; color:var(--mu); line-height:1.7; padding-left:18px; margin:0; }

    .alert-x { border-radius:10px; font-size:13.5px; background:#fde8e6; color:var(--bad); border:0; padding:12px 16px; margin-bottom:18px; }
    .alert-x ul { margin:0; padding-left:18px; }

    .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:6px; }
    .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--g9); color:#fff; border:0; border-radius:10px; padding:11px 22px; font-weight:700; font-size:14px; }
    .btn-save:hover { background:var(--g7); }
    .btn-save:disabled { background:#c9d3cc; }
    .btn-cancel { display:inline-flex; align-items:center; gap:6px; border:1px solid #c5d6c9; background:#fff; color:var(--g7); border-radius:10px; padding:11px 20px; font-weight:600; font-size:14px; }
    .btn-cancel:hover { background:var(--g1); color:var(--g9); }

    @media (max-width:900px) { .form-wrap { grid-template-columns:1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Add product</h1>
        <p>Create a new listing for the canteen catalogue.</p>
    </div>
    <a href="product_dashboard.php" class="btn-outline"><i class="bi bi-arrow-left"></i>Back to products</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert-x">
        <strong><i class="bi bi-exclamation-circle me-1"></i>Please fix the following:</strong>
        <ul class="mt-1">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="productForm" novalidate>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

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
                        <label for="stock" class="form-label">Opening stock</label>
                        <input type="number" id="stock" name="stock" class="form-control" step="1" min="0"
                               value="<?= e($old['stock']) ?>" required>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2><i class="bi bi-tags"></i>Category</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="category" class="form-label">Existing category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">Select a category</option>
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
                               placeholder="e.g. Electronics" value="<?= e($old['new_category']) ?>">
                    </div>
                </div>
                <div class="form-text mt-2">If you type a new category, it is used instead of the dropdown.</div>
            </section>
        </div>

        <div>
            <section class="panel">
                <h2><i class="bi bi-image"></i>Product image</h2>
                <div class="preview" id="previewBox">
                    <span id="previewText">No image selected</span>
                    <img id="previewImg" alt="" style="display:none">
                </div>
                <label class="drop d-block">
                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <div class="fw-semibold mt-1">Choose an image</div>
                    <small>JPG, PNG or WebP, up to 2 MB</small>
                </label>
            </section>

            <section class="panel">
                <h2><i class="bi bi-lightbulb"></i>Tips</h2>
                <ul class="tips">
                    <li>Use a square image on a plain background.</li>
                    <li>Keep product names short and specific.</li>
                    <li>Stock at or below 5 is flagged as low.</li>
                </ul>
            </section>

            <div class="actions">
                <a href="product_dashboard.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check2"></i>Save product</button>
            </div>
        </div>

    </div>
</form>

<script>
    // Image preview
    const img = document.getElementById('image');
    const previewImg = document.getElementById('previewImg');
    const previewText = document.getElementById('previewText');
    img.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Image must be 2 MB or smaller.');
            this.value = '';
            return;
        }
        previewImg.src = URL.createObjectURL(file);
        previewImg.style.display = 'block';
        previewText.style.display = 'none';
    });

    // Description counter
    const desc = document.getElementById('description');
    const descCount = document.getElementById('descCount');
    const updateCount = () => descCount.textContent = desc.value.length + ' / 1000';
    desc.addEventListener('input', updateCount);
    updateCount();

    // Submit guard
    document.getElementById('productForm').addEventListener('submit', function (e) {
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