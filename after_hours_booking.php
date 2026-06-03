<?php
require_once 'config/database.php';
require_once 'includes/province_filter.php';
include 'includes/header.php';

$db = new Database();
$keyword = trim($_GET['q'] ?? '');
function filterArray($value) {
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), function ($item) { return $item !== ''; }));
    }
    return ($value !== null && trim($value) !== '') ? [trim($value)] : [];
}
$provinceFilters = filterArray($_GET['province'] ?? []);
function locationKeyword($value) {
    return trim(str_replace(['Thành phố ', 'Tỉnh ', 'Quận ', 'Huyện ', 'Thị xã '], '', $value));
}
$where = "WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved' AND EXISTS (SELECT 1 FROM hospital_booking_forms f WHERE f.hospital_id = h.id AND LOWER(f.name) LIKE '%ngoài giờ%')";
$params = [];
if ($keyword !== '') {
    $where .= " AND (h.name LIKE :keyword OR h.address LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}
if (count($provinceFilters) > 0) {
    $provinceWheres = [];
    foreach ($provinceFilters as $index => $provinceItem) {
        $provinceParam = ':province_' . $index;
        $provinceShortParam = ':province_short_' . $index;
        $provinceWheres[] = "(h.address LIKE {$provinceParam} OR h.address LIKE {$provinceShortParam})";
        $params[$provinceParam] = '%' . $provinceItem . '%';
        $params[$provinceShortParam] = '%' . locationKeyword($provinceItem) . '%';
    }
    $where .= ' AND (' . implode(' OR ', $provinceWheres) . ')';
}
$perPage = 6;
$currentPageNum = max(1, (int)($_GET['page'] ?? 1));
$totalHospitals = 0;
try {
    $db->query("SELECT COUNT(*) AS total
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                {$where}");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    $totalHospitals = (int)($db->single()['total'] ?? 0);
} catch (Exception $e) {
    $totalHospitals = 0;
}
$totalPages = max(1, (int)ceil($totalHospitals / $perPage));
$currentPageNum = min($currentPageNum, $totalPages);
$offset = ($currentPageNum - 1) * $perPage;
try {
    $db->query("SELECT h.*, COUNT(DISTINCT a.id) AS successful_booking_count,
                (SELECT f.id FROM hospital_booking_forms f
                 WHERE f.hospital_id = h.id AND LOWER(f.name) LIKE '%ngoài giờ%'
                 ORDER BY f.sort_order ASC, f.id ASC LIMIT 1) AS after_hours_form_id
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                LEFT JOIN doctors d ON d.hospital_id = h.id
                LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status IN ('confirmed', 'completed')
                {$where}
                GROUP BY h.id
                ORDER BY successful_booking_count DESC, h.id DESC
                LIMIT {$perPage} OFFSET {$offset}");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    $hospitals = $db->resultSet();
} catch (Exception $e) {
    $hospitals = [];
}
function afterHoursPageUrl($pageNum, $keyword, $provinceFilters) {
    $query = ['page' => $pageNum];
    if ($keyword !== '') $query['q'] = $keyword;
    return 'after_hours_booking.php?' . http_build_query($query) . (count($provinceFilters) > 0 ? '&' . http_build_query(['province' => $provinceFilters]) : '');
}
function bookingFacilityImage($path, $base_url) {
    if (empty($path)) return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}
function afterHoursLogo($hospital, $base_url) {
    $name = $hospital['name'] ?? '';
    if (stripos($name, 'MEDLATEC') !== false) return $base_url . '/uploads/hospitals/medlatec_cantho_logo.png';
    if (stripos($name, 'VNVC') !== false) return 'https://sanvieclamcantho.com/upload/imagelogo/vnvc1724469700.png';
    if (stripos($name, 'Long Châu') !== false) return 'https://cdn-new.topcv.vn/unsafe/https://static.topcv.vn/company_logos/IinkQQY7z2A7AQXZ84KKTNq83awObGLS_1650511186____11070390482b3374c7cee11f4b9f6fdf.png';
    if (stripos($name, 'DIAG') !== false) return $base_url . '/uploads/hospitals/diag_logo.svg';
    if (stripos($name, 'MEDIC') !== false) return $base_url . '/uploads/hospitals/medic_logo.jpg';
    return bookingFacilityImage($hospital['logo_url'] ?? '', $base_url);
}
$provinceList = getRegisteredProvinces($db, $vnProvinces);
?>

<div style="background:linear-gradient(90deg,#eefbff 0%,#d9f4ff 100%); margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="bg-white rounded-5 shadow-sm p-4 p-md-5">
                    <h1 class="fw-bold text-uppercase mb-3" style="color:#00a8f0; font-size:clamp(2rem,4vw,3rem);">Đặt khám ngoài giờ</h1>
                    <div class="d-flex flex-column gap-3" style="color:#023f6d; font-size:1.05rem;">
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Khám bệnh ngoài giờ hành chính tại các cơ sở y tế công, tư.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hoàn phí khám nếu hủy phiếu đúng quy định.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hưởng chính sách hoàn tiền khi đặt lịch trên hệ thống (đối với các cơ sở y tế tư có áp dụng).</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <img src="https://truyencotich.vn/wp-content/uploads/2015/05/benh-vien-640x440.png" alt="Minh họa bệnh viện" class="img-fluid" style="max-height:330px; object-fit:contain;">
            </div>
        </div>
    </div>
</div>

<div class="py-4" style="background:#eaf7ff; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container">
    <div class="d-flex align-items-center gap-3 mb-4">
        <form class="d-flex align-items-center gap-3 bg-white rounded-pill shadow-sm p-2 flex-fill" style="max-width: 860px;" method="get" action="after_hours_booking.php">
            <?php foreach ($provinceFilters as $pv): ?><input type="hidden" name="province[]" value="<?php echo htmlspecialchars($pv); ?>"><?php endforeach; ?>
            <div class="input-group flex-fill">
                <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 shadow-none py-2" placeholder="Tìm kiếm cơ sở y tế">
            </div>
            <button class="btn btn-premium-primary rounded-circle flex-shrink-0" style="width:48px;height:48px;" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <button type="button" class="btn btn-outline-info rounded-pill px-4 py-3 fw-bold flex-shrink-0 bg-white" data-bs-toggle="modal" data-bs-target="#provinceFilterModal"><i class="bi bi-sliders me-2"></i>Bộ lọc</button>
    </div>

    <?php if ($keyword !== '' || count($provinceFilters) > 0): ?>
        <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small">Tìm thấy <strong><?php echo $totalHospitals; ?></strong> cơ sở y tế</span>
            <?php foreach ($provinceFilters as $pv): ?><span class="badge rounded-pill" style="background:#eef9ff; color:#00a8f0;"><?php echo htmlspecialchars($pv); ?></span><?php endforeach; ?>
            <a href="after_hours_booking.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-x-lg me-1"></i>Xóa bộ lọc</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($hospitals as $hospital): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 d-flex gap-3 align-items-center">
                        <img src="<?php echo htmlspecialchars(afterHoursLogo($hospital, $base_url)); ?>" alt="<?php echo htmlspecialchars($hospital['name']); ?>" style="width:100px;height:100px;object-fit:contain;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';">
                        <div class="flex-fill">
                            <h5 class="fw-bold mb-2" style="color:#023f6d;"><?php echo htmlspecialchars($hospital['name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                            <div class="text-muted small mb-3"><i class="bi bi-geo-alt text-warning"></i> <?php echo htmlspecialchars($hospital['address'] ?: 'Đang cập nhật địa chỉ'); ?></div>
                            <a href="specialty_booking.php?facility=<?php echo urlencode($hospital['name']); ?><?php echo !empty($hospital['after_hours_form_id']) ? '&booking_form_id=' . (int)$hospital['after_hours_form_id'] : ''; ?>" class="btn btn-premium-primary rounded-pill px-4">Đặt khám ngay</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($hospitals) === 0): ?>
            <div class="col-12"><div class="bg-white rounded-4 shadow-sm p-5 text-center text-muted">Không tìm thấy cơ sở y tế phù hợp.</div></div>
        <?php endif; ?>
    </div>

    <?php if ($totalHospitals > 0): ?>
        <nav class="mt-5" aria-label="Phân trang cơ sở y tế">
            <ul class="pagination justify-content-center align-items-center gap-2 mb-0">
                <li class="page-item <?php echo $currentPageNum <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-3 shadow-sm" style="color:#023f6d;" href="<?php echo $currentPageNum <= 1 ? '#' : htmlspecialchars(afterHoursPageUrl($currentPageNum - 1, $keyword, $provinceFilters)); ?>" aria-label="Trước"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++): ?>
                    <li class="page-item">
                        <a class="page-link border-0 rounded-3 shadow-sm fw-bold <?php echo $pageNum === $currentPageNum ? 'text-white' : ''; ?>" style="<?php echo $pageNum === $currentPageNum ? 'background:#00a8f0;' : 'color:#023f6d;'; ?>" href="<?php echo htmlspecialchars(afterHoursPageUrl($pageNum, $keyword, $provinceFilters)); ?>"><?php echo $pageNum; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $currentPageNum >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded-3 shadow-sm" style="color:#023f6d;" href="<?php echo $currentPageNum >= $totalPages ? '#' : htmlspecialchars(afterHoursPageUrl($currentPageNum + 1, $keyword, $provinceFilters)); ?>" aria-label="Sau"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="provinceFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <form method="get" action="after_hours_booking.php">
                <?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($keyword); ?>"><?php endif; ?>
                <div class="modal-header border-0 justify-content-center position-relative" style="background:#eaf7ff;">
                    <h5 class="modal-title fw-bold" style="color:#00a8f0;">Chọn tỉnh/thành</h5>
                    <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="position-relative mb-4">
                        <span class="position-absolute top-50 translate-middle-y ps-3 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="provinceSearchInput" class="form-control rounded-pill ps-5 py-2 border" placeholder="Tìm tỉnh/thành...">
                    </div>
                    <div class="row g-3" style="max-height:330px; overflow-y:auto;">
                        <?php foreach ($provinceList as $provinceItem): ?>
                            <div class="col-md-6 col-12 province-option">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                    <input type="checkbox" class="form-check-input" name="province[]" value="<?php echo htmlspecialchars($provinceItem['name']); ?>" <?php echo in_array($provinceItem['name'], $provinceFilters, true) ? 'checked' : ''; ?>>
                                    <span class="province-label"><?php echo htmlspecialchars($provinceItem['name']); ?> (<?php echo (int)$provinceItem['count']; ?>)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($provinceList) === 0): ?>
                            <div class="col-12 text-center text-muted py-3">Chưa có cơ sở y tế nào.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer border-0" style="background:#f8fafc;">
                    <a href="after_hours_booking.php" class="btn btn-outline-info rounded-pill px-4"><i class="bi bi-arrow-clockwise me-1"></i>Đặt lại</a>
                    <button type="submit" class="btn btn-premium-primary rounded-pill px-4"><i class="bi bi-funnel me-1"></i>Lọc</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const provinceSearch = document.getElementById('provinceSearchInput');
    provinceSearch?.addEventListener('input', function () {
        const term = (this.value || '').toLowerCase().trim();
        document.querySelectorAll('#provinceFilterModal .province-option').forEach(function (option) {
            const label = (option.querySelector('.province-label')?.textContent || '').toLowerCase();
            option.style.display = label.includes(term) ? '' : 'none';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
