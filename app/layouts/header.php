<?php
$page_title = isset($page_title) ? $page_title : "AI-Based Adaptive Online Examination System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>images/favicon.svg?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>images/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>images/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>images/apple-touch-icon.png?v=1">
    <meta name="theme-color" content="#2563EB">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>css/style.css" rel="stylesheet">
    <link href="<?php echo isset($asset_path) ? $asset_path : 'assets/'; ?>css/public-premium.css" rel="stylesheet">
    <noscript><style>[data-reveal]{opacity:1 !important;transform:none !important;transition:none !important;}</style></noscript>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
