<?php
require_once '../../config/database.php';
include 'includes/header.php';

if (!$isSystemAdmin) {
    echo "<div class='alert alert-danger'>Bạn không có quyền truy cập.</div>";
    include 'includes/footer.php';
    exit();
}

$db = new Database();
$error = '';
$success = '';

$db->query("CREATE TABLE IF NOT EXISTS homepage_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    link_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$db->execute();

function homepageBannerSrc($path, $base_url) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}

function uploadHomepageBanner($fieldName, &$error) {
    if (empty($_FILES[$fieldName]['name'])) {
        return null;
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $mimeType = mime_content_type($tmpPath);

    if (!isset($allowedTypes[$mimeType])) {
        $error = 'Chỉ cho phép upload ảnh JPG, PNG, WEBP hoặc GIF.';
        return false;
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        $error = 'Dung lượng ảnh không được vượt quá 5MB.';
        return false;
    }

    $uploadDir = __DIR__ . '/../../uploads/homepage_banners/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'homepage_banner_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $error = 'Không thể upload ảnh banner.';
        return false;
    }

    return 'uploads/homepage_banners/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query('DELETE FROM homepage_banners WHERE id = :id');
        $db->bind(':id', $id);
        $success = $db->execute() ? 'Đã xóa banner.' : 'Không thể xóa banner.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 1);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $imagePath = trim($_POST['current_image_path'] ?? '');
        $uploadedImage = uploadHomepageBanner('image_file', $error);

        if ($uploadedImage) {
            $imagePath = $uploadedImage;
        }

        if ($uploadedImage === false) {
        } elseif ($title === '' || $imagePath === '') {
            $error = 'Vui lòng nhập tiêu đề và chọn ảnh banner.';
        } elseif ($id > 0) {
            $db->query('UPDATE homepage_banners SET title = :title, image_path = :image_path, link_url = :link_url, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
            $db->bind(':title', $title);
            $db->bind(':image_path', $imagePath);
            $db->bind(':link_url', $linkUrl);
            $db->bind(':sort_order', $sortOrder);
            $db->bind(':is_active', $isActive);
            $db->bind(':id', $id);
            $success = $db->execute() ? 'Đã cập nhật banner.' : 'Không thể cập nhật banner.';
        } else {
            $db->query('INSERT INTO homepage_banners (title, image_path, link_url, sort_order, is_active) VALUES (:title, :image_path, :link_url, :sort_order, :is_active)');
            $db->bind(':title', $title);
            $db->bind(':image_path', $imagePath);
            $db->bind(':link_url', $linkUrl);
            $db->bind(':sort_order', $sortOrder);
            $db->bind(':is_active', $isActive);
            $success = $db->execute() ? 'Đã thêm banner.' : 'Không thể thêm banner.';
        }
    }
}

$db->query('SELECT * FROM homepage_banners ORDER BY sort_order ASC, id ASC');
$banners = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Banner Trang chủ</h2>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Thêm banner mới</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="save">
            <div class="col-md-3">
                <label class="form-label fw-bold">Tiêu đề</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Link khi bấm</label>
                <input type="url" name="link_url" class="form-control" placeholder="https://...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Thứ tự</label>
                <input type="number" name="sort_order" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Ảnh banner</label>
                <input type="file" name="image_file" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <label class="form-check-label mb-2"><input type="checkbox" name="is_active" class="form-check-input" checked> Hiện</label>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm banner</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Danh sách banner</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Thông tin</th>
                        <th style="width: 420px;">Chỉnh sửa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars(homepageBannerSrc($banner['image_path'], $base_url)); ?>" class="img-thumbnail" style="width: 160px; height: 70px; object-fit: cover;"></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($banner['title']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($banner['link_url'] ?: 'Không có link'); ?></div>
                                <span class="badge <?php echo $banner['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $banner['is_active'] ? 'Đang hiện' : 'Đang ẩn'; ?></span>
                            </td>
                            <td>
                                <form method="POST" enctype="multipart/form-data" class="row g-2">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                    <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($banner['image_path']); ?>">
                                    <div class="col-md-6"><input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($banner['title']); ?>" required></div>
                                    <div class="col-md-6"><input type="url" name="link_url" class="form-control form-control-sm" value="<?php echo htmlspecialchars($banner['link_url'] ?? ''); ?>" placeholder="https://..."></div>
                                    <div class="col-md-3"><input type="number" name="sort_order" class="form-control form-control-sm" value="<?php echo (int)$banner['sort_order']; ?>" min="1"></div>
                                    <div class="col-md-6"><input type="file" name="image_file" class="form-control form-control-sm" accept="image/*"></div>
                                    <div class="col-md-3"><label class="form-check-label small"><input type="checkbox" name="is_active" class="form-check-input" <?php echo $banner['is_active'] ? 'checked' : ''; ?>> Hiện</label></div>
                                    <div class="col-12"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-save me-1"></i> Lưu</button></div>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Xóa banner này?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$banner['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($banners) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có banner.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
