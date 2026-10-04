/* ══════════════════════════════════════════════════
   Shared Chart System — ChartManager.
   ONE initialization path for every Chart.js graph:
   - destroys any previous instance on the canvas
   - merges professional defaults (responsive sizing,
     animation, fonts, tooltips, legends)
   - validates data, toggles empty states
   - centralized debounced resize (sidebar, modal,
     fonts, orientation, window)
   Usage: ChartManager.create("canvasId",
     { type: "bar", data: {...}, options: {...} });
   ══════════════════════════════════════════════════ */
(function () {
    "use strict";

    var registry = {};
    var reducedMotion = false;
    try {
        reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (e) { reducedMotion = false; }

    function isObject(v) {
        return v !== null && typeof v === "object" && !Array.isArray(v);
    }

    function mergeDeep(base, extra) {
        var out = {};
        var k;
        for (k in base) {
            if (Object.prototype.hasOwnProperty.call(base, k)) { out[k] = base[k]; }
        }
        if (!isObject(extra)) { return out; }
        for (k in extra) {
            if (!Object.prototype.hasOwnProperty.call(extra, k)) { continue; }
            if (isObject(extra[k]) && isObject(out[k])) { out[k] = mergeDeep(out[k], extra[k]); }
            else { out[k] = extra[k]; }
        }
        return out;
    }

    function standardDefaults() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 120,
            animation: reducedMotion ? false : { duration: 700, easing: "easeOutQuart" },
            plugins: {
                legend: {
                    labels: { usePointStyle: true, boxWidth: 6, boxHeight: 6, padding: 14, font: { size: 11 } }
                },
                tooltip: {
                    backgroundColor: "#0F172A",
                    titleFont: { weight: "600", size: 12 },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: true,
                    boxPadding: 4
                }
            }
        };
    }

    function applyGlobalDefaults() {
        if (typeof Chart === "undefined" || !Chart.defaults) { return; }
        try {
            Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = "#64748B";
            Chart.defaults.borderColor = "rgba(226, 232, 240, 0.9)";
        } catch (e) { /* older Chart.js — page options still apply */ }
    }

    function hasUsableData(data) {
        if (!data || !Array.isArray(data.labels) || data.labels.length === 0) { return false; }
        if (!Array.isArray(data.datasets) || data.datasets.length === 0) { return false; }
        return data.datasets.some(function (ds) {
            return ds && Array.isArray(ds.data) && ds.data.length > 0;
        });
    }

    function setEmptyState(canvas, empty) {
        var box = canvas.closest ? canvas.closest(".chart-container, .rp-chart-wrap") : null;
        if (box) { box.classList.toggle("is-empty", !!empty); }
        var card = canvas.closest ? canvas.closest(".sp-panel, .dashboard-panel, .rp-chart-box, .dashboard-card") : null;
        var state = card ? card.querySelector(".chart-empty-state") : null;
        if (!state && box && box.parentElement) {
            state = box.parentElement.querySelector(".chart-empty-state");
        }
        if (state) { state.classList.toggle("show", !!empty); }
    }

    function create(canvasId, config) {
        if (typeof Chart === "undefined") { return null; }
        var el = document.getElementById(canvasId);
        if (!el) { return null; }
        destroy(canvasId);
        config = config || {};
        if (!hasUsableData(config.data)) {
            setEmptyState(el, true);
            return null;
        }
        setEmptyState(el, false);
        var chart = new Chart(el, {
            type: config.type || "bar",
            data: config.data,
            options: mergeDeep(standardDefaults(), config.options || {})
        });
        registry[canvasId] = chart;
        return chart;
    }

    function destroy(canvasId) {
        var old = registry[canvasId];
        if (old) {
            try { old.destroy(); } catch (e) {}
            delete registry[canvasId];
            return;
        }
        if (typeof Chart !== "undefined" && typeof Chart.getChart === "function") {
            try {
                var existing = Chart.getChart(canvasId);
                if (existing) { existing.destroy(); }
            } catch (e) {}
        }
    }

    function destroyAll() {
        Object.keys(registry).forEach(destroy);
    }

    function get(canvasId) {
        return registry[canvasId] || null;
    }

    var resizeTimer = null;
    function resizeAll() {
        Object.keys(registry).forEach(function (id) {
            try { registry[id].resize(); } catch (e) {}
        });
    }
    function scheduleResize(delay) {
        if (resizeTimer) { clearTimeout(resizeTimer); }
        resizeTimer = setTimeout(resizeAll, typeof delay === "number" ? delay : 150);
    }

    function initHooks() {
        window.addEventListener("resize", function () { scheduleResize(150); });
        window.addEventListener("orientationchange", function () { scheduleResize(250); });
        if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === "function") {
            document.fonts.ready.then(function () { scheduleResize(100); });
        }
        // Sidebar open/close/backdrop changes content width without a window resize.
        ["sidebar-open-btn", "sidebar-close-btn", "sidebarBackdrop"].forEach(function (idOrClass) {
            var el = document.getElementById(idOrClass);
            if (el) { el.addEventListener("click", function () { scheduleResize(380); }); }
        });
        Array.prototype.forEach.call(document.querySelectorAll(".sidebar-open-btn, .sidebar-close-btn"), function (btn) {
            btn.addEventListener("click", function () { scheduleResize(380); });
        });
        // Charts inside modals: resize once the modal is visible.
        document.addEventListener("shown.bs.modal", function (ev) {
            var modal = ev && ev.target ? ev.target : document;
            var found = false;
            Object.keys(registry).forEach(function (id) {
                var el = document.getElementById(id);
                if (el && modal.contains && modal.contains(el)) {
                    try { registry[id].resize(); found = true; } catch (e) {}
                }
            });
            if (!found) { scheduleResize(100); }
        });
    }

    applyGlobalDefaults();

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initHooks);
    } else {
        initHooks();
    }

    window.ChartManager = {
        create: create,
        destroy: destroy,
        destroyAll: destroyAll,
        get: get,
        resizeAll: resizeAll,
        scheduleResize: scheduleResize
    };
})();
