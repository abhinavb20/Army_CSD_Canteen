<?php
include 'config.php';
$id = $_GET['id'];
$conn->query("DELETE FROM addresses WHERE id = $id");
header("Location: manage_addresses.php?msg=Deleted");
?>
