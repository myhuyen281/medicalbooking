<?php
require_once 'config/database.php';
include 'includes/header.php';

$guideTabs = [
    'dat-lich-kham' => 'Đặt lịch khám',
    'hoan-phi' => 'Quy trình hoàn phí',
    'faq' => 'Câu hỏi thường gặp',
    'quy-trinh-di-kham' => 'Quy trình đi khám',
    'hoi-dap' => 'Cộng đồng hỏi đáp khám chữa bệnh',
];

$guideContents = [
    'dat-lich-kham' => ['Đăng nhập hoặc chọn cơ sở y tế/dịch vụ cần đặt.', 'Chọn chuyên khoa, dịch vụ, ngày giờ phù hợp.', 'Nhập thông tin người khám và xác nhận lịch hẹn.'],
    'hoan-phi' => ['Gửi yêu cầu hủy/hoàn phí từ lịch hẹn.', 'Bộ phận hỗ trợ kiểm tra điều kiện hoàn phí.', 'Tiền hoàn được xử lý theo phương thức thanh toán ban đầu.'],
    'quy-trinh-di-kham' => ['Đến cơ sở y tế trước giờ hẹn 15-30 phút.', 'Xuất trình thông tin lịch hẹn hoặc mã đặt khám.', 'Làm theo hướng dẫn tại quầy tiếp nhận.'],
    'hoi-dap' => ['Gửi câu hỏi về triệu chứng hoặc dịch vụ cần tư vấn.', 'Nhân viên hỗ trợ tiếp nhận và phản hồi.', 'Trường hợp khẩn cấp vui lòng đến cơ sở y tế gần nhất.'],
];

$faqGroups = [
    'tai-khoan' => [
        'title' => 'Vấn đề tài khoản',
        'questions' => [
            '“Mã số bệnh nhân là gì “ làm sao tôi có thể biết được mã số bệnh nhân của mình?',
            '“Tôi quên mã số bệnh nhân của mình thì phải làm sao?',
            'Làm sao tôi biết bên mình đã có mã số bệnh nhân chưa?',
            'Tôi có thể chọn tuỳ ý một hồ sơ bệnh nhân của người khác để đăng ký khám bệnh cho mình không?',
        ],
    ],
    'quy-trinh' => [
        'title' => 'Vấn đề về quy trình đặt khám',
        'questions' => [
            'Tôi đặt lịch khám trên MedicailBooking như thế nào?',
            'Sau khi đặt lịch thành công tôi cần làm gì?',
            'Tôi có thể đổi ngày khám hoặc giờ khám không?',
            'Nếu bệnh viện hết khung giờ khám thì phải làm sao?',
        ],
    ],
    'thanh-toan' => [
        'title' => 'Vấn đề về thanh toán',
        'questions' => [
            'Tôi có thể thanh toán bằng hình thức nào?',
            'Thanh toán thành công nhưng chưa thấy lịch hẹn thì xử lý ra sao?',
            'Khi hủy lịch có được hoàn phí không?',
            'Bao lâu tôi nhận được tiền hoàn phí?',
        ],
    ],
    'chung' => [
        'title' => 'Vấn đề chung',
        'questions' => [
            'Tôi cần mang theo giấy tờ gì khi đi khám?',
            'Tôi nên đến trước giờ hẹn bao lâu?',
            'Nếu đến trễ giờ hẹn thì lịch khám có còn hiệu lực không?',
            'Tôi cần liên hệ ai khi cần hỗ trợ thêm?',
        ],
    ],
];
?>
</div>

<style>
    .guide-page {
        margin-top: -1rem;
        color: #003f63;
    }
    .guide-title-wrap {
        background: #fff;
        padding: 72px 16px 56px;
        border-bottom: 1px solid #e6e6e6;
        text-align: center;
    }
    .guide-title-wrap h1 {
        color: #12b5e8;
        font-size: clamp(2rem, 4vw, 2.65rem);
        font-weight: 800;
        margin-bottom: 12px;
    }
    .guide-title-wrap p {
        color: #666;
        margin: 0;
        font-size: 1rem;
    }
    .guide-tabs-shell {
        background: #fff;
        border-bottom: 1px solid #e7edf2;
        overflow: hidden;
    }
    .guide-tabs {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding: 26px 24px;
        white-space: nowrap;
        scrollbar-width: thin;
    }
    .guide-tab {
        background: #e9f8fd;
        color: #079fe8;
        border-radius: 999px;
        padding: 11px 20px;
        font-weight: 800;
        text-decoration: none;
        font-size: 1.05rem;
    }
    .guide-tab.active,
    .guide-tab:hover {
        background: #10b6e8;
        color: #fff;
    }
    .guide-body {
        background: #eaf6fb;
        min-height: 520px;
        padding: 40px 0 70px;
        position: relative;
    }
    .guide-panel {
        background: transparent;
        max-width: 1180px;
        margin: 0 auto;
        padding: 0 24px;
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }
    .guide-sidebar {
        width: 280px;
        max-width: 100%;
    }
    .guide-side-title {
        background: linear-gradient(135deg, #03b7ef, #04cfe3);
        color: #fff;
        border-radius: 4px;
        padding: 15px 20px;
        font-weight: 800;
        margin-bottom: 8px;
    }
    .guide-faq-list {
        background: transparent;
    }
    .guide-faq-item {
        border: 0;
        background: transparent;
        color: #063e63;
        font-weight: 800;
        padding: 17px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        text-align: left;
        border-radius: 4px;
    }
    .guide-faq-item.active {
        background: #fff;
        color: #00a8f0;
        border-left: 3px solid #03b7ef;
    }
    .guide-faq-item i {
        color: inherit;
        font-size: .9rem;
    }
    .guide-question-panel {
        display: none;
    }
    .guide-question-panel.active {
        display: block;
    }
    .guide-question-card {
        background: #fff;
        border-radius: 4px;
        color: #003f63;
        font-weight: 500;
        padding: 12px 16px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 11px;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .02);
    }
    .guide-question-card i {
        color: #222;
        font-size: .9rem;
    }
    .guide-main-panel {
        display: none;
    }
    .guide-main-panel.active {
        display: grid;
    }
    .guide-info-card {
        background: #fff;
        border-radius: 10px;
        padding: 22px;
        color: #003f63;
        font-weight: 700;
        margin-bottom: 12px;
    }
    .guide-call-btn {
        position: fixed;
        right: 30px;
        bottom: 92px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #ffa536;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        box-shadow: 0 10px 24px rgba(255, 165, 54, .35);
        z-index: 10;
        text-decoration: none;
    }
    .guide-chat-bar {
        position: fixed;
        right: 0;
        bottom: 0;
        background: #05bdea;
        color: #fff;
        padding: 13px 18px;
        border-radius: 8px 0 0 0;
        min-width: 390px;
        font-weight: 700;
        z-index: 10;
        text-decoration: none;
    }
    @media (max-width: 768px) {
        .guide-tabs {
            padding-left: 14px;
            gap: 10px;
        }
        .guide-tab {
            font-size: .92rem;
            padding: 10px 15px;
        }
        .guide-body {
            padding-top: 28px;
        }
        .guide-panel {
            grid-template-columns: 1fr;
        }
        .guide-sidebar {
            width: 100%;
        }
        .guide-chat-bar {
            left: 12px;
            right: 12px;
            min-width: auto;
            border-radius: 8px 8px 0 0;
        }
    }
</style>

<main class="guide-page">
    <section class="guide-title-wrap">
        <h1>MedicailBooking có thể giúp gì cho bạn?</h1>
        <p>Giải đáp các câu hỏi nhanh giúp quý khách hiểu rõ hơn về sản phẩm, dịch vụ của chúng tôi.</p>
    </section>

    <section class="guide-tabs-shell">
        <div class="guide-tabs">
            <?php foreach ($guideTabs as $id => $label): ?>
                <a class="guide-tab <?php echo $id === 'faq' ? 'active' : ''; ?>" href="#<?php echo $id; ?>" data-guide-tab="<?php echo $id; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="guide-body" id="faq">
        <?php foreach ($guideContents as $contentId => $items): ?>
            <div class="guide-panel guide-main-panel" data-guide-main-panel="<?php echo $contentId; ?>">
                <aside class="guide-sidebar"><div class="guide-side-title"><?php echo htmlspecialchars($guideTabs[$contentId]); ?></div></aside>
                <div class="guide-questions">
                    <?php foreach ($items as $item): ?>
                        <div class="guide-info-card"><i class="bi bi-check-circle-fill text-info me-2"></i><?php echo htmlspecialchars($item); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="guide-panel guide-main-panel active" data-guide-main-panel="faq">
            <aside class="guide-sidebar">
                <div class="guide-side-title">Danh sách câu hỏi</div>
                <div class="guide-faq-list">
                    <?php $firstGroup = true; ?>
                    <?php foreach ($faqGroups as $groupId => $group): ?>
                        <button class="guide-faq-item <?php echo $firstGroup ? 'active' : ''; ?>" type="button" data-guide-target="<?php echo $groupId; ?>">
                            <i class="bi bi-caret-right-fill"></i>
                            <span><?php echo $group['title']; ?></span>
                        </button>
                        <?php $firstGroup = false; ?>
                    <?php endforeach; ?>
                </div>
            </aside>
            <div class="guide-questions">
                <?php $firstPanel = true; ?>
                <?php foreach ($faqGroups as $groupId => $group): ?>
                    <div class="guide-question-panel <?php echo $firstPanel ? 'active' : ''; ?>" data-guide-panel="<?php echo $groupId; ?>">
                        <?php foreach ($group['questions'] as $question): ?>
                            <a class="guide-question-card text-decoration-none" href="#">
                                <span><?php echo $question; ?></span>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php $firstPanel = false; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <a class="guide-call-btn" href="tel:19002115"><i class="bi bi-telephone-fill"></i></a>
        <a class="guide-chat-bar" href="booking_at_facility.php"><i class="bi bi-chat-left-text me-2"></i>TƯ VẤN ĐẶT KHÁM TRỰC TUYẾN</a>
    </section>
</main>

<script>
    function activateGuideTab(tabId) {
        document.querySelectorAll('.guide-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.guideTab === tabId);
        });
        document.querySelectorAll('.guide-main-panel').forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.guideMainPanel === tabId);
        });
    }
    document.querySelectorAll('.guide-tab').forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            const tabId = tab.dataset.guideTab;
            history.replaceState(null, '', '#' + tabId);
            activateGuideTab(tabId);
        });
    });
    activateGuideTab((location.hash || '#faq').replace('#', ''));
    document.querySelectorAll('.guide-faq-item').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = button.dataset.guideTarget;
            document.querySelectorAll('.guide-faq-item').forEach(function (item) {
                item.classList.toggle('active', item === button);
            });
            document.querySelectorAll('.guide-question-panel').forEach(function (panel) {
                panel.classList.toggle('active', panel.dataset.guidePanel === target);
            });
        });
    });
</script>

<div class="container mt-4">
<?php include 'includes/footer.php'; ?>
