<?php
require_once 'config/database.php';
include 'includes/header.php';
?>
</div>

<style>
    .partner-feature-card {
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .partner-feature-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 16px 34px rgba(2, 63, 109, .18) !important;
    }
</style>

<section class="py-5" style="background:#dff4ff; min-height:560px; position:relative; overflow:hidden;">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="fw-bold text-uppercase mb-3" style="font-size:2.4rem;color:#0b1f33;">MEDICAILBOOKING PARTNER+</div>
                <h1 class="fw-bold text-uppercase mb-4" style="font-size:clamp(2.2rem,5vw,3.2rem);line-height:1.15;color:#082b3a;">Dịch vụ truyền thông & chuyển đổi số y tế</h1>
                <div class="d-flex flex-column gap-3 fs-5 fw-semibold mb-5" style="color:#023f6d;">
                    <div><i class="bi bi-patch-check-fill text-success me-2"></i>Nâng tầm vận hành bệnh viện, phòng khám</div>
                    <div><i class="bi bi-patch-check-fill text-success me-2"></i>Phát triển thương hiệu bền vững, lâu dài</div>
                    <div><i class="bi bi-patch-check-fill text-success me-2"></i>Kết nối khách hàng, mở rộng uy tín</div>
                    <div><i class="bi bi-patch-check-fill text-success me-2"></i>Truyền thông, quảng bá rộng rãi</div>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <a href="tel:0939961179" class="btn text-white rounded-pill px-4 py-3 fw-bold" style="background:#ff9800;">Liên hệ 0939961179 <i class="bi bi-telephone-fill ms-2"></i></a>
                    <a href="booking_at_facility.php" class="btn text-white rounded-pill px-4 py-3 fw-bold" style="background:#12b5e8;">Nhận tư vấn <i class="bi bi-send-fill ms-2"></i></a>
                </div>
            </div>
            <div class="col-lg-5 text-center">
                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:300px;height:300px;background:#1577d4;">
                    <i class="bi bi-hospital text-white" style="font-size:8rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <a class="position-fixed text-white rounded-circle d-flex align-items-center justify-content-center text-decoration-none" href="tel:0939961179" style="right:30px;bottom:110px;width:60px;height:60px;background:#ffa536;font-size:1.75rem;z-index:10;"><i class="bi bi-telephone-fill"></i></a>
    <a class="position-fixed text-white text-decoration-none fw-bold px-3 py-2" href="booking_at_facility.php" style="right:0;bottom:0;background:#05bdea;border-radius:8px 0 0 0;min-width:390px;z-index:10;"><i class="bi bi-chat-left-text me-2"></i>TƯ VẤN ĐẶT KHÁM TRỰC TUYẾN</a>
</section>

<section class="py-5" style="background:#dff4ff;">
    <div class="container">
        <h2 class="text-center fw-bold mb-5" style="font-size:2.35rem;color:#000;">Các tính năng chính của MEDICAILBOOKING Partner+</h2>
        <div class="row g-4 justify-content-center align-items-center">
            <div class="col-lg-5 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-calendar2-check" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Tính năng đặt khám</h4><div>Đặt khám theo cơ sở/chuyên khoa/bác sĩ...</div></div></div></div>
            <div class="col-lg-5 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-chat-square-heart" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Trợ lý y tế 24/7</h4><div>Sắp lịch khám, tư vấn chuyên khoa, hướng dẫn quy trình khám...</div></div></div></div>
            <div class="col-lg-4 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-megaphone" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Truyền thông theo nhu cầu</h4><div>Banner, Popup, Toplist</div></div></div></div>
            <div class="col-lg-2 d-none d-lg-block text-center"><div class="rounded-4 overflow-hidden shadow-sm"><img src="https://medpro.vn/_next/image?url=%2F_next%2Fstatic%2Fmedia%2FtechnologyDoctor.a0d6ef3f.png&w=828&q=75" alt="Technology Doctor" class="img-fluid w-100" style="height:105px;object-fit:cover;"></div></div>
            <div class="col-lg-4 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-window" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Trang giới thiệu riêng</h4><div>Trang giới thiệu chuyên sâu về cơ sở Y tế</div></div></div></div>
            <div class="col-lg-5 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-calendar-heart" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Quản lý lịch khám</h4><div>Thông tin bệnh nhân, số lượt đặt khám, bật tắt tính năng đặt khám</div></div></div></div>
            <div class="col-lg-5 col-md-6"><div class="partner-feature-card bg-white rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 h-100"><i class="bi bi-bounding-box" style="font-size:3.5rem;color:#2c5f8f;"></i><div><h4 class="fw-bold mb-2">Quảng bá kết hợp</h4><div>Truyền thông chéo với các đối tác của MEDICAILBOOKING</div></div></div></div>
        </div>
    </div>
</section>

<section class="py-5" style="background:#f5fbff;">
    <div class="container">
        <h2 class="text-center fw-bold mb-4" style="font-size:2.25rem;color:#023f6d;">Các gói dịch vụ của MEDICAILBOOKING Partner+</h2>
        <div class="mx-auto text-center text-white fw-bold rounded-3 mb-5 py-3" style="max-width:720px;background:linear-gradient(90deg,#c9efff,#12b5e8,#c9efff);font-size:1.55rem;">Giá chỉ từ: 5.600.000đ/năm</div>
        <div class="row g-4 justify-content-center align-items-stretch">
            <div class="col-lg-4"><div class="h-100 rounded-5 p-4" style="background:#bfe3fb;"><h3 class="fw-bold mb-2">Basic Plan</h3><p>Quy mô cơ sở: Phòng mạch</p><a href="booking_at_facility.php" class="btn rounded-pill fw-bold w-100 py-3 mb-4" style="background:#eef6ff;color:#5577e8;">NHẬN TƯ VẤN <i class="bi bi-send-fill ms-2"></i></a><div class="d-flex flex-column gap-3 fw-semibold"><div><i class="bi bi-x-circle-fill text-danger me-2"></i>Ưu tiên tiếp cận nhóm khách hàng khám doanh nghiệp</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tính năng đặt khám trước trên hệ thống</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tổng đài hỗ trợ đặt khám 24/7: 0939961179</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Chức năng tư vấn khám bệnh qua video</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tích hợp công cụ thanh toán trực tuyến</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Hiển thị địa chỉ & định vị GG map</div><div><i class="bi bi-x-circle-fill text-danger me-2"></i>Truyền thông thương hiệu</div></div></div></div>
            <div class="col-lg-4"><div class="h-100 rounded-5 p-4 text-white" style="background:linear-gradient(180deg,#12aee8,#2857df);box-shadow:0 0 0 16px #bcd5ff;"><div class="d-flex justify-content-between align-items-start"><h3 class="fw-bold mb-2">Pro Plan</h3><span class="badge bg-white text-primary rounded-3 px-3 py-2">Phổ biến</span></div><p>Quy mô cơ sở: Bệnh viện/Phòng khám</p><a href="booking_at_facility.php" class="btn text-white rounded-pill fw-bold w-100 py-3 mb-4" style="background:#ff9800;">NHẬN TƯ VẤN <i class="bi bi-send-fill ms-2"></i></a><div class="d-flex flex-column gap-3 fw-semibold"><div><i class="bi bi-x-circle-fill text-danger me-2"></i>Ưu tiên tiếp cận nhóm khách hàng khám doanh nghiệp</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tính năng đặt khám trước trên hệ thống</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tổng đài hỗ trợ đặt khám 24/7: 0939961179</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Chức năng tư vấn khám bệnh qua video</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tích hợp công cụ thanh toán trực tuyến</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Hiển thị địa chỉ & định vị GG map</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Trợ lý Y tế riêng tại MEDICAILBOOKING</div><div><i class="bi bi-x-circle-fill text-danger me-2"></i>Truyền thông thương hiệu</div></div></div></div>
            <div class="col-lg-4"><div class="h-100 rounded-5 p-4" style="background:#bfe3fb;"><h3 class="fw-bold mb-2">Premium Plan</h3><p>Quy mô cơ sở: Bệnh viện/Phòng khám</p><a href="booking_at_facility.php" class="btn rounded-pill fw-bold w-100 py-3 mb-4" style="background:#eef6ff;color:#5577e8;">NHẬN TƯ VẤN <i class="bi bi-send-fill ms-2"></i></a><div class="d-flex flex-column gap-3 fw-semibold"><div><i class="bi bi-check-circle-fill text-success me-2"></i>Ưu tiên tiếp cận nhóm khách hàng khám doanh nghiệp</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tính năng đặt khám trước trên hệ thống</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tổng đài hỗ trợ đặt khám 24/7: 0939961179</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Chức năng tư vấn khám bệnh qua video</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Tích hợp công cụ thanh toán trực tuyến</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Hiển thị địa chỉ & định vị GG map</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Truyền thông thương hiệu (15 days)</div><div><i class="bi bi-check-circle-fill text-success me-2"></i>Gửi thông báo đến toàn bộ user</div></div></div></div>
        </div>
    </div>
</section>

<div class="container mt-4">
<?php include 'includes/footer.php'; ?>
