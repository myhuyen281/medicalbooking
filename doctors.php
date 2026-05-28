<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();

// Xử lý bộ lọc (Lọc theo chuyên khoa nếu có)
$specialty_filter = isset($_GET['specialty_id']) ? $_GET['specialty_id'] : '';
$hospital_filter = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;

// Lấy danh sách chuyên khoa cho bộ lọc
$db->query("SELECT * FROM specialties");
$specialties = $db->resultSet();

// Lấy danh sách Bác sĩ
$query = "SELECT d.id, u.full_name, s.name as specialty_name, d.experience_years, d.consultation_fee, d.description 
          FROM doctors d 
          INNER JOIN users u ON d.user_id = u.id 
          LEFT JOIN specialties s ON d.specialty_id = s.id
          WHERE d.approval_status = 'approved'";

if ($specialty_filter) {
    $query .= " AND d.specialty_id = :sid";
}
if ($hospital_filter > 0) {
    $query .= " AND d.hospital_id = :hospital_id";
}
$query .= " ORDER BY d.id DESC";

$db->query($query);
if ($specialty_filter) {
    $db->bind(':sid', $specialty_filter);
}
if ($hospital_filter > 0) {
    $db->bind(':hospital_id', $hospital_filter);
}
$doctors = $db->resultSet();
?>

<div class="row mb-4 bg-light p-4 rounded shadow-sm">
    <div class="col-md-12 text-center">
        <h2 class="text-primary fw-bold">Đội ngũ Bác sĩ Chuyên gia</h2>
        <p class="text-muted">Lựa chọn bác sĩ phù hợp với tình trạng sức khỏe của bạn để đặt lịch khám nhanh chóng.</p>
    </div>
</div>

<div class="row mb-4">
    <!-- Bộ Lọc -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-1"></i> Lọc kết quả</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="doctors.php" class="list-group-item list-group-item-action <?php echo empty($specialty_filter) ? 'active bg-primary border-primary' : ''; ?>">
                        Tất cả chuyên khoa
                    </a>
                    <?php foreach ($specialties as $spec): ?>
                        <a href="doctors.php?specialty_id=<?php echo $spec['id']; ?>" class="list-group-item list-group-item-action <?php echo ($specialty_filter == $spec['id']) ? 'active bg-primary border-primary' : ''; ?>">
                            <?php echo htmlspecialchars($spec['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách Bác sĩ -->
    <div class="col-md-9">
        <div class="row">
            <?php if (count($doctors) > 0): ?>
                <?php foreach ($doctors as $doc): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card shadow-sm h-100 border-0">
                            <!-- Hình ảnh mô phỏng Bác sĩ -->
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doc['full_name']); ?>&background=random&size=200" class="card-img-top" alt="Doctor" style="object-fit: cover; height: 200px;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-primary fw-bold text-center">BS. <?php echo htmlspecialchars($doc['full_name']); ?></h5>
                                <div class="text-center mb-3">
                                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars($doc['specialty_name'] ?? 'Đa khoa'); ?></span>
                                </div>
                                <ul class="list-unstyled mb-4">
                                    <li><i class="bi bi-award text-warning me-2"></i> <?php echo $doc['experience_years']; ?> năm kinh nghiệm</li>
                                    <li><i class="bi bi-cash-stack text-success me-2"></i> <?php echo number_format($doc['consultation_fee'], 0, ',', '.'); ?> VNĐ/lượt</li>
                                </ul>
                                <div class="mt-auto">
                                    <a href="doctor_detail.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary w-100 fw-bold">Xem chi tiết & Đặt lịch</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 mt-4 text-center">
                    <p class="text-muted fs-5">Không tìm thấy bác sĩ nào phù hợp với bộ lọc hiện tại.</p>
                    <a href="doctors.php" class="btn btn-primary mt-2">Xem Tất cả Bác sĩ</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
