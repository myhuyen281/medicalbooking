<?php
require_once 'config/database.php';
require_once 'includes/province_filter.php';
include 'includes/header.php';

$db = new Database();
$keyword = trim($_GET['q'] ?? '');
function filterArray($value) {
    if (is_array($value)) return array_values(array_filter(array_map('trim', $value), fn($item) => $item !== ''));
    return ($value !== null && trim($value) !== '') ? [trim($value)] : [];
}
$provinceFilters = filterArray($_GET['province'] ?? []);
function locationKeyword($value) {
    return trim(str_replace(['Thành phố ', 'Tỉnh ', 'Quận ', 'Huyện ', 'Thị xã '], '', $value));
}
$vaccinationExists = "(
    EXISTS (SELECT 1 FROM lab_packages lp WHERE lp.hospital_id = h.id AND lp.is_active = 1 AND lp.category = 'vaccination')
    OR EXISTS (SELECT 1 FROM hospital_booking_forms f WHERE f.hospital_id = h.id AND (LOWER(f.name) LIKE '%tiêm%' OR LOWER(f.name) LIKE '%vắc%' OR LOWER(f.name) LIKE '%vaccine%' OR LOWER(f.name) LIKE '%vaccin%'))
    OR EXISTS (SELECT 1 FROM hospital_services hs WHERE hs.hospital_id = h.id AND (LOWER(hs.name) LIKE '%tiêm%' OR LOWER(hs.name) LIKE '%vắc%' OR LOWER(hs.name) LIKE '%vaccine%' OR LOWER(hs.name) LIKE '%vaccin%' OR LOWER(hs.detail_text) LIKE '%tiêm%' OR LOWER(hs.detail_text) LIKE '%vắc%' OR LOWER(hs.detail_text) LIKE '%vaccine%' OR LOWER(hs.detail_text) LIKE '%vaccin%'))
)";
$where = "WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved' AND {$vaccinationExists}";
$params = [];
if ($keyword !== '') {
    $where .= " AND (h.name LIKE :keyword OR h.address LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}
if (count($provinceFilters) > 0) {
    $provinceWheres = [];
    foreach ($provinceFilters as $index => $provinceItem) {
        $provinceWheres[] = "(h.address LIKE :province_{$index} OR h.address LIKE :province_short_{$index})";
        $params[':province_' . $index] = '%' . $provinceItem . '%';
        $params[':province_short_' . $index] = '%' . locationKeyword($provinceItem) . '%';
    }
    $where .= ' AND (' . implode(' OR ', $provinceWheres) . ')';
}
$perPage = 6;
$currentPageNum = max(1, (int)($_GET['page'] ?? 1));
try {
    $db->query("SELECT COUNT(*) AS total FROM hospitals h LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' {$where}");
    foreach ($params as $param => $value) $db->bind($param, $value);
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
                 WHERE f.hospital_id = h.id AND (LOWER(f.name) LIKE '%tiêm%' OR LOWER(f.name) LIKE '%vắc%' OR LOWER(f.name) LIKE '%vaccine%' OR LOWER(f.name) LIKE '%vaccin%')
                 ORDER BY f.sort_order ASC, f.id ASC LIMIT 1) AS vaccination_form_id,
                (SELECT lp.id FROM lab_packages lp
                 WHERE lp.hospital_id = h.id AND lp.is_active = 1 AND lp.category = 'vaccination'
                 ORDER BY lp.id ASC LIMIT 1) AS vaccination_package_id
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                LEFT JOIN doctors d ON d.hospital_id = h.id
                LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status IN ('confirmed', 'completed')
                {$where}
                GROUP BY h.id
                ORDER BY successful_booking_count DESC, h.id DESC
                LIMIT {$perPage} OFFSET {$offset}");
    foreach ($params as $param => $value) $db->bind($param, $value);
    $hospitals = $db->resultSet();
} catch (Exception $e) {
    $hospitals = [];
}
function vaccinationPageUrl($pageNum, $keyword, $provinceFilters) {
    $query = ['page' => $pageNum];
    if ($keyword !== '') $query['q'] = $keyword;
    return 'vaccination_booking.php?' . http_build_query($query) . (count($provinceFilters) > 0 ? '&' . http_build_query(['province' => $provinceFilters]) : '');
}
function bookingFacilityImage($path, $base_url) {
    if (empty($path)) return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}
$provinceList = getRegisteredProvinces($db, $vnProvinces, $vaccinationExists);
?>

<div style="background:linear-gradient(90deg,#eefbff 0%,#d9f4ff 100%); margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="bg-white rounded-5 shadow-sm p-4 p-md-5">
                    <h1 class="fw-bold text-uppercase mb-3" style="color:#00a8f0; font-size:clamp(2rem,4vw,3rem);">Đặt lịch tiêm chủng</h1>
                    <div class="d-flex flex-column gap-3" style="color:#023f6d; font-size:1.05rem;">
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Chủ động tìm hiểu, lựa chọn loại tiêm chủng phù hợp với nhu cầu.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hoàn phí nếu hủy phiếu đúng quy định.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Nhiều lựa chọn cơ sở công, tư, mức phí phù hợp.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Giảm thiểu việc thiếu vắc xin theo mùa.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hưởng chính sách hoàn tiền khi đặt lịch trên hệ thống.</div>
                    </div>
                    <hr class="my-4" style="border-color:#00b5f1; opacity:.6;">
                    <div style="color:#023f6d;">Liên hệ <strong>chuyên gia</strong> để tư vấn thêm <strong class="text-info ms-2"><i class="bi bi-telephone-fill"></i> 19002115</strong></div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fprod-partner%2F46b37410-c3f1-494e-a7c4-9f74db2a6eec-dat-lich-tiem-chung.webp&w=1920&q=75" alt="Đặt lịch tiêm chủng" class="img-fluid" style="max-height:340px; object-fit:contain; filter:drop-shadow(0 12px 24px rgba(0,120,180,.18));">
            </div>
        </div>
    </div>
</div>

<div class="py-4" style="background:#eaf7ff; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container">
        <div class="d-flex align-items-center gap-3 mb-4">
            <form class="d-flex align-items-center gap-3 bg-white rounded-pill shadow-sm p-2 flex-fill" style="max-width: 860px;" method="get" action="vaccination_booking.php">
                <?php foreach ($provinceFilters as $pv): ?><input type="hidden" name="province[]" value="<?php echo htmlspecialchars($pv); ?>"><?php endforeach; ?>
                <div class="input-group flex-fill"><span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span><input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 shadow-none py-2" placeholder="Tìm kiếm cơ sở y tế"></div>
                <button class="btn btn-premium-primary rounded-circle flex-shrink-0" style="width:48px;height:48px;" type="submit"><i class="bi bi-search"></i></button>
            </form>
            <button type="button" class="btn btn-outline-info rounded-pill px-4 py-3 fw-bold flex-shrink-0 bg-white" data-bs-toggle="modal" data-bs-target="#provinceFilterModal"><i class="bi bi-sliders me-2"></i>Bộ lọc</button>
        </div>

        <div class="row g-4">
            <?php foreach ($hospitals as $hospital): ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4 d-flex gap-3 align-items-center">
                            <img src="<?php echo htmlspecialchars(bookingFacilityImage($hospital['logo_url'] ?? '', $base_url)); ?>" alt="<?php echo htmlspecialchars($hospital['name']); ?>" style="width:100px;height:100px;object-fit:contain;">
                            <div class="flex-fill">
                                <h5 class="fw-bold mb-2" style="color:#023f6d;"><?php echo htmlspecialchars($hospital['name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                                <div class="text-muted small mb-3"><i class="bi bi-geo-alt text-warning"></i> <?php echo htmlspecialchars($hospital['address'] ?: 'Đang cập nhật địa chỉ'); ?></div>
                                <?php $bookingLink = !empty($hospital['vaccination_package_id']) ? 'lab_package_booking.php?package_id=' . (int)$hospital['vaccination_package_id'] : 'specialty_booking.php?id=' . (int)$hospital['id'] . '&facility=' . urlencode($hospital['name']) . (!empty($hospital['vaccination_form_id']) ? '&booking_form_id=' . (int)$hospital['vaccination_form_id'] : ''); ?>
                                <a href="<?php echo htmlspecialchars($bookingLink); ?>" class="btn btn-premium-primary rounded-pill px-4">Đặt lịch ngay</a>
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
            <nav class="mt-5"><ul class="pagination justify-content-center gap-2 mb-0">
                <?php for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++): ?>
                    <li class="page-item"><a class="page-link border-0 rounded-3 shadow-sm fw-bold <?php echo $pageNum === $currentPageNum ? 'text-white' : ''; ?>" style="<?php echo $pageNum === $currentPageNum ? 'background:#00a8f0;' : 'color:#023f6d;'; ?>" href="<?php echo htmlspecialchars(vaccinationPageUrl($pageNum, $keyword, $provinceFilters)); ?>"><?php echo $pageNum; ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="provinceFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 overflow-hidden">
        <form method="get" action="vaccination_booking.php">
            <?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($keyword); ?>"><?php endif; ?>
            <div class="modal-header border-0 justify-content-center position-relative" style="background:#eaf7ff;"><h5 class="modal-title fw-bold" style="color:#00a8f0;">Chọn tỉnh/thành</h5><button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4"><div class="row g-3" style="max-height:330px; overflow-y:auto;">
                <?php foreach ($provinceList as $provinceItem): ?>
                    <div class="col-md-6 col-12"><label class="d-flex align-items-center gap-2" style="cursor:pointer;"><input type="checkbox" class="form-check-input" name="province[]" value="<?php echo htmlspecialchars($provinceItem['name']); ?>" <?php echo in_array($provinceItem['name'], $provinceFilters, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($provinceItem['name']); ?> (<?php echo (int)$provinceItem['count']; ?>)</span></label></div>
                <?php endforeach; ?>
            </div></div>
            <div class="modal-footer border-0" style="background:#f8fafc;"><a href="vaccination_booking.php" class="btn btn-outline-info rounded-pill px-4">Đặt lại</a><button type="submit" class="btn btn-premium-primary rounded-pill px-4">Lọc</button></div>
        </form>
    </div></div>
</div>

<?php include 'includes/footer.php'; ?>
