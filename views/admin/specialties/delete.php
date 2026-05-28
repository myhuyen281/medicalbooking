<?php
session_start();
// Kiểm tra quyền
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../index.php");
    exit();
}

require_once '../../../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    
    // Trong CSDL, cột `specialty_id` trong bảng `doctors` đang set `ON DELETE SET NULL`.
    // Do đó khi xóa chuyên khoa, các bác sĩ thuộc chuyên khoa đó sẽ chỉ bị mất liên kết (specialty_id chuyển thành NULL) 
    // chứ không bị xóa tài khoản.
    
    $db->query("DELETE FROM specialties WHERE id = :id");
    $db->bind(':id', $id);
    $db->execute();
}

header("Location: index.php");
exit();
?>
