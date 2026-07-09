<?php
declare(strict_types=1);
require_once __DIR__ . '/user_auth.php';

unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name']);
header('Location: shop_page.php');
exit;
