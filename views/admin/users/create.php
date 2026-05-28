<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$error = '';
$success = '';
$db = new Database();
$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $hospitalId = $_POST['hospital_id'] ?? null;

    if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        if ($role !== 'hospital') {
            $hospitalId = null;
        }
        
        // Check email or phone exists
        $db->query("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $db->bind(':email', $email);
        $db->bind(':phone', $phone);
        $db->execute();
        
        if ($db->rowCount() > 0) {
            $error = "Email hoặc Số điện thoại đã tồn tại.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $approvalStatus = $role === 'hospital' ? 'approved' : null;
            $db->query("INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status) VALUES (:name, :email, :phone, :password, :role, :hospital_id, :approval_status)");
            $db->bind(':name', $fullName);
            $db->bind(':email', $email);
            $db->bind(':phone', $phone);
            $db->bind(':password', $hashedPassword);
            $db->bind(':role', $role);
            $db->bind(':hospital_id', $hospitalId);
            $db->bind(':approval_status', $approvalStatus);
            
            if ($db->execute()) {
                $success = "Thêm người dùng mới thành công!";
            } else {
                $error = "Có lỗi xảy ra, thử lại sau.";
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Thêm Người dùng mới</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Họ và Tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Vai trò <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="patient">Bệnh nhân</option>
                                <option value="hospital">Bệnh viện</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phân quyền bệnh viện</label>
                        <select name="hospital_id" class="form-select">
                            <option value="">-- Chỉ chọn khi vai trò là Bệnh viện --</option>
                            <?php foreach ($hospitals as $hospital): ?>
                                <option value="<?php echo $hospital['id']; ?>"><?php echo htmlspecialchars($hospital['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu thông tin</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
