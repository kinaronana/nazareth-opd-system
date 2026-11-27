(function () {
    'use strict';
    const counters = Array.from(document.querySelectorAll('[data-counter-key]'));
    function animate(element, target) {
        const start = Number(element.dataset.currentValue ?? element.textContent) || 0;
        const end = Number(target);
        if (!Number.isFinite(end)) return;
        element.dataset.currentValue = String(end);
        const began = performance.now(), duration = 800;
        function tick(now) {
            const progress = Math.min((now - began) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(start + (end - start) * eased).toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }
    async function refresh(initial) {
        try {
            const response = await fetch('/api/dashboard_metrics.php', {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            if (!data.metrics) return;
            counters.forEach(el => {
                const key = el.dataset.counterKey;
                if (Object.prototype.hasOwnProperty.call(data.metrics,key)) {
                    const value = Number(data.metrics[key]);
                    if (initial || el.dataset.currentValue !== String(value)) animate(el,value);
                }
            });
        } catch (error) { /* Server-rendered values remain available offline. */ }
    }
    if (counters.length) { refresh(true); window.setInterval(() => refresh(false),30000); }
})();
