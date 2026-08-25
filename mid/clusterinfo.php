<?php
require_once __DIR__ . '/auth.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>中间件桌面ECC</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="js/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/iconfont.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>

<h2>生产集群信息合集：</h2>

<?php
// 指定目录路径
$dir = "/data/deskecc/ack/clusterinfo/";

// 遍历根目录及其一级子目录中的所有 .txt 和 .csv 文件
function getFiles($dir, $pattern) {
    $files = [];
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        $filePath = $dir . '/' . $item;
        if (is_file($filePath) && fnmatch($pattern, $item)) {
            $files[] = $filePath;
        } elseif (is_dir($filePath)) {
            // 仅遍历一级子目录
            foreach (scandir($filePath) as $subItem) {
                if ($subItem == '.' || $subItem == '..') continue;
                $subFilePath = $filePath . '/' . $subItem;
                if (is_file($subFilePath) && fnmatch($pattern, $subItem)) {
                    $files[] = $subFilePath;
                }
            }
        }
    }
    return $files;
}

// 获取所有 .txt 和 .csv 文件
$Files = getFiles($dir, '*cluster_info.csv');

if (!empty($Files)) {
    echo "<ul>";
    foreach ($Files as $file) {
        // 生成相对路径链接
        $relativePath = str_replace($dir . '/', '', $file);
        echo "<li><a href='view_csv.php?file=" . urlencode($relativePath) . "'>" . htmlspecialchars($relativePath) . "</a></li>";
    }
    echo "</ul>";
} else {
    echo "指定目录及其一级子目录中没有找到 .txt 或 .csv 文件。";
}


// // 打开指定目录
// if (is_dir($dir)) {
//     // 打开目录
//     if ($dh = opendir($dir)) {
//         // 循环读取目录中的文件
//         while (($file = readdir($dh)) !== false) {
//             // 仅处理 .csv 文件
//             if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
//                 // 输出超链接
//                 echo "<a href='view_csv.php?file=" . urlencode($file) . "'>" . $file . "</a><br>";
//             }
//         }
//         // 关闭目录句柄
//         closedir($dh);
//     }
// }
?>

</body>
</html>
