<?php
require_once 'config/database.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: doctors.php");
    exit();
}

$doctorId = $_GET['id'];
$db = new Database();

// Lấy thông tin Bác sĩ
$db->query("SELECT d.id, u.full_name, s.name as specialty_name, d.experience_years, d.consultation_fee, d.description,
                   (SELECT AVG(rating) FROM reviews WHERE doctor_id = d.id) as avg_rating,
                   (SELECT COUNT(id) FROM reviews WHERE doctor_id = d.id) as review_count
            FROM doctors d 
            INNER JOIN users u ON d.user_id = u.id 
            LEFT JOIN specialties s ON d.specialty_id = s.id 
            WHERE d.id = :id AND d.approval_status = 'approved'");
$db->bind(':id', $doctorId);
$doctor = $db->single();

if (!$doctor) {
    echo "<div class='container mt-5 text-center'><h3>Bác sĩ không tồn tại hoặc đã bị xóa.</h3><a href='doctors.php'>Quay lại danh sách</a></div>";
    include 'includes/footer.php';
    exit();
}

// Fetch Reviews
$db->query("SELECT r.*, u.full_name as patient_name 
            FROM reviews r 
            INNER JOIN users u ON r.patient_id = u.id 
            WHERE r.doctor_id = :did 
            ORDER BY r.created_at DESC");
$db->bind(':did', $doctorId);
$reviews = $db->resultSet();

// Lấy lịch làm việc của Bác sĩ (Chỉ lấy những lịch trống và từ ngày hôm nay trở đi)
$today = date('Y-m-d');
$db->query("SELECT * FROM schedules 
            WHERE doctor_id = :did AND status = 'available' AND work_date >= :today 
            ORDER BY work_date ASC, start_time ASC");
$db->bind(':did', $doctorId);
$db->bind(':today', $today);
$schedulesRaw = $db->resultSet();

// Gom nhóm lịch theo Ngày để hiển thị dễ nhìn
$groupedSchedules = [];
foreach ($schedulesRaw as $s) {
    $dateStr = date('d/m/Y', strtotime($s['work_date']));
    $groupedSchedules[$dateStr][] = $s;
}
?>

<div class="row mt-4">
    <!-- Thông tin Bác sĩ -->
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-4">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doctor['full_name']); ?>&background=random&size=300" class="card-img-top" alt="BS. <?php echo htmlspecialchars($doctor['full_name']); ?>" style="object-fit: cover; height: 350px;">
            <div class="card-body text-center">
                <h3 class="card-title text-primary fw-bold">BS. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                <h5 class="text-muted"><?php echo htmlspecialchars($doctor['specialty_name'] ?? 'Đa khoa'); ?></h5>
                
                <?php if ($doctor['review_count'] > 0): ?>
                    <div class="mb-3">
                        <span class="text-warning fs-5">
                            <?php 
                            $avg = round($doctor['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg ? '★' : '☆';
                            }
                            ?>
                        </span>
                        <span class="fw-bold ms-1"><?php echo $avg; ?> / 5</span>
                        <div class="text-muted small">(Dựa trên <?php echo $doctor['review_count']; ?> lượt đánh giá)</div>
                    </div>
                <?php else: ?>
                    <div class="text-muted mb-3"><small>Chưa có đánh giá</small></div>
                <?php endif; ?>

            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item px-4 py-3"><i class="bi bi-award text-warning me-2 fs-5"></i> <strong>Kinh nghiệm:</strong> <?php echo $doctor['experience_years']; ?> năm</li>
                <li class="list-group-item px-4 py-3"><i class="bi bi-cash-stack text-success me-2 fs-5"></i> <strong>Phí tư vấn:</strong> <strong class="text-danger fs-5"><?php echo number_format($doctor['consultation_fee'], 0, ',', '.'); ?>₫</strong></li>
            </ul>
        </div>
    </div>

    <!-- Chi tiết và Lịch khám -->
    <div class="col-md-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i> Thông tin Cụ thể</h5>
            </div>
            <div class="card-body">
                <p style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($doctor['description']); ?></p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex align-items-center">
                <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-calendar-check me-2"></i> Lịch Khám Có Sẵn</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($groupedSchedules)): ?>
                    <p class="text-muted"><i class="bi bi-hand-index-thumb me-1"></i> Bấm vào khung giờ để tiến hành đặt lịch.</p>
                    
                    <div class="accordion" id="scheduleAccordion">
                        <?php 
                        $counter = 0;
                        foreach ($groupedSchedules as $date => $slots): 
                            $counter++;
                            $headingId = "heading" . $counter;
                            $collapseId = "collapse" . $counter;
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                                    <button class="accordion-button <?php echo $counter !== 1 ? 'collapsed' : ''; ?> fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                        <i class="bi bi-calendar-event me-2"></i> Ngày khám: <?php echo $date; ?>
                                    </button>
                                </h2>
                                <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $counter === 1 ? 'show' : ''; ?>" data-bs-parent="#scheduleAccordion">
                                    <div class="accordion-body">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($slots as $slot): ?>
                                                <a href="book.php?schedule_id=<?php echo $slot['id']; ?>" class="btn btn-outline-success">
                                                    <?php echo date('H:i', strtotime($slot['start_time'])); ?> - <?php echo date('H:i', strtotime($slot['end_time'])); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0 border-0">
                        <i class="bi bi-emoji-frown me-2"></i> Hiện tại Bác sĩ chưa có lịch trống cho các ngày tới. Vui lòng quay lại sau!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Phần Reviews -->
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white py-3 d-flex align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-star-fill text-warning me-2"></i> Đánh giá từ bệnh nhân</h5>
            </div>
            <div class="card-body">
                <?php if (count($reviews) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($rev['patient_name']); ?></h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($rev['created_at'])); ?></small>
                                </div>
                                <div class="text-warning mb-2" style="font-size: 0.9rem;">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rev['rating'] ? '★' : '☆';
                                    }
                                    ?>
                                </div>
                                <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($rev['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center my-4"><i class="bi bi-chat-square-text text-light" style="font-size: 2rem;"></i><br>Chưa có đánh giá nào cho bác sĩ này.</p>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>
