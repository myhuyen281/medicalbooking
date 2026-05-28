<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    // Tạm thời chưa xử lý upload file, lưu chuỗi rỗng cho image
    $image = ''; 

    if (empty($name)) {
        $error = "Vui lòng nhập tên chuyên khoa.";
    } else {
        $db = new Database();
        $db->query("INSERT INTO specialties (name, description, image) VALUES (:name, :description, :image)");
        $db->bind(':name', $name);
        $db->bind(':description', $description);
        $db->bind(':image', $image);
        
        if ($db->execute()) {
            $success = "Thêm chuyên khoa thành công!";
            // Clear form
            $_POST = array();
        } else {
            $error = "Có lỗi xảy ra khi thêm chuyên khoa.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Thêm Chuyên khoa mới</h2>
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
                        <input type="text" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu thông tin</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
