<?php
/* ══════════════════════════════════════════════════════════════════
   Professional error page — AI Exam System.
   Standalone by design: needs NO database, NO session start, NO
   other helpers (only app/helpers/errors.php), so it renders even
   when MySQL is down. Expects $GLOBALS set by the error handler:
     __APP_ERROR_CODE / __APP_ERROR_TITLE / __APP_ERROR_MESSAGE
     __APP_ERROR_REF
   Direct visits (?code=404) also work with safe defaults.
   ══════════════════════════════════════════════════════════════════ */
require_once __DIR__ . "/app/helpers/errors.php";

$code = isset($GLOBALS["__APP_ERROR_CODE"]) ? (int)$GLOBALS["__APP_ERROR_CODE"] : 0;
if ($code <= 0 && isset($_GET["code"])) {
    $code = (int)$_GET["code"];
}
if (!in_array($code, array(400, 401, 403, 404, 422, 500, 503), true)) {
    $code = 500;
}
list($default_title, $default_message) = app_error_meta($code);
$title = isset($GLOBALS["__APP_ERROR_TITLE"]) ? (string)$GLOBALS["__APP_ERROR_TITLE"] : $default_title;
$message = isset($GLOBALS["__APP_ERROR_MESSAGE"]) ? (string)$GLOBALS["__APP_ERROR_MESSAGE"] : $default_message;
$ref = isset($GLOBALS["__APP_ERROR_REF"]) ? (string)$GLOBALS["__APP_ERROR_REF"] : "";

// Role-aware dashboard link (read-only session inspection only).
$role = app_current_role();
$dashboards = array(
    "admin" => "admin/dashboard.php",
    "faculty" => "faculty/dashboard.php",
    "student" => "student/dashboard.php",
);
$dashboard_url = isset($dashboards[$role]) ? app_url($dashboards[$role]) : "";
$home_url = app_url("index.php");
$login_url = app_url("public/login.php");

if (!headers_sent()) {
    http_response_code($code);
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Content-Type: text/html; charset=utf-8");
}

$icons = array(
    400 => "bi-exclamation-triangle-fill",
    401 => "bi-lock-fill",
    403 => "bi-shield-lock-fill",
    404 => "bi-compass",
    422 => "bi-clipboard-x-fill",
    500 => "bi-emoji-frown-fill",
    503 => "bi-cloud-slash-fill",
);
$icon = isset($icons[$code]) ? $icons[$code] : "bi-emoji-frown-fill";
$accents = array(
    400 => array("#F59E0B", "#FBBF24"),
    401 => array("#0EA5E9", "#38BDF8"),
    403 => array("#EF4444", "#F87171"),
    404 => array("#4F46E5", "#818CF8"),
    422 => array("#F59E0B", "#FBBF24"),
    500 => array("#4F46E5", "#818CF8"),
    503 => array("#0EA5E9", "#38BDF8"),
);
$accent = isset($accents[$code]) ? $accents[$code] : $accents[500];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (int)$code; ?> &middot; <?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?> &middot; AI Exam System</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(app_url("assets/images/favicon.svg") . "?v=1", ENT_QUOTES, "UTF-8"); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars(app_url("assets/images/favicon-32x32.png") . "?v=1", ENT_QUOTES, "UTF-8"); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars(app_url("assets/images/favicon-16x16.png") . "?v=1", ENT_QUOTES, "UTF-8"); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars(app_url("assets/images/apple-touch-icon.png") . "?v=1", ENT_QUOTES, "UTF-8"); ?>">
    <meta name="theme-color" content="#2563EB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --err-a: <?php echo $accent[0]; ?>; --err-b: <?php echo $accent[1]; ?>; }
        * { box-sizing: border-box; }
        body {
            font-family: "Inter", system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 15% 15%, rgba(79,70,229,.10), transparent 45%),
                        radial-gradient(circle at 85% 90%, rgba(14,165,233,.10), transparent 45%),
                        linear-gradient(135deg, #0B1220 0%, #16213E 60%, #1D4ED8 160%);
            color: #E2E8F0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            margin: 0;
        }
        .err-card {
            width: 100%;
            max-width: 560px;
            text-align: center;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 48px 36px 40px;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            animation: errIn .55s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes errIn {
            from { opacity: 0; transform: translateY(18px) scale(.98); }
            to { opacity: 1; transform: none; }
        }
        .err-brand {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-family: "Poppins", sans-serif;
            font-weight: 700;
            font-size: .95rem;
            color: #fff;
            text-decoration: none;
            margin-bottom: 26px;
        }
        .err-logo {
            width: 38px; height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            box-shadow: 0 8px 20px -6px rgba(37,99,235,.6);
        }
        .err-icon {
            width: 84px; height: 84px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--err-a), var(--err-b));
            box-shadow: 0 0 0 10px rgba(255,255,255,.05), 0 14px 34px -10px var(--err-a);
            animation: errPulse 2.6s ease-in-out infinite;
        }
        @keyframes errPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .err-code {
            font-family: "Poppins", sans-serif;
            font-size: clamp(3rem, 12vw, 4.5rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -2px;
            background: linear-gradient(90deg, #fff, #A5B4FC);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }
        .err-title {
            font-family: "Poppins", sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }
        .err-msg { color: rgba(226,232,240,.78); font-size: .95rem; margin-bottom: 0; }
        .err-ref {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 600;
            letter-spacing: .04em;
            color: #CBD5E1;
            background: rgba(255,255,255,.07);
            border: 1px dashed rgba(255,255,255,.2);
        }
        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 26px;
        }
        .err-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: .88rem;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .err-btn:hover { transform: translateY(-2px); }
        .err-btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            box-shadow: 0 12px 26px -10px rgba(79,70,229,.7);
        }
        .err-btn-ghost {
            color: #E2E8F0;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
        }
        .err-btn-ghost:hover { background: rgba(255,255,255,.14); color: #fff; }
        .err-foot { margin-top: 26px; font-size: .75rem; color: rgba(148,163,184,.85); }
        @media (max-width: 480px) {
            .err-card { padding: 36px 22px 30px; }
            .err-btn { flex: 1 1 100%; justify-content: center; }
        }
        @media (prefers-reduced-motion: reduce) {
            .err-card, .err-icon { animation: none !important; }
            .err-btn { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="err-card" role="alert" aria-live="assertive">
        <a class="err-brand" href="<?php echo htmlspecialchars($home_url, ENT_QUOTES, "UTF-8"); ?>">
            <span class="err-logo"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            AI Exam System
        </a>
        <div class="err-icon" aria-hidden="true"><i class="bi <?php echo $icon; ?>"></i></div>
        <div class="err-code"><?php echo (int)$code; ?></div>
        <div class="err-title"><?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?></div>
        <p class="err-msg"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></p>
        <?php if ($ref !== "") { ?>
        <div><span class="err-ref"><i class="bi bi-hash" aria-hidden="true"></i>Reference: <?php echo htmlspecialchars($ref, ENT_QUOTES, "UTF-8"); ?></span></div>
        <?php } ?>
        <div class="err-actions">
            <?php if ($dashboard_url !== "") { ?>
            <a class="err-btn err-btn-primary" href="<?php echo htmlspecialchars($dashboard_url, ENT_QUOTES, "UTF-8"); ?>"><i class="bi bi-grid-1x2" aria-hidden="true"></i>Go to Dashboard</a>
            <?php } ?>
            <?php if ($code === 500 || $code === 503) { ?>
            <button class="err-btn err-btn-ghost" type="button" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Try Again</button>
            <?php } ?>
            <?php if ($code === 401 || $code === 403) { ?>
            <a class="err-btn err-btn-primary" href="<?php echo htmlspecialchars($login_url, ENT_QUOTES, "UTF-8"); ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Login Again</a>
            <?php } ?>
            <button class="err-btn err-btn-ghost" type="button" onclick="if (history.length > 1) { history.back(); } else { window.location.href = '<?php echo htmlspecialchars($home_url, ENT_QUOTES, "UTF-8"); ?>'; }"><i class="bi bi-arrow-left" aria-hidden="true"></i>Go Back</button>
            <a class="err-btn err-btn-ghost" href="<?php echo htmlspecialchars($home_url, ENT_QUOTES, "UTF-8"); ?>"><i class="bi bi-house" aria-hidden="true"></i>Homepage</a>
        </div>
        <div class="err-foot">If this keeps happening, share the reference ID with support.</div>
    </main>
</body>
</html>
