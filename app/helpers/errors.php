<?php
/* ══════════════════════════════════════════════════════════════════
   Central Error Handling System — AI Exam System
   ------------------------------------------------------------------
   ONE error handler for the ENTIRE application. Loaded once from
   app/config/database.php (which every page already includes), so
   no per-page wiring is required.

   What it does:
   - Registers PHP error / exception / shutdown handlers.
   - Logs every unexpected error with a unique reference ID to a
     daily, browser-protected log file (never shown to users).
   - Renders a professional error page for normal requests, or a
     structured JSON error for AJAX/API requests.
   - Detects the application base path dynamically — works under
     http://localhost/, /kishan/AIProject/, vhosts, HTTPS, etc.
     No hard-coded paths anywhere in this file.

   What it does NOT do (by design):
   - It never changes happy-path behavior (logins, guards, alerts).
   - It never destroys sessions (except secure logout, untouched).
   - It never exposes SQL, paths, keys, passwords or stack traces.
   - errors.php itself needs NO database, NO session, NO other
     helpers, so error.php keeps working even when MySQL is down.

   Environment:
   - APP_DEBUG true  = verbose server logs (request context).
   - APP_DEBUG false = minimal server logs.
   - Users ALWAYS see only the safe professional page/JSON.
   ══════════════════════════════════════════════════════════════════ */

if (!defined("APP_DEBUG")) {
    define("APP_DEBUG", true);
}
if (!defined("APP_ROOT")) {
    // errors.php lives in app/helpers → two levels up is the project root.
    define("APP_ROOT", dirname(__DIR__, 2));
}

/* ───────────────────────── Base path / URL ────────────────────── */

function app_detect_base_path()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = "";
    if (PHP_SAPI === "cli" || empty($_SERVER["SCRIPT_NAME"])) {
        return $cached;
    }
    // Filesystem location of the running script, relative to APP_ROOT.
    $script_file = isset($_SERVER["SCRIPT_FILENAME"])
        ? str_replace("\\", "/", (string)$_SERVER["SCRIPT_FILENAME"])
        : "";
    $app_root = str_replace("\\", "/", (string)APP_ROOT);
    $script_url = str_replace("\\", "/", (string)$_SERVER["SCRIPT_NAME"]);
    if ($script_file !== "" && strpos($script_file, $app_root) === 0) {
        $rel_dir = trim(substr(dirname($script_file), strlen($app_root)), "/");
        // Strip "/<rel_dir>/<basename>" from the end of SCRIPT_NAME.
        $suffix = ($rel_dir !== "" ? "/" . $rel_dir : "") . "/" . basename($script_url);
        if ($rel_dir === "" || substr($script_url, -strlen($suffix)) === $suffix) {
            $base = $rel_dir === "" ? dirname($script_url) : substr($script_url, 0, -strlen($suffix));
            $base = rtrim(str_replace("//", "/", $base), "/");
            if ($base === "/" || $base === "\\" || $base === ".") {
                $base = "";
            }
            $cached = $base;
            return $cached;
        }
    }
    // Fallback: parent directory of a root-level script, else "".
    $dir = rtrim(str_replace("\\", "/", dirname($script_url)), "/");
    $cached = ($dir === "/" || $dir === "." || $dir === "") ? "" : $dir;
    return $cached;
}

function app_base_path()
{
    return app_detect_base_path();
}

function app_base_url()
{
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (isset($_SERVER["SERVER_PORT"]) && (string)$_SERVER["SERVER_PORT"] === "443");
    $scheme = $https ? "https" : "http";
    $host = isset($_SERVER["HTTP_HOST"]) ? trim((string)$_SERVER["HTTP_HOST"]) : "localhost";
    if ($host === "") {
        $host = "localhost";
    }
    return $scheme . "://" . $host . app_base_path();
}

function app_url($path = "")
{
    $path = "/" . ltrim((string)$path, "/");
    return app_base_url() . ($path === "/" ? "" : $path);
}

/* ─────────────────────── Reference IDs ────────────────────────── */

function app_error_reference()
{
    try {
        $rand = strtoupper(bin2hex(random_bytes(3)));
    } catch (Exception $e) {
        $rand = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    }
    return "ERR-" . date("Ymd") . "-" . $rand;
}

/* ─────────────────────── Request helpers ──────────────────────── */

function app_is_ajax_request()
{
    if (!empty($_SERVER["HTTP_X_REQUESTED_WITH"])
        && strtolower((string)$_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
        return true;
    }
    if (!empty($_SERVER["HTTP_ACCEPT"]) && stripos((string)$_SERVER["HTTP_ACCEPT"], "application/json") !== false) {
        return true;
    }
    if (isset($_GET["ajax_action"]) || isset($_POST["ajax_action"])) {
        return true;
    }
    $script = isset($_SERVER["SCRIPT_NAME"]) ? basename((string)$_SERVER["SCRIPT_NAME"]) : "";
    if ($script !== "" && substr($script, -9) === "_ajax.php") {
        return true;
    }
    return false;
}

/** Remove secrets from URIs before logging. Never logs passwords/tokens. */
function app_scrub_uri($uri)
{
    $uri = (string)$uri;
    $secret_keys = array("password", "passwd", "pwd", "pass", "token", "api_key", "apikey", "secret", "session", "phpsessid", "auth", "authorization", "csrf", "purchase_code", "license");
    foreach ($secret_keys as $key) {
        $uri = preg_replace_callback(
            '/([?&]' . preg_quote($key, '/') . '=)[^&]*/i',
            function ($m) { return $m[1] . '[redacted]'; },
            $uri
        );
    }
    if (strlen($uri) > 1000) {
        $uri = substr($uri, 0, 1000) . "…[truncated]";
    }
    return $uri;
}

function app_current_role()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!empty($_SESSION["admin_id"])) { return "admin"; }
        if (!empty($_SESSION["faculty_id"])) { return "faculty"; }
        if (!empty($_SESSION["student_id"])) { return "student"; }
    }
    return "guest";
}

function app_current_user_id()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach (array("admin_id", "faculty_id", "student_id") as $k) {
            if (!empty($_SESSION[$k])) { return (int)$_SESSION[$k]; }
        }
    }
    return 0;
}

/* ─────────────────────── Logging ──────────────────────────────── */

function app_log_file()
{
    return rtrim((string)APP_ROOT, "/\\")
        . DIRECTORY_SEPARATOR . "app"
        . DIRECTORY_SEPARATOR . "logs"
        . DIRECTORY_SEPARATOR . date("Y-m-d") . ".log";
}

function app_log_error($type, $message, $context = array())
{
    $ref = isset($context["reference"]) ? (string)$context["reference"] : app_error_reference();
    $role = app_current_role();
    $uid = app_current_user_id();
    $uri = app_scrub_uri(isset($_SERVER["REQUEST_URI"]) ? (string)$_SERVER["REQUEST_URI"] : (PHP_SAPI === "cli" ? "cli" : "/"));
    $method = isset($_SERVER["REQUEST_METHOD"]) ? (string)$_SERVER["REQUEST_METHOD"] : "CLI";

    $line = "[" . date("Y-m-d H:i:s") . "] [$ref] [$type]"
        . " role=$role" . ($uid > 0 ? " user_id=$uid" : "")
        . " $method $uri"
        . " :: " . str_replace(array("\r", "\n"), " ", (string)$message);

    if (!empty($context["file"])) {
        // Store only the path relative to APP_ROOT (never absolute disk paths).
        $rel = str_replace("\\", "/", (string)$context["file"]);
        $root = str_replace("\\", "/", (string)APP_ROOT);
        if (strpos($rel, $root) === 0) {
            $rel = ltrim(substr($rel, strlen($root)), "/");
        } else {
            $rel = basename($rel);
        }
        $line .= " @ $rel" . (!empty($context["line"]) ? ":" . (int)$context["line"] : "");
    }
    if (!empty($context["trace"]) && is_array($context["trace"])) {
        $frames = array();
        foreach (array_slice($context["trace"], 0, 12) as $f) {
            $fn = (isset($f["class"]) ? $f["class"] . $f["type"] : "") . ($f["function"] ?? "{main}");
            $fl = isset($f["file"]) ? basename((string)$f["file"]) : "[internal]";
            $ln = isset($f["line"]) ? ":" . (int)$f["line"] : "";
            // NOTE: function arguments are deliberately omitted (may hold passwords/keys).
            $frames[] = "$fn ($fl$ln)";
        }
        if ($frames) {
            $line .= " | trace: " . implode(" <- ", $frames);
        }
    }
    if (defined("APP_DEBUG") && APP_DEBUG && !empty($context["extra"]) && is_array($context["extra"])) {
        $line .= " | " . str_replace(array("\r", "\n"), " ", json_encode($context["extra"]));
    }
    $line .= PHP_EOL;

    $file = app_log_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    return $ref;
}

/* ─────────────────────── Responses ────────────────────────────── */

function app_error_meta($code)
{
    $map = array(
        400 => array("Bad Request", "The request could not be understood. Please try again."),
        401 => array("Session Expired", "Your session has expired. Please sign in again to continue."),
        403 => array("Access Denied", "You don't have permission to access this page."),
        404 => array("Page Not Found", "The page you're looking for doesn't exist or may have been moved."),
        422 => array("Validation Error", "Some of the submitted data was invalid. Please check and try again."),
        500 => array("Something Went Wrong", "We couldn't complete your request. Please try again."),
        503 => array("Service Temporarily Unavailable", "We couldn't complete this request right now. Please try again in a little while."),
    );
    return isset($map[$code]) ? $map[$code] : $map[500];
}

function app_emit_json_error($code, $message, $reference, $error_key = "INTERNAL_ERROR")
{
    if (!headers_sent()) {
        http_response_code((int)$code);
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate");
    }
    echo json_encode(array(
        "success" => false,
        "message" => (string)$message,
        "code" => (string)$error_key,
        "reference" => (string)$reference,
    ));
}

/**
 * Central responder. HTML page for browsers, JSON for AJAX/API.
 * Never redirects (avoids loops); renders directly with status code.
 */
function app_respond_error($code, $custom_message = "", $error_key = "INTERNAL_ERROR")
{
    static $handling = false;
    if ($handling) {
        // Recursive failure → minimal safe fallback, no redirect, no loop.
        if (!headers_sent()) {
            http_response_code(500);
            header("Content-Type: text/plain; charset=utf-8");
        }
        echo "An unexpected error occurred.";
        exit(1);
    }
    $handling = true;

    list($title, $default_message) = app_error_meta((int)$code);
    $message = $custom_message !== "" ? (string)$custom_message : $default_message;
    $ref = app_error_reference();
    app_log_error("HTTP" . (int)$code, $title . " — " . $message, array("reference" => $ref));

    if (app_is_ajax_request()) {
        app_emit_json_error($code, $message, $ref, $error_key);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code((int)$code);
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Pragma: no-cache");
    }
    $GLOBALS["__APP_ERROR_CODE"] = (int)$code;
    $GLOBALS["__APP_ERROR_REF"] = $ref;
    $GLOBALS["__APP_ERROR_TITLE"] = $title;
    $GLOBALS["__APP_ERROR_MESSAGE"] = $message;
    $error_view = rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "error.php";
    if (is_readable($error_view)) {
        include $error_view;
    } else {
        echo "<h1>" . htmlspecialchars($title) . "</h1><p>" . htmlspecialchars($message) . "</p>";
    }
    exit(1);
}

function abort_404($message = "") { app_respond_error(404, (string)$message, "NOT_FOUND"); }
function abort_403($message = "") { app_respond_error(403, (string)$message, "FORBIDDEN"); }
function abort_500($message = "", $key = "INTERNAL_ERROR") { app_respond_error(500, (string)$message, (string)$key); }
function abort_503($message = "") { app_respond_error(503, (string)$message, "SERVICE_UNAVAILABLE"); }

/* ─────────────────────── PHP handlers ─────────────────────────── */

class ErrorHandler
{
    private static $inited = false;

    public static function init()
    {
        if (self::$inited) {
            return;
        }
        self::$inited = true;

        // Users must never see raw PHP output; everything is logged.
        @ini_set("display_errors", "0");
        @ini_set("display_startup_errors", "0");
        @ini_set("log_errors", "1");

        set_error_handler(array("ErrorHandler", "onError"));
        set_exception_handler(array("ErrorHandler", "onException"));
        register_shutdown_function(array("ErrorHandler", "onShutdown"));
    }

    /** Log notices/warnings; never render. Execution continues. */
    public static function onError($severity, $message, $file = "", $line = 0)
    {
        // Respect @-suppressed errors.
        if (!(error_reporting() & $severity)) {
            return false;
        }
        $names = array(
            E_WARNING => "WARNING", E_NOTICE => "NOTICE", E_USER_WARNING => "USER_WARNING",
            E_USER_NOTICE => "USER_NOTICE", E_DEPRECATED => "DEPRECATED",
            E_USER_DEPRECATED => "USER_DEPRECATED", E_STRICT => "STRICT",
        );
        $type = isset($names[$severity]) ? $names[$severity] : "PHP_ERROR";
        // Notices/deprecations are only logged in debug mode (avoid log floods).
        if (in_array($severity, array(E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT), true)
            && !(defined("APP_DEBUG") && APP_DEBUG)) {
            return true;
        }
        app_log_error($type, $message, array("file" => $file, "line" => $line));
        return true; // handled — do not fall through to PHP's output
    }

    /** Uncaught exceptions → reference + log + safe response. */
    public static function onException($ex)
    {
        $message = $ex instanceof Exception || $ex instanceof Throwable
            ? get_class($ex) . ": " . $ex->getMessage()
            : "Uncaught throwable";
        $file = ($ex instanceof Exception || $ex instanceof Throwable) ? $ex->getFile() : "";
        $line = ($ex instanceof Exception || $ex instanceof Throwable) ? $ex->getLine() : 0;
        $trace = ($ex instanceof Exception || $ex instanceof Throwable) ? $ex->getTrace() : array();
        $ref = app_error_reference();
        app_log_error("EXCEPTION", $message, array("reference" => $ref, "file" => $file, "line" => $line, "trace" => $trace));
        self::renderFriendly(500, "", "INTERNAL_ERROR", $ref);
    }

    /** Fatal errors (E_ERROR, E_PARSE, ...) → same treatment. */
    public static function onShutdown()
    {
        $last = function_exists("error_get_last") ? error_get_last() : null;
        if (!is_array($last) || !isset($last["type"])) {
            return;
        }
        $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($last["type"], $fatal, true)) {
            return;
        }
        $ref = app_error_reference();
        app_log_error(
            "FATAL",
            (isset($last["message"]) ? $last["message"] : "Fatal error"),
            array("reference" => $ref, "file" => $last["file"] ?? "", "line" => $last["line"] ?? 0)
        );
        if (headers_sent()) {
            return; // Too late to render; the log entry preserves everything.
        }
        // Clean any partial output so users never see half-rendered breakage.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        self::renderFriendly(500, "", "INTERNAL_ERROR", $ref);
    }

    private static function renderFriendly($code, $message, $key, $ref)
    {
        list($title, $default_message) = app_error_meta((int)$code);
        $text = $message !== "" ? (string)$message : $default_message;
        if (app_is_ajax_request()) {
            app_emit_json_error($code, $text, $ref, $key);
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code((int)$code);
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Pragma: no-cache");
        }
        $GLOBALS["__APP_ERROR_CODE"] = (int)$code;
        $GLOBALS["__APP_ERROR_REF"] = $ref;
        $GLOBALS["__APP_ERROR_TITLE"] = $title;
        $GLOBALS["__APP_ERROR_MESSAGE"] = $text;
        $error_view = rtrim((string)APP_ROOT, "/\\") . DIRECTORY_SEPARATOR . "error.php";
        if (is_readable($error_view)) {
            include $error_view;
        } else {
            echo "<h1>" . htmlspecialchars($title) . "</h1><p>" . htmlspecialchars($text) . "</p>";
        }
        exit(1);
    }
}
