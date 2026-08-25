<?php
require_once __DIR__ . '/auth.php';
checkAuth();
?>
<link rel="stylesheet" href="css/list.css">
<?php
function findMatchingFiles($directory = "/", $pattern = "/.*sofamq.*/") {
    // 确保目录存在并且是一个目录
    if (!is_dir($directory)) {
        echo $directory . "目录不存在或不是一个有效目录";
        return;
    }

    // 扫描目录中的文件
    $files = scandir($directory);

    // 过滤文件，匹配正则表达式
    $matchingFiles = [];
    foreach ($files as $file) {
        // 跳过 . 和 .. 目录
        if ($file === '.' || $file === '..') {
            continue;
        }

        // 判断文件名是否匹配正则表达式
        if (preg_match($pattern, $file)) {
            $matchingFiles[] = $file;
        }
    }

    // 输出匹配的文件名为 POST 表单链接
    foreach ($matchingFiles as $file) {
        echo '<a href=view_sofamq.php?file=' . htmlspecialchars($file) . '>';
        echo strtok($file, '.');
        echo '</a><br/>';
    }
}

// 示例用法
//$directory = "/data/deskecc/ack/index/";
//$pattern = "/.*nodes_info\.csv$/";  // 仅匹配以 cluster_info.csv 结尾的文件

// 处理表单提交
$directory = isset($_POST['directory']) ? $_POST['directory'] : '/data/deskecc/ack/index/';
$pattern = isset($_POST['pattern']) ? $_POST['pattern'] : '/.*/';



findMatchingFiles($directory, $pattern);
?>
