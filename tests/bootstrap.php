<?php
/**
 * PHPUnit Bootstrap — Faculty Module Test Suite
 *
 * Khởi tạo môi trường test: session giả, autoload helpers.
 */

// Khởi động session giả (không cần HTTP)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload vendor
require_once __DIR__ . '/../vendor/autoload.php';

// Load faculty helpers (không có DB thật — tests dùng mock)
// faculty_helpers.php được load qua composer autoload "files"
