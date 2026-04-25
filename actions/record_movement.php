<?php
include("../config/db.php");

$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$type = $_POST['movement_type'] ?? 'IN';
$comment = trim($_POST['comment'] ?? '');

if ($product_id <= 0 || $quantity <= 0) {
    header("Location: ../pages/products.php?error=" . urlencode("Please choose a product and enter a quantity greater than zero."));
    exit;
}

if (!in_array($type, ['IN', 'OUT'], true)) {
    header("Location: ../pages/products.php?error=" . urlencode("Invalid stock movement type."));
    exit;
}

$currentStockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
if (!$currentStockStmt) {
    header("Location: ../pages/products.php?error=" . urlencode($conn->error));
    exit;
}

$currentStockStmt->bind_param("i", $product_id);
$currentStockStmt->execute();
$stockResult = $currentStockStmt->get_result();
$product = $stockResult ? $stockResult->fetch_assoc() : null;

if (!$product) {
    header("Location: ../pages/products.php?error=" . urlencode("Selected product was not found."));
    exit;
}

if ($type === 'OUT' && (int)$product['stock_quantity'] < $quantity) {
    header("Location: ../pages/products.php?error=" . urlencode("Not enough stock available for this stock-out movement."));
    exit;
}

// Record the movement
$stmt = $conn->prepare("INSERT INTO stock_movement (product_id, quantity, movement_type, movement_date, comments) VALUES (?, ?, ?, NOW(), ?)");
$updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");

if (!$stmt || !$updateStmt) {
    header("Location: ../pages/products.php?error=" . urlencode($conn->error));
    exit;
}

$stmt->bind_param("iiss", $product_id, $quantity, $type, $comment);
if (!$stmt->execute()) {
    header("Location: ../pages/products.php?error=" . urlencode($stmt->error));
    exit;
}

// Update product stock level
$modifier = ($type == 'IN') ? $quantity : -$quantity;
$updateStmt->bind_param("ii", $modifier, $product_id);
if (!$updateStmt->execute()) {
    header("Location: ../pages/products.php?error=" . urlencode($updateStmt->error));
    exit;
}

header("Location: ../pages/products.php?msg=movement_saved");
exit;
?>
