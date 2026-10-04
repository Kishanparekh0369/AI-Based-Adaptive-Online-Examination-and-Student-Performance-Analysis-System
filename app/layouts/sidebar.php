<?php
if (isset($_SESSION["admin_id"])) {
    include __DIR__ . "/admin_sidebar.php";
} else if (isset($_SESSION["student_id"])) {
    include __DIR__ . "/student_sidebar.php";
}
?>
