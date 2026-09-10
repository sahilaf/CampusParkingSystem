<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Destroy the session and return the user to the landing page.
// No HTML output — this file is purely a redirect endpoint.
logout_user();
header('Location: ' . BASE_URL . '/public/index.php');
exit;
