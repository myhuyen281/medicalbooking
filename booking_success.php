<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/header.php';

$facilityName = trim($_GET['facility_name'] ?? ($_GET['facility'] ?? 'Bệnh viện tại Cần Thơ'));
$facilityAddress = trim($_GET['facility_address'] ?? ($_GET['address'] ?? ''));
$bookingService = trim($_GET['booking_service'] ?? '');
$bookingSpecialty = trim($_GET['booking_specialty'] ?? '');
$bookingDate = trim($_GET['booking_date'] ?? '');
$bookingTime = trim($_GET['booking_time'] ?? '');
$bookingPrice = trim($_GET['booking_price'] ?? '');
$patientName = trim($_GET['patient_name'] ?? '');
$patientDob = trim($_GET['patient_dob'] ?? '');
$paymentMethod = trim($_GET['payment_method'] ?? ($_SESSION['last_booking_payment_method'] ?? (isset($_GET['vnp_BankCode']) ? 'vnpay' : '')));
$cancelPending = !empty($_SESSION['cancel_pending']) || !empty($_GET['cancel_pending']) || isset($_COOKIE['cancel_pending']) || (isset($_SERVER['HTTP_COOKIE']) && strpos($_SERVER['HTTP_COOKIE'], 'cancel_pending=1') !== false);
$hospitalId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hospitalMapEmbedUrl = '';
$createdAppointmentId = 0;
$bookingError = '';

try {
 $dbHospital = new Database();
 if ($hospitalId > 0) {
 $dbHospital->query("SELECT id, name, address, map_embed_url FROM hospitals WHERE id = :id LIMIT 1");
 $dbHospital->bind(':id', $hospitalId);
 } else {
 $dbHospital->query("SELECT id, name, address, map_embed_url FROM hospitals WHERE name = :name OR name LIKE :like_name ORDER BY id DESC LIMIT 1");
 $dbHospital->bind(':name', $facilityName);
 $dbHospital->bind(':like_name', '%' . $facilityName . '%');
 }
 $hospitalInfo = $dbHospital->single();
 if ($hospitalInfo) {
 $hospitalId = (int)($hospitalInfo['id'] ?? $hospitalId);
 if (($facilityName === 'Bệnh viện tại Cần Thơ' || $facilityName === '') && !empty($hospitalInfo['name'])) {
 $facilityName = $hospitalInfo['name'];
 }
 if ($facilityAddress === '' && !empty($hospitalInfo['address'])) {
 $facilityAddress = $hospitalInfo['address'];
 }
 if (!empty($hospitalInfo['map_embed_url'])) {
 $hospitalMapEmbedUrl = $hospitalInfo['map_embed_url'];
 }
 }
} catch (Exception $e) {
}

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'patient' && $hospitalId > 0) {
    try {
        $db = new Database();
        $patientId = (int)$_SESSION['user_id'];
        $dateForDb = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $bookingDate, $matches)) {
            $dateForDb = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
            $dateForDb = $bookingDate;
        } else {
            $dateForDb = date('Y-m-d');
        }

        $timeParts = preg_split('/\s*-\s*/', $bookingTime);
        $startTime = isset($timeParts[0]) && preg_match('/^\d{2}:\d{2}$/', trim($timeParts[0])) ? trim($timeParts[0]) . ':00' : '08:00:00';
        $endTime = isset($timeParts[1]) && preg_match('/^\d{2}:\d{2}$/', trim($timeParts[1])) ? trim($timeParts[1]) . ':00' : date('H:i:s', strtotime($startTime . ' +1 hour'));

        $db->query("SELECT d.id, d.specialty_id, d.consultation_fee FROM doctors d WHERE d.hospital_id = :hid AND d.approval_status = 'approved' ORDER BY d.id ASC LIMIT 1");
        $db->bind(':hid', $hospitalId);
        $doctor = $db->single();

        if (!$doctor) {
            $db->query("SELECT id FROM specialties ORDER BY id ASC LIMIT 1");
            $specialty = $db->single();
            $specialtyId = (int)($specialty['id'] ?? 1);
            $priceNumber = (float)preg_replace('/[^0-9]/', '', $bookingPrice ?: '0');
            $db->query("INSERT INTO doctors (user_id, hospital_id, specialty_id, experience_years, consultation_fee, description, approval_status) VALUES (NULL, :hid, :sid, 0, :fee, '', 'approved')");
            $db->bind(':hid', $hospitalId);
            $db->bind(':sid', $specialtyId);
            $db->bind(':fee', $priceNumber);
            $db->execute();
            $doctorId = (int)$db->dbh->lastInsertId();
        } else {
            $doctorId = (int)$doctor['id'];
        }

        $db->query("SELECT id FROM schedules WHERE doctor_id = :did AND work_date = :wdate AND start_time = :stime LIMIT 1");
        $db->bind(':did', $doctorId);
        $db->bind(':wdate', $dateForDb);
        $db->bind(':stime', $startTime);
        $schedule = $db->single();

        if ($schedule) {
            $scheduleId = (int)$schedule['id'];
        } else {
            $db->query("INSERT INTO schedules (doctor_id, work_date, start_time, end_time, status) VALUES (:did, :wdate, :stime, :etime, 'booked')");
            $db->bind(':did', $doctorId);
            $db->bind(':wdate', $dateForDb);
            $db->bind(':stime', $startTime);
            $db->bind(':etime', $endTime);
            $db->execute();
            $scheduleId = (int)$db->dbh->lastInsertId();
        }

        $db->query("SELECT id FROM appointments WHERE patient_id = :pid AND schedule_id = :sid LIMIT 1");
        $db->bind(':pid', $patientId);
        $db->bind(':sid', $scheduleId);
        $existingAppointment = $db->single();

        if ($existingAppointment) {
            $createdAppointmentId = (int)$existingAppointment['id'];
        } else {
 $db->query("INSERT INTO appointments (patient_id, doctor_id, schedule_id, symptoms, status) VALUES (:pid, :did, :sid, :symptoms, 'pending')");
 $db->bind(':pid', $patientId);
 $db->bind(':did', $doctorId);
 $db->bind(':sid', $scheduleId);
 $db->bind(':symptoms', $bookingService !== '' ? $bookingService : 'Khám bệnh');
 $db->execute();
            $createdAppointmentId = (int)$db->dbh->lastInsertId();
        }

        $db->query("UPDATE schedules SET status = 'booked' WHERE id = :sid");
        $db->bind(':sid', $scheduleId);
        $db->execute();
    } catch (Exception $e) {
        $createdAppointmentId = 0;
        $bookingError = 'Không thể lưu đơn đặt khám. Vui lòng kiểm tra dữ liệu bệnh viện, bác sĩ và lịch khám.';
    }
} elseif (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    $bookingError = 'Vui lòng đăng nhập tài khoản bệnh nhân để lưu đơn đặt khám.';
} elseif ($hospitalId <= 0) {
    $bookingError = 'Không xác định được bệnh viện nhận đơn đặt khám.';
}
$bookingSaved = $createdAppointmentId > 0;
$ticketCode = $bookingSaved ? 'T' . date('ymd') . 'A' . $createdAppointmentId : '';
$patientCode = 'MP-' . strtoupper(substr(md5(($patientName ?: 'patient') . ($ticketCode ?: microtime())), 0, 10));
$timeText = trim($bookingTime) !== '' ? explode('-', $bookingTime)[0] : '';

if ($bookingSaved) {
  if ($paymentMethod !== 'vnpay') {
      $_SESSION['last_booking_payment_method'] = 'onsite';
  }
  $_SESSION['latest_booking_ticket'] = [
 'appointment_id' => $createdAppointmentId,
 'ticket_code' => $ticketCode,
 'facility_name' => $facilityName,
 'booking_service' => $bookingService,
 'booking_specialty' => $bookingSpecialty,
 'booking_date' => $bookingDate,
 'booking_time' => $timeText,
 'booking_price' => $bookingPrice,
 'patient_name' => $patientName,
 ];
} else {
 unset($_SESSION['latest_booking_ticket']);
}
?>

<div class="px-2 px-md-4 pb-5" style="background-color:#eaf7fc;min-height:720px;">
    <div class="container py-4">
        <div class="mb-4 fw-bold">
            <a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a>
            <span class="mx-2 text-muted">›</span>
            <span style="color:#00a8f0;">Thông tin phiếu khám bệnh</span>
        </div>

        <div class="text-start mb-3">
            <a href="views/patient/bills.php?<?php echo htmlspecialchars(http_build_query($_SESSION['latest_booking_ticket'] ?? [])); ?>" class="btn bg-white fw-bold px-4 py-3 rounded-3" style="color:#00a8f0;"><i class="bi bi-card-checklist me-2"></i>Danh sách phiếu khám</a>
        </div>

        <div class="mx-auto bg-white rounded-4 p-4 text-center" style="max-width:360px;color:#374151;">
            <h5 class="fw-bold mb-3">PHIẾU KHÁM BỆNH</h5>
            <div class="fw-bold"><?php echo htmlspecialchars($facilityName); ?></div>
            <div class="small mb-3"><?php echo htmlspecialchars($facilityAddress !== '' ? $facilityAddress : 'Chưa có địa chỉ bệnh viện'); ?></div>
            <button type="button" class="btn text-white fw-bold rounded-3 px-4 py-2 mb-3" style="background:#1da1f2;" data-bs-toggle="modal" data-bs-target="#hospitalGuideModal"><i class="bi bi-signpost-split-fill me-2"></i>Xem hướng dẫn đi khám</button>

            <div class="text-start mb-2">Mã Phiếu Khám</div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="bi bi-qr-code" style="font-size:5.8rem;color:#111;"></i>
                <div class="border border-info rounded-3 px-4 py-3 text-center">
                    <div class="small text-muted">Giờ khám dự kiến</div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($timeText !== '' ? $timeText : '--:--'); ?></div>
                </div>
            </div>
            <?php if ($bookingSaved): ?>
 <button id="ticketStatusBadge" class="btn text-white fw-bold rounded-pill px-4 mb-4" style="background:#ff7427;">Đặt khám thành công</button>
 <?php else: ?>
 <button class="btn text-white fw-bold rounded-pill px-4 mb-4" style="background:#dc3545;">Đặt khám chưa hoàn tất</button>
 <?php if ($bookingError !== ''): ?>
 <div class="text-danger small mb-3"><?php echo htmlspecialchars($bookingError); ?></div>
 <?php endif; ?>
 <?php endif; ?>
            <div class="text-danger fst-italic mb-4">Trình phiếu tại <strong>Quầy tiếp nhận</strong> để được hướng dẫn</div>

            <div class="border border-info rounded-3 p-2 text-start mb-3"><span class="text-muted me-4">Chuyên khoa:</span><strong><?php echo htmlspecialchars($bookingSpecialty !== '' ? $bookingSpecialty : ($bookingService !== '' ? $bookingService : 'Thông tin đặt khám')); ?></strong></div>
            <div class="text-start small lh-lg">
                <div><span class="text-muted d-inline-block" style="width:110px;">Mã phiếu:</span><strong><?php echo htmlspecialchars($ticketCode !== '' ? $ticketCode : 'Chưa tạo'); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Dịch vụ:</span><strong><?php echo htmlspecialchars($bookingService !== '' ? $bookingService : 'Khám bệnh'); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Hình thức khám:</span><strong>Không có BHYT</strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Thời gian khám:</span><strong class="text-danger fs-6"><?php echo htmlspecialchars(trim($timeText . ' - ' . $bookingDate, ' -')); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Phí khám:</span><strong><?php echo htmlspecialchars($bookingPrice !== '' ? $bookingPrice : '0đ'); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Phương thức:</span><strong><?php echo htmlspecialchars($paymentMethod === 'vnpay' ? 'VNPAY' : 'Thanh toán tại cơ sở'); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Bệnh nhân:</span><strong><?php echo htmlspecialchars($patientName); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Ngày sinh:</span><strong><?php echo htmlspecialchars($patientDob); ?></strong></div>
                <div><span class="text-muted d-inline-block" style="width:110px;">Mã bệnh nhân:</span><strong><?php echo htmlspecialchars($patientCode); ?></strong></div>
            </div>

            <hr>
            <div class="text-start small"><strong>Lưu ý:</strong><br>Cắt giảm thủ tục, Lấy số trước, Thanh toán trước, Giảm xếp hàng chờ đợi...</div>
            <button class="btn text-white fw-bold w-100 rounded-3 mt-3 py-2" style="background:#00aeef;"><i class="bi bi-share-fill me-2"></i>Chia sẻ</button>
            <div class="mt-4" style="color:#00a8f0;">Bản quyền thuộc <strong>MEDICAILBOOKING</strong></div>
            <div class="small mt-2">Đặt lịch khám tại Bệnh viện - Phòng khám hàng đầu Việt Nam</div>
        </div>

            <div class="mx-auto mt-4" style="max-width:360px;">
            <?php if ($cancelPending): ?>
                <button type="button" class="btn w-100 rounded-3 py-3 fw-bold" style="background:#e5e7eb;color:#374151;" disabled>Chờ hủy</button>
            <?php else: ?>
                <button type="button" class="btn w-100 rounded-3 py-3 fw-bold" style="background:#e5e7eb;color:#374151;" data-bs-toggle="modal" data-bs-target="#cancelTicketModal"><i class="bi bi-x-lg me-2"></i>Hủy phiếu</button>
            <?php endif; ?>
            <div class="text-danger small text-center mt-3"><i class="bi bi-exclamation-circle me-1"></i>Trong thời gian quy định, nếu quý khách hủy phiếu khám sẽ được hoàn lại tiền khám và các dịch vụ đặt thêm (không bao gồm phí tiện ích).</div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 justify-content-center position-relative pt-4">
                <h5 class="modal-title fw-bold" style="color:#2f3a45;">HỦY PHIẾU KHÁM</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-5 pb-2">
                <p class="text-muted fst-italic text-center mb-4">MedicalBooking mong nhận được sự góp ý của bạn để có thể phục vụ tốt hơn</p>
                <form id="cancelTicketForm">
                    <div class="form-check mb-3"><input class="form-check-input cancel-reason" type="checkbox" value="Đặt nhầm chuyên khoa" id="cancelReason1"><label class="form-check-label" for="cancelReason1">Đặt nhầm chuyên khoa</label></div>
                    <div class="form-check mb-3"><input class="form-check-input cancel-reason" type="checkbox" value="Đặt nhầm giờ khám" id="cancelReason2"><label class="form-check-label" for="cancelReason2">Đặt nhầm giờ khám</label></div>
                    <div class="form-check mb-3"><input class="form-check-input cancel-reason" type="checkbox" value="Không còn nhu cầu" id="cancelReason3"><label class="form-check-label" for="cancelReason3">Không còn nhu cầu</label></div>
                    <div class="form-check mb-3"><input class="form-check-input cancel-reason" type="checkbox" value="Đặt trùng phiếu khám" id="cancelReason4"><label class="form-check-label" for="cancelReason4">Đặt trùng phiếu khám</label></div>
                    <div class="form-check mb-2"><input class="form-check-input cancel-reason" type="checkbox" value="Lý do khác" id="cancelReasonOther"><label class="form-check-label" for="cancelReasonOther">Lý do khác</label></div>
                    <textarea id="cancelOtherText" class="form-control d-none mt-2" rows="4" placeholder="Điều khiến bạn muốn hủy phiếu khám"></textarea>
                </form>
            </div>
            <div class="modal-footer border-0 px-5 pb-3 pt-2">
                <div class="row w-100 g-3">
                    <div class="col-6"><button type="button" class="btn btn-outline-info fw-bold py-3 w-100 rounded-3" data-bs-dismiss="modal">Không hủy</button></div>
                    <div class="col-6"><button type="button" id="submitCancelTicket" class="btn fw-bold py-3 w-100 rounded-3 text-white" style="background:#d7dce3;" disabled>Gửi</button></div>
                </div>
                <div class="w-100 text-center small text-muted mt-2">Hotline hỗ trợ <strong style="color:#00a8e8;">1900 xxxx</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="refundPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">CHÍNH SÁCH HOÀN TIỀN – HỦY LỊCH KHÁM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body lh-lg">
                <h6 class="fw-bold">1. Mục đích</h6><p>Chính sách hoàn tiền này áp dụng cho khách hàng sử dụng dịch vụ đặt lịch khám bệnh trực tuyến trên hệ thống MedicalBooking nhằm đảm bảo quyền lợi của người dùng và minh bạch trong quá trình thanh toán.</p>
                <h6 class="fw-bold">2. Điều kiện hoàn tiền</h6><ul><li>Thanh toán thành công nhưng hệ thống không ghi nhận lịch hẹn.</li><li>Bệnh viện/phòng khám hủy lịch khám do thay đổi lịch làm việc hoặc sự cố phát sinh.</li><li>Khách hàng hủy lịch khám đúng thời gian quy định.</li><li>Giao dịch thanh toán bị trừ tiền nhiều lần do lỗi hệ thống.</li></ul>
                <h6 class="fw-bold">3. Quy định hủy lịch và mức hoàn tiền</h6><ul><li>Hủy lịch trước thời gian khám từ 24 giờ trở lên: hoàn 100% phí đặt lịch.</li><li>Hủy lịch trước thời gian khám dưới 24 giờ: hoàn 50% phí đặt lịch.</li><li>Không đến khám theo lịch hẹn mà không thông báo trước: không hỗ trợ hoàn tiền.</li></ul>
                <h6 class="fw-bold">4. Thời gian xử lý hoàn tiền</h6><ul><li>Sau khi yêu cầu hoàn tiền được xác nhận hợp lệ, hệ thống sẽ xử lý trong vòng từ 3 – 7 ngày làm việc.</li><li>Thời gian nhận tiền có thể phụ thuộc vào ngân hàng hoặc cổng thanh toán của khách hàng.</li></ul>
                <h6 class="fw-bold">5. Phương thức hoàn tiền</h6><ul><li>Tài khoản ngân hàng đã dùng để thanh toán; hoặc</li><li>Ví điện tử/cổng thanh toán mà khách hàng đã sử dụng khi đặt lịch.</li></ul>
                <h6 class="fw-bold">6. Các trường hợp không hỗ trợ hoàn tiền</h6><ul><li>Khách hàng nhập sai thông tin cá nhân dẫn đến không thể xác nhận lịch khám.</li><li>Khách hàng tự ý bỏ khám mà không thực hiện thao tác hủy lịch trên hệ thống.</li><li>Các trường hợp phát sinh từ lỗi kết nối Internet hoặc thiết bị của người dùng không thuộc trách nhiệm của hệ thống.</li></ul>
                <h6 class="fw-bold">7. Liên hệ hỗ trợ</h6><ul><li>Hotline: 1900 xxxx</li><li>Email: <a href="mailto:support@medicalbooking.vn">support@medicalbooking.vn</a></li><li>Website: <a href="http://www.medicalbooking.vn" target="_blank">www.medicalbooking.vn</a></li></ul>
                <p>Chúng tôi cam kết hỗ trợ khách hàng nhanh chóng và minh bạch trong mọi vấn đề liên quan đến thanh toán và hoàn tiền.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Hủy yêu cầu</button>
                <button type="button" id="agreeRefundPolicy" class="btn btn-info text-white fw-bold">Đồng ý</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 justify-content-center"><h5 class="modal-title fw-bold">Đã gửi yêu cầu hủy phiếu</h5></div>
            <div class="modal-body text-center px-4 pb-4">
                <p id="cancelSuccessText" class="mb-3">Phiếu khám đã được hủy thành công.</p>
                <button type="button" id="viewRefundPolicy" class="btn btn-info text-white fw-bold rounded-3 px-4 d-none">Bấm vào xem chính sách hoàn tiền</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="hospitalGuideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden">
            <div class="modal-header text-white justify-content-center position-relative" style="background:#1da1f2;">
                <h5 class="modal-title fw-bold">Hướng dẫn đến bệnh viện</h5>
                <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="rounded-3 overflow-hidden border mb-3" style="height:200px;">
                    <?php if ($hospitalMapEmbedUrl !== ''): ?>
                        <iframe src="<?php echo htmlspecialchars($hospitalMapEmbedUrl); ?>" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <?php else: ?>
                        <iframe src="https://www.google.com/maps?q=<?php echo urlencode($facilityName . ' ' . $facilityAddress); ?>&output=embed" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold" style="color:#023f6d;"><?php echo htmlspecialchars($facilityName); ?></h5>
                <div class="fw-bold small mt-3"><?php echo htmlspecialchars($facilityAddress); ?></div>
                <a class="d-inline-block fw-bold text-decoration-underline mt-2" style="color:#023f6d;" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($facilityName . ' ' . $facilityAddress); ?>" target="_blank">Xem chỉ đường</a>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($bookingSaved): ?>
localStorage.setItem('latest_booking_ticket', JSON.stringify({
 ticket_code: <?php echo json_encode($ticketCode); ?>,
 facility_name: <?php echo json_encode($facilityName); ?>,
 booking_service: <?php echo json_encode($bookingService); ?>,
 booking_specialty: <?php echo json_encode($bookingSpecialty); ?>,
 booking_date: <?php echo json_encode($bookingDate); ?>,
 booking_time: <?php echo json_encode($timeText); ?>,
 booking_price: <?php echo json_encode($bookingPrice); ?>,
 patient_name: <?php echo json_encode($patientName); ?>,
 patient_dob: <?php echo json_encode($patientDob); ?>,
 patient_phone: <?php echo json_encode($_GET['patient_phone'] ?? ''); ?>
}));
<?php else: ?>
localStorage.removeItem('latest_booking_ticket');
<?php endif; ?>

// Cancel ticket script
(function() {
    const cancelModal = document.getElementById('cancelTicketModal');
    const submitBtn = document.getElementById('submitCancelTicket');
    const otherChk = document.getElementById('cancelReasonOther');
    const otherText = document.getElementById('cancelOtherText');
    const form = document.getElementById('cancelTicketForm');

    function toggleSubmit() {
        const anyChecked = document.querySelectorAll('.cancel-reason:checked').length > 0;
        submitBtn.disabled = !anyChecked;
        submitBtn.style.background = anyChecked ? '#00a8e8' : '#d7dce3';
    }

    document.querySelectorAll('.cancel-reason').forEach(chk => chk.addEventListener('change', toggleSubmit));

    if (otherChk && otherText) {
        otherChk.addEventListener('change', () => {
            otherText.classList.toggle('d-none', !otherChk.checked);
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            const reasons = Array.from(document.querySelectorAll('.cancel-reason:checked')).map(c => c.value);
            if (otherChk && otherChk.checked && otherText.value.trim()) reasons.push(otherText.value.trim());

            const bookingPriceEl = document.querySelector('.fw-bold.fs-5');
            const priceText = bookingPriceEl ? bookingPriceEl.textContent : '0đ';
            const price = parseInt(priceText.replace(/[^0-9]/g, '')) || 0;

            bootstrap.Modal.getOrCreateInstance(cancelModal).hide();
            form.reset();
            otherText.classList.add('d-none');
            submitBtn.disabled = true;
            submitBtn.style.background = '#d7dce3';

            const isOnline = <?php echo json_encode($paymentMethod === 'vnpay'); ?>;

            if (isOnline) {
                // Thanh toán online → mở thẳng chính sách hoàn tiền
                bootstrap.Modal.getOrCreateInstance(document.getElementById('refundPolicyModal')).show();
            } else {
                // Thanh toán tại quầy → thông báo hủy thành công
                const successModalEl = document.getElementById('cancelSuccessModal');
                const successText = document.getElementById('cancelSuccessText');
                successText.textContent = 'Phiếu khám đã được hủy thành công.';
                new bootstrap.Modal(successModalEl).show();
            }
        });
    }

    const agreeRefundPolicy = document.getElementById('agreeRefundPolicy');
    if (agreeRefundPolicy) {
        agreeRefundPolicy.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('refundPolicyModal')).hide();
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 50);
            document.cookie = 'cancel_pending=1; path=/; max-age=86400';
            localStorage.setItem('cancel_pending_<?php echo htmlspecialchars($ticketCode ?: md5($_SERVER['REQUEST_URI'])); ?>', '1');
            const badge = document.getElementById('ticketStatusBadge');
            if (badge) {
                badge.textContent = 'Chờ hủy';
                badge.style.background = '#ffc107';
                badge.style.color = '#023f6d';
            }
            const cancelButton = document.querySelector('[data-bs-target="#cancelTicketModal"]');
            if (cancelButton) {
                cancelButton.textContent = 'Chờ hủy';
                cancelButton.disabled = true;
            }
            alert('Yêu cầu hủy đã được gửi. Vui lòng chờ hospital/admin xác nhận hủy để hoàn tiền.');
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
