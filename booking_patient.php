<?php
require_once 'config/database.php';
include 'includes/header.php';

$facilityName = isset($_GET['facility']) ? trim($_GET['facility']) : 'Bệnh viện tại Cần Thơ';
$facilityAddress = isset($_GET['address']) ? trim($_GET['address']) : '';
$backUrl = 'specialty_booking.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $backUrl = $_SERVER['HTTP_REFERER'];
}
?>

<div class="px-2 px-md-4 pb-4" style="background-color: #eaf7fc; min-height: 560px;">
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="bg-white rounded-1 overflow-hidden">
                <div class="text-white fw-bold p-3" style="background-color: #1da1f2;">Thông tin cơ sở y tế</div>
                <div class="p-3">
                    <h6 class="fw-bold mb-2" style="color: #023f6d;"><?php echo htmlspecialchars($facilityName); ?></h6>
                    <?php if ($facilityAddress !== ''): ?>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($facilityAddress); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="bg-white">
                <div class="text-white p-3" style="background-color: #1da1f2;">
                    <div class="d-flex align-items-center justify-content-between">
                        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="text-white fs-4 text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                        <h5 class="fw-bold mb-0">Thông tin bệnh nhân</h5>
                        <span style="width: 28px;"></span>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3 px-2">
                        <i class="bi bi-stethoscope fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <div class="rounded-circle border border-3 border-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-person-fill fs-5"></i></div>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-check-circle-fill fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-wallet2 fs-5 opacity-75"></i>
                    </div>
                </div>

                <div class="p-5 text-center">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 84px; height: 84px; border-radius: 24px; background-color: #d7f0ff; color: #1da1f2;">
                        <i class="bi bi-person-vcard-fill" style="font-size: 3rem;"></i>
                    </div>
                    <p class="mb-4" style="color: #023f6d;">Bạn được phép tạo tối đa 10 hồ sơ<br>(cá nhân và người thân trong gia đình)</p>
                    <a href="views/auth/register.php" class="btn text-white fw-bold px-4 py-3 rounded-3 mb-3" style="background-color: #1da1f2;">Chưa từng khám, đăng ký mới</a>
                    <br>
                    <a href="views/auth/login.php" class="btn btn-outline-info fw-bold px-4 py-3 rounded-3">Đã từng khám, nhập số hồ sơ</a>
                    <div class="d-flex align-items-center justify-content-center my-4">
                        <div style="width: 140px; border-top: 1px dashed #e5e7eb;"></div>
                        <span class="fw-bold mx-3">Hoặc</span>
                        <div style="width: 140px; border-top: 1px dashed #e5e7eb;"></div>
                    </div>
                    <a href="views/auth/login.php" class="text-decoration-none fw-bold" style="color: #008ff0;">Đăng nhập</a>
                    <span style="color: #023f6d;"> để lấy danh sách hồ sơ của bạn</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
