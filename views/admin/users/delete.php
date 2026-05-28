<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once '../../../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Ngăn admin tự xóa chính mình
    if ($id != $_SESSION['user_id']) {
        $db = new Database();
        
        // Vì CSDL đã set ON DELETE CASCADE ở các bảng appointments, doctors, reviews...
        // Nên chỉ cần xóa từ bảng users, các dữ liệu liên quan sẽ tự động bị xóa.
        $db->query("DELETE FROM users WHERE id = :id");
        $db->bind(':id', $id);
        $db->execute();
    }
}

header("Location: index.php");
exit();
?>
