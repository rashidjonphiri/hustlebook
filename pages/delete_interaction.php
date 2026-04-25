<?php
include("../config/db.php");

if(isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $moveComment = "Sale from Interaction #" . $id;
    $moveCommentSafe = hb_escape($conn, $moveComment);

    $oldMoves = $conn->query("SELECT product_id, quantity FROM stock_movement WHERE comments = '$moveCommentSafe'");
    if ($oldMoves) {
        while ($move = $oldMoves->fetch_assoc()) {
            $restoreStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
            if ($restoreStmt) {
                $restoreStmt->bind_param("ii", $move['quantity'], $move['product_id']);
                $restoreStmt->execute();
            }
        }
    }

    $conn->query("DELETE FROM stock_movement WHERE comments = '$moveCommentSafe'");

    // Delete the interaction
    $query = "DELETE FROM interactions WHERE interaction_id = $id";
    $result = $conn->query($query);

    if($result){
        header("Location: sales_history.php?msg=deleted");
        exit;
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}
?>
