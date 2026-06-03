<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
$keyword = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$province = trim($_GET['province'] ?? '');
$district = trim($_GET['district'] ?? '');
$selectedDistricts = array_values(array_filter(array_map('trim', explode(',', $district))));
function locationKeyword($value) {
    return trim(str_replace(['Thành phố ', 'Tỉnh ', 'Quận ', 'Huyện ', 'Thị xã '], '', $value));
}
$where = "WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'";
$params = [];
if ($keyword !== '') {
    $where .= " AND (h.name LIKE :keyword OR h.address LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($type === 'hospital') {
    $where .= " AND h.facility_type IN ('public', 'private')";
} elseif ($type === 'clinic') {
    $where .= " AND h.facility_type NOT IN ('public', 'private')";
}
if ($province !== '') {
    $where .= " AND (h.address LIKE :province OR h.address LIKE :province_short)";
    $params[':province'] = '%' . $province . '%';
    $params[':province_short'] = '%' . locationKeyword($province) . '%';
}
if (count($selectedDistricts) > 0) {
    $districtWheres = [];
    foreach ($selectedDistricts as $index => $districtItem) {
        $districtParam = ':district_' . $index;
        $districtShortParam = ':district_short_' . $index;
        $districtWheres[] = "(h.address LIKE {$districtParam} OR h.address LIKE {$districtShortParam})";
        $params[$districtParam] = '%' . $districtItem . '%';
        $params[$districtShortParam] = '%' . locationKeyword($districtItem) . '%';
    }
    $where .= ' AND (' . implode(' OR ', $districtWheres) . ')';
}
try {
    $db->query("SELECT h.*, COUNT(DISTINCT a.id) AS successful_booking_count
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                LEFT JOIN doctors d ON d.hospital_id = h.id
                LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status IN ('confirmed', 'completed')
                {$where}
                GROUP BY h.id
                ORDER BY successful_booking_count DESC, h.id DESC");
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    $hospitals = $db->resultSet();
} catch (Exception $e) {
    $hospitals = [];
}
function bookingFacilityDedupeKey($hospital) {
    $name = mb_strtolower($hospital['name'] ?? '', 'UTF-8');
    if (strpos($name, 'medlatec') !== false) return 'medlatec';
    if (strpos($name, 'da liễu') !== false) return 'da_lieu';
    if (strpos($name, 'nhi đồng') !== false) return 'nhi_dong';
    if (strpos($name, 'hoàng mỹ') !== false || strpos($name, 'hoàn mỹ') !== false) return 'hoan_my';
    if (strpos($name, 'y dược') !== false) return 'y_duoc';
    if (strpos($name, 'trung ương') !== false) return 'trung_uong';
    return '';
}
function bookingFacilityDedupeHospitals($hospitals) {
    $seen = [];
    $result = [];
    foreach ($hospitals as $hospital) {
        $key = bookingFacilityDedupeKey($hospital);
        if ($key !== '') {
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
        }
        $result[] = $hospital;
    }
    return $result;
}
$hospitals = bookingFacilityDedupeHospitals($hospitals);
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
$totalHospitals = count($hospitals);
$perPage = 6;
$totalPages = max(1, (int)ceil($totalHospitals / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
if (!$showAll) {
    $hospitals = array_slice($hospitals, ($page - 1) * $perPage, $perPage);
}
function bookingFacilityPageUrl($page = null, $all = false) {
    $query = $_GET;
    if ($all) {
        $query['all'] = '1';
        unset($query['page']);
    } else {
        unset($query['all']);
        $query['page'] = $page;
    }
    return 'booking_at_facility.php?' . http_build_query($query);
}
function bookingFacilityImage($path, $base_url) {
    if (empty($path)) return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . $path;
}
function bookingFacilityLogo($hospital, $base_url) {
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
    return bookingFacilityImage($hospital['logo_url'] ?? '', $base_url);
}
$districtsByProvince = [
    'Thành phố Hồ Chí Minh' => ['Quận 1', 'Quận 3', 'Quận 4', 'Quận 5', 'Quận 6', 'Quận 7', 'Quận 8', 'Quận 10', 'Quận 11', 'Quận 12', 'Quận Phú Nhuận', 'Quận Bình Thạnh', 'Quận Gò Vấp', 'Quận Tân Bình', 'Quận Tân Phú', 'Quận Bình Tân', 'Huyện Bình Chánh', 'Huyện Hóc Môn', 'Huyện Củ Chi', 'Huyện Nhà Bè', 'Huyện Cần Giờ', 'Thành phố Thủ Đức'],
    'Thành phố Cần Thơ' => ['Quận Ninh Kiều', 'Quận Bình Thủy', 'Quận Cái Răng', 'Quận Ô Môn', 'Quận Thốt Nốt', 'Huyện Phong Điền', 'Huyện Cờ Đỏ', 'Huyện Thới Lai', 'Huyện Vĩnh Thạnh'],
    'Tỉnh Bình Dương' => ['Thành phố Thủ Dầu Một', 'Thành phố Dĩ An', 'Thành phố Thuận An', 'Thị xã Bến Cát', 'Thị xã Tân Uyên', 'Huyện Bắc Tân Uyên', 'Huyện Bàu Bàng', 'Huyện Dầu Tiếng', 'Huyện Phú Giáo'],
    'Thành phố Đà Nẵng' => ['Quận Hải Châu', 'Quận Thanh Khê', 'Quận Sơn Trà', 'Quận Ngũ Hành Sơn', 'Quận Liên Chiểu', 'Quận Cẩm Lệ', 'Huyện Hòa Vang'],
    'Thành phố Hà Nội' => ['Quận Ba Đình', 'Quận Hoàn Kiếm', 'Quận Đống Đa', 'Quận Hai Bà Trưng', 'Quận Cầu Giấy', 'Quận Thanh Xuân', 'Quận Hoàng Mai', 'Quận Long Biên', 'Quận Hà Đông', 'Quận Tây Hồ', 'Quận Nam Từ Liêm', 'Quận Bắc Từ Liêm'],
    'Tỉnh Đồng Nai' => ['Thành phố Biên Hòa', 'Thành phố Long Khánh', 'Huyện Nhơn Trạch', 'Huyện Long Thành', 'Huyện Trảng Bom', 'Huyện Vĩnh Cửu', 'Huyện Thống Nhất', 'Huyện Cẩm Mỹ', 'Huyện Xuân Lộc', 'Huyện Định Quán', 'Huyện Tân Phú']
];
$provinces = array_keys($districtsByProvince);
?>

<div style="background:linear-gradient(90deg,#eefbff 0%,#d9f4ff 100%); margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="bg-white rounded-5 shadow-sm p-4 p-md-5">
                    <h1 class="fw-bold text-uppercase mb-3" style="color:#00a8f0; font-size:clamp(2rem,4vw,3rem);">Đặt khám tại cơ sở</h1>
                    <div class="d-flex flex-column gap-3" style="color:#023f6d; font-size:1.05rem;">
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Đặt khám theo giờ, không cần chờ lấy số thứ tự, chờ thanh toán.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hoàn phí khám nếu hủy phiếu đúng quy định.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Đặt càng sớm, càng có cơ hội có số thứ tự thấp nhất, tránh hết số.</div>
                        <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hưởng chính sách hoàn tiền khi đặt lịch trên hệ thống.</div>
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
    <div class="d-flex align-items-center gap-3 mb-3">
        <form class="d-flex align-items-center gap-3 bg-white rounded-pill shadow-sm p-2 flex-fill" style="max-width: 860px;" method="get" action="booking_at_facility.php">
            <?php if ($type !== ''): ?>
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            <?php endif; ?>
            <div class="input-group flex-fill">
                <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 shadow-none py-2" placeholder="Tìm kiếm cơ sở y tế">
            </div>
            <button class="btn btn-premium-primary rounded-circle flex-shrink-0" style="width:48px;height:48px;" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <button type="button" class="btn btn-outline-info rounded-pill px-4 py-3 fw-bold flex-shrink-0 bg-white" data-bs-toggle="modal" data-bs-target="#provinceFilterModal"><i class="bi bi-sliders me-2"></i>Bộ lọc</button>
    </div>

    <div class="d-flex overflow-auto gap-3 mb-4 pb-2">
        <a href="booking_at_facility.php" class="btn rounded-pill px-4 py-2 fw-bold <?php echo $type === '' ? 'text-white' : 'text-info'; ?>" style="background:<?php echo $type === '' ? '#00a8f0' : '#fff'; ?>;">Tất cả</a>
        <a href="booking_at_facility.php?type=hospital" class="btn rounded-pill px-4 py-2 fw-bold <?php echo $type === 'hospital' ? 'text-white' : 'text-info'; ?>" style="background:<?php echo $type === 'hospital' ? '#00a8f0' : '#fff'; ?>;">Bệnh viện</a>
        <a href="booking_at_facility.php?type=clinic" class="btn rounded-pill px-4 py-2 fw-bold <?php echo $type === 'clinic' ? 'text-white' : 'text-info'; ?>" style="background:<?php echo $type === 'clinic' ? '#00a8f0' : '#fff'; ?>;">Phòng khám/ Phòng mạch/ Xét nghiệm/ Khác</a>
    </div>

    <div class="row g-4">
        <?php foreach ($hospitals as $hospital): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 d-flex gap-3 align-items-center">
                        <img src="<?php echo htmlspecialchars(bookingFacilityLogo($hospital, $base_url)); ?>" alt="<?php echo htmlspecialchars($hospital['name']); ?>" style="width:100px;height:100px;object-fit:contain;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';">
                        <div class="flex-fill">
                            <h5 class="fw-bold mb-2" style="color:#023f6d;"><?php echo htmlspecialchars($hospital['name']); ?> <i class="bi bi-patch-check-fill text-primary"></i></h5>
                            <div class="text-muted small mb-3"><i class="bi bi-geo-alt text-warning"></i> <?php echo htmlspecialchars($hospital['address'] ?: 'Đang cập nhật địa chỉ'); ?></div>
                            <a href="facility_booking_options.php?facility=<?php echo urlencode($hospital['name']); ?>" class="btn btn-premium-primary rounded-pill px-4">Đặt khám ngay</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($hospitals) === 0): ?>
            <div class="col-12"><div class="bg-white rounded-4 shadow-sm p-5 text-center text-muted">Không tìm thấy cơ sở y tế phù hợp.</div></div>
        <?php endif; ?>
    </div>

    <?php if (!$showAll && $totalPages > 1): ?>
        <nav class="mt-4" aria-label="Phân trang cơ sở y tế">
            <ul class="pagination justify-content-center align-items-center gap-2 mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-3 border-0 shadow-sm" href="<?php echo $page > 1 ? htmlspecialchars(bookingFacilityPageUrl($page - 1)) : '#'; ?>">‹</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i <= 5 || $i === $totalPages || abs($i - $page) <= 1): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link rounded-3 border-0 shadow-sm fw-bold" href="<?php echo htmlspecialchars(bookingFacilityPageUrl($i)); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php elseif ($i === 6): ?>
                        <li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-3 border-0 shadow-sm" href="<?php echo $page < $totalPages ? htmlspecialchars(bookingFacilityPageUrl($page + 1)) : '#'; ?>">›</a>
                </li>
                <li class="page-item">
                    <a class="page-link rounded-3 border-0 shadow-sm fw-bold px-4" href="<?php echo htmlspecialchars(bookingFacilityPageUrl(null, true)); ?>">Xem tất cả</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="provinceFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 justify-content-center position-relative" style="background:#eaf7ff;">
                <h5 class="modal-title fw-bold" style="color:#00a8f0;">Chọn khu vực</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0" style="min-height:430px;">
                    <div class="col-md-5 border-end" style="max-height:430px; overflow-y:auto;">
                        <?php foreach ($provinces as $index => $item): ?>
                            <button type="button" class="province-filter-option d-block w-100 text-start border-0 bg-white px-4 py-3 <?php echo ($province === $item || ($province === '' && $index === 0)) ? 'fw-bold text-info' : 'text-dark'; ?>" data-province="<?php echo htmlspecialchars($item); ?>">
                                <?php echo htmlspecialchars($item); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-7" style="max-height:430px; overflow-y:auto;">
                        <?php foreach ($districtsByProvince as $provinceName => $districts): ?>
                            <div class="district-list p-4 <?php echo ($province === $provinceName || ($province === '' && $provinceName === $provinces[0])) ? '' : 'd-none'; ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>">
                                <?php foreach ($districts as $item): ?>
                                    <label class="d-flex align-items-center gap-3 py-2" style="cursor:pointer;">
                                        <input type="checkbox" class="form-check-input district-filter-option" value="<?php echo htmlspecialchars($item); ?>" <?php echo in_array($item, $selectedDistricts, true) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($item); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0" style="background:#eaf7ff;">
                <a href="booking_at_facility.php" class="btn btn-outline-info rounded-pill px-4">Đặt lại</a>
                <button type="button" id="applyProvinceFilter" class="btn btn-premium-primary rounded-pill px-4">Áp dụng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let selectedProvince = <?php echo json_encode($province !== '' ? $province : ($provinces[0] ?? '')); ?>;
    document.querySelectorAll('.province-filter-option').forEach(function (button) {
        button.addEventListener('click', function () {
            selectedProvince = this.dataset.province;
            document.querySelectorAll('.province-filter-option').forEach(function (item) {
                item.classList.remove('fw-bold', 'text-info');
                item.classList.add('text-dark');
            });
            this.classList.add('fw-bold', 'text-info');
            this.classList.remove('text-dark');
            document.querySelectorAll('.district-list').forEach(function (list) {
                list.classList.toggle('d-none', list.dataset.province !== selectedProvince);
            });
        });
    });
    document.getElementById('applyProvinceFilter')?.addEventListener('click', function () {
        const activeList = document.querySelector('.district-list:not(.d-none)');
        const selectedDistricts = activeList ? Array.from(activeList.querySelectorAll('.district-filter-option:checked')).map(function (item) { return item.value; }) : [];
        const params = new URLSearchParams();
        if (selectedProvince) params.set('province', selectedProvince);
        if (selectedDistricts.length) params.set('district', selectedDistricts.join(','));
        <?php if ($type !== ''): ?>params.set('type', <?php echo json_encode($type); ?>);<?php endif; ?>
        <?php if ($keyword !== ''): ?>params.set('q', <?php echo json_encode($keyword); ?>);<?php endif; ?>
        window.location.href = 'booking_at_facility.php?' + params.toString();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
