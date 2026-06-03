<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
try {
    $db->query("CREATE TABLE IF NOT EXISTS hospital_booking_forms (id INT AUTO_INCREMENT PRIMARY KEY, hospital_id INT NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) NULL, target VARCHAR(30) NOT NULL DEFAULT 'specialty', sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (hospital_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
} catch (Exception $e) {
}
$facilityId = $_GET['id'] ?? null;
$facilityName = $_GET['name'] ?? '';

if ($facilityId) {
    $db->query("SELECT * FROM hospitals WHERE id = :id");
    $db->bind(':id', $facilityId);
} else {
    $db->query("SELECT h.*
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE h.name = :name OR h.name LIKE :like_name
                ORDER BY CASE WHEN u.id IS NOT NULL THEN 0 ELSE 1 END, h.id DESC
                LIMIT 1");
    $db->bind(':name', $facilityName);
    $db->bind(':like_name', '%' . $facilityName . '%');
}
$facility = $db->single();

if (!$facility) {
    header('Location: facilities.php');
    exit;
}

function facilityImageSrc($path, $base_url) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}

$logo = !empty($facility['logo_url']) ? $facility['logo_url'] : '';
$poster = !empty($facility['poster_url']) ? $facility['poster_url'] : '';
$banners = [];
$bookingForms = [];
if (!empty($facility['id'])) {
    $db->query("SELECT * FROM hospital_banners WHERE hospital_id = :hospital_id ORDER BY sort_order ASC LIMIT 5");
    $db->bind(':hospital_id', $facility['id']);
    $banners = $db->resultSet();

    $db->query("SELECT * FROM hospital_booking_forms WHERE hospital_id = :hospital_id ORDER BY sort_order ASC, id ASC");
    $db->bind(':hospital_id', $facility['id']);
    $bookingForms = $db->resultSet();
    try {
        $db->query("SELECT * FROM lab_packages WHERE hospital_id = :hospital_id AND is_active = 1 ORDER BY FIELD(category, 'lab', 'imaging', 'vaccination'), id ASC");
        $db->bind(':hospital_id', $facility['id']);
        $packageRows = $db->resultSet();
        $packageIcons = ['lab' => 'bi-clipboard2-pulse', 'imaging' => 'bi-camera', 'vaccination' => 'bi-eyedropper'];
        foreach ($packageRows as $packageRow) {
            $bookingForms[] = [
                'id' => 0,
                'name' => $packageRow['name'],
                'icon' => $packageRow['icon_path'] ?? '',
                'service_icon' => $packageIcons[$packageRow['category'] ?? 'lab'] ?? 'bi-calendar-check',
                'package_id' => (int)$packageRow['id'],
                'is_package' => 1
            ];
        }
    } catch (Exception $e) {}
}
$serviceImage = ($facility['service_image_url'] ?? '') ?: ($facility['content_image_url'] ?? '') ?: ($banners[0]['image_path'] ?? '') ?: $poster;
$rating = null;
$reviewCount = 0;
$isDermatologyHospital = stripos($facility['name'] ?? '', 'Da liễu') !== false || stripos($facility['name'] ?? '', 'Da Liễu') !== false;

if (!empty($facility['id'])) {
    $db->query("SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
                FROM reviews r
                INNER JOIN doctors d ON r.doctor_id = d.id
                WHERE d.hospital_id = :hospital_id");
    $db->bind(':hospital_id', $facility['id']);
    $ratingData = $db->single();
    $reviewCount = (int)($ratingData['review_count'] ?? 0);
    if ($reviewCount > 0) {
        $rating = round($ratingData['avg_rating'], 1);
    }
}
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb fw-bold small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color:#00b5f1;"><?php echo htmlspecialchars($facility['name']); ?></li>
        </ol>
    </nav>

    <div class="row g-3 align-items-stretch">
        <div class="col-lg-4">
            <div class="bg-white rounded-4 p-4 h-100 shadow-sm text-center">
                <img src="<?php echo htmlspecialchars(facilityImageSrc($logo, $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="img-fluid mb-3" style="max-height:130px; object-fit:contain;">
                <h4 class="fw-bold mb-2" style="color:#00b5f1;"><?php echo htmlspecialchars($facility['name']); ?></h4>
                <div class="text-warning mb-3">
                    <?php if ($reviewCount > 0): ?>
                        <span>(<?php echo htmlspecialchars($rating); ?>)</span>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($rating >= $i): ?>
                                <i class="bi bi-star-fill"></i>
                            <?php elseif ($rating >= $i - 0.5): ?>
                                <i class="bi bi-star-half"></i>
                            <?php else: ?>
                                <i class="bi bi-star text-muted"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <div class="text-muted small">Dựa trên <?php echo $reviewCount; ?> lượt đánh giá</div>
                    <?php else: ?>
                        <span class="text-muted small">Chưa có đánh giá</span>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="text-start small mb-3">
                    <div class="d-flex gap-2 mb-3"><i class="bi bi-geo-alt text-warning"></i><span><?php echo htmlspecialchars($facility['address'] ?? 'Đang cập nhật'); ?></span></div>
                    <div class="d-flex gap-2 mb-3"><i class="bi bi-clock text-warning"></i><span><?php echo htmlspecialchars($facility['working_time'] ?? 'Đang cập nhật'); ?></span></div>
                    <div class="d-flex gap-2"><i class="bi bi-telephone text-warning"></i><span>Tổng đài đặt khám nhanh: <?php echo htmlspecialchars($facility['phone'] ?? 'Đang cập nhật'); ?></span></div>
                </div>
                <a href="facility_booking_options.php?facility=<?php echo urlencode($facility['name']); ?>" class="btn text-white rounded-pill fw-bold w-100 py-2" style="background:#00b5f1;">Đặt khám ngay</a>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="rounded-4 overflow-hidden shadow-sm h-100 bg-white">
                <?php if (count($banners) > 0): ?>
                    <div id="facilityBannerCarousel" class="carousel slide h-100" data-bs-ride="carousel" data-bs-interval="1800">
                        <div class="carousel-inner h-100">
                            <?php foreach ($banners as $index => $banner): ?>
                                <div class="carousel-item h-100 <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars(facilityImageSrc($banner['image_path'], $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="w-100 h-100" style="min-height:380px; object-fit:cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#facilityBannerCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#facilityBannerCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars(facilityImageSrc($poster, $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="w-100 h-100" style="min-height:380px; object-fit:cover;">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4 align-items-center">
        <div class="col-lg-4">
            <div class="rounded-4 overflow-hidden shadow-sm bg-white">
                <img src="<?php echo htmlspecialchars(facilityImageSrc($serviceImage, $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="w-100" style="height: 230px; object-fit: cover;">
            </div>
        </div>
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm p-4">
                <h3 class="fw-bold mb-4 text-center" style="color:#00b5f1;">Các dịch vụ</h3>
                <div class="d-flex flex-wrap justify-content-center gap-4">
                    <?php if (count($bookingForms) > 0): ?>
                        <?php foreach ($bookingForms as $form): ?>
                            <?php
                                $serviceLink = !empty($form['is_package']) ? 'lab_package_booking.php?package_id=' . (int)$form['package_id'] : ((($form['target'] ?? 'specialty') === 'doctor') ? 'doctors.php?hospital_id=' . (int)($facility['id'] ?? 0) : 'specialty_booking.php?id=' . (int)($facility['id'] ?? 0) . '&facility=' . urlencode($facility['name']) . '&booking_form_id=' . (int)$form['id']);
                                $serviceIcon = !empty($form['service_icon']) ? $form['service_icon'] : 'bi-calendar2-check';
                            ?>
                            <a href="<?php echo htmlspecialchars($serviceLink); ?>" class="text-decoration-none text-center border rounded-4 p-3" style="width:150px; color:#023f6d;">
                                <?php if (!empty($form['icon']) && strpos($form['icon'], 'bi-') !== 0): ?>
                                    <img src="<?php echo htmlspecialchars($base_url . '/' . $form['icon']); ?>" alt="<?php echo htmlspecialchars($form['name']); ?>" style="width: 46px; height: 46px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="bi <?php echo htmlspecialchars($serviceIcon); ?> fs-1" style="color:#00b5f1;"></i>
                                <?php endif; ?>
                                <div class="fw-semibold mt-2"><?php echo htmlspecialchars($form['name']); ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted fw-semibold py-4">Bệnh viện chưa cập nhật dịch vụ đặt khám.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4 align-items-start">
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm p-4">
                <h3 class="fw-bold mb-3">Mô tả</h3>
                <div><?php echo nl2br(htmlspecialchars($facility['short_description'] ?? 'Đang cập nhật')); ?></div>
            </div>
            <?php if (!empty($facility['map_embed_url'] ?? '')): ?>
                <div class="bg-white rounded-4 shadow-sm overflow-hidden mt-3">
                    <iframe src="<?php echo htmlspecialchars($facility['map_embed_url']); ?>" width="100%" height="260" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm p-4" style="max-height: 650px; overflow-y: auto;">
                <?php if (!empty(($facility['content_image_url'] ?? ''))): ?>
                    <img src="<?php echo htmlspecialchars(facilityImageSrc(($facility['content_image_url'] ?? ''), $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="img-fluid rounded-4 mb-4 w-100" style="max-height: 360px; object-fit: cover;">
                <?php endif; ?>
                <?php if (!empty($facility['overview'])): ?>
                    <h3 class="fw-bold mb-3">Tổng quan về <?php echo htmlspecialchars($facility['name']); ?></h3>
                    <div class="mb-4"><?php echo nl2br(htmlspecialchars($facility['overview'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($facility['overview_file_url'])): ?>
                    <?php $overviewFile = facilityImageSrc($facility['overview_file_url'], $base_url); $overviewExt = strtolower(pathinfo($facility['overview_file_url'], PATHINFO_EXTENSION)); ?>
                    <?php if (in_array($overviewExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)): ?>
                        <img src="<?php echo htmlspecialchars($overviewFile); ?>" alt="Nội dung bổ sung" class="img-fluid rounded-4 mb-4 w-100" style="max-height: 520px; object-fit: contain;">
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($overviewFile); ?>" target="_blank" class="btn btn-outline-primary mb-4">Xem file nội dung bổ sung</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (empty($facility['overview']) && empty(($facility['content_image_url'] ?? '')) && empty(($facility['overview_file_url'] ?? ''))): ?>
                    <div>Đang cập nhật</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
