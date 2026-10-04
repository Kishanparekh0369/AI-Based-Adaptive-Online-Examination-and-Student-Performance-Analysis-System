<?php
/* ══════════════════════════════════════════════════
   Shared Admin UI renderers — ONE implementation
   reused by every admin management page.
   (Students / Faculty / Results / Feedback)
   ══════════════════════════════════════════════════ */

/**
 * Build a filter-preserving query string.
 * $params  = current filter values (assoc).
 * $over    = overrides, e.g. ["page" => 2]. Empty-string values are dropped,
 *           except an explicit "page" override is always kept.
 */
function admin_qs($params, $over = array())
{
    $merged = is_array($params) ? $params : array();
    foreach ($over as $k => $v) { $merged[$k] = $v; }
    $out = array();
    foreach ($merged as $k => $v) {
        if (is_array($v)) { continue; }
        $v = (string)$v;
        if ($v === "" && $k !== "page") { continue; }
        if ($k === "page" && ((int)$v <= 1) && !array_key_exists("page", $over)) { continue; }
        $out[$k] = $v;
    }
    $qs = http_build_query($out);
    return $qs !== "" ? "?" . $qs : "";
}

/** Breadcrumb + professional page header (shared typography/spacing). */
function admin_page_head($crumb, $icon, $eyebrow, $title, $desc, $actions = "")
{
    echo '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb dashboard-breadcrumb">';
    echo '<li class="breadcrumb-item">Admin</li>';
    echo '<li class="breadcrumb-item active">' . h($crumb) . '</li>';
    echo '</ol></nav>';
    echo '<div class="sp-page-head sp-fade-up"><div>';
    echo '<span class="sp-eyebrow"><i class="bi ' . h($icon) . '"></i> ' . h($eyebrow) . '</span>';
    echo '<h1>' . h($title) . '</h1>';
    echo '<p>' . h($desc) . '</p>';
    echo '</div>';
    if ($actions !== "") { echo '<div class="d-flex flex-wrap gap-2">' . $actions . '</div>'; }
    echo '</div>';
}

/** Statistic cards. Each card: [label, value, icon, tone, sub, url?]. */
function admin_stat_cards($cards)
{
    $i = 0;
    echo '<div class="row g-3 g-xl-4 mb-4">';
    foreach ($cards as $c) {
        $i++;
        $label = $c[0]; $value = $c[1]; $icon = $c[2]; $tone = $c[3];
        $sub = isset($c[4]) ? $c[4] : "";
        $url = isset($c[5]) ? $c[5] : "";
        echo '<div class="col-6 col-lg-4 col-xl sp-fade-up" data-delay="' . min($i, 6) . '">';
        if ($url !== "") { echo '<a class="admin-stat-link" href="' . h($url) . '" aria-label="Open ' . h($label) . '">'; }
        echo '<div class="dash-stat dash-tone-' . h($tone) . '">';
        echo '<div class="dash-stat-top"><span class="dash-stat-icon"><i class="bi ' . h($icon) . '"></i></span><span class="dash-stat-trend">Live</span></div>';
        echo '<div class="dash-stat-value">' . h((string)$value) . '</div>';
        echo '<p class="dash-stat-label">' . h($label) . '</p>';
        if ($sub !== "") { echo '<p class="dash-stat-sub">' . h($sub) . '</p>'; }
        echo '</div>';
        if ($url !== "") { echo '</a>'; }
        echo '</div>';
    }
    echo '</div>';
}

/** Active filter chips + clear-all. $chips = [[label, url]]. */
function admin_chips($chips, $clear_url)
{
    if (empty($chips)) { return; }
    echo '<div class="admin-chips" role="status" aria-live="polite">';
    echo '<span class="admin-filters-count">' . count($chips) . ' filter' . (count($chips) === 1 ? '' : 's') . '</span>';
    foreach ($chips as $chip) {
        echo '<a class="admin-chip" href="' . h($chip[1]) . '" title="Remove this filter">' . h($chip[0]) . '<i class="bi bi-x" aria-hidden="true"></i></a>';
    }
    echo '<a class="admin-chip" href="' . h($clear_url) . '" title="Clear all filters">Clear all<i class="bi bi-x-circle" aria-hidden="true"></i></a>';
    echo '</div>';
}

/** Windowed pagination preserving filters. */
function admin_pagination($total, $page, $per_page, $params, $base = "")
{
    $total = max(0, (int)$total);
    $per_page = max(1, (int)$per_page);
    $pages = max(1, (int)ceil($total / $per_page));
    $page = min(max(1, (int)$page), $pages);
    $from = $total > 0 ? (($page - 1) * $per_page + 1) : 0;
    $to = min($page * $per_page, $total);
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) { $start = max(1, $end - 4); }
    if ($end < $pages) { $end = min($pages, $start + 4); }
    echo '<nav class="admin-pagination" aria-label="Pagination">';
    echo '<span class="admin-page-info">Showing ' . $from . '&ndash;' . $to . ' of ' . $total . ' records</span>';
    if ($pages > 1) {
        echo '<ul class="pagination justify-content-center flex-wrap gap-1 mb-0">';
        if ($page > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . h($base . admin_qs($params, array("page" => $page - 1))) . '" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a></li>';
        }
        for ($i = $start; $i <= $end; $i++) {
            echo '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . h($base . admin_qs($params, array("page" => $i))) . '">' . $i . '</a></li>';
        }
        if ($page < $pages) {
            echo '<li class="page-item"><a class="page-link" href="' . h($base . admin_qs($params, array("page" => $page + 1))) . '" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';
        }
        echo '</ul>';
    }
    echo '</nav>';
}

/** Consistent status badges reusing the app badge system. */
function admin_badge($status)
{
    $s = (string)$status;
    if ($s === "Pass" || $s === "Active" || $s === "Published" || $s === "Completed" || $s === "Approved" || $s === "Read" || $s === "Replied") {
        $cls = "success";
    } elseif ($s === "Fail" || $s === "Failed") {
        $cls = "danger";
    } elseif ($s === "Inactive" || $s === "Pending" || $s === "Draft" || $s === "Unread") {
        $cls = "secondary";
    } else {
        $cls = "info";
    }
    return '<span class="badge bg-' . $cls . '">' . h($s) . '</span>';
}

/** Human duration: 45 sec / 12m 30s / 1h 5m. */
function admin_fmt_duration($s)
{
    $s = max(0, (int)$s);
    if ($s < 60) { return $s . " sec"; }
    $m = (int)floor($s / 60);
    $r = $s % 60;
    if ($m < 60) { return $r > 0 ? $m . "m " . $r . "s" : $m . " min"; }
    $h = (int)floor($m / 60);
    $rm = $m % 60;
    return $rm > 0 ? $h . "h " . $rm . "m" : $h . " hr";
}

/** Table empty-state row (colspan-aware). */
function admin_empty_row($colspan, $icon, $title, $text, $action = "")
{
    echo '<tr><td colspan="' . (int)$colspan . '"><div class="sp-empty">';
    echo '<div class="sp-empty-icon"><i class="bi ' . h($icon) . '" aria-hidden="true"></i></div>';
    echo '<h4>' . h($title) . '</h4>';
    echo '<p>' . h($text) . '</p>';
    if ($action !== "") { echo $action; }
    echo '</div></td></tr>';
}
