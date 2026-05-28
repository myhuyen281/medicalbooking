<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];
$db = new Database();
$error = '';
$success = '';

// Lấy thông tin chuyên khoa
$db->query("SELECT * FROM specialties WHERE id = :id");
$db->bind(':id', $id);
$specialty = $db->single();

if (!$specialty) {
    echo "<div class='container mt-5'><h3>Không tìm thấy chuyên khoa!</h3><a href='index.php'>Quay lại</a></div>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if (empty($name)) {
        $error = "Vui lòng nhập tên chuyên khoa.";
    } else {
        $db->query("UPDATE specialties SET name = :name, description = :description WHERE id = :id");
        $db->bind(':name', $name);
        $db->bind(':description', $description);
        $db->bind(':id', $id);
        
        if ($db->execute()) {
            $success = "Cập nhật chuyên khoa thành công!";
            // Cập nhật lại dữ liệu hiển thị
            $specialty['name'] = $name;
            $specialty['description'] = $description;
        } else {
            $error = "Có lỗi xảy ra khi cập nhật chuyên khoa.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sửa thông tin Chuyên khoa</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên chuyên khoa <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($specialty['name']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($specialty['description']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil-square me-1"></i> Cập nhật thông tin</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
