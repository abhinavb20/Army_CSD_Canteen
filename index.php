<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);

// Search
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $sql = "SELECT * FROM products
            WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?)
            ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $like = "%$search%";
    $stmt->bind_param('ss', $like, $like);
} else {
    $sql = "SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Army CSD Canteen | Online Shopping</title>

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

        /* Ticker */
        .ticker {
            background: #0c2e1c;
            color: #d7e7dc;
            font-size: 12.5px;
            height: 36px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .ticker-track {
            display: inline-flex;
            white-space: nowrap;
            animation: scroll 38s linear infinite;
            gap: 56px;
            padding-left: 100%;
        }

        .ticker-track span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ticker-track i {
            color: var(--accent);
        }

        @keyframes scroll {
            to {
                transform: translateX(-100%);
            }
        }

        /* Hero */
        .hero {
            background: linear-gradient(120deg, var(--green-900), var(--green-700));
            color: #fff;
            padding: 56px 20px 90px;
        }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--accent);
        }

        .hero h1 {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -.5px;
            margin: 12px 0 10px;
        }

        .hero p {
            color: #d3e6d9;
            font-size: 15px;
            max-width: 520px;
            margin: 0;
        }

        .hero-points {
            margin-top: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            font-size: 13px;
            color: #e6f1ea;
        }

        .hero-points span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .hero-points i {
            color: var(--accent);
        }

        /* Search */
        .search-wrap {
            max-width: 860px;
            margin: -28px auto 0;
            padding: 0 16px;
            position: relative;
            z-index: 5;
        }

        .search-box {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 30px rgba(16, 61, 37, .08);
        }

        .search-field {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 12px;
        }

        .search-field i {
            color: var(--muted);
            font-size: 16px;
        }

        .search-field input {
            border: 0;
            outline: 0;
            width: 100%;
            height: 42px;
            font-size: 14px;
            background: transparent;
        }

        .search-box button {
            background: var(--green-700);
            color: #fff;
            border: 0;
            border-radius: 8px;
            height: 42px;
            padding: 0 22px;
            font-weight: 600;
            font-size: 14px;
        }

        .search-box button:hover {
            background: var(--green-900);
        }

        .clear-link {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            padding: 0 8px;
            white-space: nowrap;
        }

        /* Section */
        .products-wrap {
            max-width: 1200px;
            margin: 44px auto 60px;
            padding: 0 20px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .section-head h2 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -.3px;
        }

        .section-head p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .count-pill {
            font-size: 12px;
            color: var(--muted);
            background: #fff;
            border: 1px solid var(--line);
            padding: 6px 12px;
            border-radius: 999px;
        }

        /* Grid and cards */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
        }

        .product-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            transition: box-shadow .25s, border-color .25s, transform .25s;
            min-width: 0;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(16, 61, 37, .1);
            border-color: #d3e0d6;
        }

        .product-image {
            height: 200px;
            background: #f6f8f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
            transition: transform .4s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.04);
        }

        .no-image {
            color: #9aa69e;
            font-size: 12px;
            text-align: center;
        }

        .no-image i {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-card h3 {
            font-size: 14.5px;
            font-weight: 600;
            line-height: 1.4;
            margin: 0 0 8px;
            min-height: 40px;
            color: var(--ink);
        }

        .description {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .read-more {
            color: var(--green-700);
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .read-more:hover {
            text-decoration: underline;
        }

        .product-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            margin-top: auto;
            margin-bottom: 12px;
        }

        .product-price small {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
            margin-left: 4px;
        }

        .btn-details {
            display: block;
            width: 100%;
            background: #fff;
            color: var(--green-700);
            border: 1px solid #c5d6c9;
            padding: 9px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: .2s;
        }

        .btn-details:hover {
            background: var(--green-100);
            border-color: var(--green-700);
            color: var(--green-900);
        }

        .cart-form {
            margin-top: 10px;
        }

        .cart-controls {
            display: flex;
            gap: 8px;
        }

        .cart-controls input {
            width: 64px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px;
            font-size: 13px;
            text-align: center;
        }

        .btn-add-cart {
            flex: 1;
            background: var(--green-900);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 9px 6px;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .btn-add-cart:hover {
            background: var(--green-700);
        }

        .btn-add-cart:disabled {
            opacity: .7;
        }

        .out-stock {
            color: #b42318;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }

        .empty-products {
            grid-column: 1 / -1;
            background: #fff;
            border: 1px solid var(--line);
            padding: 56px 20px;
            border-radius: 12px;
            text-align: center;
        }

        .empty-products i {
            font-size: 40px;
            color: #aab7ad;
        }

        /* Footer */
        footer {
            background: var(--green-900);
            color: #cfe1d5;
            padding: 26px 15px;
            text-align: center;
            font-size: 12.5px;
        }

        footer p {
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .product-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 40px 20px 80px;
            }

            .hero h1 {
                font-size: 27px;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .product-card {
                padding: 10px;
            }

            .product-image {
                height: 150px;
            }

            .product-card h3 {
                font-size: 13px;
                min-height: 36px;
            }

            .description {
                font-size: 11.5px;
            }

            .product-price {
                font-size: 16px;
            }
        }

        @media (max-width: 400px) {
            .product-image {
                height: 125px;
            }

            .cart-controls {
                flex-direction: column;
            }

            .cart-controls input {
                width: 100%;
            }

            .search-box {
                flex-wrap: wrap;
            }

            .search-box button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="ticker" aria-label="Announcements">
        <div class="ticker-track">
            <span><i class="bi bi-award-fill"></i> Welcome to the Army CSD Canteen Online Portal</span>
            <span><i class="bi bi-tags-fill"></i> Exclusive discounts on electronics, apparel and more</span>
            <span><i class="bi bi-truck"></i> Shop now and save</span>
        </div>
    </div>

    <section class="hero">
        <div class="hero-inner">
            <span class="hero-eyebrow"><i class="bi bi-shield-check"></i> Army CSD Online Store</span>
            <h1>Shop Smart. Shop CSD.</h1>
            <p>Quality products at exclusive CSD prices, from a trusted online shopping destination.</p>
            <div class="hero-points">
                <span><i class="bi bi-patch-check-fill"></i> Quality products</span>
                <span><i class="bi bi-tags-fill"></i> Exclusive prices</span>
                <span><i class="bi bi-lock-fill"></i> Secure checkout</span>
            </div>
        </div>
    </section>

    <div class="search-wrap">
        <form method="GET" action="index.php" class="search-box" role="search">
            <div class="search-field">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search for products" aria-label="Search products"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="clear-link">Clear</a>
            <?php endif; ?>
            <button type="submit">Search</button>
        </form>
    </div>

    <section class="products-wrap">
        <div class="section-head">
            <div>
                <h2>Explore our products</h2>
                <p>Latest arrivals and exclusive CSD offers</p>
            </div>
            <span class="count-pill"><i class="bi bi-grid me-1"></i><?= $result->num_rows ?> items</span>
        </div>

        <div class="product-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $id   = (int)$row['id'];
                    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                    $description = $row['description'] ?? '';
                    $fullDesc    = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                    $words       = preg_split('/\s+/', trim($description), -1, PREG_SPLIT_NO_EMPTY);
                    $shortDesc   = implode(' ', array_slice($words, 0, 25));
                    $hasMore     = count($words) > 25;
                    $stock       = (int)$row['stock'];
                    ?>

                    <article class="product-card">
                        <div class="product-image">
                            <?php if (!empty($row['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= $name ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="bi bi-image"></i>
                                    No image available
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <h3><?= $name ?></h3>

                            <div class="description" id="desc-<?= $id ?>">
                                <span class="short-text">
                                    <?= htmlspecialchars($shortDesc, ENT_QUOTES, 'UTF-8') ?><?= $hasMore ? '…' : '' ?>
                                </span>
                                <?php if ($hasMore): ?>
                                    <span class="full-text" style="display:none;"><?= $fullDesc ?></span>
                                    <a href="javascript:void(0)" class="read-more" onclick="toggleDesc(<?= $id ?>)">Read more</a>
                                <?php endif; ?>
                            </div>

                            <div class="product-price">
                                ₹<?= number_format((float)$row['price'], 2) ?>
                            </div>

                            <a href="product_details.php?id=<?= $id ?>" class="btn-details">
                                <i class="bi bi-eye me-1"></i>View details
                            </a>

                            <?php if ($stock > 0): ?>
                                <form method="POST" action="add_to_cart.php" class="cart-form">
                                    <div class="cart-controls">
                                        <input type="hidden" name="product_id" value="<?= $id ?>">
                                        <input type="number" name="quantity" value="1" min="1"
                                            max="<?= $stock ?>" required aria-label="Quantity">
                                        <button type="submit" class="btn-add-cart">
                                            <i class="bi bi-cart-plus me-1"></i>Add to cart
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="out-stock"><i class="bi bi-x-circle me-1"></i>Currently out of stock</div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-products">
                    <i class="bi bi-bag-x"></i>
                    <h4 class="mt-3 mb-1">No products found</h4>
                    <p class="text-muted mb-3">Try a different product name.</p>
                    <a href="index.php" class="btn btn-success btn-sm">View all products</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <p>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</p>
        <p class="mt-1 opacity-75"><i class="bi bi-shield-check me-1"></i>Army CSD Online Shopping Portal By Abhinav B</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        function toggleDesc(id) {
            const desc = document.getElementById('desc-' + id);
            const short = desc.querySelector('.short-text');
            const full = desc.querySelector('.full-text');
            const link = desc.querySelector('.read-more');
            const open = full.style.display === 'inline';
            full.style.display = open ? 'none' : 'inline';
            short.style.display = open ? 'inline' : 'none';
            link.textContent = open ? 'Read more' : 'Show less';
        }

        $(function() {
            $("form[action='add_to_cart.php']").on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const button = form.find('button[type="submit"]');
                const original = button.html();

                button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Adding…');

                $.ajax({
                    url: 'add_to_cart.php',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        response = $.trim(response);
                        if (response === 'success') {
                            const count = parseInt($('#cartCount').text() || '0', 10);
                            $('#cartCount').text(count + parseInt(form.find('input[name="quantity"]').val() || '1', 10));
                            button.html('<i class="bi bi-check2 me-1"></i>Added');
                            setTimeout(() => button.prop('disabled', false).html(original), 1400);
                            return;
                        }
                        alert(response);
                    },
                    error: function() {
                        alert('Something went wrong. Please try again.');
                    },
                    complete: function() {
                        if (!button.text().includes('Added')) {
                            button.prop('disabled', false).html(original);
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>