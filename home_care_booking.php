<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
$keyword = trim($_GET['q'] ?? '');
$province = trim($_GET['province'] ?? '');
$where = "WHERE lp.is_active = 1 AND lp.category = 'homecare'";
$params = [];
if ($keyword !== '') {
    $where .= " AND (lp.name LIKE :keyword OR h.name LIKE :keyword OR lps.name LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($province !== '') {
    $provinceShort = trim(str_replace(['Thành phố ', 'Tỉnh '], '', $province));
    $where .= " AND (h.address LIKE :province OR h.address LIKE :province_short)";
    $params[':province'] = '%' . $province . '%';
    $params[':province_short'] = '%' . $provinceShort . '%';
}
$provinces = ['Thành phố Hồ Chí Minh', 'Thành phố Hà Nội', 'Thành phố Cần Thơ', 'Thành phố Hải Phòng', 'Thành phố Đà Nẵng', 'Tỉnh An Giang', 'Tỉnh Bà Rịa - Vũng Tàu', 'Tỉnh Bình Dương', 'Tỉnh Đồng Nai', 'Tỉnh Long An', 'Tỉnh Tiền Giang', 'Tỉnh Vĩnh Long', 'Tỉnh Đồng Tháp', 'Tỉnh Kiên Giang', 'Tỉnh Hậu Giang', 'Tỉnh Sóc Trăng', 'Tỉnh Bạc Liêu', 'Tỉnh Cà Mau', 'Tỉnh Bến Tre', 'Tỉnh Trà Vinh', 'Tỉnh Bình Thuận'];
try {
    $db->query("SELECT lp.*, h.name AS hospital_name, h.address, h.logo_url, GROUP_CONCAT(DISTINCT lps.name ORDER BY lps.sort_order ASC SEPARATOR '||') AS service_names
                FROM lab_packages lp
                INNER JOIN hospitals h ON h.id = lp.hospital_id
                LEFT JOIN lab_package_services lps ON lps.package_id = lp.id
                {$where}
                GROUP BY lp.id
                ORDER BY lp.id DESC");
    foreach ($params as $param => $value) $db->bind($param, $value);
    $packages = $db->resultSet();
} catch (Exception $e) {
    $packages = [];
}
$totalPackages = count($packages);
$perPage = 6;
$totalPages = max(1, (int)ceil($totalPackages / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$packages = array_slice($packages, ($page - 1) * $perPage, $perPage);
function homeCarePageUrl($page) {
    $query = $_GET;
    $query['page'] = $page;
    return 'home_care_booking.php?' . http_build_query($query);
}
function homeCareImage($path, $base_url) {
    if (empty($path)) return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
    return preg_match('#^https?://#', $path) ? $path : $base_url . '/' . ltrim($path, '/');
}
function homeCareLogo($package, $base_url) {
    if (!empty($package['icon_path'])) return homeCareImage($package['icon_path'], $base_url);
    $name = $package['hospital_name'] ?? '';
    if (stripos($name, 'MEDLATEC') !== false) return $base_url . '/uploads/hospitals/medlatec_cantho_logo.png';
    if (stripos($name, 'VNVC') !== false) return 'https://sanvieclamcantho.com/upload/imagelogo/vnvc1724469700.png';
    if (stripos($name, 'Long Châu') !== false) return 'https://cdn-new.topcv.vn/unsafe/https://static.topcv.vn/company_logos/IinkQQY7z2A7AQXZ84KKTNq83awObGLS_1650511186____11070390482b3374c7cee11f4b9f6fdf.png';
    if (stripos($name, 'DIAG') !== false) return $base_url . '/uploads/hospitals/diag_logo.svg';
    if (stripos($name, 'MEDIC') !== false) return $base_url . '/uploads/hospitals/medic_logo.jpg';
    if (stripos($name, 'Y Dược') !== false) return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRUed7lW_iE0g1ImT9gSZmy0PdfBiewVl5obQ&s';
    if (stripos($name, 'Trung ương') !== false) return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSlTz_v9XDRvF_FcHXFdA0GicixowqlMdgmQg&s';
    return homeCareImage($package['logo_url'] ?? '', $base_url);
}
?>

<div style="background:linear-gradient(90deg,#eefbff 0%,#d9f4ff 100%); margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);">
    <div class="container py-5"><div class="row align-items-center g-4"><div class="col-lg-7"><div class="bg-white rounded-5 shadow-sm p-4 p-md-5">
        <h1 class="fw-bold text-uppercase mb-3" style="color:#00a8f0; font-size:clamp(2rem,4vw,3rem);">Y tế tại nhà</h1>
        <div class="d-flex flex-column gap-3" style="color:#023f6d; font-size:1.05rem;">
            <div><i class="bi bi-check-circle-fill text-success me-2"></i>Đa dạng dịch vụ y tế công/tư tại nhà.</div>
            <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hoàn phí nếu hủy phiếu đúng quy định.</div>
            <div><i class="bi bi-check-circle-fill text-success me-2"></i>Được hưởng chính sách hoàn tiền khi đặt lịch trên hệ thống.</div>
        </div>
        <hr class="my-4" style="border-color:#00b5f1; opacity:.6;"><div style="color:#023f6d;">Liên hệ <strong>chuyên gia</strong> để tư vấn thêm <strong class="text-info ms-2"><i class="bi bi-telephone-fill"></i> 19002115</strong></div>
    </div></div><div class="col-lg-5 text-center d-none d-lg-block"><img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fprod-partner%2Ffb51bd32-6d14-4f80-ad60-8a31c9f0e063-y-te-tai-nha.webp&w=1920&q=75" alt="Y tế tại nhà" class="img-fluid" style="max-height:340px; object-fit:contain; filter:drop-shadow(0 12px 24px rgba(0,120,180,.18));"></div></div></div>
</div>

<div class="py-4" style="background:#eaf7ff; margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);"><div class="container">
    <div class="d-flex align-items-center gap-3 mb-4"><form class="d-flex align-items-center gap-3 bg-white rounded-pill shadow-sm p-2 flex-fill" style="max-width:860px;" method="get" action="home_care_booking.php"><div class="input-group flex-fill"><span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span><input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 shadow-none py-2" placeholder="Tìm kiếm cơ sở y tế"></div><button class="btn btn-premium-primary rounded-circle flex-shrink-0" style="width:48px;height:48px;" type="submit"><i class="bi bi-search"></i></button></form><button type="button" class="btn btn-outline-info rounded-pill px-4 py-3 fw-bold flex-shrink-0 bg-white" data-bs-toggle="modal" data-bs-target="#homeCareFilterModal"><i class="bi bi-sliders me-2"></i>Bộ lọc</button></div>
    <div class="row g-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-lg-6"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4 d-flex gap-3 align-items-start"><img src="<?php echo htmlspecialchars(homeCareLogo($package, $base_url)); ?>" alt="<?php echo htmlspecialchars($package['hospital_name']); ?>" style="width:96px;height:96px;object-fit:contain;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';"><div class="flex-fill"><h5 class="fw-bold mb-2" style="color:#023f6d;"><?php echo htmlspecialchars($package['name']); ?></h5><div class="text-muted small mb-2"><i class="bi bi-hospital text-info"></i> <?php echo htmlspecialchars($package['hospital_name']); ?></div><div class="text-muted small mb-2"><i class="bi bi-geo-alt text-warning"></i> <?php echo htmlspecialchars($package['address'] ?: 'Đang cập nhật địa chỉ'); ?></div><div class="text-warning fw-bold mb-2">Giá: <?php echo (float)$package['price'] > 0 ? number_format((float)$package['price'], 0, ',', '.') . 'đ' : 'Đang cập nhật'; ?></div><?php $services = array_filter(explode('||', $package['service_names'] ?? '')); ?><?php if (count($services) > 0): ?><div class="small mb-3" style="color:#023f6d;"><?php foreach ($services as $service): ?><div>• <?php echo htmlspecialchars($service); ?></div><?php endforeach; ?></div><?php endif; ?><a href="lab_package_booking.php?package_id=<?php echo (int)$package['id']; ?>" class="btn btn-premium-primary rounded-pill px-4">Đặt lịch ngay</a></div></div></div></div>
        <?php endforeach; ?>
        <?php if (count($packages) === 0): ?><div class="col-12"><div class="bg-white rounded-4 shadow-sm p-5 text-center text-muted">Chưa có gói y tế tại nhà phù hợp.</div></div><?php endif; ?>
    </div>
    <?php if ($totalPackages > 0): ?><nav class="mt-4"><ul class="pagination justify-content-center align-items-center gap-2 mb-0"><?php for ($i = 1; $i <= $totalPages; $i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link rounded-3 border-0 shadow-sm fw-bold" href="<?php echo htmlspecialchars(homeCarePageUrl($i)); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</div></div>

<div class="modal fade" id="homeCareFilterModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0 rounded-4 overflow-hidden"><div class="modal-header border-0 justify-content-center position-relative" style="background:#eaf7ff;"><h5 class="modal-title fw-bold" style="color:#00a8f0;">Chọn khu vực</h5><button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><div class="fw-bold mb-3" style="color:#00a8f0;">Tỉnh/ Thành phố</div><div class="d-flex flex-wrap gap-2"><?php foreach ($provinces as $item): ?><a href="home_care_booking.php?province=<?php echo urlencode($item); ?><?php echo $keyword !== '' ? '&q=' . urlencode($keyword) : ''; ?>" class="btn rounded-pill px-3 py-2 <?php echo $province === $item ? 'btn-info text-white' : 'btn-outline-info'; ?>"><?php echo htmlspecialchars($item); ?></a><?php endforeach; ?></div></div><div class="modal-footer border-0" style="background:#eaf7ff;"><a href="home_care_booking.php" class="btn btn-outline-info rounded-pill px-4">Đặt lại</a><button type="button" class="btn btn-premium-primary rounded-pill px-4" data-bs-dismiss="modal">Áp dụng</button></div></div></div></div>
<?php include 'includes/footer.php'; ?>
