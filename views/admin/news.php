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

$db->query("CREATE TABLE IF NOT EXISTS news_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    image_path VARCHAR(500) NOT NULL,
    link_url VARCHAR(500) NULL,
    author VARCHAR(150) NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->execute();

function newsImageSrc($path, $base_url) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}

function uploadNewsImage($fieldName, &$error) {
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

    $uploadDir = __DIR__ . '/../../uploads/news/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'news_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $uploadDir . $fileName)) {
        $error = 'Không thể upload ảnh tin tức.';
        return false;
    }

    return 'uploads/news/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query('DELETE FROM news_posts WHERE id = :id');
        $db->bind(':id', $id);
        $success = $db->execute() ? 'Đã xóa tin tức.' : 'Không thể xóa tin tức.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $publishedAt = trim($_POST['published_at'] ?? date('Y-m-d\TH:i'));
        $sortOrder = (int)($_POST['sort_order'] ?? 1);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $imagePath = trim($_POST['current_image_path'] ?? '');
        $uploadedImage = uploadNewsImage('image_file', $error);

        if ($uploadedImage) {
            $imagePath = $uploadedImage;
        }

        $publishedAtSql = str_replace('T', ' ', $publishedAt);
        if (strlen($publishedAtSql) === 16) {
            $publishedAtSql .= ':00';
        }

        if ($uploadedImage === false) {
        } elseif ($title === '' || $imagePath === '') {
            $error = 'Vui lòng nhập tiêu đề và chọn ảnh tin tức.';
        } elseif ($id > 0) {
            $db->query('UPDATE news_posts SET title = :title, excerpt = :excerpt, image_path = :image_path, link_url = :link_url, author = :author, published_at = :published_at, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
            $db->bind(':title', $title);
            $db->bind(':excerpt', $excerpt);
            $db->bind(':image_path', $imagePath);
            $db->bind(':link_url', $linkUrl);
            $db->bind(':author', $author);
            $db->bind(':published_at', $publishedAtSql);
            $db->bind(':sort_order', $sortOrder);
            $db->bind(':is_active', $isActive);
            $db->bind(':id', $id);
            $success = $db->execute() ? 'Đã cập nhật tin tức.' : 'Không thể cập nhật tin tức.';
        } else {
            $db->query('INSERT INTO news_posts (title, excerpt, image_path, link_url, author, published_at, sort_order, is_active) VALUES (:title, :excerpt, :image_path, :link_url, :author, :published_at, :sort_order, :is_active)');
            $db->bind(':title', $title);
            $db->bind(':excerpt', $excerpt);
            $db->bind(':image_path', $imagePath);
            $db->bind(':link_url', $linkUrl);
            $db->bind(':author', $author);
            $db->bind(':published_at', $publishedAtSql);
            $db->bind(':sort_order', $sortOrder);
            $db->bind(':is_active', $isActive);
            $success = $db->execute() ? 'Đã thêm tin tức.' : 'Không thể thêm tin tức.';
        }
    }
}

$db->query('SELECT * FROM news_posts ORDER BY sort_order ASC, published_at DESC, id DESC');
$newsPosts = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Tin tức</h2>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Thêm tin tức mới</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="save">
            <div class="col-md-6">
                <label class="form-label fw-bold">Tiêu đề</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tác giả</label>
                <input type="text" name="author" class="form-control" placeholder="VD: Uyển Nhi">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Ngày đăng</label>
                <input type="datetime-local" name="published_at" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Mô tả ngắn</label>
                <textarea name="excerpt" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Link khi bấm</label>
                <input type="url" name="link_url" class="form-control" placeholder="https://...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Thứ tự</label>
                <input type="number" name="sort_order" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <label class="form-check-label mb-2"><input type="checkbox" name="is_active" class="form-check-input" checked> Hiện</label>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Ảnh tin tức</label>
                <input type="file" name="image_file" class="form-control" accept="image/*" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm tin tức</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Danh sách tin tức</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Thông tin</th>
                        <th style="width: 520px;">Chỉnh sửa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsPosts as $post): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars(newsImageSrc($post['image_path'], $base_url)); ?>" class="img-thumbnail" style="width: 150px; height: 90px; object-fit: cover;"></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="small text-muted"><?php echo date('d/m/Y, H:i', strtotime($post['published_at'])); ?><?php echo $post['author'] ? ' - ' . htmlspecialchars($post['author']) : ''; ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($post['link_url'] ?: 'Không có link'); ?></div>
                                <span class="badge <?php echo $post['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $post['is_active'] ? 'Đang hiện' : 'Đang ẩn'; ?></span>
                            </td>
                            <td>
                                <form method="POST" enctype="multipart/form-data" class="row g-2">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                                    <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($post['image_path']); ?>">
                                    <div class="col-md-6"><input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($post['title']); ?>" required></div>
                                    <div class="col-md-3"><input type="text" name="author" class="form-control form-control-sm" value="<?php echo htmlspecialchars($post['author'] ?? ''); ?>" placeholder="Tác giả"></div>
                                    <div class="col-md-3"><input type="datetime-local" name="published_at" class="form-control form-control-sm" value="<?php echo date('Y-m-d\TH:i', strtotime($post['published_at'])); ?>"></div>
                                    <div class="col-md-7"><textarea name="excerpt" class="form-control form-control-sm" rows="2" placeholder="Mô tả ngắn"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea></div>
                                    <div class="col-md-5"><input type="url" name="link_url" class="form-control form-control-sm" value="<?php echo htmlspecialchars($post['link_url'] ?? ''); ?>" placeholder="https://..."></div>
                                    <div class="col-md-2"><input type="number" name="sort_order" class="form-control form-control-sm" value="<?php echo (int)$post['sort_order']; ?>" min="1"></div>
                                    <div class="col-md-7"><input type="file" name="image_file" class="form-control form-control-sm" accept="image/*"></div>
                                    <div class="col-md-3"><label class="form-check-label small"><input type="checkbox" name="is_active" class="form-check-input" <?php echo $post['is_active'] ? 'checked' : ''; ?>> Hiện</label></div>
                                    <div class="col-12"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-save me-1"></i> Lưu</button></div>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Xóa tin tức này?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($newsPosts) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có tin tức.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
