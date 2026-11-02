<?php
session_start();
require_once __DIR__ . '/includes/koneksi.php';

if (isAdmin()) {
    header("Location: /admin/orders.php");
    exit;
} elseif (isCustomer()) {
    header("Location: /customer/my_orders.php");
    exit;
} else {
    header("Location: /login.php");
    exit;
}
