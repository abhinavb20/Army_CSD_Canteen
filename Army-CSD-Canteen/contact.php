<?php
// contact.php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Contact Us | Army CSD Canteen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="style2.css" rel="stylesheet">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f8f9fa;
    
    }
    .container {
      max-width: 1100px;
      margin: 40px auto;
      padding: 20px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #2d572c;
    }
    .contact-box {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .contact-details, .contact-form {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .contact-details h3, .contact-form h3 {
      margin-bottom: 15px;
      color: #444;
    }
    .contact-details p {
      margin: 8px 0;
    }
    form input, form textarea {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    form button {
      background: #2d572c;
      color: #fff;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.3s;
    }
    form button:hover {
      background: #244722;
    }
    iframe {
      border: 0;
      width: 100%;
      height: 250px;
      border-radius: 10px;
      margin-top: 15px;
    }
    @media(max-width: 768px) {
      .contact-box {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <?php include 'header.php'; ?> <!-- reuse your header -->

  <div class="container">
    <h2>Contact Us</h2>
    <div class="contact-box">

      <!-- Left side: contact details -->
      <div class="contact-details">
        <h3>Get in Touch</h3>
        <p><b>Army CSD Canteen</b></p>
        <p>📍 Station Road, Cantonment, City, State</p>
        <p>📞 +91 xxxxxxxxx</p>
        <p>📧 support@csdcanteen.in</p>
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!..." allowfullscreen></iframe>
      </div>

      <!-- Right side: form -->
      <div class="contact-form">
        <h3>Send Us a Message</h3>
        <form action="send_message.php" method="POST">
          <input type="text" name="name" placeholder="Your Name" required>
          <input type="email" name="email" placeholder="Your Email" required>
          <input type="text" name="phone" placeholder="Your Phone">
          <textarea name="message" rows="5" placeholder="Your Message..." required></textarea>
          <button type="submit">Send Message</button>
        </form>
      </div>

    </div>
  </div>

  

</body>
</html>
