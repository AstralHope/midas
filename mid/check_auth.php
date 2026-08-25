<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (isLoggedIn()) {
    echo json_encode([
        'authenticated' => true,
        'user' => $_SESSION['auth_user']
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'error' => '未登录或登录已过期'
    ]);
}
