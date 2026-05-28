<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once '../../../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    
    // Lưu ý: Chỉ xóa hồ sơ Doctor (trong bảng doctors). 
    // Tài khoản gốc (bảng users) vẫn sẽ giữ nguyên vai trò 'doctor' nhưng mất hồ sơ hành nghề.
    $db->query("DELETE FROM doctors WHERE id = :id");
    $db->bind(':id', $id);
    $db->execute();
}

header("Location: index.php");
exit();
?>
