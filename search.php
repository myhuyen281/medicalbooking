<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
$keyword = trim($_GET['kw'] ?? '');
$tab = $_GET['tab'] ?? 'all';
$like = '%' . $keyword . '%';
$results = ['facilities' => [], 'doctors' => [], 'subjects' => [], 'packages' => []];

try {
    $db->query("SELECT DISTINCT h.* FROM hospitals h INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' WHERE (h.name LIKE :kw OR h.address LIKE :kw OR h.description LIKE :kw) AND COALESCE(u.hospital_approval_status, 'approved') = 'approved' AND h.logo_url IS NOT NULL AND h.logo_url <> '' ORDER BY h.id DESC LIMIT 20");
    $db->bind(':kw', $like);
    $results['facilities'] = $db->resultSet();
} catch (Exception $e) {}

try {
    $db->query("SELECT d.*, u.full_name, h.name AS hospital_name, s.name AS specialty_name FROM doctors d LEFT JOIN users u ON u.id = d.user_id LEFT JOIN hospitals h ON h.id = d.hospital_id LEFT JOIN specialties s ON s.id = d.specialty_id WHERE u.full_name LIKE :kw OR h.name LIKE :kw OR s.name LIKE :kw ORDER BY d.id DESC LIMIT 20");
    $db->bind(':kw', $like);
    $results['doctors'] = $db->resultSet();
} catch (Exception $e) {}

try {
    $db->query("SELECT hs.*, h.name AS hospital_name, h.logo_url FROM hospital_services hs INNER JOIN hospitals h ON h.id = hs.hospital_id LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' WHERE (hs.name LIKE :kw OR hs.specialty_name LIKE :kw OR h.name LIKE :kw) AND COALESCE(u.hospital_approval_status, 'approved') = 'approved' ORDER BY hs.id DESC LIMIT 30");
    $db->bind(':kw', $like);
    $subjectRows = $db->resultSet();
    $seenSubjects = [];
    foreach ($subjectRows as $subjectRow) {
        $subjectKey = mb_strtolower(trim(($subjectRow['name'] ?? '') . '|' . ($subjectRow['hospital_name'] ?? '')), 'UTF-8');
        if (isset($seenSubjects[$subjectKey])) {
            continue;
        }
        $seenSubjects[$subjectKey] = true;
        $results['subjects'][] = $subjectRow;
    }
} catch (Exception $e) {}

try {
    $db->query("SELECT lp.*, h.name AS hospital_name FROM lab_packages lp INNER JOIN hospitals h ON h.id = lp.hospital_id WHERE lp.is_active = 1 AND (lp.name LIKE :kw OR lp.category LIKE :kw OR h.name LIKE :kw) ORDER BY lp.id DESC LIMIT 20");
    $db->bind(':kw', $like);
    $results['packages'] = $db->resultSet();
} catch (Exception $e) {}

$counts = [
    'facilities' => count($results['facilities']),
    'doctors' => count($results['doctors']),
    'subjects' => count($results['subjects']),
    'packages' => count($results['packages'])
];
$counts['all'] = array_sum($counts);
if (!isset($counts[$tab])) {
    $tab = 'all';
}

function searchLogoSrc($path) {
    if (empty($path)) return 'https://img.icons8.com/ios/80/00b5f1/hospital-3.png';
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}
?>

<style>
.search-hero { background:#fff; padding: 3rem 0 2rem; border-bottom:1px solid #e5e7eb; }
.search-title { color:#00b5f1; font-size:2.4rem; font-weight:800; }
.search-box { max-width:660px; border:0; border-radius:14px; box-shadow:0 8px 24px rgba(2,63,109,.08); padding:1rem 1.25rem; }
.search-tabs .btn { border:0; background:#eaf9ff; color:#00a8e8; font-weight:800; border-radius:999px; padding:.75rem 1.25rem; }
.search-tabs .btn.active { background:#00b5f1; color:#fff; }
.search-results { background:#eaf8fd; padding:1.25rem 0 3rem; }
.search-card { border:0; border-radius:14px; box-shadow:0 6px 18px rgba(2,63,109,.08); }
.search-card img { width:56px; height:56px; object-fit:contain; border-radius:999px; }
.search-card h5 { color:#023f6d; font-weight:800; }
</style>

<div class="search-hero text-center">
    <div class="container">
        <nav class="text-start small fw-bold mb-4"><a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a> <span class="mx-2">›</span> <span style="color:#00b5f1;">Tìm kiếm</span></nav>
        <h1 class="search-title mb-4">Kết quả tìm kiếm</h1>
        <form action="search.php" method="GET">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <input type="text" name="kw" class="form-control mx-auto search-box" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Tìm kiếm">
        </form>
    </div>
</div>

<div class="container py-4 text-center search-tabs">
    <?php $tabs = ['all' => 'Tất cả', 'facilities' => 'Cơ sở y tế', 'doctors' => 'Bác sĩ', 'subjects' => 'Chuyên khoa', 'packages' => 'Gói khám']; ?>
    <?php foreach ($tabs as $key => $label): ?>
        <a href="search.php?kw=<?php echo urlencode($keyword); ?>&tab=<?php echo $key; ?>&page=1" class="btn me-2 mb-2 <?php echo $tab === $key ? 'active' : ''; ?>"><?php echo $label; ?> (<?php echo (int)$counts[$key]; ?>)</a>
    <?php endforeach; ?>
</div>

<div class="search-results">
    <div class="container">
        <div class="row g-3">
            <?php if ($tab === 'all' || $tab === 'subjects'): ?>
                <?php foreach ($results['subjects'] as $item): ?>
                    <div class="col-md-6">
                        <a href="specialty_booking.php?id=<?php echo (int)$item['hospital_id']; ?>&facility=<?php echo urlencode($item['hospital_name']); ?>&service_id=<?php echo (int)$item['id']; ?>" class="card search-card p-3 text-decoration-none h-100">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?php echo htmlspecialchars(searchLogoSrc($item['logo_url'] ?? '')); ?>" alt="<?php echo htmlspecialchars($item['hospital_name']); ?>">
                                <div class="text-start flex-grow-1"><h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5><div style="color:#00b5f1;"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($item['hospital_name']); ?></div></div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($tab === 'all' || $tab === 'facilities'): ?>
                <?php foreach ($results['facilities'] as $item): ?>
                    <div class="col-md-6"><a href="facility_detail.php?id=<?php echo (int)$item['id']; ?>" class="card search-card p-3 text-decoration-none"><h5><?php echo htmlspecialchars($item['name']); ?></h5><div class="text-muted"><?php echo htmlspecialchars($item['address'] ?? ''); ?></div></a></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($tab === 'all' || $tab === 'doctors'): ?>
                <?php foreach ($results['doctors'] as $item): ?>
                    <div class="col-md-6"><div class="card search-card p-3"><h5><?php echo htmlspecialchars($item['full_name'] ?? 'Bác sĩ'); ?></h5><div class="text-muted"><?php echo htmlspecialchars(($item['specialty_name'] ?? '') . ' - ' . ($item['hospital_name'] ?? '')); ?></div></div></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($tab === 'all' || $tab === 'packages'): ?>
                <?php foreach ($results['packages'] as $item): ?>
                    <div class="col-md-6"><a href="lab_package_booking.php?package_id=<?php echo (int)$item['id']; ?>" class="card search-card p-3 text-decoration-none"><h5><?php echo htmlspecialchars($item['name']); ?></h5><div class="text-muted"><?php echo htmlspecialchars($item['hospital_name']); ?></div></a></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($counts[$tab] === 0): ?><div class="col-12 text-center text-muted py-5">Không tìm thấy kết quả.</div><?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
