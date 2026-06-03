<?php
require_once '../../config/database.php';
include 'includes/header.php';

if (!$isHospitalAdmin || empty($currentHospitalId)) {
    echo "<div class='alert alert-danger'>Tài khoản chưa được gán bệnh viện.</div>";
    include 'includes/footer.php';
    exit();
}

$db = new Database();
$error = '';
$success = '';

try {
    $db->query("ALTER TABLE hospitals ADD COLUMN content_image_url VARCHAR(500) NULL AFTER poster_url");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN overview_file_url VARCHAR(500) NULL AFTER content_image_url");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN map_embed_url VARCHAR(1000) NULL AFTER overview_file_url");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN service_image_url VARCHAR(500) NULL AFTER map_embed_url");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN booking_advance_days INT NOT NULL DEFAULT 30 AFTER working_time");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN facility_type VARCHAR(30) NOT NULL DEFAULT 'public' AFTER facility_code");
    $db->execute();
} catch (Exception $e) {
}

$db->query("SELECT * FROM hospitals WHERE id = :id");
$db->bind(':id', $currentHospitalId);
$hospital = $db->single();

$db->query("SELECT * FROM hospital_banners WHERE hospital_id = :hospital_id ORDER BY sort_order ASC");
$db->bind(':hospital_id', $currentHospitalId);
$hospitalBanners = $db->resultSet();

function hospitalImageSrc($path, $base_url) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}

function uploadHospitalImage($fieldName, $currentHospitalId, &$error) {
    if (empty($_FILES[$fieldName]['name'])) {
        return null;
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $mimeType = mime_content_type($tmpPath);

    if (!isset($allowedTypes[$mimeType])) {
        $error = "Chỉ cho phép upload ảnh JPG, PNG, WEBP hoặc GIF.";
        return false;
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        $error = "Dung lượng ảnh không được vượt quá 5MB.";
        return false;
    }

    $uploadDir = __DIR__ . '/../../uploads/hospitals/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = $currentHospitalId . '_' . $fieldName . '_' . time() . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $error = "Không thể upload ảnh.";
        return false;
    }

    return 'uploads/hospitals/' . $fileName;
}

function uploadHospitalOverviewFile($fieldName, $currentHospitalId, &$error) {
    if (empty($_FILES[$fieldName]['name'])) {
        return null;
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $mimeType = mime_content_type($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        $error = "File nội dung bổ sung chỉ cho phép ảnh, PDF, DOC hoặc DOCX.";
        return false;
    }
    if ($_FILES[$fieldName]['size'] > 10 * 1024 * 1024) {
        $error = "File nội dung bổ sung không được vượt quá 10MB.";
        return false;
    }
    $uploadDir = __DIR__ . '/../../uploads/hospitals/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = $currentHospitalId . '_overview_file_' . time() . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $uploadDir . $fileName)) {
        $error = "Không thể upload file nội dung bổ sung.";
        return false;
    }
    return 'uploads/hospitals/' . $fileName;
}

function uploadHospitalBannerImages($fieldName, $currentHospitalId, &$error) {
    if (empty($_FILES[$fieldName]['name'][0])) {
        return null;
    }

    if (count($_FILES[$fieldName]['name']) > 5) {
        $error = "Chỉ được upload nhiều nhất 5 ảnh banner.";
        return false;
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $uploadDir = __DIR__ . '/../../uploads/hospitals/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $paths = [];
    foreach ($_FILES[$fieldName]['name'] as $index => $name) {
        if ($_FILES[$fieldName]['error'][$index] !== UPLOAD_ERR_OK) {
            $error = "Không thể upload đủ 5 ảnh banner.";
            return false;
        }

        $tmpPath = $_FILES[$fieldName]['tmp_name'][$index];
        $mimeType = mime_content_type($tmpPath);
        if (!isset($allowedTypes[$mimeType])) {
            $error = "Banner chỉ cho phép ảnh JPG, PNG, WEBP hoặc GIF.";
            return false;
        }

        if ($_FILES[$fieldName]['size'][$index] > 5 * 1024 * 1024) {
            $error = "Mỗi ảnh banner không được vượt quá 5MB.";
            return false;
        }

        $fileName = $currentHospitalId . '_banner_' . ($index + 1) . '_' . time() . '.' . $allowedTypes[$mimeType];
        $targetPath = $uploadDir . $fileName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $error = "Không thể upload ảnh banner.";
            return false;
        }
        $paths[] = 'uploads/hospitals/' . $fileName;
    }

    return $paths;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $facilityType = trim($_POST['facility_type'] ?? '');
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $logoUrl = $hospital['logo_url'] ?? '';
    $posterUrl = $hospital['poster_url'] ?? '';
    $contentImageUrl = $hospital['content_image_url'] ?? '';
    $overviewFileUrl = $hospital['overview_file_url'] ?? '';
    $serviceImageUrl = $hospital['service_image_url'] ?? '';
    $workingGroups = $_POST['working_groups'] ?? [];
    $workingParts = [];
    $invalidWorkingTime = false;
    foreach ($workingGroups as $group) {
        $days = array_values(array_filter($group['days'] ?? [], 'strlen'));
        $startTime = $group['start_time'] ?? '';
        $endTime = $group['end_time'] ?? '';
        if (!count($days) && $startTime === '' && $endTime === '') {
            continue;
        }
        if (!count($days) || $startTime === '' || $endTime === '' || $startTime >= $endTime) {
            $invalidWorkingTime = true;
            break;
        }
        $workingParts[] = implode(', ', $days) . ': ' . $startTime . ' - ' . $endTime;
    }
    $workingTime = implode('; ', $workingParts);
    $bookingAdvanceDays = max(1, min(365, (int)($_POST['booking_advance_days'] ?? 30)));
    $description = '';
    $shortDescription = trim($_POST['short_description']);
    $servicesInfo = '';
    $overview = trim($_POST['overview']);
    $mapEmbedUrl = trim($_POST['map_embed_url'] ?? '');
    if (preg_match('/src=["\']([^"\']+)["\']/', $mapEmbedUrl, $mapMatches)) {
        $mapEmbedUrl = $mapMatches[1];
    }

    $uploadedLogo = uploadHospitalImage('logo_image', $currentHospitalId, $error);
    $uploadedPoster = uploadHospitalImage('poster_image', $currentHospitalId, $error);
    $uploadedContentImage = uploadHospitalImage('content_image', $currentHospitalId, $error);
    $uploadedServiceImage = uploadHospitalImage('service_image', $currentHospitalId, $error);
    $uploadedOverviewFile = uploadHospitalOverviewFile('overview_file', $currentHospitalId, $error);
    $uploadedBanners = uploadHospitalBannerImages('banner_images', $currentHospitalId, $error);

    if ($uploadedLogo) {
        $logoUrl = $uploadedLogo;
    }
    if ($uploadedPoster) {
        $posterUrl = $uploadedPoster;
    }
    if ($uploadedContentImage) {
        $contentImageUrl = $uploadedContentImage;
    }
    if ($uploadedServiceImage) {
        $serviceImageUrl = $uploadedServiceImage;
    }
    if ($uploadedOverviewFile) {
        $overviewFileUrl = $uploadedOverviewFile;
    }

    if ($uploadedLogo === false || $uploadedPoster === false || $uploadedContentImage === false || $uploadedServiceImage === false || $uploadedOverviewFile === false || $uploadedBanners === false) {
        
    } elseif (count($hospitalBanners) === 0 && $uploadedBanners === null) {
        $error = "Vui lòng upload ít nhất 1 ảnh banner, tối đa 5 ảnh.";
    } elseif ($invalidWorkingTime) {
        $error = "Vui lòng chọn đủ thứ, giờ bắt đầu và giờ kết thúc hợp lệ cho từng nhóm.";
    } elseif (empty($name) || empty($phone) || empty($email)) {
        $error = "Vui lòng nhập tên bệnh viện, email và số điện thoại.";
    } else {
        $db->query("UPDATE hospitals SET name = :name, facility_type = :facility_type, address = :address, phone = :phone, email = :email, logo_url = :logo_url, poster_url = :poster_url, content_image_url = :content_image_url, overview_file_url = :overview_file_url, map_embed_url = :map_embed_url, service_image_url = :service_image_url, working_time = :working_time, booking_advance_days = :booking_advance_days, description = :description, short_description = :short_description, services_info = :services_info, overview = :overview WHERE id = :id");
        $db->bind(':name', $name);
        $db->bind(':facility_type', $facilityType);
        $db->bind(':address', $address);
        $db->bind(':phone', $phone);
        $db->bind(':email', $email);
        $db->bind(':logo_url', $logoUrl);
        $db->bind(':poster_url', $posterUrl);
        $db->bind(':content_image_url', $contentImageUrl);
        $db->bind(':overview_file_url', $overviewFileUrl);
        $db->bind(':map_embed_url', $mapEmbedUrl);
        $db->bind(':service_image_url', $serviceImageUrl);
        $db->bind(':working_time', $workingTime);
        $db->bind(':booking_advance_days', $bookingAdvanceDays);
        $db->bind(':description', $description);
        $db->bind(':short_description', $shortDescription);
        $db->bind(':services_info', $servicesInfo);
        $db->bind(':overview', $overview);
        $db->bind(':id', $currentHospitalId);
        if ($db->execute()) {
            if (is_array($uploadedBanners)) {
                $db->query("DELETE FROM hospital_banners WHERE hospital_id = :hospital_id");
                $db->bind(':hospital_id', $currentHospitalId);
                $db->execute();

                foreach ($uploadedBanners as $index => $path) {
                    $db->query("INSERT INTO hospital_banners (hospital_id, image_path, sort_order) VALUES (:hospital_id, :image_path, :sort_order)");
                    $db->bind(':hospital_id', $currentHospitalId);
                    $db->bind(':image_path', $path);
                    $db->bind(':sort_order', $index + 1);
                    $db->execute();
                }
            }


            $success = "Cập nhật hồ sơ bệnh viện thành công.";
        } else {
            $error = "Không thể cập nhật hồ sơ.";
        }
    }
}

$db->query("SELECT * FROM hospitals WHERE id = :id");
$db->bind(':id', $currentHospitalId);
$hospital = $db->single();

if ($error && $_SERVER["REQUEST_METHOD"] == "POST") {
    $hospital['name'] = $_POST['name'] ?? $hospital['name'];
    $hospital['address'] = $_POST['address'] ?? $hospital['address'];
    $hospital['phone'] = $_POST['phone'] ?? $hospital['phone'];
    $hospital['email'] = $_POST['email'] ?? $hospital['email'];
    $hospital['short_description'] = $_POST['short_description'] ?? $hospital['short_description'];
    $hospital['services_info'] = $_POST['services_info'] ?? $hospital['services_info'];
    $hospital['overview'] = $_POST['overview'] ?? $hospital['overview'];
    $hospital['description'] = $_POST['description'] ?? $hospital['description'];
    $hospital['map_embed_url'] = $_POST['map_embed_url'] ?? ($hospital['map_embed_url'] ?? '');
    $hospital['booking_advance_days'] = $_POST['booking_advance_days'] ?? ($hospital['booking_advance_days'] ?? 30);
    $hospital['working_time'] = $workingTime ?: ($hospital['working_time'] ?? '');
}

$db->query("SELECT * FROM hospital_banners WHERE hospital_id = :hospital_id ORDER BY sort_order ASC");
$db->bind(':hospital_id', $currentHospitalId);
$hospitalBanners = $db->resultSet();


$dayOptions = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
$currentWorkingTime = $hospital['working_time'] ?? '';
$currentWorkingGroups = [];
foreach (array_filter(array_map('trim', explode(';', $currentWorkingTime))) as $workingPart) {
    if (preg_match('/^(.+?):\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/u', $workingPart, $matches)) {
        $currentWorkingGroups[] = [
            'days' => array_map('trim', explode(',', $matches[1])),
            'start_time' => str_pad($matches[2], 5, '0', STR_PAD_LEFT),
            'end_time' => str_pad($matches[3], 5, '0', STR_PAD_LEFT),
        ];
    }
}
if (!count($currentWorkingGroups) && preg_match('/\((\d{2}:\d{2})-(\d{2}:\d{2})\)/', $currentWorkingTime, $matches)) {
    $currentWorkingGroups[] = ['days' => ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'], 'start_time' => $matches[1], 'end_time' => $matches[2]];
}
if (!count($currentWorkingGroups)) {
    $currentWorkingGroups[] = ['days' => [], 'start_time' => '', 'end_time' => ''];
}
$facilityTypeOptions = [
    'public' => 'Bệnh viện công',
    'private' => 'Bệnh viện tư',
    'clinic' => 'Phòng khám',
    'office' => 'Phòng mạch',
    'lab' => 'Xét nghiệm',
    'home' => 'Y tế tại nhà',
    'vaccination' => 'Tiêm chủng'
];
$timeOptions = [];
for ($hour = 0; $hour <= 23; $hour++) {
    foreach (['00', '30'] as $minute) {
        $timeOptions[] = sprintf('%02d:%s', $hour, $minute);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Hồ sơ Bệnh viện</h2>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên bệnh viện <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($hospital['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Loại cơ sở y tế</label>
                        <select name="facility_type" class="form-select">
                            <option value="">Chưa phân loại</option>
                            <?php foreach ($facilityTypeOptions as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo (($hospital['facility_type'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Địa chỉ</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($hospital['address'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Google Map bệnh viện</label>
                        <textarea name="map_embed_url" class="form-control" rows="3" placeholder="Dán link Google Maps Embed hoặc toàn bộ mã iframe"><?php echo htmlspecialchars($hospital['map_embed_url'] ?? ''); ?></textarea>
                        <small class="text-muted">Vào Google Maps → Chia sẻ → Nhúng bản đồ, rồi dán link src hoặc mã iframe vào đây.</small>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($hospital['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ảnh đại diện / Logo</label>
                            <?php if (!empty($hospital['logo_url'])): ?>
                                <div class="mb-2"><img src="<?php echo $base_url . '/' . htmlspecialchars($hospital['logo_url']); ?>" class="img-thumbnail" style="max-height: 90px;"></div>
                            <?php endif; ?>
                            <input type="file" name="logo_image" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ảnh banner bệnh viện <span class="text-danger">*</span></label>
                            <?php if (count($hospitalBanners) > 0): ?>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($hospitalBanners as $banner): ?>
                                        <img src="<?php echo hospitalImageSrc($banner['image_path'], $base_url); ?>" class="img-thumbnail" style="width: 70px; height: 55px; object-fit: cover;">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="banner_images[]" id="bannerImages" class="form-control" accept="image/*" multiple <?php echo count($hospitalBanners) === 0 ? 'required' : ''; ?>>
                            <small class="text-muted">Chọn tối đa 5 ảnh trong một lần upload. Nếu upload lại, ảnh cũ sẽ được thay thế.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ảnh bên trái mục Các dịch vụ</label>
                        <?php if (!empty($hospital['service_image_url'])): ?>
                            <div class="mb-2"><img src="<?php echo hospitalImageSrc($hospital['service_image_url'], $base_url); ?>" class="img-thumbnail" style="max-height: 120px;"></div>
                        <?php endif; ?>
                        <input type="file" name="service_image" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ảnh nội dung bổ sung</label>
                        <?php if (!empty($hospital['content_image_url'])): ?>
                            <div class="mb-2"><img src="<?php echo hospitalImageSrc($hospital['content_image_url'], $base_url); ?>" class="img-thumbnail" style="max-height: 120px;"></div>
                        <?php endif; ?>
                        <input type="file" name="content_image" class="form-control" accept="image/*">
                        <small class="text-muted">Ảnh này hiển thị trong khung nội dung bên phải.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Thời gian làm việc</label>
                        <div id="workingGroups" class="d-flex flex-column gap-2">
                            <?php foreach ($currentWorkingGroups as $groupIndex => $group): ?>
                                <div class="working-group border rounded-3 p-3">
                                    <div class="d-flex flex-wrap gap-3 mb-3">
                                        <?php foreach ($dayOptions as $day): ?>
                                            <label class="form-check-label">
                                                <input type="checkbox" name="working_groups[<?php echo $groupIndex; ?>][days][]" value="<?php echo $day; ?>" class="form-check-input" <?php echo in_array($day, $group['days'], true) ? 'checked' : ''; ?>>
                                                <?php echo $day; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small text-muted">Giờ bắt đầu</label>
                                            <select name="working_groups[<?php echo $groupIndex; ?>][start_time]" class="form-select">
                                                <option value="">Chọn giờ</option>
                                                <?php foreach ($timeOptions as $time): ?>
                                                    <option value="<?php echo $time; ?>" <?php echo ($group['start_time'] ?? '') === $time ? 'selected' : ''; ?>><?php echo $time; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small text-muted">Giờ kết thúc</label>
                                            <select name="working_groups[<?php echo $groupIndex; ?>][end_time]" class="form-select">
                                                <option value="">Chọn giờ</option>
                                                <?php foreach ($timeOptions as $time): ?>
                                                    <option value="<?php echo $time; ?>" <?php echo ($group['end_time'] ?? '') === $time ? 'selected' : ''; ?>><?php echo $time; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-working-group">×</button></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="addWorkingGroup" class="btn btn-outline-primary btn-sm mt-2">Thêm nhóm ngày giờ</button>
                        <small class="text-muted d-block mt-2">Chọn thứ và giờ riêng cho từng nhóm, ví dụ Thứ 2-7 một giờ, Chủ nhật một giờ khác.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cho phép đặt khám trước</label>
                        <div class="input-group">
                            <input type="number" name="booking_advance_days" class="form-control" min="1" max="365" value="<?php echo htmlspecialchars($hospital['booking_advance_days'] ?? 30); ?>">
                            <span class="input-group-text">ngày</span>
                        </div>
                        <small class="text-muted">Trang đặt khám chỉ cho chọn ngày trong thời hạn này.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả ngắn</label>
                        <textarea name="short_description" class="form-control" rows="5" placeholder="Nội dung hiển thị trong khung Mô tả bên trái"><?php echo htmlspecialchars($hospital['short_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nội dung bổ sung</label>
                        <textarea name="overview" class="form-control" rows="10" placeholder="Nhập toàn bộ nội dung muốn hiển thị bên phải trang chi tiết bệnh viện"><?php echo htmlspecialchars($hospital['overview'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">File nội dung bổ sung</label>
                        <?php if (!empty($hospital['overview_file_url'])): ?>
                            <div class="mb-2"><a href="<?php echo $base_url . '/' . htmlspecialchars($hospital['overview_file_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Xem file đã upload</a></div>
                        <?php endif; ?>
                        <input type="file" name="overview_file" class="form-control" accept="image/*,.pdf,.doc,.docx">
                        <small class="text-muted">Có thể upload ảnh, PDF, DOC hoặc DOCX. Nếu là ảnh sẽ hiển thị trực tiếp trên trang chi tiết.</small>
                    </div>


                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu thông tin</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const dayOptions = <?php echo json_encode(array_values($dayOptions), JSON_UNESCAPED_UNICODE); ?>;
const timeOptions = <?php echo json_encode(array_values($timeOptions), JSON_UNESCAPED_UNICODE); ?>;
function workingGroupHtml(index) {
    const days = dayOptions.map(day => '<label class="form-check-label"><input type="checkbox" name="working_groups[' + index + '][days][]" value="' + day + '" class="form-check-input"> ' + day + '</label>').join('');
    const times = '<option value="">Chọn giờ</option>' + timeOptions.map(time => '<option value="' + time + '">' + time + '</option>').join('');
    return '<div class="working-group border rounded-3 p-3"><div class="d-flex flex-wrap gap-3 mb-3">' + days + '</div><div class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label small text-muted">Giờ bắt đầu</label><select name="working_groups[' + index + '][start_time]" class="form-select">' + times + '</select></div><div class="col-md-5"><label class="form-label small text-muted">Giờ kết thúc</label><select name="working_groups[' + index + '][end_time]" class="form-select">' + times + '</select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-working-group">×</button></div></div></div>';
}

document.getElementById('addWorkingGroup')?.addEventListener('click', function () {
    const wrapper = document.getElementById('workingGroups');
    wrapper.insertAdjacentHTML('beforeend', workingGroupHtml(wrapper.querySelectorAll('.working-group').length));
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-working-group')) {
        const groups = document.querySelectorAll('.working-group');
        if (groups.length > 1) {
            event.target.closest('.working-group').remove();
        }
    }
});

const bannerImages = document.getElementById('bannerImages');
if (bannerImages) {
    bannerImages.addEventListener('change', function () {
        if (this.files.length > 5) {
            alert('Chỉ được chọn nhiều nhất 5 ảnh banner.');
            this.value = '';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
