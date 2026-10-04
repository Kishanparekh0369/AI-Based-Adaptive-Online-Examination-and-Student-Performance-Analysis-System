<?php
if (session_status() === PHP_SESSION_NONE) {
    $session_path = __DIR__ . "/../../assets/uploads/sessions";
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0775, true);
    }
    @session_save_path($session_path);
    session_start();
}
?>
