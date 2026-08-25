<?php
// ============================================
// auth.php - 全局登录鉴权模块
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('AUTH_USERNAME', 'midadmin');
define('AUTH_PASSWORD', 'uspOB%0331');

/**
 * 检查当前用户是否已登录
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['auth_user']) && $_SESSION['auth_user'] === AUTH_USERNAME;
}

/**
 * 强制进行鉴权检查，未登录则跳转或拦截
 */
function checkAuth() {
    if (!isLoggedIn()) {
        // 如果是 AJAX / API 请求
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '未登录或登录已过期']);
            exit;
        }

        // 普通页面/iframe 请求：重定向到登录页（顶层窗口跳转）
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <script>
        if (window.top && window.top !== window) {
            window.top.location.href = "login.php";
        } else {
            window.location.href = "login.php";
        }
    </script>
</head>
<body>
    <script>location.href = "login.php";</script>
    <noscript><meta http-equiv="refresh" content="0;url=login.php"></noscript>
    <p>正在跳转到登录页面... <a href="login.php">点击此处直接跳转</a></p>
</body>
</html>';
        exit;
    }
}

/**
 * 登录验证
 * @param string $username
 * @param string $password
 * @return bool
 */
function loginUser($username, $password) {
    if ($username === AUTH_USERNAME && $password === AUTH_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = AUTH_USERNAME;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

/**
 * 退出登录
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
