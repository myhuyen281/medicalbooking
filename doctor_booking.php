<?php
require_once 'config/database.php';
require_once 'includes/province_filter.php';
include 'includes/header.php';

$db = new Database();

$keyword = trim($_GET['q'] ?? '');
$tab = ($_GET['tab'] ?? '') === 'facility' ? 'facility' : 'doctor';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;

function filterArray($value) {
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), function ($item) { return $item !== ''; }));
    }
    return ($value !== null && trim($value) !== '') ? [trim($value)] : [];
}
$specialtyFilters = array_map('intval', filterArray($_GET['specialty_id'] ?? []));
$titleFilters = filterArray($_GET['academic_title'] ?? []);
$genderFilters = filterArray($_GET['gender'] ?? []);
$hospitalFilters = array_map('intval', filterArray($_GET['hospital_id'] ?? []));
$provinceFilters = filterArray($_GET['province'] ?? []);
$hasDoctorFilter = count($specialtyFilters) || count($titleFilters) || count($genderFilters) || count($hospitalFilters);

$provinceList = getRegisteredProvinces($db, $vnProvinces);
function provinceKeyword($value) {
    return trim(str_replace(['Thành phố ', 'Tỉnh '], '', $value));
}

$db->query("SELECT s.id, s.name, COUNT(d.id) AS doctor_count
            FROM specialties s
            INNER JOIN doctors d ON d.specialty_id = s.id AND d.approval_status = 'approved'
            GROUP BY s.id, s.name
            ORDER BY s.name ASC");
$specialties = $db->resultSet();

$db->query("SELECT h.id, h.name, COUNT(d.id) AS doctor_count
            FROM hospitals h
            INNER JOIN doctors d ON d.hospital_id = h.id AND d.approval_status = 'approved'
            GROUP BY h.id, h.name
            ORDER BY h.name ASC");
$hospitalList = $db->resultSet();

$db->query("SELECT DISTINCT academic_title FROM doctors WHERE approval_status='approved' AND academic_title IS NOT NULL AND academic_title<>'' ORDER BY academic_title ASC");
$titleList = array_column($db->resultSet(), 'academic_title');

$db->query("SELECT DISTINCT gender FROM doctors WHERE approval_status='approved' AND gender IS NOT NULL AND gender<>'' ORDER BY gender ASC");
$genderList = array_column($db->resultSet(), 'gender');

$where = "WHERE d.approval_status = 'approved'";
$params = [];
if ($keyword !== '') {
    $where .= " AND (u.full_name LIKE :keyword OR s.name LIKE :keyword OR h.name LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}
function buildInClause(&$where, &$params, $column, $values, $prefix, $isInt = false) {
    if (count($values) === 0) {
        return;
    }
    $placeholders = [];
    foreach ($values as $index => $value) {
        $key = ':' . $prefix . $index;
        $placeholders[] = $key;
        $params[$key] = $isInt ? (int)$value : $value;
    }
    $where .= " AND {$column} IN (" . implode(', ', $placeholders) . ")";
}
buildInClause($where, $params, 'd.specialty_id', $specialtyFilters, 'spec', true);
buildInClause($where, $params, 'd.academic_title', $titleFilters, 'title');
buildInClause($where, $params, 'd.gender', $genderFilters, 'gender');
buildInClause($where, $params, 'd.hospital_id', $hospitalFilters, 'hosp', true);

try {
    $db->query("SELECT d.id, u.full_name, d.academic_title, d.experience_years, d.consultation_fee,
                       d.display_price_text, d.display_schedule_text, d.treatment_text, d.doctor_image_url, d.gender,
                       s.name AS specialty_name, h.name AS hospital_name, h.address AS hospital_address,
                       (SELECT hs.price
                        FROM hospital_services hs
                        LEFT JOIN hospital_booking_forms hbf ON hbf.id = hs.booking_form_id
                        WHERE hs.hospital_id = d.hospital_id
                          AND hs.price > 0
                          AND (hbf.target = 'doctor' OR hbf.name LIKE '%bác sĩ%' OR hbf.name LIKE '%bac si%' OR hs.booking_form_id IS NULL)
                        ORDER BY CASE WHEN hbf.target = 'doctor' THEN 0 WHEN hbf.name LIKE '%bác sĩ%' OR hbf.name LIKE '%bac si%' THEN 1 ELSE 2 END, hs.id ASC
                        LIMIT 1) AS common_service_price,
                       (SELECT AVG(rating) FROM reviews WHERE doctor_id = d.id) AS avg_rating,
                       (SELECT COUNT(id) FROM reviews WHERE doctor_id = d.id) AS review_count
                FROM doctors d
                INNER JOIN users u ON d.user_id = u.id
                LEFT JOIN specialties s ON d.specialty_id = s.id
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                {$where}
                ORDER BY d.experience_years DESC, d.id DESC");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    $doctors = $db->resultSet();
} catch (Exception $e) {
    $doctors = [];
}

function doctorBookingImage($path, $fullName, $base_url) {
    if (empty($path)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=00a8f0&color=fff&size=200';
    }
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . ltrim($path, '/');
}

function doctorBookingFacilityLogo($hospital, $base_url) {
    $name = $hospital['name'] ?? '';
    if (stripos($name, 'MEDLATEC') !== false) {
        return $base_url . '/uploads/hospitals/medlatec_cantho_logo.png';
    }
    if (stripos($name, 'VNVC') !== false) {
        return 'https://sanvieclamcantho.com/upload/imagelogo/vnvc1724469700.png';
    }
    if (stripos($name, 'Long Châu') !== false) {
        return 'https://cdn-new.topcv.vn/unsafe/https://static.topcv.vn/company_logos/IinkQQY7z2A7AQXZ84KKTNq83awObGLS_1650511186____11070390482b3374c7cee11f4b9f6fdf.png';
    }
    if (stripos($name, 'DIAG') !== false) {
        return $base_url . '/uploads/hospitals/diag_logo.svg';
    }
    if (stripos($name, 'MEDIC') !== false) {
        return $base_url . '/uploads/hospitals/medic_logo.jpg';
    }
    if (stripos($name, 'Y Dược') !== false) {
        return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRUed7lW_iE0g1ImT9gSZmy0PdfBiewVl5obQ&s';
    }
    if (stripos($name, 'Trung ương') !== false) {
        return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSlTz_v9XDRvF_FcHXFdA0GicixowqlMdgmQg&s';
    }
    if (!empty($hospital['logo_url'])) {
        return preg_match('#^https?://#', $hospital['logo_url']) ? $hospital['logo_url'] : $base_url . '/' . ltrim($hospital['logo_url'], '/');
    }
    return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
}

function doctorBookingPageUrl($pageNumber) {
    $params = $_GET;
    if ($pageNumber <= 1) {
        unset($params['page']);
    } else {
        $params['page'] = $pageNumber;
    }
    $query = http_build_query($params);
    return 'doctor_booking.php' . ($query ? '?' . $query : '');
}

$doctorTotal = count($doctors);
$doctorTotalPages = max(1, (int)ceil($doctorTotal / $perPage));
if ($tab === 'doctor' && $page > $doctorTotalPages) {
    $page = $doctorTotalPages;
}
$doctorOffset = ($page - 1) * $perPage;
$pagedDoctors = array_slice($doctors, $doctorOffset, $perPage);

$facilities = [];
if ($tab === 'facility') {
    $fWhere = "WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'";
    $fParams = [];
    if ($keyword !== '') {
        $fWhere .= " AND (h.name LIKE :keyword OR h.address LIKE :keyword)";
        $fParams[':keyword'] = '%' . $keyword . '%';
    }
    if (count($hospitalFilters) > 0) {
        buildInClause($fWhere, $fParams, 'h.id', $hospitalFilters, 'fhosp', true);
    }
    if (count($provinceFilters) > 0) {
        $provinceWheres = [];
        foreach ($provinceFilters as $index => $provinceItem) {
            $key = ':province' . $index;
            $keyShort = ':province_short' . $index;
            $provinceWheres[] = "(h.address LIKE {$key} OR h.address LIKE {$keyShort})";
            $fParams[$key] = '%' . $provinceItem . '%';
            $fParams[$keyShort] = '%' . provinceKeyword($provinceItem) . '%';
        }
        $fWhere .= ' AND (' . implode(' OR ', $provinceWheres) . ')';
    }
    try {
        $db->query("SELECT h.id, h.name, h.address, h.phone, h.working_time, h.logo_url, h.short_description,
                           COUNT(DISTINCT d.id) AS doctor_count
                    FROM hospitals h
                    LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                    INNER JOIN doctors d ON d.hospital_id = h.id AND d.approval_status = 'approved'
                    {$fWhere}
                    GROUP BY h.id
                    ORDER BY h.homepage_priority DESC, h.id DESC");
        foreach ($fParams as $param => $value) {
            $db->bind($param, $value);
        }
        $facilities = $db->resultSet();
    } catch (Exception $e) {
        $facilities = [];
    }
}

$facilityTotal = count($facilities);
$facilityTotalPages = max(1, (int)ceil($facilityTotal / $perPage));
if ($tab === 'facility' && $page > $facilityTotalPages) {
    $page = $facilityTotalPages;
}
$facilityOffset = ($page - 1) * $perPage;
$pagedFacilities = array_slice($facilities, $facilityOffset, $perPage);
?>

<div style="background:linear-gradient(120deg,#eafaff 0%,#d4f0ff 55%,#c5e9ff 100%); margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="fw-bold text-uppercase mb-4" style="color:#0090d8; font-size:clamp(1.8rem,3.6vw,2.8rem); letter-spacing:.5px;">Đặt khám theo bác sĩ</h1>
                <div class="d-flex flex-column gap-3" style="color:#0b3a5c; font-size:1.12rem; line-height:1.5;">
                    <div class="d-flex align-items-start gap-2"><i class="bi bi-check-circle-fill text-success mt-1"></i><span>Chủ động chọn bác sĩ tin tưởng, đặt càng sớm càng có cơ hội có số thứ tự thấp nhất, tránh hết số.</span></div>
                    <div class="d-flex align-items-start gap-2"><i class="bi bi-check-circle-fill text-success mt-1"></i><span>Đặt khám theo giờ, không cần chờ lấy số thứ tự, chờ thanh toán (đối với csyt mở thanh toán online).</span></div>
                    <div class="d-flex align-items-start gap-2"><i class="bi bi-check-circle-fill text-success mt-1"></i><span>Được hoàn phí khám nếu hủy phiếu.</span></div>
                    <div class="d-flex align-items-start gap-2"><i class="bi bi-check-circle-fill text-success mt-1"></i><span>Được hưởng chính sách hoàn tiền khi đặt lịch trên hệ thống (đối với các csyt tư có áp dụng).</span></div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <img src="<?php echo $base_url; ?>/uploads/illustrations/doctor_team.png" alt="Đội ngũ bác sĩ" class="img-fluid" style="max-height:320px; object-fit:contain; filter:drop-shadow(0 12px 24px rgba(0,120,180,.18));">
            </div>
        </div>
    </div>
</div>

<div class="py-4" style="background:#eaf7ff; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container">
        <div class="d-flex align-items-center gap-3 mb-4">
            <form class="d-flex align-items-center gap-3 bg-white rounded-pill shadow-sm p-2 flex-fill" style="max-width: 860px;" method="get" action="doctor_booking.php">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <?php foreach ($specialtyFilters as $sv): ?><input type="hidden" name="specialty_id[]" value="<?php echo (int)$sv; ?>"><?php endforeach; ?>
                <?php foreach ($titleFilters as $tv): ?><input type="hidden" name="academic_title[]" value="<?php echo htmlspecialchars($tv); ?>"><?php endforeach; ?>
                <?php foreach ($genderFilters as $gv): ?><input type="hidden" name="gender[]" value="<?php echo htmlspecialchars($gv); ?>"><?php endforeach; ?>
                <?php foreach ($hospitalFilters as $hv): ?><input type="hidden" name="hospital_id[]" value="<?php echo (int)$hv; ?>"><?php endforeach; ?>
                <?php foreach ($provinceFilters as $pv): ?><input type="hidden" name="province[]" value="<?php echo htmlspecialchars($pv); ?>"><?php endforeach; ?>
                <div class="input-group flex-fill">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 shadow-none py-2" placeholder="<?php echo $tab === 'facility' ? 'Tìm kiếm cơ sở y tế' : 'Tìm kiếm bác sĩ'; ?>">
                </div>
                <button class="btn btn-premium-primary rounded-circle flex-shrink-0" style="width:48px;height:48px;" type="submit"><i class="bi bi-search"></i></button>
            </form>
            <button type="button" class="btn btn-outline-info rounded-pill px-4 py-3 fw-bold flex-shrink-0 bg-white" data-bs-toggle="modal" data-bs-target="#<?php echo $tab === 'facility' ? 'provinceFilterModal' : 'doctorFilterModal'; ?>"><i class="bi bi-sliders me-2"></i>Bộ lọc</button>
        </div>

        <div class="d-flex justify-content-center gap-2 mb-4">
            <a href="doctor_booking.php?tab=doctor<?php echo $keyword !== '' ? '&q=' . urlencode($keyword) : ''; ?>" class="btn rounded-pill px-4 fw-bold <?php echo $tab === 'doctor' ? 'btn-premium-primary text-white' : 'btn-outline-info bg-white'; ?>">Bác sĩ</a>
            <a href="doctor_booking.php?tab=facility<?php echo $keyword !== '' ? '&q=' . urlencode($keyword) : ''; ?>" class="btn rounded-pill px-4 fw-bold <?php echo $tab === 'facility' ? 'btn-premium-primary text-white' : 'btn-outline-info bg-white'; ?>">Cơ sở y tế</a>
        </div>

        <?php if ($tab === 'doctor'): ?>
        <?php if ($hasDoctorFilter || $keyword !== ''): ?>
            <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small">Tìm thấy <strong><?php echo count($doctors); ?></strong> bác sĩ</span>
                <a href="doctor_booking.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-x-lg me-1"></i>Xóa bộ lọc</a>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-4">
            <?php foreach ($pagedDoctors as $doctor): ?>
                <?php
                    $displayRating = (int)($doctor['review_count'] ?? 0) > 0 ? round((float)$doctor['avg_rating'], 1) : 0;
                    $commonPrice = (float)($doctor['common_service_price'] ?? 0);
                    $priceText = !empty($doctor['display_price_text'])
                        ? $doctor['display_price_text']
                        : ((float)$doctor['consultation_fee'] > 0 ? number_format((float)$doctor['consultation_fee'], 0, ',', '.') . 'đ' : ($commonPrice > 0 ? number_format($commonPrice, 0, ',', '.') . 'đ' : 'Đang cập nhật'));
                    $scheduleText = !empty($doctor['display_schedule_text']) ? $doctor['display_schedule_text'] : 'Đang cập nhật';
                    $treatmentText = !empty($doctor['treatment_text']) ? $doctor['treatment_text'] : 'Đang cập nhật...';
                ?>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-auto">
                                <img src="<?php echo htmlspecialchars(doctorBookingImage($doctor['doctor_image_url'] ?? '', $doctor['full_name'], $base_url)); ?>" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>" class="rounded-3" style="width:120px;height:120px;object-fit:cover; background:#eef9ff;" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($doctor['full_name']); ?>&background=00a8f0&color=fff&size=200';">
                            </div>
                            <div class="col">
                                <h5 class="fw-bold mb-3" style="color:#00a8f0;"><?php echo htmlspecialchars($doctor['full_name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                                <div class="row small g-2" style="color:#023f6d;">
                                    <div class="col-12 d-flex"><span class="fw-semibold flex-shrink-0" style="width:110px;">Chuyên trị:</span><span class="text-muted"><?php echo htmlspecialchars($treatmentText); ?></span></div>
                                    <div class="col-12 d-flex"><span class="fw-semibold flex-shrink-0" style="width:110px;">Lịch khám:</span><span class="text-muted"><?php echo htmlspecialchars($scheduleText); ?></span></div>
                                    <div class="col-12 d-flex"><span class="fw-semibold flex-shrink-0" style="width:110px;">Giá khám:</span><span class="text-muted"><?php echo htmlspecialchars($priceText); ?></span></div>
                                    <div class="col-12 d-flex"><span class="fw-semibold flex-shrink-0" style="width:110px;">Chuyên khoa:</span><span class="text-muted"><?php echo htmlspecialchars($doctor['specialty_name'] ?? 'Đa khoa'); ?></span></div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-start gap-2" style="max-width:680px;">
                                <i class="bi bi-geo-alt-fill text-info mt-1"></i>
                                <div>
                                    <div class="fw-bold" style="color:#023f6d;"><?php echo htmlspecialchars($doctor['hospital_name'] ?? 'Đang cập nhật'); ?> <i class="bi bi-patch-check-fill text-primary"></i></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($doctor['hospital_address'] ?? ''); ?></div>
                                </div>
                            </div>
                            <a href="doctor_detail.php?id=<?php echo (int)$doctor['id']; ?>" class="btn btn-premium-primary rounded-pill px-5 flex-shrink-0">Đặt ngay</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($doctors) === 0): ?>
                <div class="bg-white rounded-4 shadow-sm p-5 text-center text-muted">Không tìm thấy bác sĩ phù hợp.</div>
            <?php endif; ?>
        </div>
        <?php if ($doctorTotal > 0): ?>
            <nav class="mt-4 d-flex justify-content-center">
                <ul class="pagination gap-2 flex-wrap">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl(max(1, $page - 1))); ?>">‹</a></li>
                    <?php for ($i = 1; $i <= $doctorTotalPages; $i++): ?>
                        <?php if ($i === 1 || $i === $doctorTotalPages || abs($i - $page) <= 1): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl($i)); ?>"><?php echo $i; ?></a></li>
                        <?php elseif ($i === 2 || $i === $doctorTotalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link rounded-pill border-0 bg-transparent">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $doctorTotalPages ? 'disabled' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl(min($doctorTotalPages, $page + 1))); ?>">›</a></li>
                </ul>
            </nav>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($keyword !== '' || count($hospitalFilters) > 0 || count($provinceFilters) > 0): ?>
            <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small">Tìm thấy <strong><?php echo count($facilities); ?></strong> cơ sở y tế</span>
                <?php foreach ($provinceFilters as $pv): ?><span class="badge rounded-pill" style="background:#eef9ff; color:#00a8f0;"><?php echo htmlspecialchars($pv); ?></span><?php endforeach; ?>
                <a href="doctor_booking.php?tab=facility" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-x-lg me-1"></i>Xóa bộ lọc</a>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($pagedFacilities as $facility): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4 d-flex gap-3 align-items-center">
                            <img src="<?php echo htmlspecialchars(doctorBookingFacilityLogo($facility, $base_url)); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="rounded-3 flex-shrink-0" style="width:100px;height:100px;object-fit:contain; background:#fff;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';">
                            <div class="flex-fill">
                                <h5 class="fw-bold mb-2" style="color:#00a8f0;"><?php echo htmlspecialchars($facility['name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                                <div class="text-muted small mb-1"><i class="bi bi-geo-alt-fill text-info me-1"></i><?php echo htmlspecialchars($facility['address'] ?: 'Đang cập nhật địa chỉ'); ?></div>
                                <div class="text-muted small mb-3"><i class="bi bi-people-fill text-info me-1"></i><?php echo (int)$facility['doctor_count']; ?> bác sĩ</div>
                                <div class="d-flex gap-2">
                                    <a href="facility_detail.php?id=<?php echo (int)$facility['id']; ?>" class="btn btn-outline-info rounded-pill px-3 fw-bold">Xem chi tiết</a>
                                    <a href="facility_booking_options.php?facility=<?php echo urlencode($facility['name']); ?>" class="btn btn-premium-primary rounded-pill px-3">Đặt khám ngay</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($facilities) === 0): ?>
                <div class="col-12"><div class="bg-white rounded-4 shadow-sm p-5 text-center text-muted">Không tìm thấy cơ sở y tế phù hợp.</div></div>
            <?php endif; ?>
        </div>
        <?php if ($facilityTotal > 0): ?>
            <nav class="mt-4 d-flex justify-content-center">
                <ul class="pagination gap-2 flex-wrap">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl(max(1, $page - 1))); ?>">‹</a></li>
                    <?php for ($i = 1; $i <= $facilityTotalPages; $i++): ?>
                        <?php if ($i === 1 || $i === $facilityTotalPages || abs($i - $page) <= 1): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl($i)); ?>"><?php echo $i; ?></a></li>
                        <?php elseif ($i === 2 || $i === $facilityTotalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link rounded-pill border-0 bg-transparent">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $facilityTotalPages ? 'disabled' : ''; ?>"><a class="page-link rounded-pill border-0 shadow-sm" href="<?php echo htmlspecialchars(doctorBookingPageUrl(min($facilityTotalPages, $page + 1))); ?>">›</a></li>
                </ul>
            </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="provinceFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <form method="get" action="doctor_booking.php">
                <input type="hidden" name="tab" value="facility">
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
                    <a href="doctor_booking.php?tab=facility" class="btn btn-outline-info rounded-pill px-4"><i class="bi bi-arrow-clockwise me-1"></i>Đặt lại</a>
                    <button type="submit" class="btn btn-premium-primary rounded-pill px-4"><i class="bi bi-funnel me-1"></i>Lọc</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="doctorFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <form method="get" action="doctor_booking.php">
                <input type="hidden" name="tab" value="doctor">
                <?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($keyword); ?>"><?php endif; ?>
                <div class="modal-header border-0 p-0" style="background:#eaf7ff;">
                    <ul class="nav nav-tabs border-0 flex-fill px-2 pt-2" id="filterTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-specialty" type="button">Chuyên khoa</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-title" type="button">Học hàm/ học vị</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-gender" type="button">Giới tính</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-hospital" type="button">Cơ sở y tế</button></li>
                    </ul>
                    <button type="button" class="btn-close me-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="position-relative mb-4">
                        <span class="position-absolute top-50 translate-middle-y ps-3 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="filterSearchInput" class="form-control rounded-pill ps-5 py-2 border" placeholder="Tìm kiếm...">
                    </div>
                    <div class="tab-content" style="max-height:330px; overflow-y:auto;">
                        <div class="tab-pane fade show active" id="tab-specialty">
                            <div class="row g-3 filter-group">
                                <?php foreach ($specialties as $spec): ?>
                                    <div class="col-md-4 col-6 filter-option">
                                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                            <input type="checkbox" class="form-check-input" name="specialty_id[]" value="<?php echo (int)$spec['id']; ?>" <?php echo in_array((int)$spec['id'], $specialtyFilters, true) ? 'checked' : ''; ?>>
                                            <span class="filter-label"><?php echo htmlspecialchars($spec['name']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-title">
                            <div class="row g-3 filter-group">
                                <?php foreach ($titleList as $title): ?>
                                    <div class="col-md-4 col-6 filter-option">
                                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                            <input type="checkbox" class="form-check-input" name="academic_title[]" value="<?php echo htmlspecialchars($title); ?>" <?php echo in_array($title, $titleFilters, true) ? 'checked' : ''; ?>>
                                            <span class="filter-label"><?php echo htmlspecialchars($title); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-gender">
                            <div class="row g-3 filter-group">
                                <?php foreach ($genderList as $gender): ?>
                                    <div class="col-md-4 col-6 filter-option">
                                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                            <input type="checkbox" class="form-check-input" name="gender[]" value="<?php echo htmlspecialchars($gender); ?>" <?php echo in_array($gender, $genderFilters, true) ? 'checked' : ''; ?>>
                                            <span class="filter-label"><?php echo htmlspecialchars($gender); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-hospital">
                            <div class="row g-3 filter-group">
                                <?php foreach ($hospitalList as $hospitalItem): ?>
                                    <div class="col-md-6 col-12 filter-option">
                                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                            <input type="checkbox" class="form-check-input" name="hospital_id[]" value="<?php echo (int)$hospitalItem['id']; ?>" <?php echo in_array((int)$hospitalItem['id'], $hospitalFilters, true) ? 'checked' : ''; ?>>
                                            <span class="filter-label"><?php echo htmlspecialchars($hospitalItem['name']); ?> (<?php echo (int)$hospitalItem['doctor_count']; ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0" style="background:#f8fafc;">
                    <a href="doctor_booking.php" class="btn btn-outline-info rounded-pill px-4"><i class="bi bi-arrow-clockwise me-1"></i>Đặt lại</a>
                    <button type="submit" class="btn btn-premium-primary rounded-pill px-4"><i class="bi bi-funnel me-1"></i>Lọc</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('filterSearchInput');
    function applySearch() {
        const term = (searchInput.value || '').toLowerCase().trim();
        const activePane = document.querySelector('#doctorFilterModal .tab-pane.active');
        if (!activePane) return;
        activePane.querySelectorAll('.filter-option').forEach(function (option) {
            const label = (option.querySelector('.filter-label')?.textContent || '').toLowerCase();
            option.style.display = label.includes(term) ? '' : 'none';
        });
    }
    searchInput?.addEventListener('input', applySearch);
    document.querySelectorAll('#filterTabs button').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () { searchInput.value = ''; applySearch(); });
    });

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
    </div>
</div>

<?php include 'includes/footer.php'; ?>
