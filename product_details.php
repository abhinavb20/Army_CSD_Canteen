<?php
session_start();
include 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    die("Product ID not specified.");
}

// Product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    die("Product not found.");
}

// Reviews
$reviews = [];
$reviewsAvailable = true;
$alreadyReviewed = false;
try {
    $stmt = $conn->prepare(
        "SELECT r.user_id, r.rating, r.review, u.name
         FROM reviews r
         JOIN users u ON r.user_id = u.id
         WHERE r.product_id = ?
         ORDER BY r.id DESC"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($isLoggedIn && (int)$row['user_id'] === (int)$_SESSION['user_id']) {
            $alreadyReviewed = true;
        }
        $reviews[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Unable to load product reviews: ' . $e->getMessage());
    $reviewsAvailable = false;
}

$hasPurchased = false;
if ($isLoggedIn) {
    try {
        $stmt = $conn->prepare(
            "SELECT 1
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = ? AND oi.product_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("ii", $_SESSION['user_id'], $productId);
        $stmt->execute();
        $hasPurchased = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Unable to verify product purchase for review: ' . $e->getMessage());
    }
}

$reviewCount = count($reviews);
$avgRating   = $reviewCount ? array_sum(array_column($reviews, 'rating')) / $reviewCount : 0;
$stock       = (int)$product['stock'];
$msg         = $_GET['msg'] ?? '';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($product['name']) ?> | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-900: #103d25;
            --green-700: #17623b;
            --green-100: #eaf3ec;
            --accent: #c8e49b;
            --ink: #18251d;
            --muted: #6b7a70;
            --line: #e4ebe5;
            --bg: #f4f6f4;
            --gold: #d9a521;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--green-700);
        }

        .container-narrow {
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .crumbs {
            font-size: 13px;
            color: var(--muted);
            padding: 22px 0 0;
        }

        .crumbs a {
            color: var(--muted);
            text-decoration: none;
        }

        .crumbs a:hover {
            color: var(--green-700);
        }

        .product-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 36px;
            margin-top: 18px;
        }

        .gallery {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 24px;
            height: fit-content;
        }

        .gallery .frame {
            height: 420px;
            background: #f6f8f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .gallery img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 16px;
        }

        .gallery .no-img {
            color: #9aa69e;
            text-align: center;
        }

        .gallery .no-img i {
            font-size: 40px;
            display: block;
            margin-bottom: 8px;
        }

        .buybox {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 28px;
        }

        .buybox h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.3px;
            line-height: 1.3;
            margin: 0 0 10px;
        }

        .rating-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 18px;
        }

        .stars {
            color: var(--gold);
            letter-spacing: 1px;
        }

        .price {
            font-size: 30px;
            font-weight: 800;
            color: var(--ink);
        }

        .price-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            margin: 18px 0;
        }

        .stock-badge.in {
            color: var(--green-700);
        }

        .stock-badge.out {
            color: #b42318;
        }

        .qty-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .qty-row label {
            font-weight: 600;
            font-size: 13px;
        }

        .qty-row input {
            width: 80px;
            height: 42px;
            border: 1px solid var(--line);
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-cart,
        .btn-buy,
        .btn-goto,
        .btn-login {
            height: 46px;
            border-radius: 9px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border: 0;
            transition: .2s;
        }

        .btn-cart {
            background: var(--green-900);
            color: #fff;
        }

        .btn-cart:hover {
            background: var(--green-700);
            color: #fff;
        }

        .btn-buy {
            background: #fff;
            color: var(--green-900);
            border: 1.5px solid var(--green-900) !important;
        }

        .btn-buy:hover {
            background: var(--green-100);
            color: var(--green-900);
        }

        .btn-goto {
            grid-column: 1 / -1;
            background: var(--accent);
            color: var(--green-900);
        }

        .btn-goto:hover {
            background: #d7eeb0;
            color: var(--green-900);
        }

        .btn-cart:disabled {
            opacity: .7;
        }

        .btn-login {
            grid-column: 1 / -1;
            background: var(--green-900);
            color: #fff;
        }

        .btn-login:hover {
            background: var(--green-700);
            color: #fff;
        }

        .perks {
            margin-top: 22px;
            border-top: 1px solid var(--line);
            padding-top: 18px;
            display: grid;
            gap: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        .perks span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .perks i {
            color: var(--green-700);
        }

        .section {
            margin: 36px 0;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 28px;
        }

        .section h2 {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 14px;
        }

        .description {
            color: #414d45;
            line-height: 1.75;
            white-space: pre-line;
        }

        .review {
            border-bottom: 1px solid var(--line);
            padding: 16px 0;
        }

        .review:last-child {
            border-bottom: 0;
        }

        .review-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .review-name {
            font-weight: 700;
        }

        .review-text {
            color: #414d45;
            line-height: 1.6;
            margin: 0;
        }

        .review-stars {
            color: var(--gold);
            font-size: 13px;
        }

        .review-form {
            background: var(--bg);
            border-radius: 12px;
            padding: 20px;
            margin-top: 18px;
        }

        .review-form label {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }

        .review-form select,
        .review-form textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
            margin-bottom: 14px;
            background: #fff;
        }

        .review-form textarea {
            min-height: 96px;
            resize: vertical;
        }

        .btn-review {
            background: var(--green-900);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-review:hover {
            background: var(--green-700);
        }

        .alert-soft {
            border-radius: 10px;
            font-size: 13px;
        }

        .empty-text {
            color: var(--muted);
            font-size: 13px;
        }

        footer {
            background: var(--green-900);
            color: #cfe1d5;
            padding: 24px 15px;
            text-align: center;
            font-size: 12.5px;
            margin-top: 40px;
        }

        @media (max-width: 880px) {
            .product-wrap {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .gallery .frame {
                height: 300px;
            }

            .buybox h1 {
                font-size: 20px;
            }

            .price {
                font-size: 26px;
            }
        }

        @media (max-width: 480px) {
            .actions {
                grid-template-columns: 1fr;
            }

            .btn-buy {
                grid-column: auto;
            }

            .gallery .frame {
                height: 240px;
            }

            .buybox,
            .section {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="container-narrow">

        <nav class="crumbs" aria-label="breadcrumb">
            <a href="index.php">Home</a> <span class="mx-1">/</span> <?= e($product['name']) ?>
        </nav>

        <?php if ($msg === 'login_required'): ?>
            <div class="alert alert-warning alert-soft mt-3"><i class="bi bi-info-circle me-1"></i>Please log in to add items to your cart.</div>
        <?php endif; ?>

        <div class="product-wrap">

            <!-- Image -->
            <div class="gallery">
                <div class="frame">
                    <?php if (!empty($product['image'])): ?>
                        <img src="uploads/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
                    <?php else: ?>
                        <div class="no-img"><i class="bi bi-image"></i>No image available</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Buy box -->
            <div class="buybox">
                <h1><?= e($product['name']) ?></h1>

                <div class="rating-line">
                    <?php if ($reviewCount): ?>
                        <span class="stars"><?= str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)) ?></span>
                        <span><?= number_format($avgRating, 1) ?> (<?= $reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?>)</span>
                    <?php else: ?>
                        <span>No reviews yet</span>
                    <?php endif; ?>
                </div>

                <div class="price">₹<?= number_format((float)$product['price'], 2) ?></div>
                <div class="price-note">Inclusive of all taxes</div>

                <?php if ($stock > 0): ?>
                    <div class="stock-badge in"><i class="bi bi-check-circle-fill"></i>In stock<?= $stock <= 5 ? " · only $stock left" : '' ?></div>

                    <?php if ($isLoggedIn): ?>
                        <form method="post" action="add_to_cart.php" id="addToCartForm">
                            <input type="hidden" name="product_id" value="<?= $productId ?>">
                            <div class="qty-row">
                                <label for="qty">Quantity</label>
                                <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $stock ?>" required>
                            </div>
                            <div class="actions">
                                <button type="submit" class="btn-cart" id="addToCartBtn"><i class="bi bi-cart-plus"></i>Add to cart</button>
                                <a href="checkout.php?id=<?= $productId ?>&amp;quantity=1" class="btn-buy" id="buyNowBtn" aria-label="Buy <?= e($product['name']) ?> now"><i class="bi bi-lightning-charge"></i>Buy now</a>
                                <a href="cart.php" class="btn-goto d-none" id="goToCartBtn"><i class="bi bi-bag-check"></i>Go to cart</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="qty-row">
                            <label>Quantity</label>
                            <input type="number" value="1" min="1" max="<?= $stock ?>" disabled>
                        </div>
                        <div class="actions">
                            <a href="login.php?msg=login_required&amp;redirect=<?= urlencode('product_details.php?id=' . $productId) ?>" class="btn-login" aria-label="Log in to buy <?= e($product['name']) ?>"><i class="bi bi-person"></i>Login to buy</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stock-badge out"><i class="bi bi-x-circle-fill"></i>Currently out of stock</div>
                <?php endif; ?>

                <div class="perks">
                    <span><i class="bi bi-patch-check"></i>Genuine products from authorised dealers</span>
                    <span><i class="bi bi-lock"></i>Secure checkout</span>
                    <span><i class="bi bi-arrow-counterclockwise"></i>7-day replacement policy</span>
                </div>
            </div>
        </div>

        <!-- Description -->
        <section class="section">
            <h2>Product description</h2>
            <div class="description"><?= e($product['description'] ?: 'No description provided.') ?></div>
        </section>

        <!-- Reviews -->
        <section class="section">
            <h2>Customer reviews</h2>

            <?php if (!$reviewsAvailable): ?>
                <p class="empty-text">Reviews are temporarily unavailable.</p>
            <?php elseif ($reviewCount): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review">
                        <div class="review-head">
                            <span class="review-name"><?= e($r['name']) ?></span>
                            <span class="review-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                        </div>
                        <p class="review-text"><?= e($r['review']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-text">No reviews yet. Be the first to share your experience.</p>
            <?php endif; ?>

            <?php if ($reviewsAvailable && $isLoggedIn && $hasPurchased && !$alreadyReviewed): ?>
                <form method="POST" action="add_review.php" class="review-form">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <label for="rating">Your rating</label>
                    <select id="rating" name="rating" required>
                        <option value="5">5 – Excellent</option>
                        <option value="4">4 – Good</option>
                        <option value="3">3 – Average</option>
                        <option value="2">2 – Poor</option>
                        <option value="1">1 – Very poor</option>
                    </select>
                    <label for="review">Your review</label>
                    <textarea id="review" name="review" maxlength="1000" placeholder="Share your experience with this product" required></textarea>
                    <button type="submit" class="btn-review">Submit review</button>
                </form>
            <?php elseif ($reviewsAvailable && !$isLoggedIn): ?>
                <p class="mt-3 mb-0 empty-text"><a href="login.php">Log in</a> to write a review.</p>
            <?php elseif ($reviewsAvailable && $isLoggedIn && !$hasPurchased): ?>
                <p class="mt-3 mb-0 empty-text">Only customers who have purchased this product can write a review.</p>
            <?php elseif ($reviewsAvailable && $isLoggedIn && $alreadyReviewed): ?>
                <p class="mt-3 mb-0 empty-text">You have already reviewed this product.</p>
            <?php endif; ?>
        </section>

    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($isLoggedIn): ?>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script>
            $(function() {
                const stock = <?= $stock ?>;

                function updateBuyNowUrl() {
                    let quantity = parseInt($('#qty').val(), 10);
                    if (!Number.isFinite(quantity) || quantity < 1) quantity = 1;
                    if (quantity > stock) quantity = stock;

                    $('#qty').val(quantity);
                    $('#buyNowBtn').attr('href', 'checkout.php?id=<?= $productId ?>&quantity=' + encodeURIComponent(quantity));
                }

                $('#qty').on('input change', updateBuyNowUrl);
                $('#buyNowBtn').on('click', function(e) {
                    e.preventDefault();
                    updateBuyNowUrl();
                    window.location.href = $(this).attr('href');
                });
                updateBuyNowUrl();

                $('#addToCartForm').on('submit', function(e) {
                    e.preventDefault();
                    const btn = $('#addToCartBtn');
                    const original = btn.html();
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Adding…');

                    $.ajax({
                        url: 'add_to_cart.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if ($.trim(response) === 'success') {
                                const qty = parseInt($('#qty').val() || '1', 10);
                                $('#cartCount').text(parseInt($('#cartCount').text() || '0', 10) + qty);
                                btn.addClass('d-none');
                                $('#goToCartBtn').removeClass('d-none');
                            } else {
                                alert($.trim(response));
                                btn.prop('disabled', false).html(original);
                            }
                        },
                        error: function() {
                            alert('Something went wrong. Please try again.');
                            btn.prop('disabled', false).html(original);
                        }
                    });
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>