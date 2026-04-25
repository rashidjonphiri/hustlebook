<?php
include("../config/db.php");

$productsError = null;
$pageError = $_GET['error'] ?? '';
$pageMsg = $_GET['msg'] ?? '';
$inventorySql = "SELECT product_id, product_name, stocking_price, selling_price, stock_quantity,
    (stocking_price * stock_quantity) AS cost_value,
    (selling_price * stock_quantity) AS retail_value
    FROM products ORDER BY product_name ASC";
$inventoryRes = hb_query($conn, $inventorySql);
if ($inventoryRes === false) {
    $productsError = $conn->error;
}

$productOptionsRes = hb_query($conn, "SELECT product_id, product_name, stock_quantity FROM products ORDER BY product_name ASC");
if ($productOptionsRes === false) {
    $productsError = $productsError ?? $conn->error;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Management</title>
    <?php session_start(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <link href="../bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include("../includes/sidebar.php"); ?>
    <div class="main-content">
        <div class="container page-shell">
            <div class="card page-header">
                <div>
                    <h2 class="mb-0">Product & Stock Management</h2>
                    <p>Track inventory value, add new products, and record stock movement from one workspace.</p>
                </div>
            </div>

            <?php if ($pageMsg !== ''): ?>
                <div class="alert alert-success rounded-4" role="alert">
                    <?= $pageMsg === 'product_saved' ? 'Product saved successfully.' : ($pageMsg === 'movement_saved' ? 'Stock movement recorded successfully.' : '') ?>
                </div>
            <?php endif; ?>

            <?php if ($pageError !== ''): ?>
                <div class="alert alert-danger rounded-4" role="alert">
                    <?= htmlspecialchars($pageError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($productsError)): ?>
                <div class="alert alert-danger rounded-4" role="alert">
                    <strong>Could not load products.</strong> <?= htmlspecialchars($productsError) ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column: Forms -->
                <div class="col-md-4">
                    <div class="card card1 section-card mb-4">
                        <div class="section-title">
                            <div>
                                <h5><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                                <p class="section-subtitle">Set your product name, cost, retail price, and opening stock.</p>
                            </div>
                        </div>
                        <form action="../actions/save_product.php" method="POST">
                            <div class="mb-3">
                                <label class="field-label">Product Name</label>
                                <input type="text" name="product_name" class="form-control" placeholder="Product Name" required>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Stocking price (cost per unit)</label>
                                <input type="number" step="0.01" name="stocking_price" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Selling price (retail per unit)</label>
                                <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Initial stock</label>
                                <input type="number" name="quantity" class="form-control" placeholder="0" required min="0">
                            </div>
                            <button class="btn btn-lime w-100">Create Product</button>
                        </form>
                    </div>

                    <div class="card card1 section-card">
                        <div class="section-title">
                            <div>
                                <h5><i class="bi bi-arrow-left-right me-2"></i>Record Movement</h5>
                                <p class="section-subtitle">Log stock entering or leaving inventory without editing the product record.</p>
                            </div>
                        </div>
                        <form action="../actions/record_movement.php" method="POST">
                            <div class="mb-3">
                                <label class="field-label">Product</label>
                                <select name="product_id" class="form-select" required <?= ($productOptionsRes && $productOptionsRes->num_rows === 0) ? 'disabled' : '' ?>>
                                    <option value=""><?= ($productOptionsRes && $productOptionsRes->num_rows === 0) ? 'No products yet — add one first' : 'Select Product...' ?></option>
                                    <?php
                                    if ($productOptionsRes) {
                                        while ($p = $productOptionsRes->fetch_assoc()) {
                                            echo "<option value='" . (int)$p['product_id'] . "'>" . htmlspecialchars($p['product_name']) . " (" . (int)$p['stock_quantity'] . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" placeholder="Quantity" required>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Movement Type</label>
                                <select name="movement_type" class="form-select">
                                    <option value="IN">Stock IN (Purchase/Restock)</option>
                                    <option value="OUT">Stock OUT (Personal Drawing/Loss)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="field-label">Notes</label>
                                <textarea name="comment" class="form-control" rows="2" placeholder="Notes (e.g. Owner drawing)"></textarea>
                            </div>
                            <button class="btn btn-primary w-100" type="submit" <?= ($productOptionsRes && $productOptionsRes->num_rows === 0) ? 'disabled' : '' ?>><?= ($productOptionsRes && $productOptionsRes->num_rows === 0) ? 'Add a product first' : 'Update Inventory' ?></button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Inventory List & Sketchpad -->
                <div class="col-md-8">
                    <div class="card card1 section-card mb-4">
                        <div class="section-title">
                            <div>
                                <h5>Current Inventory</h5>
                                <p class="section-subtitle">Monitor stock levels and compare cost value against retail value at a glance.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Stocking</th>
                                        <th>Selling</th>
                                        <th>Stock</th>
                                        <th>Inventory (cost)</th>
                                        <th>Inventory (retail)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($inventoryRes && $inventoryRes->num_rows === 0):
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-white-50">No products in inventory yet. Use the form on the left to add your first product.</td>
                                    </tr>
                                    <?php
                                    elseif ($inventoryRes):
                                    while ($row = $inventoryRes->fetch_assoc()):
                                        $qty = (int)($row['stock_quantity'] ?? 0);
                                        $stocking = (float)($row['stocking_price'] ?? 0);
                                        $selling = (float)($row['selling_price'] ?? 0);
                                        $costVal = (float)($row['cost_value'] ?? 0);
                                        $retailVal = (float)($row['retail_value'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['product_name'] ?? '') ?></td>
                                        <td>$<?= number_format($stocking, 2) ?></td>
                                        <td>$<?= number_format($selling, 2) ?></td>
                                        <td>
                                            <span class="badge <?= $qty < 10 ? 'bg-danger' : 'bg-success' ?>">
                                                <?= $qty ?>
                                            </span>
                                        </td>
                                        <td>$<?= number_format($costVal, 2) ?></td>
                                        <td>$<?= number_format($retailVal, 2) ?></td>
                                    </tr>
                                    <?php
                                    endwhile;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
