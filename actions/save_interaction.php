<?php
include("../config/db.php");

$customerName = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$gender = $_POST['gender'] ?? '';
$location = trim($_POST['location'] ?? '');
$address = trim($_POST['address'] ?? '');
$sourceId = (int)($_POST['source_id'] ?? 0);
$interactionDirection = $_POST['interaction_direction'] ?? '';
$inboxDatetime = !empty($_POST['inbox_datetime']) ? $_POST['inbox_datetime'] : null;
$responseDatetime = !empty($_POST['response_datetime']) ? $_POST['response_datetime'] : null;
$deliveryDatetime = !empty($_POST['delivery_datetime']) ? $_POST['delivery_datetime'] : null;
$freeGift = (int)($_POST['free_gift'] ?? 0);
$isSale = (int)($_POST['is_sale'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($customerName === '' || $sourceId <= 0 || !in_array($interactionDirection, ['Inbound', 'Outbound'], true)) {
    header("Location: ../pages/add_interaction.php?error=" . urlencode("Customer name, source, and interaction direction are required."));
    exit;
}

// 1. Insert customer
$stmt = $conn->prepare("
    INSERT INTO customers (customer_name, phone, gender, location, address)
    VALUES (?, ?, ?, ?, ?)
");

if (!$stmt) {
    header("Location: ../pages/add_interaction.php?error=" . urlencode($conn->error));
    exit;
}

$stmt->bind_param("sssss", $customerName, $phone, $gender, $location, $address);

if (!$stmt->execute()) {
    header("Location: ../pages/add_interaction.php?error=" . urlencode($stmt->error));
    exit;
}

$customer_id = $stmt->insert_id;

// Calculate total quantity for the interaction record
$total_qty = 0;
if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $q) {
        $total_qty += (int)$q;
    }
}

// 2. Insert interaction
$stmt2 = $conn->prepare("
    INSERT INTO interactions 
    (customer_id, source_id, interaction_direction, inbox_datetime, response_datetime, delivery_datetime,
     sale_quantity, free_gift, is_sale, comment)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt2->bind_param("iissssiiis",
    $customer_id,
    $sourceId,
    $interactionDirection,
    $inboxDatetime,
    $responseDatetime,
    $deliveryDatetime,
    $total_qty,
    $freeGift,
    $isSale,
    $comment
);

if (!$stmt2->execute()) {
    header("Location: ../pages/add_interaction.php?error=" . urlencode($stmt2->error));
    exit;
}

$interaction_id = $stmt2->insert_id;

// 3. If it's a sale and a product was selected, update stock
if ($isSale === 1 && isset($_POST['product_ids'])) {
    foreach ($_POST['product_ids'] as $index => $product_id) {
        $product_id = (int)$product_id;
        $qty = (int)($_POST['quantities'][$index] ?? 0);
        if ($product_id <= 0 || $qty <= 0) {
            continue;
        }
        $move_comment = "Sale from Interaction #" . $interaction_id;

        // Record Stock Movement OUT
        $moveStmt = $conn->prepare("INSERT INTO stock_movement (product_id, quantity, movement_type, movement_date, comments) VALUES (?, ?, 'OUT', NOW(), ?)");
        if (!$moveStmt) {
            header("Location: ../pages/add_interaction.php?error=" . urlencode($conn->error));
            exit;
        }

        $moveStmt->bind_param("iis", $product_id, $qty, $move_comment);
        if (!$moveStmt->execute()) {
            header("Location: ../pages/add_interaction.php?error=" . urlencode($moveStmt->error));
            exit;
        }

        // Update Product Inventory
        $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        if (!$updateStmt) {
            header("Location: ../pages/add_interaction.php?error=" . urlencode($conn->error));
            exit;
        }

        $updateStmt->bind_param("ii", $qty, $product_id);
        if (!$updateStmt->execute()) {
            header("Location: ../pages/add_interaction.php?error=" . urlencode($updateStmt->error));
            exit;
        }
    }
}

header("Location: ../pages/sales_history.php?msg=saved");
exit;
?>
