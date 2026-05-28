<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$db = new Database();
$db->query("UPDATE doctors SET approval_status = 'approved' WHERE id = :id");
$db->bind(':id', $_GET['id']);
$db->execute();

header('Location: index.php');
exit();
?>
