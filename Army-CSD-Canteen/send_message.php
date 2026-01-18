<?php
session_start();
include 'config.php'; // Your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = mysqli_real_escape_string($conn, $_POST['name']);
    $email   = mysqli_real_escape_string($conn, $_POST['email']);
    $phone   = mysqli_real_escape_string($conn, $_POST['phone']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // Insert into DB
    $sql = "INSERT INTO contact_messages (name, email, phone, message) 
            VALUES ('$name', '$email', '$phone', '$message')";

    if (mysqli_query($conn, $sql)) {
        echo "<script>
                alert('✅ Your message has been sent successfully!');
                window.location.href='contact.php';
              </script>";
    } else {
        echo "<script>
                alert('❌ Something went wrong. Please try again.');
                window.location.href='contact.php';
              </script>";
    }
}
?>
