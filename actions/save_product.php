<?php
include("../config/db.php");

$productName = trim($_POST['product_name'] ?? '');
$stockingPrice = (doubleval($_POST['stocking_price'] ?? 0));
$sellingPrice = (doubleval($_POST['selling_price'] ?? 0));
$stockQuantity = (int)($_POST['quantity'] ?? 0);

$stmt = $conn->prepare("
    INSERT INTO products (product_name, stocking_price, selling_price, stock_quantity)
    VALUES (?, ?, ?, ?)
");

if (!$stmt) {
    header("Location: ../pages/products.php?error=" . urlencode($conn->error));
    exit;
}

$stmt->bind_param("sddi", $productName, $stockingPrice, $sellingPrice, $stockQuantity);

if (!$stmt->execute()) {
    header("Location: ../pages/products.php?error=" . urlencode($stmt->error));
    exit;
}

header("Location: ../pages/products.php?msg=product_saved");
exit;
?>
