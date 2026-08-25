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
    <title>展示文本内容</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="js/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/iconfont.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>

<input type="text" id="filterInput" placeholder="筛选表格...">
<button id="filterButton" class="btn btn-default btn-sm">筛选</button>
<button id="clearButton" class="btn btn-default btn-sm">清除筛选</button>

<?php
// 检查是否设置了文件参数
if (isset($_GET['file'])) {
    // 获取文件名并转义
    $filename = $_GET['file'];
    //$filename = basename($filename);
    //$filename = str_replace("/", "", $filename); // 防止路径遍历攻击

    // 检查文件是否存在
    if (file_exists("/data/deskecc/ack/clusterinfo/" . $filename) && (pathinfo($filename, PATHINFO_EXTENSION) === 'txt') || (pathinfo($filename, PATHINFO_EXTENSION) === 'csv') ) {
        // 读取文件内容
        $file_content = file_get_contents("/data/deskecc/ack/clusterinfo/" . $filename);
        // 将内容按行分割
        $lines = explode("\n", $file_content);

        // 分离表头和数据行
        $header = [];
        $rows = [];
        foreach ($lines as $index => $line) {
            if (trim($line) !== "") {
                $cells = explode(",", $line);
                if ($index == 0) {
                    $header = $cells;
                } else {
                    $rows[] = $cells;
                }
            }
        }

        // 将 master 行移到最前面
        usort($rows, function($a, $b) {
            return (trim($a[2]) === 'master') ? -1 : 1;
        });

        // 输出表格
        echo "<table id='dataTable' class='table table-striped table-bordered table-hover mp20'>";
        // 输出表头
        echo "<thead><tr>";
        foreach ($header as $cell) {
            echo "<th>" . htmlspecialchars(trim($cell)) . "</th>";
        }
        echo "</tr></thead><tbody>";

        // 输出数据行
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $index => $cell) {
                if ($index == 3) { // 假设第四列是索引3
                    echo "<td><a href='view_txt.php?file=" . urlencode(trim($cell)) . ".txt'>" . htmlspecialchars(trim($cell)) . "</a></td>";
                } else {
                    echo "<td>" . htmlspecialchars(trim($cell)) . "</td>";
                }
            }
            echo "</tr>";
        }

        echo "</tbody></table>";


    } else {
        echo "文件不存在或不是 .csv文件。";
    }
} else {
    echo "未指定文件。";
}
?>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterInput = document.getElementById('filterInput');
    const filterButton = document.getElementById('filterButton');
    const clearButton = document.getElementById('clearButton');
    const table = document.getElementById('dataTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));

    filterButton.addEventListener('click', function() {
        const filter = filterInput.value.toLowerCase();
        rows.forEach(row => {
            const cells = Array.from(row.getElementsByTagName('td'));
            const match = cells.some(cell => cell.textContent.toLowerCase().includes(filter));
            row.style.display = match ? '' : 'none';
        });
    });

    clearButton.addEventListener('click', function() {
        filterInput.value = '';
        rows.forEach(row => {
            row.style.display = '';
        });
    });

    const headers = Array.from(table.getElementsByTagName('th'));
    headers.forEach((header, index) => {
        header.addEventListener('click', function() {
            const ascending = header.classList.toggle('ascending');
            rows.sort((a, b) => {
                const aText = a.getElementsByTagName('td')[index].textContent.trim();
                const bText = b.getElementsByTagName('td')[index].textContent.trim();
                return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });
            tbody.append(...rows);
        });
    });
});
</script>
</body>
</html>
