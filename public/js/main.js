function kiloSmoothScrollBy(target, delta, duration) {
    var el = typeof target === 'string' ? document.getElementById(target) : target;
    if (!el) return;

    if (typeof duration !== 'number') duration = 600;

    if (el._kiloScrollRAF) {
        cancelAnimationFrame(el._kiloScrollRAF);
    }

    var direction = delta < 0 ? -1 : 1;
    var page = el.clientWidth;

    var maxScroll = el.scrollWidth - el.clientWidth;
    var start = el.scrollLeft;
    var dest = Math.max(0, Math.min(start + direction * page, maxScroll));
    var change = dest - start;

    if (change === 0) return;

    var startTime = null;
    var easeOutCubic = function (t) { return 1 - Math.pow(1 - t, 3); };

    function step(now) {
        if (startTime === null) startTime = now;
        var progress = Math.min((now - startTime) / duration, 1);
        el.scrollLeft = start + change * easeOutCubic(progress);
        if (progress < 1) {
            el._kiloScrollRAF = requestAnimationFrame(step);
        } else {
            el._kiloScrollRAF = null;
        }
    }

    el._kiloScrollRAF = requestAnimationFrame(step);
}
