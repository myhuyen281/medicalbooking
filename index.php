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

if (count($homepageBanners) === 0) {
    $homepageBanners = [
        [
            'title' => 'Bệnh viện Da liễu Cần Thơ',
            'image_path' => 'https://benhviendalieucantho.vn/hinhtintuc/trinhchieu/banner_face_web_2026.png',
            'link_url' => 'https://benhviendalieucantho.vn/'
        ],
        [
            'title' => 'Khám bệnh hiệu quả',
            'image_path' => 'https://qn.medcare.vn/wp-content/uploads/sites/6/2022/03/Banner-Kham-benh-hieu-qua-scaled.jpg',
            'link_url' => 'https://qn.medcare.vn/kham-benh/'
        ],
        [
            'title' => 'SIS Cần Thơ',
            'image_path' => 'https://sisvietnam.vn/wp-content/uploads/2026/05/18.5Artboard-2-copy.jpg',
            'link_url' => 'https://sisvietnam.vn/tong-quan-ve-sis/'
        ],
        [
            'title' => 'Phòng khám Đức Quang',
            'image_path' => 'https://www.phongkhamducquang.com/files/sites/site_43/site_43_banner/banner-ducquang.jpg',
            'link_url' => 'https://phongkhamducquang.com/index.html'
        ],
        [
            'title' => 'Bệnh viện Đông Đô',
            'image_path' => 'https://benhviendongdo.com.vn/wp-content/uploads/2019/05/banner-1.jpg',
            'link_url' => 'https://benhviendongdo.com.vn/banner-1/'
        ]
    ];
}

try {
    $db->query("SELECT DISTINCT h.*
                FROM hospitals h
                INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                ORDER BY h.id DESC
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
                WHERE u.id IS NOT NULL OR EXISTS (SELECT 1 FROM doctors d2 WHERE d2.hospital_id = h.id)
                GROUP BY h.id
                ORDER BY successful_booking_count DESC, h.rating DESC, h.id ASC
                LIMIT 8");
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

function homepageBannerImageSrc($path) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}

function homepageImageSrc($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
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
            <p class="fs-5 fw-medium text-secondary" style="color: #475569 !important;">Giải pháp chăm sóc sức khỏe toàn diện tại Cần Thơ</p>
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
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/clock--v1.png" alt="Đặt khám ngoài giờ" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>ngoài giờ</h6>
                </div>
            </div>
        </a>
        <!-- Item 6 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/fluency/48/trust.png" alt="Giúp việc cá nhân" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Giúp việc<br>cá nhân</h6>
                </div>
            </div>
        </a>
        <!-- Item 7 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/organization.png" alt="Khám doanh nghiệp" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Khám doanh<br>nghiệp</h6>
                </div>
            </div>
        </a>
        <!-- Item 8 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/stethoscope.png" alt="Đặt khám theo bác sĩ" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt khám<br>theo bác sĩ</h6>
                </div>
            </div>
        </a>
        <!-- Item 9 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://cdn-icons-png.flaticon.com/128/11831/11831343.png" alt="Chụp phim & Nội soi" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Chụp phim<br>& Nội soi</h6>
                </div>
            </div>
        </a>
        <!-- Item 10 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/heart-health.png" alt="Gói khám sức khỏe" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Gói khám<br>sức khỏe</h6>
                </div>
            </div>
        </a>
        <!-- Item 11 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/home.png" alt="Y tế tại nhà" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Y tế<br>tại nhà</h6>
                </div>
            </div>
        </a>
        <!-- Item 12 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/syringe.png" alt="Đặt lịch tiêm chủng" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Đặt lịch<br>tiêm chủng</h6>
                </div>
            </div>
        </a>
        <!-- Item 13 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
            <div class="card shadow-sm h-100 border-0 rounded-4 feature-card text-center">
                <div class="card-body p-3">
                    <img src="https://img.icons8.com/color/48/document.png" alt="Khám sức khỏe thông tư" width="45" height="45" class="mb-2 d-block mx-auto">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-6">Khám sức khỏe<br>thông tư</h6>
                </div>
            </div>
        </a>
        <!-- Item 14 -->
        <a href="#" class="text-decoration-none flex-shrink-0" style="width: 140px;">
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
                    <div class="bg-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center p-2" style="width: 90px; height: 90px; border: 1px solid #d4d4d4;">
                        <img src="<?php echo htmlspecialchars(homepageImageSrc($partner['logo_url'] ?? '', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png')); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="img-fluid" style="max-height: 65px;">
                    </div>
                    <p class="text-dark mb-0 fw-medium" style="font-size: 0.95rem;"><?php echo htmlspecialchars($partner['name']); ?> <i class="bi bi-check-circle-fill text-primary ms-1"></i></p>
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
<div class="row mb-5">
    <div class="col-12 px-3 px-md-5">
        <div id="bannerCarousel" class="carousel slide rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel" data-bs-interval="1800">
            <div class="carousel-indicators">
                <?php foreach ($homepageBanners as $index => $banner): ?>
                    <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" <?php echo $index === 0 ? 'aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($homepageBanners as $index => $banner): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php if (!empty($banner['link_url'])): ?>
                            <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars(homepageBannerImageSrc($banner['image_path'])); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($banner['title']); ?>" style="height: 350px; object-fit: contain; background-color: #f8f9fa;">
                        <?php if (!empty($banner['link_url'])): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev" style="width: 5%;">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next" style="width: 5%;">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>



<!-- Outstanding Facilities (Cơ sở y tế nổi bật) -->
<div class="row mb-5 position-relative mt-5 bg-primary bg-opacity-10 py-5 rounded-4 px-3 px-md-4">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Các bệnh viện nổi bật ở Cần Thơ</h3>
    </div>

    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0;" onclick="document.getElementById('facilitiesScroll').scrollBy({left: -320, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="facilitiesScroll" class="d-flex overflow-auto flex-nowrap py-3 px-1 scrollbar-hide gap-4" style="scroll-behavior: smooth;">
        <?php if (count($featuredHospitals) > 0): ?>
            <?php foreach ($featuredHospitals as $hospital): ?>
                <div class="flex-shrink-0" style="width: 290px;">
                    <div class="card card-premium h-100 border-0 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-center bg-white pt-4 pb-2" style="height: 180px; border-radius: 20px 20px 0 0;">
                            <img src="<?php echo htmlspecialchars(homepageImageSrc($hospital['logo_url'] ?? '', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/512px-No_image_available.svg.png')); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($hospital['name']); ?>" style="max-height: 140px; object-fit: contain;">
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
</div>

<!-- Comprehensive Healthcare Section -->
<div class="row mb-5 py-5 rounded-4 position-relative mx-0" style="background-color: #f4f8fe; border-radius: 24px !important;">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold mb-0 text-uppercase" style="color: #023f6d;">Chăm sóc sức khỏe toàn diện</h3>
    </div>
    
    <!-- Tabs -->
    <div class="col-12 d-flex justify-content-center gap-2 gap-md-4 mb-4">
        <button class="btn btn-premium-primary rounded-pill px-4 py-2 fw-bold">Sức khỏe</button>
        <button class="btn btn-premium-outline rounded-pill px-4 py-2 fw-bold">Xét nghiệm</button>
        <button class="btn btn-premium-outline rounded-pill px-4 py-2 fw-bold">Tiêm chủng</button>
    </div>

    <!-- Left Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 start-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white ms-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 30px;" onclick="document.getElementById('healthcareScroll').scrollBy({left: -320, behavior: 'smooth'})">
        <i class="bi bi-chevron-left text-dark"></i>
    </button>

    <div id="healthcareScroll" class="d-flex overflow-auto flex-nowrap py-3 px-2 scrollbar-hide gap-4 w-100" style="scroll-behavior: smooth;">
        <!-- Card 1 -->
        <div class="flex-shrink-0" style="width: 290px;">
            <div class="card card-premium h-100 border-0 d-flex flex-column bg-white">
                <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=640" class="w-100 object-fit-cover" style="height: 180px; border-radius: 20px 20px 0 0;" alt="Bệnh Tiêu Hoá - Gan Mật">
                <div class="card-body p-3 d-flex flex-column bg-white">
                    <h6 class="card-title fw-bold mb-3" style="color: #023f6d !important; font-size: 1.05rem; min-height: 48px; line-height: 1.4;">Đặt khám Bệnh Tiêu Hoá - Gan Mật</h6>
                    <div class="text-secondary small fw-medium mb-3" style="min-height: 40px;">
                        <i class="bi bi-hospital text-muted"></i> Trung Tâm Nội Soi Tiêu Hoá Doctor Check <i class="bi bi-patch-check-fill text-primary ms-1"></i>
                    </div>
                    <div class="fw-bold mb-3" style="color: #f7941d; font-size: 1.05rem;">
                        <span class="border rounded-circle d-inline-flex justify-content-center align-items-center me-1" style="width: 20px; height: 20px; border-color: #f7941d !important; font-size: 0.8rem;"><i class="bi bi-currency-dollar"></i></span> 200.000đ
                    </div>
                    <div class="mt-auto">
                        <a href="#" class="btn btn-premium-primary w-100 py-2">Đặt khám ngay</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="flex-shrink-0" style="width: 290px;">
            <div class="card card-premium h-100 border-0 d-flex flex-column bg-white">
                <img src="https://images.unsplash.com/photo-1587834571871-331da2f5b5f6?q=80&w=640" class="w-100 object-fit-cover" style="height: 180px; border-radius: 20px 20px 0 0;" alt="Gói khám mắt tổng quát">
                <div class="card-body p-3 d-flex flex-column bg-white">
                    <h6 class="card-title fw-bold mb-3" style="color: #023f6d !important; font-size: 1.05rem; min-height: 48px; line-height: 1.4;">Gói khám mắt tổng quát</h6>
                    <div class="text-secondary small fw-medium mb-3" style="min-height: 40px;">
                        <i class="bi bi-hospital text-muted"></i> Trung Tâm Mắt Quốc Tế Phương Đông
                    </div>
                    <div class="fw-bold mb-3" style="color: #f7941d; font-size: 1.05rem;">
                        <span class="border rounded-circle d-inline-flex justify-content-center align-items-center me-1" style="width: 20px; height: 20px; border-color: #f7941d !important; font-size: 0.8rem;"><i class="bi bi-currency-dollar"></i></span> 500.000đ
                    </div>
                    <div class="mt-auto">
                        <a href="#" class="btn btn-premium-primary w-100 py-2">Đặt khám ngay</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="flex-shrink-0" style="width: 290px;">
            <div class="card card-premium h-100 border-0 d-flex flex-column bg-white">
                <img src="https://images.unsplash.com/photo-1505751172876-fa1923c5c528?q=80&w=640" class="w-100 object-fit-cover" style="height: 180px; border-radius: 20px 20px 0 0;" alt="Gói khám tiểu đường">
                <div class="card-body p-3 d-flex flex-column bg-white">
                    <h6 class="card-title fw-bold mb-3" style="color: #023f6d !important; font-size: 1.05rem; min-height: 48px; line-height: 1.4;">Gói khám tiểu đường</h6>
                    <div class="text-secondary small fw-medium mb-3" style="min-height: 40px;">
                        <i class="bi bi-hospital text-muted"></i> Phòng Khám Đa khoa Quốc Tế Golden Healthcare
                    </div>
                    <div class="fw-bold mb-3" style="color: #f7941d; font-size: 1.05rem;">
                        <span class="border rounded-circle d-inline-flex justify-content-center align-items-center me-1" style="width: 20px; height: 20px; border-color: #f7941d !important; font-size: 0.8rem;"><i class="bi bi-currency-dollar"></i></span> 720.000đ
                    </div>
                    <div class="mt-auto">
                        <a href="#" class="btn btn-premium-primary w-100 py-2">Đặt khám ngay</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="flex-shrink-0" style="width: 290px;">
            <div class="card card-premium h-100 border-0 d-flex flex-column bg-white">
                <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=640" class="w-100 object-fit-cover" style="height: 180px; border-radius: 20px 20px 0 0;" alt="Khám sức khỏe xin việc">
                <div class="card-body p-3 d-flex flex-column bg-white">
                    <h6 class="card-title fw-bold mb-3" style="color: #023f6d !important; font-size: 1.05rem; min-height: 48px; line-height: 1.4;">Khám sức khỏe xin việc</h6>
                    <div class="text-secondary small fw-medium mb-3" style="min-height: 40px;">
                        <i class="bi bi-hospital text-muted"></i> Phòng Khám Đa Khoa Pháp Anh
                    </div>
                    <div class="fw-bold mb-3" style="color: #f7941d; font-size: 1.05rem;">
                        <span class="border rounded-circle d-inline-flex justify-content-center align-items-center me-1" style="width: 20px; height: 20px; border-color: #f7941d !important; font-size: 0.8rem;"><i class="bi bi-currency-dollar"></i></span> 380.000đ
                    </div>
                    <div class="mt-auto">
                        <a href="#" class="btn btn-premium-primary w-100 py-2">Đặt khám ngay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Button -->
    <button class="btn btn-white rounded-circle shadow position-absolute top-50 end-0 translate-middle-y z-3 d-none d-md-flex align-items-center justify-content-center bg-white me-3" style="width: 45px; height: 45px; border: 1px solid #e0e0e0; margin-top: 30px;" onclick="document.getElementById('healthcareScroll').scrollBy({left: 320, behavior: 'smooth'})">
        <i class="bi bi-chevron-right text-dark"></i>
    </button>

    <!-- Nút Xem tất cả -->
    <div class="col-12 text-center mt-4">
        <a href="#" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold" style="border-width: 2px;">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
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
                    <h4 class="fw-bold text-dark mb-1">1K+</h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Lượt khám</p>
                </div>
                <!-- Cơ sở Y tế -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/hospital-3.png" alt="Cơ sở Y tế" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1">50+</h4>
                    <p class="text-muted mb-0 fw-medium" style="font-size: 0.9rem;">Cơ sở Y tế</p>
                </div>
                <!-- Bác sĩ -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/medical-doctor.png" alt="Bác sĩ" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1">1500+</h4>
                    <p class="text-muted mb-0 fw-medium" scontyle="font-size: 0.9rem;">Bác sĩ</p>
                </div>
                <!-- Lượt truy cập -->
                <div class="col-6 col-md-3">
                    <img src="https://img.icons8.com/ios/64/00b5f1/visible--v1.png" alt="Lượt truy cập tháng" class="mb-3" style="width: 45px; height: 45px;">
                    <h4 class="fw-bold text-dark mb-1">1K+</h4>
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
            <!-- Main News (Left) -->
            <div class="col-lg-5 text-start">
                <a href="#" class="text-decoration-none">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                        <img src="https://images.unsplash.com/photo-1570125909232-eb263c188f7e?q=80&w=800" class="card-img-top w-100" style="height: 260px; object-fit: cover;" alt="Nhà xe đưa đón">
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="card-title fw-bold text-dark mb-3" style="line-height: 1.5; color: #023f6d !important;">Gợi ý các nhà xe hỗ trợ đưa đón đến bệnh viện tại TP.HCM</h5>
                            <p class="text-muted small mb-3">06/05/2026, 02:08</p>
                            <p class="card-text text-dark text-opacity-75" style="font-size: 0.95rem;">Cập nhật danh sách các nhà xe uy tín chuyên đưa đón và trung chuyển bệnh nhân đến tận cổng bệnh viện. Tham khảo ngay để tìm được nhà xe phù hợp.</p>
                        </div>
                    </div>
                </a>
            </div>
            
            <!-- Side News (Right) -->
            <div class="col-lg-7 text-start">
                <div class="row g-4">
                    <!-- News 1 -->
                    <div class="col-md-6">
                        <a href="#" class="text-decoration-none">
                            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                                <img src="https://images.unsplash.com/photo-1584515979956-d9f6e5d0a642?q=80&w=800" class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="Đau mắt đỏ">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.4; color: #023f6d !important;">Khám đau mắt đỏ ở đâu? 4 bệnh viện phòng khám uy tín TPHCM</h6>
                                    <p class="text-muted small mb-0 mt-auto pt-2">06/05/2026, 02:08</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <!-- News 2 -->
                    <div class="col-md-6">
                        <a href="#" class="text-decoration-none">
                            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                                <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=800" class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="Khám bệnh online">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.4; color: #023f6d !important;">Khám bệnh online dịp lễ: An tâm cùng bác sĩ Medpro</h6>
                                    <p class="text-muted small mb-0 mt-auto pt-2">29/04/2026, 05:36 - Uyển Nhi</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <!-- News 3 -->
                    <div class="col-md-6">
                        <a href="#" class="text-decoration-none">
                            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                                <img src="https://images.unsplash.com/photo-1584516150909-c43483ee7932?q=80&w=800" class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="Viêm hô hấp trẻ em">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.4; color: #023f6d !important;">Bệnh viêm hô hấp ở trẻ em: dấu hiệu, nguyên nhân, điều trị</h6>
                                    <p class="text-muted small mb-0 mt-auto pt-2">30/10/2024, 10:30 - Thanh Ngân</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <!-- News 4 -->
                    <div class="col-md-6">
                        <a href="#" class="text-decoration-none">
                            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden news-card">
                                <img src="https://images.unsplash.com/photo-1542884841-9f546e727bca?q=80&w=800" class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="Bướu máu trẻ sơ sinh">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title fw-bold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.4; color: #023f6d !important;">Bướu máu ở trẻ sơ sinh: từ nguyên nhân cho đến cách điều trị</h6>
                                    <p class="text-muted small mb-0 mt-auto pt-2">29/10/2024, 04:23 - Uyển Nhi</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 text-center mt-5">
            <a href="#" class="text-decoration-none text-primary fw-medium" style="font-size: 1.05rem;">Xem tất cả <i class="bi bi-chevron-double-right" style="font-size: 0.9rem;"></i></a>
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
});
</script>

<?php include 'includes/footer.php'; ?>
