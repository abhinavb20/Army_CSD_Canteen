<?php
session_start();

function e($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// CSRF token
if (empty($_SESSION['contact_csrf'])) {
  $_SESSION['contact_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['contact_csrf'];

// Flash message and any values to re-fill after an error
$flash = $_SESSION['contact_flash'] ?? null;
$old   = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_flash'], $_SESSION['contact_old']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us | Army CSD Canteen</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --green-900: #103d25;
      --green-700: #17623b;
      --green-100: #eaf3ec;
      --ink: #18251d;
      --muted: #6b7a70;
      --line: #e4ebe5;
      --bg: #f4f6f4;
      --danger: #b42318;
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

    .page {
      max-width: 1080px;
      margin: 0 auto;
      padding: 36px 20px 60px;
    }

    .page-head {
      margin-bottom: 24px;
    }

    .page-head h1 {
      font-size: 26px;
      font-weight: 800;
      letter-spacing: -.4px;
      margin: 0 0 6px;
    }

    .page-head p {
      color: var(--muted);
      margin: 0;
      max-width: 560px;
    }

    .layout {
      display: grid;
      grid-template-columns: 1fr 1.15fr;
      gap: 24px;
      align-items: start;
    }

    .panel {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 26px;
    }

    .panel h2 {
      font-size: 16px;
      font-weight: 700;
      margin: 0 0 16px;
    }

    .info-row {
      display: flex;
      gap: 14px;
      padding: 14px 0;
      border-bottom: 1px solid var(--line);
    }

    .info-row:last-of-type {
      border-bottom: 0;
    }

    .info-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: var(--green-100);
      color: var(--green-700);
      display: grid;
      place-items: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .info-row strong {
      display: block;
      font-size: 13px;
      margin-bottom: 2px;
    }

    .info-row span,
    .info-row a {
      color: var(--muted);
      font-size: 13.5px;
      text-decoration: none;
    }

    .info-row a:hover {
      color: var(--green-700);
    }

    .hours {
      margin-top: 14px;
      background: var(--bg);
      border-radius: 10px;
      padding: 14px 16px;
      font-size: 13px;
    }

    .hours div {
      display: flex;
      justify-content: space-between;
      padding: 3px 0;
      color: #3f4d44;
    }

    .hours strong {
      color: var(--ink);
    }

    .map {
      margin-top: 18px;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--line);
      height: 230px;
      background: #eef2ef;
      display: grid;
      place-items: center;
      color: var(--muted);
      font-size: 13px;
    }

    .map iframe {
      width: 100%;
      height: 100%;
      border: 0;
      display: block;
    }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
    }

    .form-control,
    .form-control:focus {
      border-color: var(--line);
    }

    .form-control {
      border-radius: 9px;
      padding: 10px 12px;
      font-size: 14px;
    }

    .form-control:focus {
      border-color: var(--green-700);
      box-shadow: 0 0 0 .2rem rgba(23, 98, 59, .15);
    }

    .field {
      margin-bottom: 14px;
    }

    .hp {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
      opacity: 0;
    }

    .btn-send {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      height: 46px;
      border: 0;
      border-radius: 10px;
      background: var(--green-900);
      color: #fff;
      font-weight: 700;
      font-size: 15px;
    }

    .btn-send:hover {
      background: var(--green-700);
    }

    .btn-send:disabled {
      background: #c9d3cc;
    }

    .note {
      font-size: 12px;
      color: var(--muted);
      margin-top: 10px;
      text-align: center;
    }

    .alert-soft {
      border-radius: 10px;
      font-size: 13.5px;
    }

    footer {
      background: var(--green-900);
      color: #cfe1d5;
      padding: 24px 15px;
      text-align: center;
      font-size: 12.5px;
      margin-top: 30px;
    }

    @media (max-width:880px) {
      .layout {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>

  <?php include 'header.php'; ?>

  <main class="page">

    <div class="page-head">
      <h1>Contact us</h1>
      <p>Questions about an order, your account or a product? Send us a message and our team will get back to you.</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-danger' ?> alert-soft">
        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?> me-1"></i>
        <?= e($flash['text']) ?>
      </div>
    <?php endif; ?>

    <div class="layout">

      <!-- Left: details -->
      <section class="panel">
        <h2>Visit or call us</h2>

        <div class="info-row">
          <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
          <div>
            <strong>Address</strong>
            <span>Station Road, Cantonment, City, State</span>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="bi bi-telephone"></i></div>
          <div>
            <strong>Phone</strong>
            <a href="tel:+91xxxxxxxxxx">+91 xxxxx xxxxx</a>
          </div>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="bi bi-envelope"></i></div>
          <div>
            <strong>Email</strong>
            <a href="mailto:support@csdcanteen.in">support@csdcanteen.in</a>
          </div>
        </div>

        <div class="hours">
          <div><strong>Opening hours</strong></div>
          <div><span>Monday – Saturday</span><span>09:00 – 18:00</span></div>
          <div><span>Sunday</span><span>Closed</span></div>
        </div>

        <div class="map">
          <!-- Replace with your Google Maps embed code -->
          <span><i class="bi bi-map me-1"></i>Map will appear here</span>
        </div>
      </section>

      <!-- Right: form -->
      <section class="panel">
        <h2>Send us a message</h2>

        <form action="send_message.php" method="POST" id="contactForm" novalidate>
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

          <!-- Honeypot: hidden from people, bots often fill it in -->
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">

          <div class="row g-3">
            <div class="col-md-6 field">
              <label for="name" class="form-label">Full name</label>
              <input type="text" id="name" name="name" class="form-control" maxlength="100"
                value="<?= e($old['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 field">
              <label for="phone" class="form-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
              <input type="tel" id="phone" name="phone" class="form-control" maxlength="15"
                value="<?= e($old['phone'] ?? '') ?>">
            </div>
          </div>

          <div class="field">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" maxlength="120"
              value="<?= e($old['email'] ?? '') ?>" required>
          </div>

          <div class="field">
            <label for="message" class="form-label">Message</label>
            <textarea id="message" name="message" class="form-control" rows="6" maxlength="2000"
              required><?= e($old['message'] ?? '') ?></textarea>
            <div class="form-text" id="charCount">0 / 2000</div>
          </div>

          <button type="submit" class="btn-send" id="sendBtn">
            <i class="bi bi-send"></i>Send message
          </button>
          <p class="note">We usually reply within one working day.</p>
        </form>
      </section>

    </div>

  </main>

  <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const form = document.getElementById('contactForm');
    const msg = document.getElementById('message');
    const count = document.getElementById('charCount');

    msg.addEventListener('input', () => {
      count.textContent = msg.value.length + ' / 2000';
    });
    count.textContent = msg.value.length + ' / 2000';

    form.addEventListener('submit', function(e) {
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('phone').value.trim();

      if (phone && !/^[0-9+\-\s]{7,15}$/.test(phone)) {
        e.preventDefault();
        alert('Please enter a valid phone number.');
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return;
      }
      if (!form.checkValidity()) {
        e.preventDefault();
        form.reportValidity();
        return;
      }

      const btn = document.getElementById('sendBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
    });
  </script>
</body>

</html>