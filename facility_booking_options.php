<?php
require_once 'config/database.php';
include 'includes/header.php';

$facilityName = isset($_GET['facility']) ? trim($_GET['facility']) : 'Bệnh viện tại Cần Thơ';
$db = new Database();
try {
    $db->query("CREATE TABLE IF NOT EXISTS hospital_booking_forms (id INT AUTO_INCREMENT PRIMARY KEY, hospital_id INT NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) NULL, target VARCHAR(30) NOT NULL DEFAULT 'specialty', sort_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (hospital_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
} catch (Exception $e) {
}
$hospital = null;
    $bookingForms = [];

try {
    $db->query("SELECT h.*
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE h.name = :name OR h.name LIKE :like_name
                ORDER BY CASE WHEN u.id IS NOT NULL THEN 0 ELSE 1 END, h.id DESC
                LIMIT 1");
    $db->bind(':name', $facilityName);
    $db->bind(':like_name', '%' . $facilityName . '%');
    $hospital = $db->single();

    if ($hospital) {
        $facilityName = $hospital['name'];
        $db->query("SELECT f.*, hs.id AS service_id, hs.name AS service_name, hs.service_icon, hs.service_target
                    FROM hospital_booking_forms f
                    LEFT JOIN hospital_services hs ON hs.id = (
                        SELECT hs2.id
                        FROM hospital_services hs2
                        WHERE hs2.hospital_id = f.hospital_id AND hs2.booking_form_id = f.id
                        ORDER BY hs2.id ASC
                        LIMIT 1
                    )
                    WHERE f.hospital_id = :hospital_id
                    ORDER BY f.sort_order ASC, f.id ASC");
        $db->bind(':hospital_id', $hospital['id']);
        $bookingForms = $db->resultSet();
        try {
            $db->query("SELECT lp.* FROM lab_packages lp WHERE lp.hospital_id = :hospital_id AND lp.is_active = 1 ORDER BY FIELD(lp.category, 'lab', 'imaging', 'vaccination'), lp.id ASC");
            $db->bind(':hospital_id', $hospital['id']);
            $packageRows = $db->resultSet();
            $packageIcons = ['lab' => 'bi-clipboard2-pulse', 'imaging' => 'bi-camera', 'vaccination' => 'bi-eyedropper'];
            foreach ($packageRows as $packageRow) {
                $bookingForms[] = ['id' => 0, 'name' => $packageRow['name'], 'icon' => $packageRow['icon_path'] ?? '', 'service_icon' => $packageIcons[$packageRow['category'] ?? 'lab'] ?? 'bi-calendar-check', 'package_id' => (int)$packageRow['id'], 'is_package' => 1];
            }
        } catch (Exception $e) {}
    }
} catch (Exception $e) {
$bookingForms = [];
}
?>

<div class="py-4 px-2 px-md-4 rounded-4" style="background-color: #eaf7fc; min-height: 520px;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb fw-semibold">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color: #023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="index.php#facilitiesScroll" class="text-decoration-none" style="color: #023f6d;"><?php echo htmlspecialchars($facilityName); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color: #00b5f1;">Hình thức đặt khám</li>
        </ol>
    </nav>

    <div class="text-center mb-4">
        <h1 class="fw-bold mb-1" style="color: #00b5f1; font-size: clamp(2rem, 5vw, 3.2rem);">Các hình thức đặt khám</h1>
        <p class="mb-0" style="color: #023f6d;">Đặt khám nhanh chóng, không phải chờ đợi với nhiều cơ sở y tế trên khắp các tỉnh thành</p>
    </div>

    <div class="row justify-content-center g-4 mt-2">
        <?php if (count($bookingForms) > 0): ?>
            <?php foreach ($bookingForms as $form): ?>
                <?php
                    $serviceLink = !empty($form['is_package']) ? 'lab_package_booking.php?package_id=' . (int)$form['package_id'] : 'specialty_booking.php?facility=' . urlencode($facilityName) . '&booking_form_id=' . (int)$form['id'];
                    $cardTitle = $form['name'];
                    $cardIcon = !empty($form['service_icon']) ? $form['service_icon'] : 'bi-calendar-check';
                ?>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo htmlspecialchars($serviceLink); ?>" class="text-decoration-none">
                        <div class="bg-white rounded-4 shadow-sm px-5 py-4 d-flex align-items-center gap-4 h-100" style="min-height: 130px;">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 64px; height: 64px; background-color: #eaf7ff;">
                                <?php if (!empty($form['icon']) && strpos($form['icon'], 'bi-') !== 0): ?>
                                    <img src="<?php echo htmlspecialchars($base_url . '/' . $form['icon']); ?>" alt="<?php echo htmlspecialchars($cardTitle); ?>" style="width: 48px; height: 48px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="bi <?php echo htmlspecialchars($cardIcon); ?>" style="font-size: 2.4rem; color: #00a8f0;"></i>
                                <?php endif; ?>
                            </span>
                            <div class="fw-bold fs-5" style="color: #023f6d;"><?php echo htmlspecialchars($cardTitle); ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-md-10 col-lg-8">
                <div class="bg-white rounded-4 shadow-sm p-4 text-center fw-semibold" style="color: #023f6d;">Cơ sở y tế chưa cập nhật dịch vụ đặt khám.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="row justify-content-center mt-4">
        <div class="col-md-10 col-lg-8">
            <a href="index.php#facilitiesScroll" class="text-decoration-none fw-bold" style="color: #023f6d;">Quay lại <i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
