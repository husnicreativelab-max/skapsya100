<?php
// Keluar admin.
require_once __DIR__ . '/../includes/auth.php';
proses_logout();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
