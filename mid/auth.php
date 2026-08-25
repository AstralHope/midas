<?php
// ============================================
// auth.php - 全局登录鉴权模块
// ============================================

// 3天免登录时长 (3 * 24 * 60 * 60 = 259200 秒)
define('AUTH_SESSION_LIFETIME', 259200);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', AUTH_SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => AUTH_SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 检查会话是否已超过3天（主动校验防御）
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > AUTH_SESSION_LIFETIME)) {
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

define('AUTH_USERNAME', 'midadmin');
define('AUTH_PASSWORD', 'uspOB%0331');

/**
 * 获取 HTTP Basic 认证凭据（支持 curl -u 等方式）
 * @return array [username, password]
 */
function getBasicAuthCredentials() {
    // 1. PHP_AUTH_USER / PHP_AUTH_PW (标准 FastCGI / Apache)
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']];
    }

    // 2. HTTP_AUTHORIZATION header 或 REDIRECT_HTTP_AUTHORIZATION
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }

    if ($authHeader && stripos($authHeader, 'Basic ') === 0) {
        $decoded = base64_decode(trim(substr($authHeader, 6)));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            return explode(':', $decoded, 2);
        }
    }

    return [null, null];
}

/**
 * 检查当前用户是否已登录（支持 Session 或 HTTP Basic Auth）
 * @return bool
 */
function isLoggedIn() {
    // 1. 检查 Session
    if (isset($_SESSION['auth_user']) && $_SESSION['auth_user'] === AUTH_USERNAME) {
        return true;
    }

    // 2. 检查 HTTP Basic Auth（支持 curl -u username:password）
    [$user, $pass] = getBasicAuthCredentials();
    if ($user !== null && $user === AUTH_USERNAME && $pass === AUTH_PASSWORD) {
        return true;
    }

    return false;
}

/**
 * 强制进行鉴权检查，未登录则跳转或拦截
 */
function checkAuth() {
    if (!isLoggedIn()) {
        [$user, ] = getBasicAuthCredentials();
        $isCurlOrCli = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'curl') !== false;
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isApi = $user !== null || $isCurlOrCli || $isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // 如果是 API / curl / AJAX / 携带了 Basic Auth 头的请求
        if ($isApi) {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="MIDAS API"');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => '401 Unauthorized', 'message' => '用户名或密码错误 / 未授权访问'], JSON_UNESCAPED_UNICODE);
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
