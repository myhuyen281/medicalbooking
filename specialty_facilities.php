<?php
require_once 'config/database.php';
include 'includes/header.php';

$keyword = trim($_GET['q'] ?? '');
$specialties = [
    ['name' => 'Da liễu', 'icon' => '<i class="bi bi-bandaid"></i>'],
    ['name' => 'Bác sĩ Gia Đình', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/medical-doctor.png" alt="Bác sĩ Gia Đình">'],
    ['name' => 'Tiêu Hóa Gan Mật', 'icon' => '<i class="bi bi-capsule-pill"></i>'],
    ['name' => 'Nội Tổng Quát', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/health-book.png" alt="Nội Tổng Quát">'],
    ['name' => 'Nội Tiết', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/gender.png" alt="Nội Tiết">'],
    ['name' => 'Nội Tim Mạch', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/heart-with-pulse.png" alt="Nội Tim Mạch">'],
    ['name' => 'Nội Thần Kinh', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/brain--v1.png" alt="Nội Thần Kinh">'],
    ['name' => 'Nội Cơ Xương Khớp', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/knee-joint.png" alt="Nội Cơ Xương Khớp">'],
    ['name' => 'Tai Mũi Họng', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/head-with-brain.png" alt="Tai Mũi Họng">'],
    ['name' => 'Mắt', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/visible--v1.png" alt="Mắt">'],
    ['name' => 'Nội Tiêu Hoá', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/stomach.png" alt="Nội Tiêu Hoá">'],
    ['name' => 'Nội Truyền Nhiễm', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/liver.png" alt="Nội Truyền Nhiễm">'],
    ['name' => 'Nội Hô Hấp', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/lungs.png" alt="Nội Hô Hấp">'],
    ['name' => 'Nội Tiết Niệu', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/kidneys.png" alt="Nội Tiết Niệu">'],
    ['name' => 'Ngoại Cơ Xương Khớp', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/knee-joint.png" alt="Ngoại Cơ Xương Khớp">'],
    ['name' => 'Sản - Phụ Khoa', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/uterus.png" alt="Sản - Phụ Khoa">'],
    ['name' => 'Ngoại Tiêu Hoá', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/stomach.png" alt="Ngoại Tiêu Hoá">'],
    ['name' => 'Ngoại Tiết Niệu', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/kidneys.png" alt="Ngoại Tiết Niệu">'],
    ['name' => 'Tâm Lý', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/mental-health.png" alt="Tâm Lý">'],
    ['name' => 'Ngoại Hô Hấp', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/lungs.png" alt="Ngoại Hô Hấp">'],
    ['name' => 'Ngoại Thần Kinh', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/brain--v1.png" alt="Ngoại Thần Kinh">'],
    ['name' => 'Răng Hàm Mặt', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/tooth.png" alt="Răng Hàm Mặt">'],
    ['name' => 'Chấn Thương Chỉnh Hình', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/broken-bone.png" alt="Chấn Thương Chỉnh Hình">'],
    ['name' => 'Vô Sinh - Hiếm Muộn', 'icon' => '<i class="bi bi-gender-ambiguous"></i>'],
    ['name' => 'Nhi Khoa', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/baby.png" alt="Nhi Khoa">'],
    ['name' => 'Nam Khoa', 'icon' => '<img src="https://img.icons8.com/ios/50/00b5f1/male.png" alt="Nam Khoa">']
];
if ($keyword !== '') {
    $specialties = array_values(array_filter($specialties, function ($item) use ($keyword) {
        return stripos($item['name'], $keyword) !== false;
    }));
}
?>

<div class="py-4">
    <nav aria-label="breadcrumb" class="mb-5">
        <ol class="breadcrumb fw-semibold">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color:#00b5f1;">Đặt khám chuyên khoa</li>
        </ol>
    </nav>

    <form class="mx-auto mb-5" style="max-width:660px;" method="get" action="specialty_facilities.php">
        <div class="input-group bg-white shadow-sm rounded-4 overflow-hidden border">
            <span class="input-group-text bg-white border-0 ps-4"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" class="form-control border-0 py-3 shadow-none" placeholder="Tìm kiếm">
        </div>
    </form>

    <div class="row g-5 justify-content-center text-center">
        <?php foreach ($specialties as $item): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="facilities.php?q=<?php echo urlencode($item['name']); ?>" class="text-decoration-none specialty-grid-item d-block">
                    <div class="icon-wrapper rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                        <?php echo $item['icon']; ?>
                    </div>
                    <h5 class="fw-medium mb-0" style="color:#023f6d; font-size:1.15rem; line-height:1.25;"><?php echo htmlspecialchars($item['name']); ?></h5>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (count($specialties) === 0): ?>
            <div class="col-12"><div class="text-muted py-5">Không tìm thấy chuyên khoa phù hợp.</div></div>
        <?php endif; ?>
    </div>
</div>

<style>
.specialty-grid-item .icon-wrapper {
    width:80px;
    height:80px;
    border:1px solid #dfeaf2;
    color:#00b5f1;
    background:#fff;
    box-shadow:0 2px 7px rgba(0,0,0,.06);
    transition:all .2s ease;
}
.specialty-grid-item i { font-size:50px; line-height:1; color:#00b5f1; }
.specialty-grid-item img { width:50px; height:50px; }
.specialty-grid-item:hover .icon-wrapper { background:#eaf8ff; border-color:#00b5f1; transform:translateY(-3px); }
.specialty-grid-item:hover h5 { color:#00a8f0 !important; }
</style>

<?php include 'includes/footer.php'; ?>
