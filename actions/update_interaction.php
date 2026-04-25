<?php
include("../config/db.php");

$interactionId = (int)($_POST['interaction_id'] ?? 0);
$customerId = (int)($_POST['customer_id'] ?? 0);
$customerName = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$gender = $_POST['gender'] ?? '';
$location = trim($_POST['location'] ?? '');
$address = trim($_POST['address'] ?? '');
$sourceId = (int)($_POST['source_id'] ?? 0);
$interactionDirectionRaw = trim($_POST['interaction_direction'] ?? '');
$interactionDirection = ucfirst(strtolower($interactionDirectionRaw));
$inboxDatetime = !empty($_POST['inbox_datetime']) ? $_POST['inbox_datetime'] : null;
$responseDatetime = !empty($_POST['response_datetime']) ? $_POST['response_datetime'] : null;
$deliveryDatetime = !empty($_POST['delivery_datetime']) ? $_POST['delivery_datetime'] : null;
$freeGift = (int)($_POST['free_gift'] ?? 0);
$isSale = (int)($_POST['is_sale'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($interactionId <= 0 || $customerId <= 0 || $customerName === '' || $sourceId <= 0 || !in_array($interactionDirection, ['Inbound', 'Outbound'], true)) {
    header("Location: ../pages/sales_history.php?error=" . urlencode("Missing required interaction data."));
    exit;
}

// Update Customer
$stmt = $conn->prepare("
    UPDATE customers 
    SET customer_name=?, phone=?, gender=?, location=?, address=? 
    WHERE customer_id=?
");

$stmt->bind_param("sssssi", 
    $customerName,
    $phone,
    $gender,
    $location,
    $address,
    $customerId
);

if (!$stmt->execute()) {
    header("Location: ../pages/sales_history.php?error=" . urlencode($stmt->error));
    exit;
}

// Calculate total quantity from arrays
$total_qty = 0;
if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $q) {
        $total_qty += (int)$q;
    }
}

// Update Interaction
$stmt2 = $conn->prepare("
    UPDATE interactions 
    SET source_id=?, interaction_direction=?, inbox_datetime=?, response_datetime=?, delivery_datetime=?, 
        sale_quantity=?, free_gift=?, is_sale=?, comment=? 
    WHERE interaction_id=?
");

$stmt2->bind_param("issssiiisi", 
    $sourceId, $interactionDirection, $inboxDatetime, $responseDatetime, $deliveryDatetime,
    $total_qty, $freeGift, $isSale, $comment, $interactionId
);
if (!$stmt2->execute()) {
    header("Location: ../pages/sales_history.php?error=" . urlencode($stmt2->error));
    exit;
}

// Handle Stock Reversion and Updates
$move_comment = "Sale from Interaction #" . $interactionId;
$moveCommentSafe = hb_escape($conn, $move_comment);

// 1. Revert old stock
$oldMoves = $conn->query("SELECT product_id, quantity FROM stock_movement WHERE comments = '$moveCommentSafe'");
if ($oldMoves) {
    while($om = $oldMoves->fetch_assoc()) {
        $updateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        if ($updateStock) {
            $updateStock->bind_param("ii", $om['quantity'], $om['product_id']);
            $updateStock->execute();
        }
    }
}

// 2. Delete old movements
$conn->query("DELETE FROM stock_movement WHERE comments = '$moveCommentSafe'");

// 3. Apply new stock movements if it's still a sale
if ($isSale === 1 && isset($_POST['product_ids'])) {
    foreach ($_POST['product_ids'] as $index => $product_id) {
        $product_id = (int)$product_id;
        $qty = (int)($_POST['quantities'][$index] ?? 0);
        if ($product_id <= 0 || $qty <= 0) continue;

        // Record New Stock Movement OUT
        $moveStmt = $conn->prepare("INSERT INTO stock_movement (product_id, quantity, movement_type, movement_date, comments) VALUES (?, ?, 'OUT', NOW(), ?)");
        $moveStmt->bind_param("iis", $product_id, $qty, $move_comment);
        $moveStmt->execute();

        // Update Product Inventory
        $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $updateStmt->bind_param("ii", $qty, $product_id);
        $updateStmt->execute();
    }
}

header("Location: ../pages/sales_history.php?msg=updated");
exit;
?>
