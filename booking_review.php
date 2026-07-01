<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/header.php';

$facilityName = trim($_GET['facility'] ?? 'Bệnh viện tại Cần Thơ');
$facilityAddress = trim($_GET['address'] ?? '');
$bookingTitle = trim($_GET['booking_title'] ?? 'Thông tin đặt khám');
$bookingService = trim($_GET['booking_service'] ?? '');
$bookingSpecialty = trim($_GET['booking_specialty'] ?? '');
$bookingDate = trim($_GET['booking_date'] ?? '');
$bookingTime = trim($_GET['booking_time'] ?? '');
$bookingPrice = trim($_GET['booking_price'] ?? '');
$patientName = trim($_GET['patient_name'] ?? '');
$patientPhone = trim($_GET['patient_phone'] ?? '');
$patientDob = trim($_GET['patient_dob'] ?? '');

if ($bookingService === '' && isset($_GET['service'])) {
    $bookingService = trim($_GET['service']);
}

$backUrl = 'booking_patient.php?' . http_build_query(array_diff_key($_GET, array_flip(['patient_name', 'patient_phone', 'patient_dob'])));
$nextUrl = 'booking_confirm.php?' . http_build_query($_GET);
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
                        <h5 class="fw-bold mb-0">Xác nhận thông tin</h5>
                        <span style="width: 28px;"></span>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3 px-2">
                        <i class="bi bi-stethoscope fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-person-fill fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <div class="rounded-circle border border-3 border-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-check-circle-fill fs-5"></i></div>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-wallet2 fs-5 opacity-75"></i>
                    </div>
                </div>

                <div class="p-3">
                    <div class="bg-white rounded-3 shadow-sm p-3" style="color:#023f6d;">
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($facilityName); ?></h6>
                        <?php if ($facilityAddress !== ''): ?>
                            <div class="text-muted small pb-3 border-bottom"><?php echo htmlspecialchars($facilityAddress); ?></div>
                        <?php endif; ?>

                        <div class="fw-bold mt-3 mb-2">Thông tin bệnh nhân</div>
                        <div class="d-flex align-items-center justify-content-between pb-3 border-bottom">
                            <div><i class="bi bi-person-fill text-info me-2"></i><?php echo htmlspecialchars($patientName); ?></div>
                            <i class="bi bi-chevron-right"></i>
                        </div>

                        <div class="fw-bold mt-3 mb-2">Thông tin đặt khám</div>
                        <?php if ($bookingTitle !== ''): ?><div class="mb-2"><i class="bi bi-stethoscope text-info me-2"></i><?php echo htmlspecialchars($bookingTitle); ?></div><?php endif; ?>
                        <?php if ($bookingService !== ''): ?><div class="mb-2"><i class="bi bi-heart-pulse-fill text-info me-2"></i><?php echo htmlspecialchars($bookingService); ?></div><?php endif; ?>
                        <?php if ($bookingSpecialty !== ''): ?><div class="mb-2"><i class="bi bi-person-badge text-info me-2"></i><?php echo htmlspecialchars($bookingSpecialty); ?></div><?php endif; ?>
                        <?php if ($bookingDate !== '' || $bookingTime !== ''): ?><div class="mb-2"><i class="bi bi-calendar-date-fill text-info me-2"></i><?php echo htmlspecialchars(trim($bookingDate . '  (' . $bookingTime . ')')); ?></div><?php endif; ?>
                        <?php if ($bookingPrice !== ''): ?><div class="mb-2"><i class="bi bi-wallet-fill text-info me-2"></i><?php echo htmlspecialchars($bookingPrice); ?></div><?php endif; ?>
                    </div>

                    <div class="bg-white rounded-3 p-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between fw-bold fs-5 mb-2">
                            <span>Phí khám bệnh</span>
                            <span><?php echo htmlspecialchars($bookingPrice !== '' ? $bookingPrice : '0đ'); ?></span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span>Phí đặt lịch (theo quy định)</span>
                            <span>0đ</span>
                        </div>
                    </div>

                    <div class="small mt-3 px-1" style="color:#6b7c93;">
                        <i class="bi bi-check-circle-fill me-1" style="color:#44c727;"></i>Bằng việc nhấn vào “Tiếp tục”, bạn đã đồng ý với Chính sách xử lý dữ liệu & quyền lợi MedicailBooking
                    </div>

                    <div class="mt-3">
                        <a href="<?php echo htmlspecialchars($nextUrl); ?>" class="btn text-white fw-bold py-3 w-100 rounded-pill" style="background:#13b5ea;">Tiếp tục</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
