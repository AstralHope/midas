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
                <span class="success-msg">已加载 <?php echo count($vsw_data); ?> 条网段数据</span>
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
        // 3. JS 纯前端逻辑
        
        // IP 范围重叠匹配逻辑
        function isIpRangeOverlap(inputIpStr, cidr) {
            try {
                // 将用户输入的模糊 IP 转为范围 (Min - Max)
                const parts = inputIpStr.split('.').filter(p => p.trim() !== '');
                if (parts.length === 0) return false;

                let minParts = [], maxParts = [];
                for (let i = 0; i < 4; i++) {
                    if (i < parts.length) {
                        let num = parseInt(parts[i]);
                        if (isNaN(num)) return false; 
                        minParts.push(num);
                        maxParts.push(num);
                    } else {
                        minParts.push(0);
                        maxParts.push(255);
                    }
                }

                const ipToBigInt = arr => arr.reduce((acc, part) => (acc * 256n) + BigInt(part), 0n);
                const inputMin = ipToBigInt(minParts);
                const inputMax = ipToBigInt(maxParts);

                // 将目标 CIDR 网段转为范围 (Min - Max)
                const [range, bits = 32] = cidr.split('/');
                const rangeInt = ipToBigInt(range.split('.').map(Number));
                const shift = BigInt(32 - bits);
                
                const invMask = (1n << shift) - 1n;
                const netMask = ((1n << 32n) - 1n) ^ invMask; 

                const cidrMin = rangeInt & netMask;
                const cidrMax = cidrMin | invMask;

                // 判断两个范围是否有交集
                return inputMin <= cidrMax && inputMax >= cidrMin;
            } catch (e) {
                return false;
            }
        }

        // 表格筛选主函数
        function filterTable() {
            const keyword = document.getElementById("ipInput").value.trim();
            const rows = document.getElementById("vswBody").getElementsByTagName("tr");
            
            // 判断是否是纯数字+点组成的 IP 格式倾向
            const isIpLike = /^[0-9\.]+$/.test(keyword);

            for (let row of rows) {
                const cells = row.getElementsByTagName("td");
                const instanceId = cells[0].innerText;
                const instanceName = cells[1].innerText;
                const cidrCell = cells[2].innerText;
                const cloudId = cells[3].innerText;

                let isMatch = false;

                if (keyword === "") {
                    isMatch = true; 
                } 
                else if (isIpLike) {
                    const cidrs = cidrCell.split(/[\s,，]+/).filter(c => c.length > 0);
                    for (let cidr of cidrs) {
                        if (isIpRangeOverlap(keyword, cidr)) {
                            isMatch = true;
                            break;
                        }
                    }
                } 
                
                // 降级策略：纯文本模糊搜索
                if (!isMatch) {
                    const rowText = `${instanceId} ${instanceName} ${cidrCell} ${cloudId}`.toLowerCase();
                    if (rowText.includes(keyword.toLowerCase())) {
                        isMatch = true;
                    }
                }

                row.style.display = isMatch ? "" : "none";
            }
        }

        // 清除筛选
        function clearFilter() {
            const rows = document.getElementById("vswBody").getElementsByTagName("tr");
            for (let row of rows) {
                row.style.display = "";
            }
            document.getElementById("ipInput").value = "";
        }

        // 监听回车键事件，按下回车直接搜索
        document.getElementById("ipInput").addEventListener("keyup", function(event) {
            if (event.key === "Enter") {
                event.preventDefault(); 
                filterTable();
            }
        });
    </script>
</body>
</html>