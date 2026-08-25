<?php
require_once __DIR__ . '/auth.php';
checkAuth();

function displayCsv($filePath, $page = 1, $rowsPerPage = 5) {
    // 检查文件是否存在
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return '文件不存在或不可读取';
    }

    $data = [];
    // 打开 CSV 文件
    if (($handle = fopen($filePath, 'r')) !== false) {
        // 读取所有数据
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    } else {
        return '无法打开文件';
    }

    // 计算总行数和总页数
    $totalRows = count($data);
    $totalPages = ceil($totalRows / $rowsPerPage);
    $offset = ($page - 1) * $rowsPerPage;

    // 列出当前页的数据
    $output = '<table border="1">';
    for ($i = $offset; $i < $offset + $rowsPerPage && $i < $totalRows; $i++) {
        $output .= '<tr>';
        foreach ($data[$i] as $cell) {
            $output .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $output .= '</tr>';
    }
    $output .= '</table>';

    // 添加翻页链接
    $output .= '<div>';
    if ($page > 1) {
        $output .= '<a href="?file=' . urlencode($filePath) . '&page=' . ($page - 1) . '">上一页</a> ';
    }
    if ($page < $totalPages) {
        $output .= '<a href="?file=' . urlencode($filePath) . '&page=' . ($page + 1) . '">下一页</a>';
    }
    $output .= '</div>';

    return $output;
}

// 获取 URL 参数
$filePath = isset($_GET['file']) ? $_GET['file'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 调用函数并输出结果
echo displayCsv($filePath, $page);
?>

