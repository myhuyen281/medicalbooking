<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN facility_type VARCHAR(30) NOT NULL DEFAULT 'public' AFTER facility_code");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("UPDATE hospitals SET facility_type = 'public' WHERE facility_type IS NULL OR facility_type = ''");
    $db->execute();
} catch (Exception $e) {
}
$keyword = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$allowedTypes = ['public', 'private', 'clinic', 'office', 'lab', 'home', 'vaccination'];

$where = "WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'";
$params = [];

if ($keyword !== '') {
    $where .= " AND (h.name LIKE :keyword OR h.address LIKE :keyword OR h.description LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}

if (in_array($type, $allowedTypes, true)) {
    $where .= " AND h.facility_type = :facility_type";
    $params[':facility_type'] = $type;
}

try {
    $db->query("SELECT DISTINCT h.*, COUNT(DISTINCT a.id) AS successful_booking_count, COUNT(DISTINCT r.id) AS review_count, AVG(r.rating) AS avg_rating, GROUP_CONCAT(DISTINCT hb.image_path ORDER BY hb.sort_order ASC SEPARATOR '||') AS banner_images
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                LEFT JOIN doctors d ON d.hospital_id = h.id
                LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status IN ('confirmed', 'completed')
                LEFT JOIN reviews r ON r.doctor_id = d.id
                LEFT JOIN hospital_banners hb ON hb.hospital_id = h.id
                {$where}
                GROUP BY h.id
                ORDER BY successful_booking_count DESC, avg_rating DESC, h.id DESC");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    $hospitals = $db->resultSet();
} catch (Exception $e) {
    $hospitals = [];
}

function facilitiesImageSrc($path, $fallback, $base_url) {
    if (empty($path)) {
        return $fallback;
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}

$categories = [
    '' => 'Tất cả',
    'public' => 'Bệnh viện công',
    'private' => 'Bệnh viện tư',
    'clinic' => 'Phòng khám',
    'office' => 'Phòng mạch',
    'lab' => 'Xét nghiệm',
    'home' => 'Y tế tại nhà',
    'vaccination' => 'Tiêm chủng'
];
$categoryCounts = array_fill_keys(array_keys($categories), 0);
try {
    $db->query("SELECT COALESCE(h.facility_type, 'public') AS facility_type, COUNT(DISTINCT h.id) AS total
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                GROUP BY COALESCE(h.facility_type, 'public')");
    $countRows = $db->resultSet();
    foreach ($countRows as $row) {
        if (isset($categoryCounts[$row['facility_type']])) {
            $categoryCounts[$row['facility_type']] = (int)$row['total'];
        }
        $categoryCounts[''] += (int)$row['total'];
    }
} catch (Exception $e) {
}
?>

<div class="py-4 px-2 px-md-4" style="background:#f8fafc; min-height:650px;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb fw-semibold">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color:#00b5f1;">Cơ sở y tế</li>
        </ol>
    </nav>

    <div class="text-center mb-4 py-4">
        <h1 class="fw-bold mb-2" style="color:#00a8f0; font-size:clamp(2rem,5vw,3rem);">Cơ sở y tế</h1>
        <p class="mb-4" style="color:#023f6d;">Với những cơ sở Y tế hàng đầu sẽ giúp trải nghiệm khám, chữa bệnh của bạn tốt hơn</p>
        <form class="mx-auto" style="max-width:660px;" method="get" action="facilities.php">
            <?php if ($type !== ''): ?>
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            <?php endif; ?>
            <div class="input-group bg-white shadow-sm rounded-4 overflow-hidden">
                <span class="input-group-text bg-white border-0 ps-4"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 py-3 shadow-none" placeholder="Tìm kiếm cơ sở y tế...">
            </div>
        </form>
    </div>

    <div class="d-flex overflow-auto gap-3 py-3 mb-4 scrollbar-hide border-top border-bottom">
        <?php foreach ($categories as $key => $label): ?>
            <a href="facilities.php<?php echo $key !== '' ? '?type=' . urlencode($key) : ''; ?>" class="btn rounded-pill px-4 py-2 fw-bold flex-shrink-0 <?php echo $type === $key ? 'text-white' : 'text-info'; ?>" style="background:<?php echo $type === $key ? '#00a8f0' : '#eef9ff'; ?>;">
                <?php echo htmlspecialchars($label); ?> (<?php echo (int)($categoryCounts[$key] ?? 0); ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="facility-scroll-column d-flex flex-column gap-4 pe-lg-2" style="max-height: calc(100vh - 230px); overflow-y: auto; overscroll-behavior: contain; scroll-behavior: smooth; -webkit-overflow-scrolling: touch;">
                <?php foreach ($hospitals as $index => $hospital): ?>
                    <?php
                        $displayRating = (int)($hospital['review_count'] ?? 0) > 0 ? round((float)$hospital['avg_rating'], 1) : 0;
                        $bannerImages = [];
                        foreach (array_filter(explode('||', $hospital['banner_images'] ?? '')) as $bannerImage) {
                            $bannerImages[] = facilitiesImageSrc($bannerImage, '', $base_url);
                        }
                        if (!empty($hospital['content_image_url'])) {
                            $bannerImages[] = facilitiesImageSrc($hospital['content_image_url'], '', $base_url);
                        }
                        if (!empty($hospital['service_image_url'])) {
                            $bannerImages[] = facilitiesImageSrc($hospital['service_image_url'], '', $base_url);
                        }
                        $hospitalDetail = [
                            'id' => (int)$hospital['id'],
                            'name' => $hospital['name'],
                            'address' => $hospital['address'] ?: 'Đang cập nhật địa chỉ',
                            'phone' => $hospital['phone'] ?: 'Đang cập nhật',
                            'working_time' => $hospital['working_time'] ?: 'Đang cập nhật',
                            'description' => $hospital['short_description'] ?: ($hospital['description'] ?: 'Cơ sở y tế đang cập nhật thông tin giới thiệu.'),
                            'logo' => facilitiesImageSrc($hospital['logo_url'] ?? '', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png', $base_url),
                            'map_embed_url' => $hospital['map_embed_url'] ?? '',
                            'images' => array_values(array_unique(array_filter($bannerImages))),
                            'rating' => number_format($displayRating, 1),
                            'booking_count' => (int)($hospital['successful_booking_count'] ?? 0),
                            'detail_url' => 'facility_detail.php?id=' . (int)$hospital['id'],
                            'booking_url' => 'facility_booking_options.php?facility=' . urlencode($hospital['name'])
                        ];
                    ?>
                    <div class="facility-select-card card border-0 shadow-sm rounded-4 overflow-hidden text-start <?php echo $index === 0 ? 'active' : ''; ?>" data-facility='<?php echo htmlspecialchars(json_encode($hospitalDetail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>' style="background:#fff; width:100%; cursor:pointer; border:2px solid <?php echo $index === 0 ? '#00b5f1' : 'transparent'; ?> !important;">
                        <div class="row g-0 align-items-center">
                            <div class="col-md-3 bg-white d-flex align-items-center justify-content-center p-4">
                                <img src="<?php echo htmlspecialchars($hospitalDetail['logo']); ?>" alt="<?php echo htmlspecialchars($hospital['name']); ?>" class="img-fluid" style="max-height:120px; object-fit:contain;">
                            </div>
                            <div class="col-md-9">
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-2" style="color:#023f6d;"><?php echo htmlspecialchars($hospital['name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                                    <div class="text-muted small mb-2"><i class="bi bi-geo-alt text-warning"></i> <?php echo htmlspecialchars($hospitalDetail['address']); ?></div>
                                    <div class="text-warning small mb-2"><span class="me-1">(<?php echo $hospitalDetail['rating']; ?>)</span><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?php echo $displayRating >= $i ? 'bi-star-fill' : ($displayRating >= $i - 0.5 ? 'bi-star-half' : 'bi-star text-muted'); ?>"></i><?php endfor; ?></div>
                                    <div class="text-primary small fw-bold mb-3"><i class="bi bi-calendar-check"></i> <?php echo $hospitalDetail['booking_count']; ?> đơn đặt khám thành công</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="<?php echo htmlspecialchars($hospitalDetail['detail_url']); ?>" class="btn btn-outline-info rounded-pill fw-bold px-3">Xem chi tiết</a>
                                        <a href="<?php echo htmlspecialchars($hospitalDetail['booking_url']); ?>" class="btn btn-premium-primary rounded-pill px-3">Đặt khám ngay</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($hospitals) === 0): ?>
                    <div class="bg-white rounded-4 shadow-sm p-5 text-center fw-semibold" style="color:#023f6d;">Chưa tìm thấy cơ sở y tế phù hợp.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div id="facilityDetailPanel" class="facility-scroll-column bg-white rounded-4 shadow-sm p-4" style="min-height:420px; max-height: calc(100vh - 230px); overflow-y: auto; overscroll-behavior: contain; scroll-behavior: smooth; -webkit-overflow-scrolling: touch;">
                <div class="text-center text-muted py-5">Chọn một cơ sở y tế để xem thông tin.</div>
            </div>
        </div>
    </div>

    <style>
    .facility-scroll-column {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .facility-scroll-column::-webkit-scrollbar {
        width: 6px;
    }
    .facility-scroll-column::-webkit-scrollbar-track {
        background: transparent;
    }
    .facility-scroll-column::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }
    .facility-scroll-column::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const cards = document.querySelectorAll('.facility-select-card');
        const panel = document.getElementById('facilityDetailPanel');
        function escapeHtml(value) {
            return String(value || '').replace(/[&<>'"]/g, function (char) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char];
            });
        }
        function renderFacility(data) {
            const map = data.map_embed_url ? '<h6 class="fw-bold mt-3" style="color:#023f6d;">Bản đồ</h6><div class="rounded-4 overflow-hidden mb-3" style="height:160px;"><iframe src="' + escapeHtml(data.map_embed_url) + '" width="100%" height="160" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>' : '';
            const images = Array.isArray(data.images) && data.images.length ? '<h6 class="fw-bold mt-3" style="color:#023f6d;">Ảnh</h6><div class="row g-2">' + data.images.slice(0, 6).map(function (image) { return '<div class="col-4"><img src="' + escapeHtml(image) + '" class="rounded-3 w-100" style="height:82px; object-fit:cover;" alt="Ảnh cơ sở y tế"></div>'; }).join('') + '</div>' : '';
            panel.innerHTML = '<div class="text-center mb-3"><img src="' + escapeHtml(data.logo) + '" alt="' + escapeHtml(data.name) + '" class="img-fluid" style="max-height:150px; object-fit:contain;"></div>'
                + '<h4 class="fw-bold text-center mb-3" style="color:#00a8f0;">' + escapeHtml(data.name) + ' <i class="bi bi-patch-check-fill text-primary"></i></h4>'
                + '<div class="small mb-3"><div class="mb-2"><i class="bi bi-geo-alt text-warning"></i> ' + escapeHtml(data.address) + '</div><div class="mb-2"><i class="bi bi-clock text-warning"></i> ' + escapeHtml(data.working_time) + '</div><div><i class="bi bi-telephone text-warning"></i> ' + escapeHtml(data.phone) + '</div></div>'
                + '<hr><p class="text-muted" style="line-height:1.7;">' + escapeHtml(data.description) + '</p>'
                + map + images;
        }
        cards.forEach(function (card) {
            card.addEventListener('click', function (event) {
                if (event.target.closest('a')) return;
                cards.forEach(function (item) { item.style.setProperty('border-color', 'transparent', 'important'); item.classList.remove('active'); });
                card.style.setProperty('border-color', '#00b5f1', 'important');
                card.classList.add('active');
                renderFacility(JSON.parse(card.dataset.facility));
            });
        });
        if (cards[0]) renderFacility(JSON.parse(cards[0].dataset.facility));
    });
    </script>
</div>

<?php include 'includes/footer.php'; ?>
