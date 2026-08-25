<?php
require_once __DIR__ . '/auth.php';

// 如果已经登录，直接跳转到首页
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error_msg = '请输入用户名和密码';
    } elseif (loginUser($username, $password)) {
        header("Location: index.php");
        exit;
    } else {
        $error_msg = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>中间件桌面ECC登录页</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="css/reset.css">
    <link rel="stylesheet" type="text/css" href="css/iconfont.css">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <style>
        .error_tip {
            color: #d9534f;
            text-align: center;
            margin-bottom: 12px;
            font-size: 14px;
            background: #fdf7f7;
            border: 1px solid #eed3d7;
            padding: 8px 12px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="main_wrap">
        <div class="header">
            <a href="#" class="logo"><img src="images/logo04.png" alt=""></a>
            <div class="copyright">CopyRight © 2024 交通银行数据中心系统部中间件条线<br>All Rights Reserved</div>
        </div>

        <div class="login_form_con">
            <div class="login_title"></div>
            <?php if (!empty($error_msg)): ?>
                <div class="error_tip"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST" class="login_form">
                <i class="iconfont icon-user"></i>
                <i class="iconfont icon-key"></i>
                <input type="text" name="username" class="input_txt" placeholder="用户名" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <input type="password" name="password" class="input_pass" placeholder="密码" required>
                <input type="submit" class="input_sub" value="登 录">
            </form>
        </div>

    </div> 

</body>

</html>
