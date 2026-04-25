<?php 
include("../config/db.php"); 
$id = (int)($_GET['id'] ?? 0);
$data = $conn->query("
    SELECT i.*, c.* 
    FROM interactions i 
    JOIN customers c ON i.customer_id = c.customer_id 
    WHERE i.interaction_id = $id
")->fetch_assoc();

if (!$data) {
    die("Interaction not found or no longer exists.");
}

// Format dates for input fields (Y-m-d\TH:i)
$inbox = !empty($data['inbox_datetime']) ? date('Y-m-d\TH:i', strtotime($data['inbox_datetime'])) : '';
$response = !empty($data['response_datetime']) ? date('Y-m-d\TH:i', strtotime($data['response_datetime'])) : '';
$delivery = !empty($data['delivery_datetime']) ? date('Y-m-d\TH:i', strtotime($data['delivery_datetime'])) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Interaction</title>
    <?php session_start(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include("../includes/sidebar.php"); ?>
<div class="main-content">
<div class="container page-shell">
    <div class="card page-header">
        <div>
            <h3><i class="bi bi-pencil-square me-2"></i>Edit Interaction</h3>
            <p>Refine the customer record, update outreach direction, and keep the sales timeline accurate.</p>
        </div>
    </div>

    <div class="card p-4 section-card">

        <form action="../actions/update_interaction.php" method="POST">
            <input type="hidden" name="interaction_id" value="<?= $data['interaction_id'] ?>">
            <input type="hidden" name="customer_id" value="<?= $data['customer_id'] ?>">

            <div class="form-section">
                <h5 class="form-section-title">Customer Info</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="field-label" for="edit_customer_name">Customer Name</label>
                        <input id="edit_customer_name" type="text" name="name" class="form-control" value="<?= htmlspecialchars($data['customer_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_customer_phone">Phone Number</label>
                        <input id="edit_customer_phone" type="text" name="phone" class="form-control" value="<?= htmlspecialchars($data['phone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_customer_gender">Gender</label>
                        <select id="edit_customer_gender" name="gender" class="form-control">
                            <option value="Male" <?= $data['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $data['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_customer_location">Location</label>
                        <input id="edit_customer_location" type="text" name="location" class="form-control" value="<?= htmlspecialchars($data['location']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_customer_address">Address</label>
                        <input id="edit_customer_address" type="text" name="address" class="form-control" value="<?= htmlspecialchars($data['address']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Source & Direction</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="edit_source_id">Source</label>
                        <select id="edit_source_id" name="source_id" class="form-control">
                            <?php
                            $sources = $conn->query("SELECT * FROM sources");
                            while($s = $sources->fetch_assoc()){
                                $sel = $s['source_id'] == $data['source_id'] ? 'selected' : '';
                                echo "<option value='{$s['source_id']}' $sel>" . htmlspecialchars($s['source_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Direction</label>
                        <div class="toggle-group">
                            <div>
                                <input id="edit_direction_inbound" type="radio" name="interaction_direction" value="Inbound" <?= $data['interaction_direction'] === 'Inbound' ? 'checked' : '' ?>>
                                <label class="toggle-option" for="edit_direction_inbound"><i class="bi bi-arrow-down-left-circle"></i>Inbound</label>
                            </div>
                            <div>
                                <input id="edit_direction_outbound" type="radio" name="interaction_direction" value="Outbound" <?= $data['interaction_direction'] === 'Outbound' ? 'checked' : '' ?>>
                                <label class="toggle-option" for="edit_direction_outbound"><i class="bi bi-arrow-up-right-circle"></i>Outbound</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Time Tracking</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="field-label" for="edit_inbox_datetime">Inbox Time</label>
                        <input id="edit_inbox_datetime" type="datetime-local" name="inbox_datetime" class="form-control" value="<?= $inbox ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_response_datetime">Response Time</label>
                        <input id="edit_response_datetime" type="datetime-local" name="response_datetime" class="form-control" value="<?= $response ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="edit_delivery_datetime">Delivery Time</label>
                        <input id="edit_delivery_datetime" type="datetime-local" name="delivery_datetime" class="form-control" value="<?= $delivery ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Sale & Extras</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="is_sale">Sale Status</label>
                        <select name="is_sale" id="is_sale" class="form-control" onchange="toggleProductSection()">
                            <option value="1" <?= $data['is_sale'] == 1 ? 'selected' : '' ?>>Sale Completed</option>
                            <option value="0" <?= $data['is_sale'] == 0 ? 'selected' : '' ?>>No Sale</option>
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
                                    <input type="checkbox" name="free_gift" value="1" <?= (int)$data['free_gift'] === 1 ? 'checked' : '' ?>>
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
                        <p class="section-subtitle">Adjust the products tied to this interaction without leaving the record.</p>
                    </div>
                </div>
                <div id="product_list">
                    <?php
                    $move_comment = "Sale from Interaction #" . $id;
                    $moveCommentSafe = hb_escape($conn, $move_comment);
                    $existingProds = $conn->query("SELECT * FROM stock_movement WHERE comments = '$moveCommentSafe'");
                    
                    if ($existingProds && $existingProds->num_rows > 0):
                        while($ep = $existingProds->fetch_assoc()): ?>
                            <div class="product-row row g-3 mb-3">
                                <div class="col-md-7">
                                    <label class="field-label">Product</label>
                                    <select name="product_ids[]" class="form-select">
                                        <?php
                                        $prods = $conn->query("SELECT * FROM products ORDER BY product_name ASC");
                                        while($p = $prods->fetch_assoc()) {
                                            $sel = ($p['product_id'] == $ep['product_id']) ? 'selected' : '';
                                            echo "<option value='{$p['product_id']}' $sel>" . htmlspecialchars($p['product_name']) . " (Stock: {$p['stock_quantity']})</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Quantity</label>
                                    <input type="number" name="quantities[]" class="form-control" value="<?= $ep['quantity'] ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label d-block">&nbsp;</label>
                                    <button type="button" class="btn btn-danger w-100" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endwhile;
                    else: ?>
                        <!-- Fallback row if no products were found -->
                        <div class="product-row row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="field-label">Product</label>
                                <select name="product_ids[]" class="form-select">
                                    <option value="">Select Product...</option>
                                    <?php
                                    $prods = $conn->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY product_name ASC");
                                    while($p = $prods->fetch_assoc()) {
                                        echo "<option value='{$p['product_id']}'>" . htmlspecialchars($p['product_name']) . " ({$p['stock_quantity']})</option>";
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
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-info mb-3" onclick="addProductRow()">
                    <i class="bi bi-plus"></i> Add Another Product
                </button>
            </div>

            <div class="form-section">
                <h5 class="form-section-title">Notes</h5>
                <label class="field-label" for="edit_interaction_comment">Comments</label>
                <textarea id="edit_interaction_comment" name="comment" class="form-control mb-3" rows="3"><?= htmlspecialchars((string)$data['comment']) ?></textarea>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-lime px-4"><i class="bi bi-check-lg"></i> Update Interaction</button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
function toggleProductSection() {
    const isSale = document.getElementById('is_sale').value;
    const section = document.getElementById('product_section');
    section.style.display = (isSale == '1') ? 'block' : 'none';
}

function addProductRow() {
    const list = document.getElementById('product_list');
    const rows = document.querySelectorAll('.product-row');
    const newRow = rows[0].cloneNode(true);
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
toggleProductSection();
</script>
</body>
</html>
