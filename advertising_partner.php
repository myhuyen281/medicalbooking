<?php
require_once 'config/database.php';
include 'includes/header.php';
?>
</div>

<section class="py-5 bg-white text-center">
    <div class="container py-4">
        <h1 class="fw-bold mb-3" style="font-size:2.6rem;color:#12b5e8;">Quảng cáo với MEDICAILBOOKING</h1>
        <p class="fs-5 mx-auto" style="max-width:850px;color:#001f3f;">MEDICAILBOOKING là nền tảng công nghệ kết nối người dân với Cơ sở - Dịch vụ Y tế, mang lại giải pháp tiếp cận khách hàng tiềm năng và phù hợp nhu cầu thực tế</p>
    </div>
</section>

<section class="py-5" style="background:#eaf7fc;min-height:520px;">
    <div class="container">
        <div class="bg-white rounded-3 p-4 p-md-5">
            <div class="row g-5">
                <div class="col-lg-5">
                    <h4 class="fw-bold mb-4">Thông tin chi tiết</h4>
                    <div class="d-flex gap-3 mb-4">
                        <i class="bi bi-building fs-2" style="color:#12b5e8;"></i>
                        <div><div class="fw-bold" style="color:#00446f;">MEDPRO - ĐẶT LỊCH KHÁM BỆNH</div><div style="color:#00446f;">236/29/18 Điện Biên Phủ - Phường 17 - Quận Bình Thạnh - TPHCM.</div></div>
                    </div>
                    <div class="d-flex gap-3">
                        <i class="bi bi-hand-index-thumb fs-2" style="color:#12b5e8;"></i>
                        <div><div class="fw-bold" style="color:#00446f;">LIÊN HỆ TƯ VẤN</div><a class="text-decoration-none fw-bold" href="tel:0984448419" style="color:#00a8f0;">0984448419</a> <span class="fw-bold" style="color:#00446f;">(Bộ phận Kinh doanh)</span></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <form class="row g-4">
                        <div class="col-12"><label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control py-3" placeholder="Nhập họ và tên"></div>
                        <div class="col-12"><label class="form-label fw-bold">Công ty <span class="text-danger">*</span></label><input type="text" class="form-control py-3" placeholder="Nhập công ty"></div>
                        <div class="col-12"><label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label><input type="tel" class="form-control py-3" placeholder="Nhập số điện thoại"></div>
                        <div class="col-12"><label class="form-label fw-bold">Lĩnh vực kinh doanh<span class="text-danger">*</span></label><textarea class="form-control" rows="4" placeholder="Nhu yếu phẩm&#10;Dược phẩm và thiết bị y tế&#10;Dịch vụ chăm sóc sức khỏe chuyên nghiệp&#10;Cơ Sở Y tế: Bệnh viện, phòng khám, phòng mạch"></textarea></div>
                        <div class="col-12 text-end"><button type="button" class="btn text-white fw-bold rounded-3 px-5 py-2" style="background:#12b5e8;">Đăng ký ngay</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <a class="position-fixed text-white rounded-circle d-flex align-items-center justify-content-center text-decoration-none" href="tel:0939961179" style="right:30px;bottom:110px;width:60px;height:60px;background:#ffa536;font-size:1.75rem;z-index:10;"><i class="bi bi-telephone-fill"></i></a>
    <a class="position-fixed text-white text-decoration-none fw-bold px-3 py-2" href="booking_at_facility.php" style="right:0;bottom:0;background:#05bdea;border-radius:8px 0 0 0;min-width:390px;z-index:10;"><i class="bi bi-chat-left-text me-2"></i>TƯ VẤN ĐẶT KHÁM TRỰC TUYẾN</a>
</section>

<div class="container mt-4">
<?php include 'includes/footer.php'; ?>
