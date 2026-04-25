<?php include("../config/db.php"); ?>
<?php
$pageMsg = $_GET['msg'] ?? '';
$pageError = $_GET['error'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterGender = $_GET['gender'] ?? '';
$filterSource = $_GET['source'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <title>Sales History</title>
<?php session_start(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Viridian Greenish-Gray Dark Mode Palette */
        [data-bs-theme="dark"] body, 
        [data-bs-theme="dark"] .main-content {
            background-color: #121717 !important;
            color: #e8eaed !important;
        }
        [data-bs-theme="dark"] .card, 
        [data-bs-theme="dark"] .section-card,
        [data-bs-theme="dark"] .page-header {
            background-color: #1b2222 !important;
            border-color: #2d3333 !important;
            box-shadow: none !important;
        }
        [data-bs-theme="dark"] .table {
            color: #e8eaed !important;
            border-color: #2d3333 !important;
        }
        [data-bs-theme="dark"] .form-control, 
        [data-bs-theme="dark"] .form-select {
            background-color: #232b2b !important;
            border-color: #343d3d !important;
            color: #e8eaed !important;
        }
    </style>
</head>
<body>
<?php include("../includes/sidebar.php"); ?>
<div class="main-content">
<div class="container page-shell">

    <div class="card page-header">
        <div>
            <h3><i class="bi bi-bar-chart me-2"></i>Sales History</h3>
            <p>Review every interaction, filter records quickly, and jump straight into edits when details change.</p>
        </div>
    </div>

    <div class="card section-card">

        <?php if ($pageMsg !== ''): ?>
            <div class="alert alert-success rounded-4">
                <?= $pageMsg === 'saved' ? 'Interaction saved successfully.' : ($pageMsg === 'updated' ? 'Interaction updated successfully.' : ($pageMsg === 'deleted' ? 'Interaction deleted successfully.' : '')) ?>
            </div>
        <?php endif; ?>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger rounded-4"><?= htmlspecialchars($pageError) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="section-title">
            <div>
                <h5>Filters</h5>
                <p class="section-subtitle">Narrow the table by date, location, gender, or source.</p>
            </div>
        </div>
        <form method="GET" class="row g-3 mt-2">
            <div class="col-md-3">
                <label class="field-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>

            <div class="col-md-3">
                <label class="field-label">Location</label>
                <input type="text" name="location" class="form-control" placeholder="Location" value="<?= htmlspecialchars($filterLocation) ?>">
            </div>

            <div class="col-md-2">
                <label class="field-label">Gender</label>
                <select name="gender" class="form-control">
                    <option value="">Gender</option>
                    <option <?= $filterGender === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option <?= $filterGender === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="field-label">Source</label>
                <select name="source" class="form-control">
                    <option value="">Source</option>
                    <?php
                    $sources = $conn->query("SELECT * FROM sources");
                    while($s = $sources->fetch_assoc()){
                        $selected = ((string)$filterSource === (string)$s['source_id']) ? 'selected' : '';
                        echo "<option value='{$s['source_id']}' {$selected}>" . htmlspecialchars($s['source_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-success w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card section-card">
        <div class="section-title">
            <div>
                <h5>Interaction Records</h5>
                <p class="section-subtitle">Every saved sale and non-sale appears here with actions for quick maintenance.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Source</th>
                        <th>Products Sold</th>
                        <th>Inbox</th>
                        <th>Response</th>
                        <th>Delivery</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

<?php

$query = "
SELECT i.*, c.*, s.source_name
FROM interactions i
JOIN customers c ON i.customer_id = c.customer_id
JOIN sources s ON i.source_id = s.source_id
WHERE 1
";

// Apply filters
if(!empty($_GET['date'])){
    $date = hb_escape($conn, $_GET['date']);
    $query .= " AND DATE(i.inbox_datetime) = '{$date}'";
}
if(!empty($_GET['location'])){
    $location = hb_escape($conn, $_GET['location']);
    $query .= " AND c.location LIKE '%{$location}%'";
}
if(!empty($_GET['gender'])){
    $gender = hb_escape($conn, $_GET['gender']);
    $query .= " AND c.gender = '{$gender}'";
}
if(!empty($_GET['source'])){
    $query .= " AND s.source_id = " . (int)$_GET['source'];
}

$query .= " ORDER BY i.inbox_datetime DESC";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
while($row = $result->fetch_assoc()){
    $status = $row['is_sale'] ? "<span class='sale'>Sale</span>" : "<span class='no-sale'>No Sale</span>";

    // Fetch products for this specific interaction from stock movements
    $prodList = "";
    if($row['is_sale']) {
        $move_comment = "Sale from Interaction #" . $row['interaction_id'];
        $moveCommentSafe = hb_escape($conn, $move_comment);
        $pRes = $conn->query("SELECT p.product_name, sm.quantity FROM stock_movement sm JOIN products p ON sm.product_id = p.product_id WHERE sm.comments = '$moveCommentSafe'");
        if ($pRes) {
            while($pRow = $pRes->fetch_assoc()) {
                $prodList .= "<div>" . htmlspecialchars($pRow['product_name']) . " (x" . (int)$pRow['quantity'] . ")</div>";
            }
        }
    }

    echo "
    <tr>
        <td>" . htmlspecialchars($row['customer_name']) . "</td>
        <td>" . htmlspecialchars($row['source_name']) . "</td>
        <td>" . (!empty($prodList) ? $prodList : "-") . "</td>
        <td>" . htmlspecialchars((string)$row['inbox_datetime']) . "</td>
        <td>" . htmlspecialchars((string)$row['response_datetime']) . "</td>
        <td>" . htmlspecialchars((string)$row['delivery_datetime']) . "</td>
        <td>" . (int)$row['sale_quantity'] . "</td>
        <td>$status</td>
        <td>" . htmlspecialchars((string)$row['comment']) . "</td>
        <td>
            <a href='edit_interaction.php?id={$row['interaction_id']}' class='btn btn-sm btn-outline-light'>
                <i class='bi bi-pencil'></i>
            </a>
            <a href='delete_interaction.php?id={$row['interaction_id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this record?\")'>
                <i class='bi bi-trash'></i>
            </a>
        </td>
    </tr>
    ";
}
} else {
    echo "<tr><td colspan='10' class='text-white-50'>No interaction records found for the selected filters.</td></tr>";
}
?>

                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

<script>
    // Global listener to fix the theme toggle button
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#themeToggle') || e.target.closest('.theme-toggle') || e.target.closest('#bd-theme');
        if (btn) {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            window.dispatchEvent(new Event('themeChanged'));
        }
    });
</script>
</body>
</html>
