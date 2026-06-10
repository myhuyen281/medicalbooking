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
        $targetChoice = $_POST['target_choice'] ?? 'link';
        $linkUrl = trim($_POST['link_url'] ?? '');
        if (strpos($targetChoice, 'hospital:') === 0) {
            $hospitalId = (int)substr($targetChoice, 9);
            if ($hospitalId > 0) {
                $linkUrl = $base_url . '/facility_detail.php?id=' . $hospitalId;
            }
        }
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

$db->query("SELECT id, name FROM hospitals ORDER BY CASE WHEN (logo_url IS NOT NULL AND logo_url <> '') OR (poster_url IS NOT NULL AND poster_url <> '') THEN 0 ELSE 1 END, id DESC");
$hospitalRows = $db->resultSet();
$hospitals = [];
$seenHospitalNames = [];
foreach ($hospitalRows as $hospital) {
    $normalizedName = mb_strtolower($hospital['name'], 'UTF-8');
    $normalizedName = str_replace(['bệnh viện', 'thành phố', 'tp.', 'tp', 'cần thơ'], '', $normalizedName);
    $normalizedName = preg_replace('/\s+/', ' ', trim($normalizedName));
    if (isset($seenHospitalNames[$normalizedName])) {
        continue;
    }
    $seenHospitalNames[$normalizedName] = true;
    $hospitals[] = $hospital;
}

$db->query('SELECT * FROM homepage_banners ORDER BY sort_order ASC, id ASC');
$banners = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Banner Trang chủ</h2>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<style>
.banner-card,
.banner-card .card-body {
    overflow: visible;
}
.banner-target-list {
    background: #ffffff;
    max-height: 320px !important;
    overflow-y: auto !important;
}
</style>

<div class="card shadow-sm border-0 mb-4 banner-card">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Thêm banner mới</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="action" value="save">
            <div class="col-md-3">
                <label class="form-label fw-bold">Tiêu đề</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-md-3 position-relative banner-target-wrap">
                <label class="form-label fw-bold">Liên kết</label>
                <input type="text" class="form-control banner-target-input" placeholder="Tìm hoặc chọn bệnh viện" value="Link tùy chỉnh" autocomplete="off">
                <input type="hidden" name="target_choice" class="banner-target-choice" value="link">
                <div class="list-group position-absolute start-0 end-0 mx-2 mt-1 shadow-sm d-none banner-target-list" style="z-index: 3000;">
                    <button type="button" class="list-group-item list-group-item-action" data-value="link">Link tùy chỉnh</button>
                    <?php foreach ($hospitals as $hospital): ?>
                        <button type="button" class="list-group-item list-group-item-action" data-value="hospital:<?php echo (int)$hospital['id']; ?>"><?php echo htmlspecialchars($hospital['name']); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-3 banner-link-field">
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
                        <?php
                            $bannerLinkUrl = $banner['link_url'] ?? '';
                            $bannerHospitalId = 0;
                            if (preg_match('/facility_detail\.php\?id=(\d+)/', $bannerLinkUrl, $matches)) {
                                $bannerHospitalId = (int)$matches[1];
                            }
                            $bannerTargetChoice = $bannerHospitalId > 0 ? 'hospital:' . $bannerHospitalId : 'link';
                        ?>
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
                                    <div class="col-md-6 position-relative banner-target-wrap">
                                        <?php
                                            $bannerTargetLabel = 'Link tùy chỉnh';
                                            foreach ($hospitals as $hospital) {
                                                if ($bannerHospitalId === (int)$hospital['id']) {
                                                    $bannerTargetLabel = $hospital['name'];
                                                    break;
                                                }
                                            }
                                        ?>
                                        <input type="text" class="form-control form-control-sm banner-target-input" placeholder="Tìm hoặc chọn bệnh viện" value="<?php echo htmlspecialchars($bannerTargetLabel); ?>" autocomplete="off">
                                        <input type="hidden" name="target_choice" class="banner-target-choice" value="<?php echo htmlspecialchars($bannerTargetChoice); ?>">
                                        <div class="list-group position-absolute start-0 end-0 mx-2 mt-1 shadow-sm d-none banner-target-list" style="z-index: 3000;">
                                            <button type="button" class="list-group-item list-group-item-action" data-value="link">Link tùy chỉnh</button>
                                            <?php foreach ($hospitals as $hospital): ?>
                                                <button type="button" class="list-group-item list-group-item-action" data-value="hospital:<?php echo (int)$hospital['id']; ?>"><?php echo htmlspecialchars($hospital['name']); ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 banner-link-field <?php echo $bannerHospitalId > 0 ? 'd-none' : ''; ?>"><input type="url" name="link_url" class="form-control form-control-sm" value="<?php echo htmlspecialchars($bannerHospitalId > 0 ? '' : $bannerLinkUrl); ?>" placeholder="https://..."></div>
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

<script>
document.querySelectorAll('form').forEach(function (form) {
    const targetInput = form.querySelector('.banner-target-input');
    const targetChoice = form.querySelector('.banner-target-choice');
    const targetList = form.querySelector('.banner-target-list');
    const linkField = form.querySelector('.banner-link-field');
    if (!targetInput || !targetChoice || !targetList || !linkField) return;
    const items = Array.from(targetList.querySelectorAll('button'));
    function updateBannerTargetFields() {
        linkField.classList.toggle('d-none', targetChoice.value !== 'link');
    }
    function filterTargetList() {
        let keyword = targetInput.value.trim().toLowerCase();
        if (targetChoice.value === 'link' && keyword === 'link tùy chỉnh') {
            keyword = '';
        }
        items.forEach(function (item) {
            item.classList.toggle('d-none', item.dataset.value !== 'link' && !item.textContent.toLowerCase().includes(keyword));
        });
        targetList.classList.remove('d-none');
    }
    targetInput.addEventListener('focus', filterTargetList);
    targetInput.addEventListener('wheel', function (event) {
        filterTargetList();
        targetList.scrollTop += event.deltaY;
        event.preventDefault();
    });
    targetInput.addEventListener('input', function () {
        targetChoice.value = 'link';
        updateBannerTargetFields();
        filterTargetList();
    });
    items.forEach(function (item) {
        item.addEventListener('click', function () {
            targetInput.value = item.textContent.trim();
            targetChoice.value = item.dataset.value;
            targetList.classList.add('d-none');
            updateBannerTargetFields();
        });
    });
    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            targetList.classList.add('d-none');
        }
    });
    updateBannerTargetFields();
});
</script>

<?php include 'includes/footer.php'; ?>
