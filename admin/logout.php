<?php
// Keluar admin.
require_once __DIR__ . '/../includes/auth.php';
proses_logout();
header('Location: ' . BASE_PATH . '/admin/login.php');
exit;
