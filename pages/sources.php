<?php
include("../config/db.php");
session_start();

$pageError = '';
$pageMsg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $sourceName = trim($_POST['source_name'] ?? '');
        $sourceType = $_POST['source_type'] ?? '';

        if ($sourceName === '' || !in_array($sourceType, ['DM', 'Phone', 'Direct'], true)) {
            $pageError = "Source name and source type are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO sources (source_name, source_type) VALUES (?, ?)");
            if (!$stmt) {
                $pageError = $conn->error;
            } else {
                $stmt->bind_param("ss", $sourceName, $sourceType);
                if ($stmt->execute()) {
                    header("Location: sources.php?msg=added");
                    exit;
                }
                $pageError = $stmt->error;
            }
        }
    }

    if ($action === 'delete') {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        if ($sourceId <= 0) {
            $pageError = "Invalid source selected.";
        } else {
            $checkRes = $conn->query("SELECT COUNT(*) AS total FROM interactions WHERE source_id = {$sourceId}");
            $linkedCount = $checkRes ? (int)($checkRes->fetch_assoc()['total'] ?? 0) : 0;

            if ($linkedCount > 0) {
                $pageError = "This source cannot be deleted because it is already used by one or more interactions.";
            } else {
                $deleteStmt = $conn->prepare("DELETE FROM sources WHERE source_id = ?");
                if (!$deleteStmt) {
                    $pageError = $conn->error;
                } else {
                    $deleteStmt->bind_param("i", $sourceId);
                    if ($deleteStmt->execute()) {
                        header("Location: sources.php?msg=deleted");
                        exit;
                    }
                    $pageError = $deleteStmt->error;
                }
            }
        }
    }
}

$sources = $conn->query("
    SELECT s.source_id, s.source_name, s.source_type, COUNT(i.interaction_id) AS interaction_count
    FROM sources s
    LEFT JOIN interactions i ON i.source_id = s.source_id
    GROUP BY s.source_id, s.source_name, s.source_type
    ORDER BY s.source_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sources</title>
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
                <h2 class="mb-0">Manage Sources</h2>
                <p>Organize the channels your leads come from so reporting stays clean and actionable.</p>
            </div>
        </div>

        <?php if ($pageMsg !== ''): ?>
            <div class="alert alert-success rounded-4">
                <?= $pageMsg === 'added' ? 'Source added successfully.' : ($pageMsg === 'deleted' ? 'Source deleted successfully.' : '') ?>
            </div>
        <?php endif; ?>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger rounded-4"><?= htmlspecialchars($pageError) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card card1 section-card mb-4">
                    <div class="section-title">
                        <div>
                            <h5><i class="bi bi-plus-circle me-2"></i>Add Source</h5>
                            <p class="section-subtitle">Create a source once and reuse it across interaction records.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="field-label">Source Name</label>
                            <input type="text" name="source_name" class="form-control" placeholder="Source name" required>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Source Type</label>
                            <select name="source_type" class="form-select" required>
                                <option value="">Select source type...</option>
                                <option value="DM">DM</option>
                                <option value="Phone">Phone</option>
                                <option value="Direct">Direct</option>
                            </select>
                        </div>
                        <button class="btn btn-lime w-100" type="submit">Save Source</button>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card card1 section-card">
                    <div class="section-title">
                        <div>
                            <h5>Available Sources</h5>
                            <p class="section-subtitle">Delete only unused sources to protect reporting integrity.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Interactions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($sources && $sources->num_rows > 0): ?>
                                    <?php while ($source = $sources->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($source['source_name']) ?></td>
                                            <td><?= htmlspecialchars($source['source_type']) ?></td>
                                            <td><?= (int)$source['interaction_count'] ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Delete this source?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="source_id" value="<?= (int)$source['source_id'] ?>">
                                                    <button class="btn btn-sm btn-danger" type="submit" <?= (int)$source['interaction_count'] > 0 ? 'disabled' : '' ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-white-50">No sources found yet. Add your first source using the form on the left.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-white-50">Sources already linked to interactions cannot be deleted.</small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
