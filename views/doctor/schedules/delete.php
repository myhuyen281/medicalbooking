<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../../../index.php");
    exit();
}

require_once '../../../config/database.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    
    // Lấy doctor_id
    $db->query("SELECT id FROM doctors WHERE user_id = :uid AND approval_status = 'approved'");
    $db->bind(':uid', $_SESSION['user_id']);
    $doctor = $db->single();

    if ($doctor) {
        $doctorId = $doctor['id'];

        // Cần đảm bảo lịch chỉ bị xóa nếu status = 'available' và đúng của người này quản lý
        $db->query("DELETE FROM schedules WHERE id = :id AND doctor_id = :did AND status = 'available'");
        $db->bind(':id', $id);
        $db->bind(':did', $doctorId);
        $db->execute();
    }
}

header("Location: index.php");
exit();
?>
