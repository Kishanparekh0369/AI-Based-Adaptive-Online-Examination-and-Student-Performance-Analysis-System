<?php
require_once("../app/config/database.php");

$conn = conn();
if (isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
    $name = isset($_SESSION["student_name"]) ? (string)$_SESSION["student_name"] : "";
    perform_logout("You have been safely logged out.", "../public/logout.php", $name);
} else {
    header("Location: dashboard.php");
    exit;
}
