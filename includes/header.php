<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Hàm tiện ích để lấy đường dẫn gốc (tùy chỉnh theo thư mục local của bạn)
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$hospitalPages = ['facility_detail.php', 'facility_booking_options.php', 'specialty_booking.php'];
$showHeaderHotline = in_array($currentPage, $hospitalPages, true);
$showHeaderSearch = $currentPage !== 'index.php' && !$showHeaderHotline;
$adminHotline = '19002115';
if ($showHeaderHotline && class_exists('Database')) {
    try {
        $headerDb = new Database();
        $headerDb->query("SELECT phone FROM users WHERE role = 'admin' AND phone IS NOT NULL AND phone != '' ORDER BY id ASC LIMIT 1");
        $headerAdmin = $headerDb->single();
        if (!empty($headerAdmin['phone'])) {
            $adminHotline = $headerAdmin['phone'];
        }
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Đặt lịch Khám bệnh Online</title>
    <!-- Bootstrap CSS để thiết kế nhanh -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="<?php echo $base_url; ?>/public/css/style.css" rel="stylesheet">
    <style>
        .healthcare-dropdown,
        .service-dropdown,
        .news-dropdown,
        .guide-dropdown,
        .partner-dropdown {
            min-width: 200px;
            background: linear-gradient(180deg, #fff8ec 0%, #f4fbff 100%);
            border-radius: 8px !important;
            overflow: hidden;
        }
        .service-dropdown {
            min-width: 230px;
        }
        .news-dropdown {
            min-width: 200px;
        }
        .guide-dropdown {
            min-width: 210px;
        }
        .partner-dropdown {
            min-width: 180px;
        }
        .healthcare-dropdown .dropdown-item,
        .service-dropdown .dropdown-item,
        .news-dropdown .dropdown-item,
        .guide-dropdown .dropdown-item,
        .partner-dropdown .dropdown-item {
            color: #023f6d;
            font-size: 0.9rem;
        }
        .navbar .dropdown-menu.show {
            display: block;
        }
        .navbar .dropdown > .nav-link.show {
            color: #023f6d !important;
            border-bottom: 3px solid #00b5f1;
        }
        .healthcare-dropdown,
        .service-dropdown,
        .news-dropdown,
        .guide-dropdown,
        .partner-dropdown {
            top: 100%;
            left: 0;
            margin-top: 0 !important;
            padding: 8px 0 !important;
            max-height: 420px;
            overflow-y: auto !important;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
        }
        .healthcare-dropdown .dropdown-item:hover,
        .service-dropdown .dropdown-item:hover,
        .news-dropdown .dropdown-item:hover,
        .guide-dropdown .dropdown-item:hover,
        .partner-dropdown .dropdown-item:hover {
            background-color: #e5f7ff;
            color: #00a8f0;
        }
        .marquee-track {
            display: flex;
            width: max-content;
            animation: marquee 40s linear infinite;
        }
        .marquee-text {
            flex-shrink: 0;
            padding-right: 4rem;
        }
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <!-- Header phong cách Medpro -->
    <header class="bg-white border-bottom sticky-top shadow-sm">
        <!-- Top Bar -->
        <div class="border-bottom d-none d-lg-block py-1">
            <div class="container d-flex justify-content-end align-items-center" style="font-size: 0.9rem;">
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hospital'): ?>
                            <a class="btn btn-outline-info rounded-pill btn-sm fw-bold px-3 text-info border-info" href="<?php echo $base_url; ?>/views/admin/dashboard.php">
                                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['role'] == 'hospital' ? 'Quản lý bệnh viện' : 'Trang quản trị'; ?>
                            </a>
                        <?php else: ?>
                            <a class="btn btn-outline-info rounded-pill btn-sm fw-bold px-3 text-info border-info" href="<?php echo $base_url; ?>/views/patient/dashboard.php">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $base_url; ?>/views/auth/logout.php" class="text-danger dropdown-item d-inline fw-semibold ms-1" style="width: auto;">Thoát</a>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>/views/auth/register.php" class="btn btn-outline-info rounded-pill btn-sm fw-bold px-3 text-info border-info" style="color: #0dcaf0;">
                            <i class="bi bi-person-fill"></i> Tài khoản
                        </a>
                    <?php endif; ?>
                    
                    <!-- Language Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm dropdown-toggle border-0 px-1" type="button" data-bs-toggle="dropdown">
                            <img src="https://flagcdn.com/w20/vn.png" alt="VN">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end min-w-auto">
                            <li><a class="dropdown-item" href="#"><img src="https://flagcdn.com/w20/vn.png" alt="VN" class="me-2"> Tiếng Việt</a></li>
                            <li><a class="dropdown-item" href="#"><img src="https://flagcdn.com/w20/gb.png" alt="EN" class="me-2"> English</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light py-2">
            <div class="container-fluid px-lg-5 align-items-center flex-nowrap">
                <!-- Logo -->
                <a class="navbar-brand text-info fw-bolder fs-3 m-0 p-0 flex-shrink-0" style="letter-spacing: -1px; color: #00b5f1 !important; text-transform: uppercase;" href="<?php echo $base_url; ?>/index.php">
                    medicailbooking
                </a>

                <?php if ($showHeaderSearch): ?>
                    <form class="d-none d-xl-flex ms-4 flex-shrink-0" style="width: 270px;" action="<?php echo $base_url; ?>/facilities.php" method="get">
                        <div class="input-group rounded-pill bg-light overflow-hidden border">
                            <span class="input-group-text bg-light border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" id="header-dynamic-search-placeholder" class="form-control bg-light border-0 shadow-none py-2" placeholder="Tìm kiếm..." autocomplete="off">
                        </div>
                    </form>
                <?php elseif ($showHeaderHotline): ?>
                    <a href="tel:<?php echo htmlspecialchars($adminHotline); ?>" class="d-none d-xxl-flex ms-4 align-items-center gap-2 text-decoration-none rounded-pill px-3 py-2 fw-bold flex-shrink-0" style="background:#fff4df; color:#023f6d; font-size:0.92rem; line-height:1;">
                        <i class="bi bi-telephone-fill text-warning"></i>
                        <span>Hotline: <?php echo htmlspecialchars($adminHotline); ?></span>
                    </a>
                <?php endif; ?>
                
                <button class="navbar-toggler border-0 shadow-none text-info" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarMain">
                    
                    <!-- Nav Links -->
                    <ul class="navbar-nav ms-auto fw-semibold align-items-lg-center flex-nowrap" style="font-size: 0.88rem; white-space: nowrap;">
                        <li class="nav-item dropdown px-1 position-relative">
                            <a class="nav-link dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Cơ sở y tế
                            </a>
                            <ul class="dropdown-menu border-0 shadow-sm mt-2 rounded-3 py-2 healthcare-dropdown">
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=public">Bệnh viện công</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=private">Bệnh viện tư</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=clinic">Phòng khám</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=office">Phòng mạch</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=lab">Xét nghiệm</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=home">Y tế tại nhà</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=vaccination">Tiêm chủng</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown px-1">
                            <a class="nav-link dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown">
                                Dịch vụ y tế
                            </a>
                            <ul class="dropdown-menu border-0 shadow-sm mt-2 rounded-3 py-2 service-dropdown">
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/booking_at_facility.php">Đặt khám tại cơ sở</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/specialty_facilities.php">Đặt khám chuyên khoa</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="#">Gọi video với bác sĩ</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/lab_booking.php">Đặt lịch xét nghiệm</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/after_hours_booking.php">Đặt khám ngoài giờ</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/doctor_booking.php">Đặt khám theo bác sĩ</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/imaging_booking.php">Đặt lịch Chụp phim & Nội soi</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/health_package_booking.php">Gói khám sức khỏe</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/facilities.php?type=home">Y tế tại nhà</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown px-1 position-relative">
                            <a class="nav-link dropdown-toggle text-dark" href="<?php echo $base_url; ?>/news.php" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Tin tức
                            </a>
                            <ul class="dropdown-menu border-0 shadow-sm mt-2 rounded-3 py-2 news-dropdown">
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/news.php?category=science">Tin tức y khoa</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/news.php?category=service">Tin dịch vụ</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/news.php?category=medical">Tin y tế</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/news.php?category=common">Y học thường thức</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown px-1 position-relative">
                            <a class="nav-link dropdown-toggle text-dark" href="<?php echo $base_url; ?>/guide.php" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Hướng dẫn
                            </a>
                            <ul class="dropdown-menu border-0 shadow-sm mt-2 rounded-3 py-2 guide-dropdown">
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/guide.php#dat-lich-kham">Đặt lịch khám</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/guide.php#hoan-phi">Quy trình hoàn phí</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/guide.php#faq">Câu hỏi thường gặp</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/guide.php#quy-trinh-di-kham">Quy trình đi khám</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/guide.php#hoi-dap">Cộng đồng hỏi đáp khám chữa bệnh</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown px-1 position-relative">
                            <a class="nav-link dropdown-toggle text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Liên hệ hợp tác
                            </a>
                            <ul class="dropdown-menu border-0 shadow-sm mt-2 rounded-3 py-2 partner-dropdown">
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/medical_partner.php">Cơ sở y tế</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="<?php echo $base_url; ?>/advertising_partner.php">Quảng cáo</a></li>
                                <li><a class="dropdown-item py-3 px-3 fw-semibold" href="#">Về MEDICAILBOOKING</a></li>
                            </ul>
                        </li>
                    </ul>
                    
                    <!-- Mobile Account Button (shown only on mobile) -->
                    <div class="d-lg-none mt-3 pb-2 border-top pt-3">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo $base_url; ?>/views/auth/logout.php" class="btn btn-outline-danger w-100 rounded-pill">Đăng xuất</a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>/views/auth/register.php" class="btn btn-outline-info w-100 rounded-pill mb-2">Đăng nhập / Đăng ký</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <div class="overflow-hidden fw-bold text-white py-2" style="background-color: #ffb02e; white-space: nowrap;">
            <div class="marquee-track">
                <span class="marquee-text"><i class="bi bi-stars me-2"></i>Kính chào quý khách đến với hệ thống đặt lịch khám bệnh online – đặt lịch nhanh chóng, thuận tiện, tiết kiệm thời gian và được phục vụ chu đáo tại bệnh viện.</span>
                <span class="marquee-text"><i class="bi bi-stars me-2"></i>Kính chào quý khách đến với hệ thống đặt lịch khám bệnh online – đặt lịch nhanh chóng, thuận tiện, tiết kiệm thời gian và được phục vụ chu đáo tại bệnh viện.</span>
                <span class="marquee-text"><i class="bi bi-stars me-2"></i>Kính chào quý khách đến với hệ thống đặt lịch khám bệnh online – đặt lịch nhanh chóng, thuận tiện, tiết kiệm thời gian và được phục vụ chu đáo tại bệnh viện.</span>
                <span class="marquee-text"><i class="bi bi-stars me-2"></i>Kính chào quý khách đến với hệ thống đặt lịch khám bệnh online – đặt lịch nhanh chóng, thuận tiện, tiết kiệm thời gian và được phục vụ chu đáo tại bệnh viện.</span>
            </div>
        </div>
    </header>

    <!-- Thư viện JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const headerSearchInput = document.getElementById('header-dynamic-search-placeholder');
        if (headerSearchInput) {
            const headerSearchWords = ['Tìm kiếm', 'Tìm kiếm bác sĩ', 'Tìm kiếm gói khám', 'Tìm kiếm cơ sở y tế'];
            let headerSearchWordIndex = 0;
            let headerSearchCharIndex = 0;
            let headerSearchDeleting = false;

            function headerSearchTypeEffect() {
                const currentWord = headerSearchWords[headerSearchWordIndex];
                if (headerSearchDeleting) {
                    headerSearchInput.setAttribute('placeholder', currentWord.substring(0, headerSearchCharIndex - 1));
                    headerSearchCharIndex--;
                } else {
                    headerSearchInput.setAttribute('placeholder', currentWord.substring(0, headerSearchCharIndex + 1));
                    headerSearchCharIndex++;
                }

                let speed = headerSearchDeleting ? 50 : 100;
                if (!headerSearchDeleting && headerSearchCharIndex === currentWord.length) {
                    speed = 2000;
                    headerSearchDeleting = true;
                } else if (headerSearchDeleting && headerSearchCharIndex === 0) {
                    headerSearchDeleting = false;
                    headerSearchWordIndex = (headerSearchWordIndex + 1) % headerSearchWords.length;
                    speed = 500;
                }
                setTimeout(headerSearchTypeEffect, speed);
            }
            setTimeout(headerSearchTypeEffect, 1000);
        }

        document.querySelectorAll('.navbar .dropdown').forEach(function (item) {
            const toggle = item.querySelector('[data-bs-toggle="dropdown"]');
            const menu = item.querySelector('.dropdown-menu');
            if (!toggle || !menu) return;

            let timer;
            const dropdown = bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: 'outside' });

            item.addEventListener('mouseenter', function () {
                clearTimeout(timer);
                document.querySelectorAll('.navbar .dropdown-menu.show').forEach(function (openMenu) {
                    if (openMenu !== menu) {
                        const openToggle = openMenu.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
                        bootstrap.Dropdown.getOrCreateInstance(openToggle).hide();
                    }
                });
                dropdown.show();
            });

            item.addEventListener('mouseleave', function () {
                timer = setTimeout(function () {
                    dropdown.hide();
                }, 900);
            });
        });
    </script>

    <!-- Main Content -->
    <div class="container mt-4">
