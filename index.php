<?php 
require_once 'config/database.php';
include 'includes/header.php'; 

$db = new Database();
$db->query("SELECT * FROM specialties ORDER BY id ASC LIMIT 6");
$specialties = $db->resultSet();

try {
    $db->query("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $homepageBanners = $db->resultSet();
} catch (Exception $e) {
    $homepageBanners = [];
}

try {
    $db->query("SELECT DISTINCT h.*
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                ORDER BY CASE
                    WHEN h.name LIKE '%Da liễu%' THEN 0
                    WHEN h.name LIKE '%Nhi đồng%' THEN 1
                    WHEN h.name LIKE '%Hoàng Mỹ%' OR h.name LIKE '%Hoàn Mỹ%' THEN 2
                    ELSE 3
                END, CASE WHEN h.logo_url IS NOT NULL AND h.logo_url <> '' THEN 0 ELSE 1 END, h.id DESC
                LIMIT 20");
    $partnerHospitals = $db->resultSet();
} catch (Exception $e) {
    $partnerHospitals = [];
}

try {
    $db->query("SELECT h.*, COUNT(a.id) AS successful_booking_count
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                LEFT JOIN doctors d ON d.hospital_id = h.id
                LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status IN ('confirmed', 'completed')
                WHERE u.id IS NOT NULL AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                GROUP BY h.id
                ORDER BY CASE
                    WHEN h.name LIKE '%Da liễu%' THEN 0
                    WHEN h.name LIKE '%Nhi đồng%' THEN 1
                    WHEN h.name LIKE '%Hoàng Mỹ%' OR h.name LIKE '%Hoàn Mỹ%' THEN 2
                    ELSE 3
                END, CASE WHEN h.logo_url IS NOT NULL AND h.logo_url <> '' THEN 0 ELSE 1 END, successful_booking_count DESC, h.rating DESC, h.id DESC
                LIMIT 12");
    $featuredHospitals = $db->resultSet();
} catch (Exception $e) {
    $featuredHospitals = [];
}

try {
    $db->query("SELECT hs.id, hs.name, hs.detail_text, h.name AS hospital_name, h.address
                FROM hospital_services hs
                INNER JOIN hospitals h ON h.id = hs.hospital_id
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                ORDER BY hs.id DESC
                LIMIT 30");
    $searchServices = $db->resultSet();
} catch (Exception $e) {
    $searchServices = [];
}

try {
    $db->query("SELECT lp.*, h.name AS hospital_name, h.logo_url, COALESCE(NULLIF(lp.price, 0), MIN(NULLIF(lps.price, 0)), 0) AS display_price
                FROM lab_packages lp
                INNER JOIN hospitals h ON h.id = lp.hospital_id
                LEFT JOIN lab_package_services lps ON lps.package_id = lp.id
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE lp.is_active = 1
                  AND lp.category IN ('health', 'lab', 'vaccination')
                  AND COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                GROUP BY lp.id
                ORDER BY lp.id DESC
                LIMIT 60");
    $homepagePackages = $db->resultSet();
} catch (Exception $e) {
    $homepagePackages = [];
}

try {
    $db->query("SELECT * FROM news_posts WHERE is_active = 1 ORDER BY sort_order ASC, published_at DESC, id DESC LIMIT 5");
    $homepageNewsPosts = $db->resultSet();
} catch (Exception $e) {
    $homepageNewsPosts = [];
}

try {
    $db->query("CREATE TABLE IF NOT EXISTS site_visits (id INT AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(128) NOT NULL, visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_session_month (session_id, visited_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
    if (($_SESSION['monthly_visit_recorded'] ?? '') !== date('Y-m')) {
        $db->query("INSERT INTO site_visits (session_id, visited_at) VALUES (:session_id, NOW())");
        $db->bind(':session_id', session_id());
        $db->execute();
        $_SESSION['monthly_visit_recorded'] = date('Y-m');
    }
    $db->query("SELECT COUNT(*) AS total FROM appointments");
    $appointmentStats = $db->single();
    $db->query("SELECT COUNT(DISTINCT h.id) AS total FROM hospitals h LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' WHERE u.id IS NULL OR COALESCE(u.hospital_approval_status, 'approved') = 'approved'");
    $hospitalStats = $db->single();
    $db->query("SELECT COUNT(*) AS total FROM doctors");
    $doctorStats = $db->single();
    $db->query("SELECT COUNT(*) AS total FROM site_visits WHERE visited_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $visitStats = $db->single();
    $homepageStats = [
        'appointments' => (int)($appointmentStats['total'] ?? 0),
        'hospitals' => (int)($hospitalStats['total'] ?? 0),
        'doctors' => (int)($doctorStats['total'] ?? 0),
        'visits' => (int)($visitStats['total'] ?? 0)
    ];
} catch (Exception $e) {
    $homepageStats = ['appointments' => 0, 'hospitals' => 0, 'doctors' => 0, 'visits' => 0];
}

function homepagePriorityKey($hospital) {
    $name = mb_strtolower($hospital['name'] ?? '', 'UTF-8');
    if (strpos($name, 'medlatec') !== false) {
        return 'medlatec';
    }
    if (strpos($name, 'da liễu') !== false) {
        return 'da_lieu';
    }
    if (strpos($name, 'nhi đồng') !== false) {
        return 'nhi_dong';
    }
    if (strpos($name, 'hoàng mỹ') !== false || strpos($name, 'hoàn mỹ') !== false) {
        return 'hoan_my';
    }
    return '';
}

function homepageDedupePriorityHospitals($hospitals) {
    $seen = [];
    $result = [];
    foreach ($hospitals as $hospital) {
        $key = homepagePriorityKey($hospital);
        if ($key !== '') {
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
        }
        $result[] = $hospital;
    }
    return $result;
}

$partnerHospitals = homepageDedupePriorityHospitals($partnerHospitals);
$featuredHospitals = homepageDedupePriorityHospitals($featuredHospitals);

function homepageBannerImageSrc($path) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}

function homepageHospitalLogo($hospital) {
    $name = $hospital['name'] ?? '';
    if (stripos($name, 'MEDLATEC') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/medlatec_logo.png';
    }
    if (stripos($name, 'Long Châu') !== false || stripos($name, 'FPT') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/phuongchau_logo.png';
    }
    if (stripos($name, 'VNVC') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/8_logo_image_1779368204.webp';
    }
    if (!empty($hospital['logo_url'])) {
        $logoUrl = $hospital['logo_url'];
        if (preg_match('#^https?://#', $logoUrl)) {
            return $logoUrl;
        }
        if (file_exists(__DIR__ . '/' . ltrim($logoUrl, '/'))) {
            return homepageImageSrc($logoUrl, '');
        }
    }
    if (stripos($name, 'DIAG') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/diag_logo.svg';
    }
    if (stripos($name, 'MEDIC') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/medic_logo.jpg';
    }
    if (stripos($name, 'Y Dược') !== false || stripos($name, 'Y Dược Cần Thơ') !== false) {
        return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRUed7lW_iE0g1ImT9gSZmy0PdfBiewVl5obQ&s';
    }
    if (stripos($name, 'Đa khoa Trung ương') !== false || stripos($name, 'Trung ương') !== false) {
        return 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSlTz_v9XDRvF_FcHXFdA0GicixowqlMdgmQg&s';
    }
    if (stripos($name, 'Da liễu') !== false || stripos($name, 'Da Liễu') !== false) {
        return 'https://benhviendalieucantho.vn/upload/image/logo/logobvdl.png';
    }
    if (stripos($name, 'Nhi đồng') !== false) {
        return 'https://cdn.haitrieu.com/wp-content/uploads/2022/09/logo-benh-vien-nhi-dong-can-tho-1024x1024.png';
    }
    if (stripos($name, 'Hoàng Mỹ') !== false || stripos($name, 'Hoàn Mỹ') !== false) {
        return $GLOBALS['base_url'] . '/uploads/hospitals/hoanmy_cuulong_logo.webp';
    }
    return 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';
}

function homepageImageSrc($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}

function homepagePackageImage($package) {
    if (!empty($package['icon_path'])) {
        return homepageImageSrc($package['icon_path'], '');
    }
    return homepageHospitalLogo(['name' => $package['hospital_name'] ?? '', 'logo_url' => $package['logo_url'] ?? '']);
}

function homepagePackageLink($package) {
    return 'lab_package_booking.php?package_id=' . (int)$package['id'];
}

function homepageNewsImageSrc($path) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}

function homepageNewsLink($post) {
    return !empty($post['link_url']) ? $post['link_url'] : '#';
}

function homepageNewsDate($post) {
    $text = date('d/m/Y, H:i', strtotime($post['published_at']));
    if (!empty($post['author'])) {
        $text .= ' - ' . $post['author'];
    }
    return $text;
}
?>

<!-- Hero Section -->
<div class="row align-items-center mb-5 position-relative overflow-hidden" style="min-height: 480px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 0 0 40px 40px;">
    <!-- Background element styling -->
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: radial-gradient(rgba(0, 181, 241, 0.15) 1.5px, transparent 1.5px); background-size: 24px 24px; opacity: 0.8; z-index: 0;"></div>
    
    <!-- Banner Image Full -->
    <img src="https://media.istockphoto.com/id/1359494953/vi/vec-to/c%C3%A1c-b%C3%A1c-s%C4%A9-t%C6%B0%C6%A1ng-t%C3%A1c-v%E1%BB%9Bi-giao-di%E1%BB%87n-k%E1%BB%B9-thu%E1%BA%ADt-s%E1%BB%91-v%C3%A0-ki%E1%BB%83m-tra-d%E1%BB%AF-li%E1%BB%87u-s%E1%BB%A9c-kh%E1%BB%8Fe.jpg?s=1024x1024&w=is&k=20&c=xmoUgLICa_IczGl1hwkDwnMIrJ-GqXJf9xSIV4GK9JA=" alt="Doctors Team" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit: cover; object-position: center 20%; z-index: 0; mix-blend-mode: multiply; opacity: 0.35;">

    <div class="col-12 text-center position-relative pt-5 pb-5 px-3" style="z-index: 2;">
        <div class="mb-4">
            <h1 class="fw-extrabold text-uppercase mb-2" style="font-size: 2.2rem; color: #023f6d; letter-spacing: -0.5px; font-weight: 800;">Đặt lịch khám bệnh online</h1>
            <p class="fs-5 fw-medium text-secondary" style="color: #475569 !important;">Giải pháp chăm sóc sức khỏe toàn diện</p>
        </div>

        <div class="row justify-content-center mb-4 mt-2">
            <div class="col-md-11 col-lg-7">
                <!-- Search Bar -->
                <div class="position-relative">
                    <div class="input-group shadow rounded-pill bg-white p-2 border border-2 border-transparent" style="transition: all 0.3s ease; box-shadow: 0 10px 30px rgba(2, 63, 109, 0.12) !important;" onfocusin="this.style.borderColor='#00b5f1'; this.style.boxShadow='0 10px 30px rgba(0, 181, 241, 0.25)';" onfocusout="this.style.borderColor='transparent'; this.style.boxShadow='0 10px 30px rgba(2, 63, 109, 0.12)';">
                        <span class="input-group-text bg-white border-0 ps-4 pe-3"><i class="bi bi-search text-info" style="font-size: 1.4rem;"></i></span>
                        <input type="text" id="dynamic-search-placeholder" class="form-control border-0 px-2 py-2 shadow-none text-dark fw-semibold" style="font-size: 1.1rem; height: 3.2rem; background: #ffffff;" placeholder="Tìm kiếm bác sĩ, chuyên khoa hoặc dịch vụ..." autocomplete="off">
                    </div>
                    <div id="searchSuggestions" class="d-none position-absolute start-0 end-0 mt-2 bg-white rounded-4 shadow-lg text-start overflow-hidden border border-slate-100" style="z-index: 20; max-height: 430px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>

        <!-- Benefits details -->
        <div class="d-flex flex-column align-items-center text-dark text-start mx-auto mt-4 px-3" style="max-width: 680px; font-size: 0.95rem; font-weight: 600; color: #023f6d !important;">
            <div class="mb-2 d-flex align-items-center w-100 justify-content-center justify-content-lg-start">
                <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                <span class="text-truncate">Nền tảng đặt lịch khám tiện lợi – Chọn bác sĩ nhanh – Lấy số thứ tự trực tuyến</span>
            </div>
            <div class="mb-2 d-flex align-items-center w-100 justify-content-center justify-content-lg-start">
                <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                <span>Đặt khám theo khung giờ – Chủ động thời gian – Hạn chế chờ đợi tại bệnh viện</span>
            </div>
            <div class="mb-0 d-flex align-items-center w-100 justify-content-center justify-content-lg-start">
                <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                <span>Hỗ trợ hủy lịch hoàn phí – Nhận ưu đãi tái khám – Tư vấn sức khỏe từ xa</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="position-relative mb-5 container mx-auto px-4 px-md-5" style="margin-top: -80px; z-index: 2;">
    <!-- Left Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white" style="width: 45px; height: 45px; border: 1px solid #e0e0e0;" onclick="document.getElementById('quickActionsScroll').scrollBy({left: -350, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="quickActionsScroll" class="d-flex overflow-auto flex-nowrap py-3 px-2 scrollbar-hide gap-3" style="scroll-behavior: smooth;">
        <!-- Item 1 -->
        <a href="booking_at_facility.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/clinic.png" alt="Đặt khám tại cơ sở" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>tại cơ sở</h6>
                </div>
            </div>
        </a>
        <!-- Item 2 -->
        <a href="specialty_facilities.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/medical-doctor.png" alt="Đặt khám chuyên khoa" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>chuyên khoa</h6>
                </div>
            </div>
        </a>
        <!-- Item 4 -->
        <a href="lab_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/microscope.png" alt="Đặt lịch xét nghiệm" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt lịch<br>xét nghiệm</h6>
                </div>
            </div>
        </a>
        <!-- Item 5 -->
        <a href="after_hours_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/clock--v1.png" alt="Đặt khám ngoài giờ" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>ngoài giờ</h6>
                </div>
            </div>
        </a>
        <!-- Item 8 -->
        <a href="doctor_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/stethoscope.png" alt="Đặt khám theo bác sĩ" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>theo bác sĩ</h6>
                </div>
            </div>
        </a>
        <!-- Item 9 -->
        <a href="imaging_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://cdn-icons-png.flaticon.com/128/11831/11831343.png" alt="Chụp phim & Nội soi" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Chụp phim<br>& Nội soi</h6>
                </div>
            </div>
        </a>
        <!-- Item 10 -->
        <a href="health_package_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/heart-health.png" alt="Gói khám sức khỏe" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Gói khám<br>sức khỏe</h6>
                </div>
            </div>
        </a>
        <!-- Item 11 -->
        <a href="home_care_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/home.png" alt="Y tế tại nhà" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Y tế<br>tại nhà</h6>
                </div>
            </div>
        </a>
        <!-- Item 12 -->
        <a href="vaccination_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/syringe.png" alt="Đặt lịch tiêm chủng" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt lịch<br>tiêm chủng</h6>
                </div>
            </div>
        </a>
        <!-- Item 13 -->
        <a href="health_circular_booking.php" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/document.png" alt="Khám sức khỏe thông tư" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Khám sức khỏe<br>thông tư</h6>
                </div>
            </div>
        </a>
        <!-- Item 14 -->
        <a href="https://www.nhathuocankhang.com/?utm_source=web&utm_medium=card&utm_campaign=medpro_ankhang&utm_content=feature_card_homepage#voucher-medpro" class="text-decoration-none flex-shrink-0" style="width: 140px;" target="_blank" rel="noopener">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/pills.png" alt="Mua thuốc An Khang" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Mua thuốc<br>An Khang</h6>
                </div>
            </div>
        </a>
    </div>

    <!-- Right Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white" style="width: 45px; height: 45px; border: 1px solid #e0e0e0;" onclick="document.getElementById('quickActionsScroll').scrollBy({left: 350, behavior: 'smooth'})">
        <i class="bi bi-chevron-right text-dark"></i>
    </button>
</div>

<style>
/* Ẩn thanh scroll cho webkit browsers */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
/* Ẩn thanh scroll cho IE, Edge và Firefox */
.scrollbar-hide {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>

<!-- Partners Section -->
<div class="row mb-5 mt-5">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Được tin tưởng hợp tác và đồng hành</h3>
    </div>
    <div class="position-relative w-100">
        <!-- Left Button -->
        <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 40px; height: 40px; border: 1px solid #e0e0e0;" onclick="document.getElementById('partnersScroll').scrollBy({left: -350, behavior: 'smooth'})">
            <i class="bi bi-chevron-left text-dark"></i>
        </button>

        <div id="partnersScroll" class="d-flex overflow-auto flex-nowrap py-3 px-3 scrollbar-hide gap-5 align-items-start" style="scroll-behavior: smooth;">
            <?php foreach ($partnerHospitals as $partner): ?>
                <a href="facility_detail.php?id=<?php echo (int)$partner['id']; ?>" class="text-decoration-none flex-shrink-0 text-center" style="width: 170px;">
                    <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center p-2 shadow-sm" style="width: 96px; height: 96px; border: 1px solid #c7e7ff; background: linear-gradient(135deg, #eaf8ff 0%, #d7f0ff 100%);">
                        <img src="<?php echo htmlspecialchars(homepageHospitalLogo($partner)); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="img-fluid" style="max-width: 76px; max-height: 76px; object-fit: contain;" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';">
                    </div>
                    <p class="text-dark mb-0 fw-medium" style="font-size: 0.95rem;"><?php echo htmlspecialchars(stripos($partner['name'], 'MEDLATEC') !== false ? 'Bệnh viện Đa khoa MEDLATEC' : trim(preg_replace('/\s*-\s*Dịch vụ y tế tại nhà\s*$/u', '', $partner['name']))); ?> <i class="bi bi-check-circle-fill text-primary ms-1"></i></p>
                </a>
            <?php endforeach; ?>

            <?php if (count($partnerHospitals) === 0): ?>
                <div class="text-muted py-4">Chưa có bệnh viện nào được duyệt tài khoản.</div>
            <?php endif; ?>

            <!-- View All -->
            <a href="facilities.php" class="text-decoration-none flex-shrink-0 text-center d-flex flex-column justify-content-start align-items-center" style="width: 130px;">
                <div class="d-flex align-items-center justify-content-center mb-3" style="width: 90px; height: 90px;">
                    <i class="bi bi-chevron-double-right mb-2" style="font-size: 2.5rem; color: #64b5f6;"></i>
                </div>
                <p class="text-dark mb-0 fw-medium" style="font-size: 0.95rem;">Xem tất cả</p>
            </a>
        </div>

        <!-- Right Button -->
        <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white me-3" style="width: 40px; height: 40px; border: 1px solid #e0e0e0;" onclick="document.getElementById('partnersScroll').scrollBy({left: 350, behavior: 'smooth'})">
            <i class="bi bi-chevron-right text-dark"></i>
        </button>
    </div>
</div>

<!-- Promotions/Banners Trượt Ngang -->
<style>
    .homepage-banner-section {
        margin-top: 2.5rem;
    }
    .homepage-banner-shell {
        max-width: 1165px;
        margin: 0 auto;
        padding: 0 12px;
    }
    .homepage-banner-carousel {
        border-radius: 16px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 18px 45px rgba(2, 63, 109, 0.08);
    }
    .homepage-banner-carousel .carousel-inner,
    .homepage-banner-carousel .carousel-item {
        height: clamp(250px, 28vw, 360px);
    }
    .homepage-banner-carousel img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
        display: block;
        background: linear-gradient(90deg, #f7fbff 0%, #ffffff 50%, #f7fbff 100%);
    }
    .homepage-banner-carousel .carousel-indicators {
        position: static;
        margin: 1rem 0 0;
        gap: 6px;
    }
    .homepage-banner-carousel .carousel-indicators [data-bs-target] {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        border: 0;
        background-color: #d5dbe3;
        opacity: 1;
        transition: all 0.2s ease;
    }
    .homepage-banner-carousel .carousel-indicators .active {
        width: 28px;
        background-color: #00b5f1;
    }
    .homepage-banner-carousel .carousel-control-prev,
    .homepage-banner-carousel .carousel-control-next {
        width: 52px;
        height: 52px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .homepage-banner-carousel:hover .carousel-control-prev,
    .homepage-banner-carousel:hover .carousel-control-next {
        opacity: 1;
    }
    .homepage-banner-carousel .carousel-control-prev-icon,
    .homepage-banner-carousel .carousel-control-next-icon {
        width: 38px;
        height: 38px;
        border-radius: 999px;
        background-size: 55%;
        background-color: rgba(2, 63, 109, 0.55);
    }
    @media (max-width: 768px) {
        .homepage-banner-carousel .carousel-inner,
        .homepage-banner-carousel .carousel-item {
            height: 180px;
        }
    }
</style>
<div class="homepage-banner-section mb-5 px-3 px-md-5">
    <div class="homepage-banner-shell">
        <div id="bannerCarousel" class="carousel slide homepage-banner-carousel" data-bs-ride="carousel" data-bs-interval="2500">
            <div class="carousel-inner">
                <?php foreach ($homepageBanners as $index => $banner): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php if (!empty($banner['link_url'])): ?>
                            <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" class="d-block h-100">
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars(homepageBannerImageSrc($banner['image_path'])); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                        <?php if (!empty($banner['link_url'])): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            <div class="carousel-indicators">
                <?php foreach ($homepageBanners as $index => $banner): ?>
                    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" <?php echo $index === 0 ? 'aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>



<!-- Outstanding Facilities (Cơ sở y tế nổi bật) -->
<div class="row mb-5 position-relative mt-5 bg-primary bg-opacity-10 py-5 rounded-4 px-3 px-md-4">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Các bệnh viện nổi bật</h3>
    </div>

    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0;" onclick="document.getElementById('facilitiesScroll').scrollBy({left: -320, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="facilitiesScroll" class="d-flex overflow-auto flex-nowrap py-3 px-1 scrollbar-hide gap-4" style="scroll-behavior: smooth;">
        <?php if (count($featuredHospitals) > 0): ?>
            <?php foreach ($featuredHospitals as $hospital): ?>
            <div class="flex-shrink-0 healthcare-package-card" data-category="<?php echo htmlspecialchars($package['category'] ?? 'health'); ?>" style="width: 290px;">
                    <div class="card card-premium h-100 border-0 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-center bg-white pt-4 pb-2" style="height: 180px; border-radius: 20px 20px 0 0;">
                            <img src="<?php echo htmlspecialchars(homepageHospitalLogo($hospital)); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($hospital['name']); ?>" style="max-height: 140px; object-fit: contain;" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>/uploads/hospitals/hoanmy_cuulong_logo.webp';">
                        </div>
                        <div class="card-body p-3 d-flex flex-column bg-white border-top">
                            <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 1rem; color: #023f6d !important;"><?php echo htmlspecialchars($hospital['name']); ?> <i class="bi bi-check-circle-fill text-primary ms-1" style="font-size: 0.85rem;"></i></h6>
                            <p class="card-text text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($hospital['address'] ?: 'Thành phố Cần Thơ'); ?></p>
                            <p class="card-text text-primary small fw-bold mb-3"><i class="bi bi-calendar-check"></i> <?php echo (int)$hospital['successful_booking_count']; ?> đơn đặt khám thành công</p>
                            <div class="mb-3 text-warning small mt-auto">
                                <span class="text-warning me-1">(<?php echo number_format((float)($hospital['rating'] ?? 4.5), 1); ?>)</span>
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                            </div>
                            <a href="facility_booking_options.php?facility=<?php echo urlencode($hospital['name']); ?>" class="btn btn-premium-primary w-100">Đặt khám ngay</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="w-100 text-center text-muted py-4">Chưa có bệnh viện nào đăng ký tài khoản.</div>
        <?php endif; ?>
    </div>

    <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white me-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0;" onclick="document.getElementById('facilitiesScroll').scrollBy({left: 320, behavior: 'smooth'})">
        <i class="bi bi-chevron-right text-dark"></i>
    </button>
    <div class="col-12 text-center mt-4">
        <a href="facilities.php" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" style="border-width: 2px;">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</div>

<!-- Comprehensive Healthcare Section -->
<div class="row mb-5 py-5 rounded-4 position-relative mx-0" style="background-color: #f4f8fe; border-radius: 24px !important;">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Chăm sóc sức khỏe toàn diện</h3>
    </div>
    
    <!-- Tabs -->
    <div class="col-12 d-flex justify-content-center gap-2 gap-md-4 mb-4">
        <button type="button" class="btn btn-premium-primary rounded-pill px-4 py-2 fw-bold healthcare-tab" data-category="health" data-link="health_package_booking.php">Sức khỏe</button>
        <button type="button" class="btn btn-premium-outline rounded-pill px-4 py-2 fw-bold healthcare-tab" data-category="lab" data-link="lab_booking.php">Xét nghiệm</button>
        <button type="button" class="btn btn-premium-outline rounded-pill px-4 py-2 fw-bold healthcare-tab" data-category="vaccination" data-link="vaccination_booking.php">Tiêm chủng</button>
    </div>

    <!-- Left Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 30px;" onclick="document.getElementById('healthcareScroll').scrollBy({left: -320, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="healthcareScroll" class="d-flex overflow-auto flex-nowrap py-3 px-2 scrollbar-hide gap-4 w-100" style="scroll-behavior: smooth;">
        <?php foreach ($homepagePackages as $package): ?>
            <div class="flex-shrink-0 healthcare-package-card" data-category="<?php echo htmlspecialchars($package['category'] ?? 'health'); ?>" style="width: 290px;">
                <div class="card card-premium h-100 border-0 d-flex flex-column bg-white">
                    <div class="bg-white d-flex align-items-center justify-content-center" style="height:180px; border-radius:20px 20px 0 0; overflow:hidden;">
                        <img src="<?php echo htmlspecialchars(homepagePackageImage($package)); ?>" class="w-100 h-100" style="object-fit:contain; padding:18px;" alt="<?php echo htmlspecialchars($package['name']); ?>" onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png';">
                    </div>
                    <div class="card-body p-3 d-flex flex-column bg-white">
                        <h6 class="card-title fw-bold mb-3" style="color:#023f6d !important; font-size:1.05rem; min-height:48px; line-height:1.4;"><?php echo htmlspecialchars($package['name']); ?></h6>
                        <div class="text-secondary small fw-medium mb-3" style="min-height:40px;"><i class="bi bi-hospital text-muted"></i> <?php echo htmlspecialchars($package['hospital_name']); ?> <i class="bi bi-patch-check-fill text-primary ms-1"></i></div>
                        <div class="fw-bold mb-3" style="color:#f7941d; font-size:1.05rem;"><span class="border rounded-circle d-inline-flex justify-content-center align-items-center me-1" style="width:20px;height:20px;border-color:#f7941d !important;font-size:.8rem;"><i class="bi bi-currency-dollar"></i></span> <?php echo (float)($package['display_price'] ?? $package['price'] ?? 0) > 0 ? number_format((float)($package['display_price'] ?? $package['price']), 0, ',', '.') . 'đ' : 'Đang cập nhật'; ?></div>
                        <div class="mt-auto"><a href="<?php echo htmlspecialchars(homepagePackageLink($package)); ?>" class="btn btn-premium-primary w-100 py-2">Đặt khám ngay</a></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($homepagePackages) === 0): ?>
            <div class="w-100 text-center text-muted py-4">Chưa có gói chăm sóc sức khỏe.</div>
        <?php endif; ?>
        <div id="healthcareEmpty" class="w-100 text-center text-muted py-4 d-none">Chưa có gói phù hợp.</div>
    </div>

    <!-- Right Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white me-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 30px;" onclick="document.getElementById('healthcareScroll').scrollBy({left: 320, behavior: 'smooth'})">
        <i class="bi bi-chevron-right text-dark"></i>
    </button>

    <!-- Nút Xem tất cả -->
    <div class="col-12 text-center mt-4">
        <a href="health_package_booking.php" id="healthcareViewAll" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" style="border-width: 2px;">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</div>

<!-- Chuyên Khoa Section -->
<div class="row mb-5 py-4 mx-0 bg-white">
    <div class="col-12 text-center mb-5">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Chuyên Khoa</h3>
    </div>
    
    <div class="col-12 px-2 px-lg-5">
        <div class="d-flex flex-wrap justify-content-center" style="gap: 1.5rem;">
            <!-- Da liễu -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <i class="bi bi-bandaid" style="font-size: 50px; color: #00b5f1; line-height: 1;" aria-label="Da liễu"></i>
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Da liễu</h6>
            </a>

            <!-- Bác sĩ Gia Đình -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/medical-doctor.png" style="width: 50px; height: 50px;" alt="Bác sĩ Gia Đình">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Bác sĩ Gia<br>Đình</h6>
            </a>

            <!-- Tiêu Hóa Gan Mật -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <i class="bi bi-capsule-pill" style="font-size: 50px; color: #00b5f1; line-height: 1;" aria-label="Tiêu Hóa Gan Mật"></i>
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Tiêu Hóa Gan<br>Mật</h6>
            </a>

            <!-- Nội Tổng Quát -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/health-book.png" style="width: 50px; height: 50px;" alt="Nội Tổng Quát">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Tổng Quát</h6>
            </a>

            <!-- Nội Tiết -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/gender.png" style="width: 50px; height: 50px;" alt="Nội Tiết">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Tiết</h6>
            </a>


            <!-- Nội Tim Mạch -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/heart-with-pulse.png" style="width: 50px; height: 50px;" alt="Nội Tim Mạch">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Tim Mạch</h6>
            </a>

            <!-- Nội Thần Kinh -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/brain--v1.png" style="width: 50px; height: 50px;" alt="Nội Thần Kinh">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Thần Kinh</h6>
            </a>

            <!-- Nội Cơ Xương Khớp -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/knee-joint.png" style="width: 50px; height: 50px;" alt="Nội Cơ Xương Khớp">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Cơ Xương<br>Khớp</h6>
            </a>

            <!-- Tai Mũi Họng -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/head-with-brain.png" style="width: 50px; height: 50px;" alt="Tai Mũi Họng">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Tai Mũi Họng</h6>
            </a>

            <!-- Mắt -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/visible--v1.png" style="width: 50px; height: 50px;" alt="Mắt">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Mắt</h6>
            </a>

            <!-- Nội Tiêu Hoá -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/stomach.png" style="width: 50px; height: 50px;" alt="Nội Tiêu Hoá">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Tiêu Hoá</h6>
            </a>

            <!-- Nội Truyền Nhiễm -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/liver.png" style="width: 50px; height: 50px;" alt="Nội Truyền Nhiễm">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Truyền<br>Nhiễm</h6>
            </a>

            <!-- Nội Hô Hấp -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/lungs.png" style="width: 50px; height: 50px;" alt="Nội Hô Hấp">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Hô Hấp</h6>
            </a>

            <!-- Nội Tiết Niệu -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/kidneys.png" style="width: 50px; height: 50px;" alt="Nội Tiết Niệu">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nội Tiết Niệu</h6>
            </a>

            <!-- Ngoại Cơ Xương Khớp -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/knee-joint.png" style="width: 50px; height: 50px;" alt="Ngoại Cơ">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Ngoại Cơ<br>Xương Khớp</h6>
            </a>

            <!-- Sản - Phụ -->
            <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                    <img src="https://img.icons8.com/ios/50/00b5f1/uterus.png" style="width: 50px; height: 50px;" alt="Sản - Phụ">
                </div>
                <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Sản - Phụ<br>Khoa</h6>
            </a>
        </div>

        <!-- Phần mở rộng (ẩn mặc định) -->
        <div class="collapse mt-4" id="moreSpecialties">
            <div class="d-flex flex-wrap justify-content-center" style="gap: 1.5rem;">
                <!-- Ngoại Tiêu Hoá -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/stomach.png" style="width: 50px; height: 50px;" alt="Ngoại Tiêu Hoá">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Ngoại Tiêu<br>Hoá</h6>
                </a>

                <!-- Ngoại Tiết Niệu -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/kidneys.png" style="width: 50px; height: 50px;" alt="Ngoại Tiết Niệu">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Ngoại Tiết<br>Niệu</h6>
                </a>

                <!-- Tâm Lý -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/mental-health.png" style="width: 50px; height: 50px;" alt="Tâm Lý">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Tâm Lý</h6>
                </a>

                <!-- Ngoại Hô Hấp -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/lungs.png" style="width: 50px; height: 50px;" alt="Ngoại Hô Hấp">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Ngoại Hô Hấp</h6>
                </a>

                <!-- Ngoại Thần Kinh -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/brain--v1.png" style="width: 50px; height: 50px;" alt="Ngoại Thần Kinh">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Ngoại Thần<br>Kinh</h6>
                </a>

                <!-- Răng Hàm Mặt -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/tooth.png" style="width: 50px; height: 50px;" alt="Răng Hàm Mặt">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Răng Hàm<br>Mặt</h6>
                </a>

                <!-- Chấn Thương Chỉnh Hình -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/broken-bone.png" style="width: 50px; height: 50px;" alt="Chấn Thương Chỉnh Hình">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Chấn Thương<br>Chỉnh Hình</h6>
                </a>

                <!-- Vô Sinh - Hiếm Muộn -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <i class="bi bi-gender-ambiguous" style="font-size: 50px; color: #00b5f1; line-height: 1;" aria-label="Vô Sinh - Hiếm Muộn"></i>
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Vô Sinh -<br>Hiếm Muộn</h6>
                </a>

                <!-- Nhi Khoa -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/baby.png" style="width: 50px; height: 50px;" alt="Nhi Khoa">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nhi Khoa</h6>
                </a>

                <!-- Nam Khoa -->
                <a href="specialty_facilities.php" class="text-decoration-none text-center specialty-hover" style="width: 130px;">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle icon-wrapper" style="width: 80px; height: 80px; background-color: transparent;">
                        <img src="https://img.icons8.com/ios/50/00b5f1/male.png" style="width: 50px; height: 50px;" alt="Nam Khoa">
                    </div>
                    <h6 class="fw-medium text-dark" style="font-size: 0.95rem; line-height: 1.3;">Nam Khoa</h6>
                </a>
            </div>
        </div>

        <!-- Nút Xem tất cả -->
        <div class="col-12 text-center mt-5">
            <button id="btnToggleSpecialties" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" type="button" aria-expanded="false" aria-controls="moreSpecialties" style="border-width: 2px;">
                Xem tất cả <i class="bi bi-chevron-down ms-1"></i>
            </button>
        </div>

    </div>
</div>

<style>
/* Hiệu ứng hover cho icon chuyên khoa */
.specialty-hover .icon-wrapper {
    transition: all 0.2s ease-in-out;
}
.specialty-hover:hover .icon-wrapper {
    background-color: #e0f2fe !important;
    transform: scale(1.05);
}
.specialty-hover:hover h6 {
    color: #00b5f1 !important;
}
</style>
<script>
document.querySelectorAll('.specialty-hover').forEach(function (link) {
    const label = link.textContent.replace(/\s+/g, ' ').trim();
    if (label) {
        link.href = 'search.php?kw=' + encodeURIComponent(label) + '&tab=subjects&page=1';
    }
});
</script>

<!-- Cảm nhận của khách hàng Section -->
<div class="row mb-5 py-5 position-relative mx-0">
    <div class="col-12 text-center mb-5">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Cảm nhận từ khách hàng</h3>
    </div>

    <!-- Left Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 15px;" onclick="document.getElementById('testimonialScroll').scrollBy({left: -350, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="testimonialScroll" class="d-flex overflow-auto flex-nowrap py-3 px-2 scrollbar-hide gap-4 w-100" style="scroll-behavior: smooth;">
        <!-- Card 1 -->
        <div class="flex-shrink-0" style="width: 380px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 d-flex flex-column p-4" style="background-color: #f4f6f9;">
                <div class="text-center mb-3">
                    <i class="bi bi-quote" style="font-size: 3rem; color: #d1d5db; opacity: 0.7;"></i>
                </div>
                <p class="text-center text-dark text-opacity-75 fw-medium mb-4 flex-grow-1" style="font-size: 0.95rem; line-height: 1.6;">
                    Đặt lịch xét nghiệm bên này rất gọn, có ngày giờ cụ thể luôn lên là được xét nghiệm liền không rườm rà gì mấy. An tâm đặt cho gia đình, có cả xét nghiệm tận nhà, không mất thời gian.
                </p>
                <hr class="border-secondary opacity-25 mx-4">
                <div class="d-flex align-items-center justify-content-center mt-2">
                    <img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fmedpro-production%2Fdefault%2Favatar_nam.png&w=256&q=75" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="Nhân Nguyễn">
                    <h6 class="mb-0 fw-bold" style="color: #023f6d;">Nhân Nguyễn</h6>
                </div>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="flex-shrink-0" style="width: 380px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 d-flex flex-column p-4" style="background-color: #f4f6f9;">
                <div class="text-center mb-3">
                    <i class="bi bi-quote" style="font-size: 3rem; color: #d1d5db; opacity: 0.7;"></i>
                </div>
                <p class="text-center text-dark text-opacity-75 fw-medium mb-4 flex-grow-1" style="font-size: 0.95rem; line-height: 1.6;">
                    Dịch vụ đặt lịch khám tiện thật. Mình chọn được bệnh viện, ngày giờ khám trước nên không phải chờ đợi lâu. Thông tin rõ ràng, thao tác nhanh và rất dễ sử dụng.
                </p>
                <hr class="border-secondary opacity-25 mx-4">
                <div class="d-flex align-items-center justify-content-center mt-2">
                    <img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fmedpro-production%2Fdefault%2Favatar_nu.png&w=256&q=75" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="Mai Vy">
                    <h6 class="mb-0 fw-bold" style="color: #023f6d;">Mai Vy</h6>
                </div>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="flex-shrink-0" style="width: 380px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 d-flex flex-column p-4" style="background-color: #f4f6f9;">
                <div class="text-center mb-3">
                    <i class="bi bi-quote" style="font-size: 3rem; color: #d1d5db; opacity: 0.7;"></i>
                </div>
                <p class="text-center text-dark text-opacity-75 fw-medium mb-4 flex-grow-1" style="font-size: 0.95rem; line-height: 1.6;">
                    Lần đầu tải trang web về xài, thấy dễ và rất tiện lợi. Đi làm bận rộn như mình đặt trước ở đây có chọn ngày giờ thì khỏi mất thời gian tới xếp hàng đợi ở bệnh viện. Chưa kể có thể lấy số đặt khám tại các bệnh viện như Y Dược 1 2 3, Da liễu, Mắt TPHCM...
                </p>
                <hr class="border-secondary opacity-25 mx-4">
                <div class="d-flex align-items-center justify-content-center mt-2">
                    <img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fmedpro-production%2Fdefault%2Favatar_nu.png&w=256&q=75" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="Mộc Trà">
                    <h6 class="mb-0 fw-bold" style="color: #023f6d;">Mộc Trà</h6>
                </div>
            </div>
        </div>
        
        <!-- Card 4 -->
        <div class="flex-shrink-0" style="width: 380px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 d-flex flex-column p-4" style="background-color: #f4f6f9;">
                <div class="text-center mb-3">
                    <i class="bi bi-quote" style="font-size: 3rem; color: #d1d5db; opacity: 0.7;"></i>
                </div>
                <p class="text-center text-dark text-opacity-75 fw-medium mb-4 flex-grow-1" style="font-size: 0.95rem; line-height: 1.6;">
                    Tôi đã sử dụng chức năng đặt lịch xét nghiệm cho con, rất nhanh chóng và tiện lợi. Bác sĩ hỗ trợ tư vấn rất kỹ, không phải chờ đợi lâu. Sẽ tiếp tục ủng hộ nền tảng này!
                </p>
                <hr class="border-secondary opacity-25 mx-4">
                <div class="d-flex align-items-center justify-content-center mt-2">
                    <img src="https://medpro.vn/_next/image?url=https%3A%2F%2Fcdn.medpro.vn%2Fmedpro-production%2Fdefault%2Favatar_nu.png&w=256&q=75" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="Thanh Thảo">
                    <h6 class="mb-0 fw-bold" style="color: #023f6d;">Thanh Thảo</h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white me-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 15px;" onclick="document.getElementById('testimonialScroll').scrollBy({left: 350, behavior: 'smooth'})">
        <i class="bi bi-chevron-right text-dark"></i>
    </button>
</div>

<!-- Thống Kê Section -->
<div class="row mb-5 py-4 position-relative mx-0" style="background-color: #f2f9fc;">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Thống kê</h3>
    </div>
    
    <div class="col-12 px-2 px-md-5">
        <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 1000px;">
            <div class="row text-center g-4 justify-content-center">
                <!-- Lượt khám -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/stethoscope.png" alt="Lượt khám" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($homepageStats['appointments']); ?></h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Lượt khám</p>
                </div>
                <!-- Cơ sở Y tế -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/hospital-3.png" alt="Cơ sở Y tế" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($homepageStats['hospitals']); ?></h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Cơ sở Y tế</p>
                </div>
                <!-- Bác sĩ -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/medical-doctor.png" alt="Bác sĩ" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($homepageStats['doctors']); ?></h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Bác sĩ</p>
                </div>
                <!-- Lượt truy cập -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/visible--v1.png" alt="Lượt truy cập tháng" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($homepageStats['visits']); ?></h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Lượt truy cập tháng</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tin Tức Y Tế Section -->
<div class="row mb-5 py-5 mx-0" style="background-color: #f4f8fe;">
    <div class="col-12 text-center mb-5">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Tin tức y tế</h3>
    </div>
    
    <div class="col-12 col-xl-10 mx-auto px-2 px-md-4">
        <div class="row g-4">
            <?php if (count($homepageNewsPosts) > 0): ?>
            <?php $mainNews = $homepageNewsPosts[0]; ?>
            <div class="col-lg-5 text-start">
                <a href="<?php echo htmlspecialchars(homepageNewsLink($mainNews)); ?>" class="text-decoration-none">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                        <img src="<?php echo htmlspecialchars(homepageNewsImageSrc($mainNews['image_path'])); ?>" class="card-img-top w-100" style="height: 260px; object-fit: cover;" alt="<?php echo htmlspecialchars($mainNews['title']); ?>">
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="card-title fw-bold text-dark mb-3" style="line-height: 1.5; color: #023f6d !important;"><?php echo htmlspecialchars($mainNews['title']); ?></h5>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars(homepageNewsDate($mainNews)); ?></p>
                            <p class="card-text text-dark text-opacity-75" style="font-size: 0.95rem;"><?php echo htmlspecialchars($mainNews['excerpt'] ?? ''); ?></p>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-lg-7 text-start">
                <div class="row g-4">
                    <?php foreach (array_slice($homepageNewsPosts, 1, 4) as $post): ?>
                    <div class="col-md-6">
                        <a href="<?php echo htmlspecialchars(homepageNewsLink($post)); ?>" class="text-decoration-none">
                            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                                <img src="<?php echo htmlspecialchars(homepageNewsImageSrc($post['image_path'])); ?>" class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.4; color: #023f6d !important;"><?php echo htmlspecialchars($post['title']); ?></h6>
                                    <p class="text-muted small mb-0 mt-auto pt-2"><?php echo htmlspecialchars(homepageNewsDate($post)); ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="col-12 text-center text-muted py-4">Chưa có tin tức.</div>
            <?php endif; ?>
        </div>
        
        <div class="col-12 text-center mt-5">
            <a href="news.php" class="text-decoration-none text-primary fw-medium" style="font-size: 1.05rem;">Xem tất cả <i class="bi bi-chevron-double-right" style="font-size: 0.9rem;"></i></a>
        </div>
    </div>
</div>

<style>
.news-card {
    transition: all 0.3s ease;
}
.news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.news-card:hover .card-title {
    color: #00b5f1 !important;
}
</style>

<!-- Process Section -->
<div class="bg-primary bg-opacity-10 rounded-4 p-5 mb-5 text-center px-4">
    <h2 class="fw-bold mb-5">Quy trình đặt lịch đơn giản</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="bg-white rounded-circle d-inline-flex justify-content-center align-items-center shadow-sm mb-3" style="width: 80px; height: 80px;">
                <h3 class="text-primary mb-0 fw-bold">1</h3>
            </div>
            <h5 class="fw-bold">Chọn Chuyên Khoa</h5>
            <p class="text-muted">Tìm kiếm bác sĩ hoặc chuyên khoa phù hợp với tình trạng sức khỏe.</p>
        </div>
        <div class="col-md-4">
            <div class="bg-white rounded-circle d-inline-flex justify-content-center align-items-center shadow-sm mb-3" style="width: 80px; height: 80px;">
                <h3 class="text-primary mb-0 fw-bold">2</h3>
            </div>
            <h5 class="fw-bold">Chọn Khung Giờ</h5>
            <p class="text-muted">Tra cứu lịch làm việc và chọn khung giờ khám rảnh rỗi của bác sĩ.</p>
        </div>
        <div class="col-md-4">
            <div class="bg-white rounded-circle d-inline-flex justify-content-center align-items-center shadow-sm mb-3" style="width: 80px; height: 80px;">
                <h3 class="text-primary mb-0 fw-bold">3</h3>
            </div>
            <h5 class="fw-bold">Xác Nhận Đặt Lịch</h5>
            <p class="text-muted">Điền triệu chứng và cập nhật trạng thái đơn duyệt từ bác sĩ.</p>
        </div>
    </div>
</div>

<style>
/* CSS cho hiệu ứng di chuột vào Card */
.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.search-suggestion-item:hover {
    background-color: #eefcff;
}
.search-suggestion-section {
    background-color: #eaf7ff;
    color: #023f6d;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const input = document.getElementById("dynamic-search-placeholder");
    const words = ["Tìm kiếm", "Tìm kiếm bác sĩ", "Tìm kiếm gói khám", "Tìm kiếm cơ sở y tế"];
    let wordIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    let typingSpeed = 100;
    let deleteSpeed = 50;
    let delayBetweenWords = 2000;

    function typeEffect() {
        const currentWord = words[wordIndex];
        
        if (isDeleting) {
            input.setAttribute("placeholder", currentWord.substring(0, charIndex - 1));
            charIndex--;
        } else {
            input.setAttribute("placeholder", currentWord.substring(0, charIndex + 1));
            charIndex++;
        }

        let speed = isDeleting ? deleteSpeed : typingSpeed;

        if (!isDeleting && charIndex === currentWord.length) {
            speed = delayBetweenWords;
            isDeleting = true;
        } else if (isDeleting && charIndex === 0) {
            isDeleting = false;
            wordIndex = (wordIndex + 1) % words.length;
            speed = 500;
        }

        setTimeout(typeEffect, speed);
    }

    // Start effect
    setTimeout(typeEffect, 1000);

    const suggestionsBox = document.getElementById('searchSuggestions');
    const searchItems = <?php echo json_encode(array_merge(array_map(function ($service) {
        return [
            'section' => 'Dịch vụ',
            'title' => $service['name'],
            'subtitle' => $service['hospital_name'],
            'icon' => 'bi-calendar-check',
            'link' => 'specialty_booking.php?facility=' . urlencode($service['hospital_name']) . '&service_id=' . (int)$service['id']
        ];
    }, $searchServices), array_map(function ($hospital) {
        return [
            'section' => 'Cơ sở y tế',
            'title' => $hospital['name'],
            'subtitle' => $hospital['address'] ?: 'Thành phố Cần Thơ',
            'icon' => 'bi-hospital',
            'link' => 'facility_booking_options.php?facility=' . urlencode($hospital['name'])
        ];
    }, $partnerHospitals)), JSON_UNESCAPED_UNICODE); ?>;

    function renderSuggestions(keyword) {
        const normalizedKeyword = keyword.trim().toLowerCase();
        if (!normalizedKeyword) {
            suggestionsBox.classList.add('d-none');
            suggestionsBox.innerHTML = '';
            return;
        }

        const results = searchItems.filter(function (item) {
            return (item.title + ' ' + item.subtitle + ' ' + item.section).toLowerCase().includes(normalizedKeyword);
        });

        if (!results.length) {
            suggestionsBox.classList.remove('d-none');
            suggestionsBox.innerHTML = '<div class="p-4 text-muted">Không tìm thấy nội dung phù hợp.</div>';
            return;
        }

        let currentSection = '';
        suggestionsBox.innerHTML = results.map(function (item) {
            const sectionHeader = currentSection !== item.section ? '<div class="search-suggestion-section px-3 py-2 fw-bold d-flex justify-content-between"><span>' + item.section + '</span><small>Xem tất cả <i class="bi bi-chevron-double-right"></i></small></div>' : '';
            currentSection = item.section;
            return sectionHeader + '<a href="' + item.link + '" class="search-suggestion-item d-flex align-items-center gap-3 px-3 py-3 text-decoration-none border-bottom"><span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width: 44px; height: 44px; background-color: #eaf7ff;"><i class="bi ' + item.icon + '" style="font-size: 1.7rem; color: #00a8f0;"></i></span><span><strong style="color: #023f6d;">' + item.title + '</strong><br><small class="text-muted">' + item.subtitle + ' <i class="bi bi-check-circle-fill text-primary"></i></small></span></a>';
        }).join('');
        suggestionsBox.classList.remove('d-none');
    }

    input.addEventListener('input', function () {
        renderSuggestions(this.value);
    });

    document.addEventListener('click', function (event) {
        if (!suggestionsBox.contains(event.target) && event.target !== input) {
            suggestionsBox.classList.add('d-none');
        }
    });

    // Xử lý đổi chữ Xem tất cả / Thu gọn cho phần Chuyên khoa
    const moreSpecialties = document.getElementById('moreSpecialties');
    const btnToggleSpecialties = document.getElementById('btnToggleSpecialties');
    
    if(moreSpecialties && btnToggleSpecialties) {
        btnToggleSpecialties.addEventListener('click', function () {
            const isExpanded = moreSpecialties.classList.contains('show');

            if (isExpanded) {
                moreSpecialties.classList.remove('show');
                btnToggleSpecialties.setAttribute('aria-expanded', 'false');
                btnToggleSpecialties.innerHTML = 'Xem tất cả <i class="bi bi-chevron-down ms-1"></i>';
            } else {
                moreSpecialties.classList.add('show');
                btnToggleSpecialties.setAttribute('aria-expanded', 'true');
                btnToggleSpecialties.innerHTML = 'Thu gọn <i class="bi bi-chevron-up ms-1"></i>';
            }
        });
    }

    const healthcareTabs = document.querySelectorAll('.healthcare-tab');
    const healthcareCards = document.querySelectorAll('.healthcare-package-card');
    const healthcareEmpty = document.getElementById('healthcareEmpty');
    const healthcareViewAll = document.getElementById('healthcareViewAll');
    function filterHealthcare(category, link) {
        let visibleCount = 0;
        healthcareCards.forEach(function (card) {
            const show = card.dataset.category === category;
            card.classList.toggle('d-none', !show);
            if (show) visibleCount++;
        });
        healthcareEmpty?.classList.toggle('d-none', visibleCount > 0);
        if (healthcareViewAll && link) healthcareViewAll.href = link;
        document.getElementById('healthcareScroll')?.scrollTo({left: 0, behavior: 'smooth'});
    }
    healthcareTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            healthcareTabs.forEach(function (item) {
                item.classList.remove('btn-premium-primary');
                item.classList.add('btn-premium-outline');
            });
            this.classList.add('btn-premium-primary');
            this.classList.remove('btn-premium-outline');
            filterHealthcare(this.dataset.category, this.dataset.link);
        });
    });
    filterHealthcare('health', 'health_package_booking.php');
});
</script>

<?php include 'includes/footer.php'; ?>
