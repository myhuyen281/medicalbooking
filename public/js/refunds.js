document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-refund-confirm]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (!confirm(button.dataset.refundConfirm)) {
                event.preventDefault();
            }
        });
    });
});
