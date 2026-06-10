<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
try {
    $db->query("SELECT * FROM news_posts WHERE is_active = 1 ORDER BY sort_order ASC, published_at DESC, id DESC");
    $newsPosts = $db->resultSet();
} catch (Exception $e) {
    $newsPosts = [];
}

$selectedCategory = $_GET['category'] ?? 'science';
$categoryOrder = ['science', 'service', 'medical', 'common'];
if (!in_array($selectedCategory, $categoryOrder, true)) {
    $selectedCategory = 'science';
}
if ($selectedCategory !== 'science') {
    $categoryOrder = array_values(array_unique(array_merge([$selectedCategory], $categoryOrder)));
}
$scienceNews = array_values(array_filter($newsPosts, function ($post) { return ($post['category'] ?? 'science') === 'science'; }));
$serviceNews = array_values(array_filter($newsPosts, function ($post) { return ($post['category'] ?? 'science') === 'service'; }));
$medicalNews = array_values(array_filter($newsPosts, function ($post) { return ($post['category'] ?? 'science') === 'medical'; }));
$commonNews = array_values(array_filter($newsPosts, function ($post) { return ($post['category'] ?? 'science') === 'common'; }));
$featuredByCategory = [
    'science' => $scienceNews,
    'service' => $serviceNews,
    'medical' => $medicalNews,
    'common' => $commonNews
];
$featuredPosts = count($featuredByCategory[$selectedCategory] ?? []) > 0 ? $featuredByCategory[$selectedCategory] : $newsPosts;
$featuredPost = $featuredPosts[0] ?? null;
$sidePosts = array_slice($featuredPosts, 1, 3);
$morePosts = array_slice($featuredPosts, 4);

function newsPageImageSrc($path) {
    if (empty($path)) {
        return '';
    }
    return preg_match('#^https?://#', $path) ? $path : $GLOBALS['base_url'] . '/' . $path;
}

function newsPageLink($post) {
    return !empty($post['link_url']) ? $post['link_url'] : '#';
}

function newsPageDate($post) {
    return date('d/m/Y', strtotime($post['published_at']));
}

$newsPageCategoryLabels = [
    'science' => 'Tin tức y khoa',
    'service' => 'Tin dịch vụ',
    'medical' => 'Tin y tế',
    'common' => 'Y học thường thức'
];

function newsPageCategoryLabel($post) {
    return $GLOBALS['newsPageCategoryLabels'][$post['category'] ?? 'science'] ?? 'Tin tức y khoa';
}
?>

<style>
.news-page-wrap {
    background: #ffffff;
    min-height: 70vh;
}
.news-tabs a {
    color: #9ca3af;
    font-size: 1.25rem;
    font-weight: 700;
}
.news-tabs a.active {
    color: #023f6d;
    font-size: 1.75rem;
    font-weight: 800;
}
.news-feature-card img {
    height: 370px;
    object-fit: cover;
    border-radius: 0 0 12px 12px;
}
.news-feature-card h2 {
    color: #023f6d;
    font-weight: 800;
    line-height: 1.35;
}
.news-side-item img {
    width: 140px;
    height: 92px;
    object-fit: cover;
    border-radius: 8px;
}
.news-side-item h5,
.news-grid-card h5 {
    color: #023f6d;
    font-weight: 800;
    line-height: 1.45;
}
.news-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #ffb02e;
    display: inline-block;
}
.news-grid-card img {
    height: 185px;
    object-fit: cover;
}
.news-category-section {
    padding: 4rem 0 1rem;
}
.news-category-title {
    color: #00b5f1;
    font-size: 2.1rem;
    font-weight: 800;
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.news-category-title::after {
    content: '';
    height: 2px;
    background: #00b5f1;
    flex: 1;
    opacity: 0.8;
}
.news-horizontal-scroll {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(250px, 1fr);
    gap: 1.5rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    padding: 1rem 0 1.5rem;
}
.news-horizontal-scroll::-webkit-scrollbar {
    height: 6px;
}
.news-horizontal-scroll::-webkit-scrollbar-thumb {
    background: #d5dbe3;
    border-radius: 999px;
}
.news-category-card {
    scroll-snap-align: start;
    border-radius: 14px;
    transition: all 0.25s ease;
}
.news-category-card:hover {
    border-color: #00b5f1 !important;
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(2, 63, 109, 0.12) !important;
}
.news-category-card img {
    height: 180px;
    object-fit: cover;
}
.news-category-card h5 {
    color: #023f6d;
    font-weight: 800;
    line-height: 1.35;
}
.news-read-more {
    color: #00b5f1;
    font-weight: 800;
}
@media (max-width: 768px) {
    .news-feature-card img {
        height: 220px;
    }
    .news-side-item img {
        width: 112px;
        height: 78px;
    }
}
</style>

<div class="news-page-wrap py-5">
    <div class="container">
        <div class="d-flex flex-wrap gap-4 align-items-center news-tabs mb-4">
            <?php foreach ($newsPageCategoryLabels as $categoryKey => $categoryLabel): ?>
                <a href="news.php?category=<?php echo urlencode($categoryKey); ?>" class="text-decoration-none <?php echo $selectedCategory === $categoryKey ? 'active' : ''; ?>"><?php echo htmlspecialchars($selectedCategory === $categoryKey ? mb_strtoupper($categoryLabel, 'UTF-8') : $categoryLabel); ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($featuredPost): ?>
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <a href="<?php echo htmlspecialchars(newsPageLink($featuredPost)); ?>" class="text-decoration-none news-feature-card d-block">
                    <img src="<?php echo htmlspecialchars(newsPageImageSrc($featuredPost['image_path'])); ?>" class="w-100 shadow-sm" alt="<?php echo htmlspecialchars($featuredPost['title']); ?>">
                    <h2 class="mt-4 mb-3"><?php echo htmlspecialchars($featuredPost['title']); ?></h2>
                    <?php if (!empty($featuredPost['excerpt'])): ?>
                        <p class="text-muted fs-6"><?php echo htmlspecialchars($featuredPost['excerpt']); ?></p>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($sidePosts as $post): ?>
                        <a href="<?php echo htmlspecialchars(newsPageLink($post)); ?>" class="text-decoration-none d-flex gap-3 news-side-item">
                            <img src="<?php echo htmlspecialchars(newsPageImageSrc($post['image_path'])); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <div>
                                <div class="d-flex align-items-center gap-2 text-muted small mb-2"><span class="news-dot"></span> <?php echo htmlspecialchars(newsPageCategoryLabel($post)); ?></div>
                                <h5 class="mb-2 fs-6"><?php echo htmlspecialchars($post['title']); ?></h5>
                                <div class="small fw-bold" style="color:#023f6d;"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars(newsPageDate($post)); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (count($morePosts) > 0): ?>
        <div class="row g-4 mt-4">
            <?php foreach ($morePosts as $post): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="<?php echo htmlspecialchars(newsPageLink($post)); ?>" class="text-decoration-none card border-0 shadow-sm rounded-4 overflow-hidden h-100 news-grid-card">
                        <img src="<?php echo htmlspecialchars(newsPageImageSrc($post['image_path'])); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 text-muted small mb-2"><span class="news-dot"></span> <?php echo htmlspecialchars(newsPageCategoryLabel($post)); ?></div>
                            <h5 class="fs-6 mb-2"><?php echo htmlspecialchars($post['title']); ?></h5>
                            <div class="small fw-bold" style="color:#023f6d;"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars(newsPageDate($post)); ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
            <div class="text-center text-muted py-5">Chưa có tin tức.</div>
        <?php endif; ?>

        <?php
            $categoryLabels = $newsPageCategoryLabels;
            $categoryData = [
                'science' => $scienceNews,
                'service' => $serviceNews,
                'medical' => $medicalNews,
                'common' => $commonNews
            ];
        ?>
        <?php foreach ($categoryOrder as $categoryKey): ?>
            <?php $sectionTitle = $categoryLabels[$categoryKey]; ?>
            <?php $sectionPosts = $categoryData[$categoryKey]; ?>
            <?php if (count($sectionPosts) > 0): ?>
            <section class="news-category-section" id="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $sectionTitle))); ?>">
                <h2 class="news-category-title mb-4"><?php echo htmlspecialchars($sectionTitle); ?></h2>
                <div class="news-horizontal-scroll">
                    <?php foreach ($sectionPosts as $post): ?>
                        <a href="<?php echo htmlspecialchars(newsPageLink($post)); ?>" class="text-decoration-none card border shadow-sm overflow-hidden news-category-card">
                            <img src="<?php echo htmlspecialchars(newsPageImageSrc($post['image_path'])); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 text-muted small mb-3"><span class="news-dot"></span> <?php echo htmlspecialchars($sectionTitle); ?></div>
                                <h5 class="mb-3"><?php echo htmlspecialchars($post['title']); ?></h5>
                                <div class="small fw-bold mb-3" style="color:#023f6d;"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars(newsPageDate($post)); ?><?php echo !empty($post['author']) ? ' - ' . htmlspecialchars($post['author']) : ''; ?></div>
                                <span class="news-read-more mt-auto">Xem tiếp <i class="bi bi-arrow-right ms-1"></i></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="news.php?category=<?php echo urlencode($categoryKey); ?>" class="btn btn-primary rounded-pill px-5 fw-bold">Xem tất cả <i class="bi bi-chevron-double-right ms-1"></i></a>
                </div>
            </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
