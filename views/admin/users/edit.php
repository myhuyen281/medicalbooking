<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];
$db = new Database();
$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();
$error = '';
$success = '';

$db->query("SELECT * FROM users WHERE id = :id");
$db->bind(':id', $id);
$user = $db->single();

if (!$user) {
    echo "<h3>Không tìm thấy người dùng!</h3>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $hospitalId = $role === 'hospital' ? ($_POST['hospital_id'] ?? null) : null;
    $address = trim($_POST['address']);
    
    if (empty($fullName) || empty($email) || empty($phone)) {
        $error = "Vui lòng nhập các trường bắt buộc.";
    } else {
        // Nếu có nhập pass mới thì update pass, không thì giữ nguyên
        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $db->query("UPDATE users SET full_name = :name, email = :email, phone = :phone, role = :role, hospital_id = :hospital_id, address = :address, password = :password WHERE id = :id");
            $db->bind(':password', $hashedPassword);
        } else {
            $db->query("UPDATE users SET full_name = :name, email = :email, phone = :phone, role = :role, hospital_id = :hospital_id, address = :address WHERE id = :id");
        }
        
        $db->bind(':name', $fullName);
        $db->bind(':email', $email);
        $db->bind(':phone', $phone);
        $db->bind(':role', $role);
        $db->bind(':hospital_id', $hospitalId);
        $db->bind(':address', $address);
        $db->bind(':id', $id);
        
        if ($db->execute()) {
            $success = "Cập nhật thông tin thành công!";
            // Update temporary view variables
            $user['full_name'] = $fullName;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['role'] = $role;
            $user['hospital_id'] = $hospitalId;
            $user['address'] = $address;
        } else {
            $error = "Có lỗi xảy ra, thử lại sau.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sửa thông tin Người dùng</h2>
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
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Vai trò <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>Bệnh nhân</option>
                                <option value="hospital" <?php echo $user['role'] == 'hospital' ? 'selected' : ''; ?>>Bệnh viện</option>
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phân quyền bệnh viện</label>
                        <select name="hospital_id" class="form-select">
                            <option value="">-- Chỉ chọn khi vai trò là Bệnh viện --</option>
                            <?php foreach ($hospitals as $hospital): ?>
                                <option value="<?php echo $hospital['id']; ?>" <?php echo ($user['hospital_id'] ?? '') == $hospital['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($hospital['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Địa chỉ</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mật khẩu mới (Để trống nếu không đổi)</label>
                        <input type="password" name="password" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil-square me-1"></i> Cập nhật</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
