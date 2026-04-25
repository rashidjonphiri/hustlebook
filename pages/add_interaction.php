<?php include("../config/db.php"); ?>
<?php
$pageError = $_GET['error'] ?? '';
$sources = $conn->query("SELECT * FROM sources ORDER BY source_name ASC");
$products = $conn->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY product_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Interaction</title>
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
            <h3><i class="bi bi-chat-dots me-2"></i>Add Interaction</h3>
            <p>Capture customer details, contact direction, timeline, and sales outcomes in one clean workflow.</p>
        </div>
    </div>

    <div class="card p-4 section-card">

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger rounded-4"><?= htmlspecialchars($pageError) ?></div>
        <?php endif; ?>

        <form action="../actions/save_interaction.php" method="POST">
            <div class="form-section">
                <h5 class="form-section-title">Customer Info</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="field-label" for="customer_name">Customer Name</label>
                        <input id="customer_name" type="text" name="name" class="form-control" placeholder="Customer Name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="customer_phone">Phone Number</label>
                        <input id="customer_phone" type="text" name="phone" class="form-control" placeholder="Phone">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="customer_gender">Gender</label>
                        <select id="customer_gender" name="gender" class="form-control">
                            <option value="">Select Gender</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="customer_location">Location</label>
                        <input id="customer_location" type="text" name="location" class="form-control" placeholder="Location">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="customer_address">Address</label>
                        <input id="customer_address" type="text" name="address" class="form-control" placeholder="Address">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Source & Direction</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="source_id">Source</label>
                        <select id="source_id" name="source_id" class="form-control" <?= ($sources && $sources->num_rows === 0) ? 'disabled' : '' ?>>
                            <option value=""><?= ($sources && $sources->num_rows === 0) ? 'No sources available' : 'Select source...' ?></option>
                            <?php
                            if ($sources) {
                                while($row = $sources->fetch_assoc()){
                                    echo "<option value='{$row['source_id']}'>" . htmlspecialchars($row['source_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <?php if ($sources && $sources->num_rows === 0): ?>
                            <small class="field-help">No source data found. Add source records before saving interactions.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Direction</label>
                        <div class="toggle-group">
                            <div>
                                <input id="direction_inbound" type="radio" name="interaction_direction" value="Inbound" checked>
                                <label class="toggle-option" for="direction_inbound"><i class="bi bi-arrow-down-left-circle"></i>Inbound</label>
                            </div>
                            <div>
                                <input id="direction_outbound" type="radio" name="interaction_direction" value="Outbound">
                                <label class="toggle-option" for="direction_outbound"><i class="bi bi-arrow-up-right-circle"></i>Outbound</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Time Tracking</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="field-label" for="inbox_datetime">Inbox Time</label>
                        <input id="inbox_datetime" type="datetime-local" name="inbox_datetime" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="response_datetime">Response Time</label>
                        <input id="response_datetime" type="datetime-local" name="response_datetime" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="delivery_datetime">Delivery Time</label>
                        <input id="delivery_datetime" type="datetime-local" name="delivery_datetime" class="form-control">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Sale & Extras</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="is_sale">Sale Status</label>
                        <select name="is_sale" id="is_sale" class="form-control" onchange="toggleProductSection()">
                            <option value="1">Sale Completed</option>
                            <option value="0">No Sale</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Free Gift</label>
                        <div class="switch-row">
                            <div class="switch-copy">
                                <strong>Gift included</strong>
                                <span>Switch on if a complimentary item was given.</span>
                            </div>
                            <div>
                                <input type="hidden" name="free_gift" value="0">
                                <label class="switch-control">
                                    <input type="checkbox" name="free_gift" value="1">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="product_section" class="form-section">
                <div class="section-title">
                    <div>
                        <h5 class="form-section-title mb-0">Products</h5>
                        <p class="section-subtitle">Attach one or more products to completed sales.</p>
                    </div>
                </div>
                <div id="product_list">
                    <div class="product-row row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="field-label">Product</label>
                            <select name="product_ids[]" class="form-select">
                                <option value=""><?= ($products && $products->num_rows === 0) ? 'No stocked products available' : 'Select Product...' ?></option>
                                <?php
                                if ($products) {
                                    while($p = $products->fetch_assoc()) {
                                        echo "<option value='{$p['product_id']}'>" . htmlspecialchars($p['product_name']) . " ({$p['stock_quantity']})</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="field-label">Quantity</label>
                            <input type="number" name="quantities[]" class="form-control" placeholder="Qty">
                        </div>
                        <div class="col-md-2">
                            <label class="field-label d-block">&nbsp;</label>
                            <button type="button" class="btn btn-danger w-100" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
                <?php if ($products && $products->num_rows === 0): ?>
                    <div class="alert alert-warning rounded-4">No products with stock are available. Sales can only be recorded after stock has been added.</div>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-info mb-3" onclick="addProductRow()">
                    <i class="bi bi-plus"></i> Add Another Product
                </button>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Notes</h5>
                <label class="field-label" for="interaction_comment">Comments</label>
                <textarea id="interaction_comment" name="comment" class="form-control mb-3" placeholder="Comments / Reason for no sale"></textarea>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-lime px-4">
                    <i class="bi bi-save"></i> Save Interaction
                </button>
            </div>
        </form>
    </div>
</div>
</div>
  <script src="../bootstrap/js/jquery-3.2.1.slim.min.js"></script>
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
function toggleProductSection() {
    const isSale = document.getElementById('is_sale').value;
    const section = document.getElementById('product_section');
    section.style.display = (isSale == '1') ? 'block' : 'none';
}

function addProductRow() {
    const list = document.getElementById('product_list');
    const firstRow = document.querySelector('.product-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('input').value = '';
    newRow.querySelector('select').value = '';
    list.appendChild(newRow);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.product-row');
    if (rows.length > 1) {
        btn.closest('.product-row').remove();
    } else {
        alert("At least one product row is required for a sale.");
    }
}

// Initial check
toggleProductSection();
</script>

</body>
</html>
