<?php
// 1. PHP 后端逻辑：读取并解析 CSV 文件
$file_path = '/data/deskecc/ack/vpcinfo/All_vsw.csv';
$vsw_data = [];
$error_msg = '';

// 检查文件是否存在且可读
if (file_exists($file_path) && is_readable($file_path)) {
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $is_header = true;
        // 使用 fgetcsv 逐行安全读取
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // 跳过第一行表头
            if ($is_header) {
                $is_header = false;
                continue;
            }
            // 确保当前行至少有 4 列数据
            if (count($data) >= 4) {
                $vsw_data[] = [
                    'id'    => htmlspecialchars($data[0]),
                    'name'  => htmlspecialchars($data[1]),
                    'cidr'  => htmlspecialchars($data[2]),
                    'cloud' => htmlspecialchars($data[3])
                ];
            }
        }
        fclose($handle);
    } else {
        $error_msg = "文件存在，但打开失败，请检查 PHP 进程权限。";
    }
} else {
    $error_msg = "找不到文件或无读取权限: " . htmlspecialchars($file_path);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIDAS - 交换机网段查询</title>
    <link rel="stylesheet" href="js/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/jquery-1.12.4.min.js"></script>
    <style>
        .search-box { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .error-msg { color: #d9534f; font-weight: bold; margin-bottom: 15px; }
        .success-msg { color: #5cb85c; font-size: 14px; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>输入云上IP匹配VSW信息</h2>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger error-msg">
                <strong>系统错误：</strong> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <label for="ipInput">输入搜索 IP：</label>
            <input type="text" id="ipInput" class="form-control" style="display:inline-block; width:200px;" placeholder="例如: 10.1.1.5">
            <button onclick="filterTable()" class="btn btn-primary btn-sm">筛选</button>
            <button onclick="clearFilter()" class="btn btn-default btn-sm">清除</button>
            
            <?php if (empty($error_msg)): ?>
                <span class="success-msg">共 <?php echo count($vsw_data); ?> 条网段数据 </span>
            <?php endif; ?>
        </div>

        <table id="vswTable" class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th>实例ID</th>
                    <th>实例名称</th>
                    <th>IPv4网段</th>
                    <th>云标识</th>
                </tr>
            </thead>
            <tbody id="vswBody">
                <?php
                // 2. PHP 渲染：直接将数据循环输出为 HTML 表格行
                foreach ($vsw_data as $row) {
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['name']}</td>";
                    echo "<td class='cidr-cell'>{$row['cidr']}</td>";
                    echo "<td>{$row['cloud']}</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
        // 3. JS 纯前端逻辑：只负责 IP 计算和过滤显示
        
        // IP 匹配逻辑 (使用 BigInt 防止 32位 整数溢出)
        function isIPInCIDR(ip, cidr) {
            try {
                const parts = cidr.split('/');
                const range = parts[0];
                const bits = parts[1] ? parseInt(parts[1]) : 32;
                
                const mask = (1n << 32n) - (1n << BigInt(32 - bits));
                const ipToBigInt = str => str.split('.').reduce((acc, part) => (acc * 256n) + BigInt(part), 0n);
                
                const ipInt = ipToBigInt(ip);
                const rangeInt = ipToBigInt(range);
                
                return (ipInt & mask) === (rangeInt & mask);
            } catch (e) {
                return false;
            }
        }

        function filterTable() {
            const ip = document.getElementById("ipInput").value.trim();
            const ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
            
            if (!ipRegex.test(ip)) {
                alert("请输入有效的 IPv4 地址");
                return;
            }

            const rows = document.getElementById("vswBody").getElementsByTagName("tr");
            for (let row of rows) {
                // 读取带 'cidr-cell' class 的单元格内容
                const cidrCell = row.querySelector('.cidr-cell').innerText;
                // 支持一个单元格内有多个网段（逗号或空格分隔）
                const cidrs = cidrCell.split(/[\s,，]+/).filter(c => c.length > 0);
                
                let isMatch = false;
                for (let cidr of cidrs) {
                    if (isIPInCIDR(ip, cidr)) {
                        isMatch = true;
                        break;
                    }
                }
                // 匹配则显示，不匹配则隐藏
                row.style.display = isMatch ? "" : "none";
            }
        }

        function clearFilter() {
            const rows = document.getElementById("vswBody").getElementsByTagName("tr");
            for (let row of rows) {
                row.style.display = "";
            }
            document.getElementById("ipInput").value = "";
        }

        // 监听回车键，按下回车直接搜索
        document.getElementById("ipInput").addEventListener("keyup", function(event) {
            if (event.key === "Enter") {
                filterTable();
            }
        });
    </script>
</body>
</html>