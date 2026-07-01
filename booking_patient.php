<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/header.php';

$facilityName = isset($_GET['facility']) ? trim($_GET['facility']) : 'Bệnh viện tại Cần Thơ';
$facilityAddress = isset($_GET['address']) ? trim($_GET['address']) : '';
$bookingTitle = trim($_GET['booking_title'] ?? 'Thông tin đặt khám');
$bookingService = trim($_GET['booking_service'] ?? '');
$bookingSpecialty = trim($_GET['booking_specialty'] ?? '');
$bookingDate = trim($_GET['booking_date'] ?? '');
$bookingTime = trim($_GET['booking_time'] ?? '');
$bookingPrice = trim($_GET['booking_price'] ?? '');
if ($bookingService === '' && isset($_GET['service'])) {
    $bookingService = trim($_GET['service']);
}
$showPatientForm = isset($_GET['new_profile']) && $_GET['new_profile'] === '1';
$backUrl = 'specialty_booking.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $backUrl = $_SERVER['HTTP_REFERER'];
}

$isLoggedIn = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'patient';
$profile = null;

if ($isLoggedIn) {
    $db = new Database();
    $userId = $_SESSION['user_id'];
    $db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
    $db->bind(':id', $userId);
    $user = $db->single();
    try {
        $db->query("SELECT * FROM patient_profiles WHERE user_id = :uid");
        $db->bind(':uid', $userId);
        $profile = $db->single();
    } catch (Exception $e) {
        $profile = null;
    }
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
                        <h5 class="fw-bold mb-0">Chọn hồ sơ</h5>
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

                <?php
                $hasBookingInfo = $bookingService !== '' || $bookingSpecialty !== '' || $bookingDate !== '' || $bookingTime !== '' || $bookingPrice !== '';
                ?>
                <?php if ($showPatientForm): ?>
                    <div class="px-3 pt-3">
                        <h6 class="fw-bold mb-2" style="color:#00a8f0;">Thông tin đặt khám</h6>
                        <div class="bg-white rounded-3 shadow-sm p-3" style="color:#374151;">
                            <?php if ($bookingTitle !== ''): ?><div class="mb-2"><i class="bi bi-stethoscope text-info me-2"></i><?php echo htmlspecialchars($bookingTitle); ?></div><?php endif; ?>
                            <?php if ($bookingService !== ''): ?><div class="mb-2"><i class="bi bi-heart-pulse-fill text-info me-2"></i><?php echo htmlspecialchars($bookingService); ?></div><?php endif; ?>
                            <?php if ($bookingSpecialty !== ''): ?><div class="mb-2"><i class="bi bi-person-badge text-info me-2"></i><?php echo htmlspecialchars($bookingSpecialty); ?></div><?php endif; ?>
                            <?php if ($bookingDate !== '' || $bookingTime !== ''): ?><div class="mb-2"><i class="bi bi-calendar-date-fill text-info me-2"></i><?php echo htmlspecialchars(trim($bookingDate . '  (' . $bookingTime . ')')); ?></div><?php endif; ?>
                            <?php if ($bookingPrice !== ''): ?><div><i class="bi bi-wallet-fill text-info me-2"></i><?php echo htmlspecialchars($bookingPrice); ?></div><?php endif; ?>
                            <?php if (!$hasBookingInfo): ?><div class="text-muted small mb-0">Chưa có thông tin đặt khám. Vui lòng chọn dịch vụ, ngày và giờ khám ở bước trước.</div><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <?php if (!$showPatientForm): ?>
                        <div class="p-5 text-center">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 84px; height: 84px; border-radius: 24px; background-color: #d7f0ff; color: #1da1f2;">
                                <i class="bi bi-person-vcard-fill" style="font-size: 3rem;"></i>
                            </div>
                            <p class="mb-4" style="color: #023f6d;">Bạn được phép tạo tối đa 10 hồ sơ<br>(cá nhân và người thân trong gia đình)</p>
                            <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'new_profile=1'); ?>" class="btn text-white fw-bold px-4 py-3 rounded-3" style="background-color: #1da1f2; min-width: 270px;">
                                Chưa từng khám, đăng ký mới
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="px-3 pt-3 pb-4">
                            <h6 class="fw-bold mb-2" style="color:#00a8f0;">Thông tin người khám</h6>
                            <div class="bg-white rounded-3 shadow-sm overflow-hidden">
                                <div class="d-flex">
                                    <button type="button" class="btn fw-bold rounded-0 px-4 py-3 patient-type-btn" style="background:#fff;color:#023f6d;" onclick="document.querySelectorAll('.patient-type-btn').forEach(button => button.style.background = '#f1f3f5'); this.style.background = '#fff'; document.querySelectorAll('.relative-booker-info').forEach(field => field.classList.add('d-none'))">Đặt cho mình</button>
                                    <button type="button" class="btn fw-bold rounded-0 px-4 py-3 patient-type-btn" style="background:#f1f3f5;color:#023f6d;" onclick="document.querySelectorAll('.patient-type-btn').forEach(button => button.style.background = '#f1f3f5'); this.style.background = '#fff'; document.querySelectorAll('.relative-booker-info').forEach(field => field.classList.remove('d-none'))">Đặt cho người thân</button>
                                </div>
                                <div class="p-3">
                                    <div class="row g-3 text-start">
                                        <div class="col-md-6"><label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label><input id="patientPhone" class="form-control py-3" placeholder="Nhập số điện thoại..." value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
                                        <div class="col-md-6"><label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label><input id="patientName" class="form-control py-3 text-uppercase" placeholder="NHẬP HỌ VÀ TÊN" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"></div>
                                        <div class="col-12"><label class="form-label fw-bold">Ngày sinh <span class="text-danger">*</span></label><input id="patientDob" class="form-control py-3" placeholder="Nhập ngày sinh" pattern="\d{2}/\d{2}/\d{4}" title="Vui lòng nhập ngày sinh theo định dạng dd/mm/yyyy, ví dụ 28/02/2004" oninput="this.nextElementSibling.classList.toggle('d-none', this.value === ''); this.setCustomValidity(this.value !== '' && this.validity.patternMismatch ? 'Vui lòng nhập ngày sinh theo định dạng dd/mm/yyyy, ví dụ 28/02/2004' : '')"><div class="text-danger small mt-1 d-none">Bắt buộc nhập theo định dạng dd/mm/yyyy, ví dụ: 28/02/2004</div></div>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-link text-decoration-none fw-bold px-0" style="color:#00a8f0;" onclick="const extraInfo = document.getElementById('extraPatientInfo'); const isOpen = extraInfo.classList.toggle('show'); this.setAttribute('aria-expanded', isOpen ? 'true' : 'false'); this.querySelector('i').className = 'bi ' + (isOpen ? 'bi-chevron-up' : 'bi-chevron-down') + ' ms-1';" aria-expanded="false">
                                                Thông tin khác <i class="bi bi-chevron-down ms-1"></i>
                                            </button>
                                        </div>
                                        <div class="col-12 collapse" id="extraPatientInfo">
                                            <div class="row g-3">
                                                <div class="col-md-6"><label class="form-label fw-bold">Giới tính</label><select class="form-select py-3"><option>Chọn giới tính</option><option>Nam</option><option>Nữ</option><option>Khác</option></select></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Mã định danh/CCCD</label><div class="input-group"><select class="form-select py-3" style="max-width:95px;"><option>CCCD</option><option>CMND</option></select><input class="form-control py-3" placeholder="Vui lòng nhập Mã định danh/CCCD"></div></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Mã bảo hiểm y tế</label><input class="form-control py-3" placeholder="Nhập Mã bảo hiểm y tế"></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Email (dùng để nhận phiếu khám bệnh)</label><input type="email" class="form-control py-3" placeholder="Nhập địa chỉ Email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Nghề nghiệp</label><select class="form-select py-3"><option>Chọn nghề nghiệp</option><option>Học sinh/Sinh viên</option><option>Nhân viên văn phòng</option><option>Kinh doanh</option><option>Khác</option></select></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Quốc gia</label><select class="form-select py-3"><option>Việt Nam</option></select></div>
                                                <div class="col-12"><label class="form-label fw-bold">Dân tộc</label><select class="form-select py-3"><option>Kinh</option><option>Hoa</option><option>Khmer</option><option>Khác</option></select></div>
                                                <div class="col-12"><div class="fw-bold mt-2" style="color:#00a8f0;">Địa chỉ theo CCCD (cũ)</div></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Tỉnh/Thành</label><select class="form-select py-3"><option>Chọn tỉnh thành</option></select></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Quận/Huyện</label><select class="form-select py-3"><option>Chọn quận huyện</option></select></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Phường/Xã</label><select class="form-select py-3"><option>Chọn xã phường</option></select></div>
                                                <div class="col-md-6"><label class="form-label fw-bold">Số nhà/Tên đường/Ấp thôn xóm</label><div class="text-danger small fst-italic mb-2">(Không bao gồm tỉnh/thành, quận/huyện, phường/xã)</div><input class="form-control py-3" placeholder="Nhập số nhà, tên đường, ấp thôn xóm,..."></div>
                                                <div class="col-12 relative-booker-info d-none"><div class="fw-bold mt-2" style="color:#00a8f0;">Thông tin người đặt</div></div>
                                                <div class="col-md-6 relative-booker-info d-none"><label class="form-label fw-bold">Họ và tên (có dấu) <span class="text-danger">*</span></label><input class="form-control py-3" placeholder="Nhập họ và tên người đặt" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"></div>
                                                <div class="col-md-6 relative-booker-info d-none"><label class="form-label fw-bold">Quan hệ với người khám <span class="text-danger">*</span></label><select class="form-select py-3"><option>Chọn mối quan hệ</option><option>Cha/Mẹ</option><option>Vợ/Chồng</option><option>Con</option><option>Anh/Chị/Em</option><option>Khác</option></select></div>
                                                <div class="col-md-6 relative-booker-info d-none"><label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label><input class="form-control py-3" placeholder="Nhập số điện thoại người đặt" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
                                                <div class="col-md-6 relative-booker-info d-none"><label class="form-label fw-bold">Địa chỉ Email</label><input type="email" class="form-control py-3" placeholder="Nhập địa chỉ email người đặt" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 p-3 border-top">
                                    <a href="<?php echo htmlspecialchars(str_replace(['&new_profile=1', '?new_profile=1'], '', $_SERVER['REQUEST_URI'])); ?>" class="btn btn-outline-secondary fw-bold py-3 flex-fill">Quay lại</a>
                                    <button type="button" class="btn text-white fw-bold py-3 flex-fill" style="background:#1da1f2;" onclick="goToBookingConfirm()">Tiếp tục</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function goToBookingConfirm() {
    const phone = document.getElementById('patientPhone');
    const name = document.getElementById('patientName');
    const dob = document.getElementById('patientDob');
    const dobPattern = /^\d{2}\/\d{2}\/\d{4}$/;

    if (phone.value.trim() === '') {
        phone.focus();
        return;
    }

    if (name.value.trim() === '') {
        name.focus();
        return;
    }

    if (!dobPattern.test(dob.value.trim())) {
        dob.nextElementSibling.classList.remove('d-none');
        dob.focus();
        return;
    }

    const params = new URLSearchParams(window.location.search);
    params.set('patient_name', name.value.trim());
    params.set('patient_phone', phone.value.trim());
    params.set('patient_dob', dob.value.trim());
    window.location.href = 'booking_review.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>
