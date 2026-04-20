<?php
// 1. PHP 后端逻辑：读取并解析 CSV 文件
$file_path = '/data/deskecc/ack/vpcinfo/All_vsw.csv';
$vsw_data = [];
$error_msg = '';

if (file_exists($file_path) && is_readable($file_path)) {
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $is_header = true;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($is_header) {
                $is_header = false;
                continue;
            }
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
        $error_msg = "文件存在，但打开失败，请检查权限。";
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
            <button onclick="executeSearch()" class="btn btn-primary btn-sm">筛选</button>
            <button onclick="clearFilter()" class="btn btn-default btn-sm">清除</button>
            
            <?php if (empty($error_msg)): ?>
                <span class="success-msg">已加载 <span id="dataCount"><?php echo count($vsw_data); ?></span> 条网段数据</span>
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
                </tbody>
        </table>
    </div>

    <script>
        // 【核心优化1】将 PHP 数据一次性转为 JS 全局变量放入内存
        const globalVswData = <?php echo json_encode($vsw_data, JSON_UNESCAPED_UNICODE); ?> || [];
        const tbody = document.getElementById("vswBody");
        const countSpan = document.getElementById("dataCount");

        // 页面加载完毕后，立刻渲染全量数据
        window.onload = function() {
            renderTable(globalVswData);
        };

        // 高效渲染表格视图 (只做一次 DOM 写入)
        function renderTable(dataArray) {
            // 使用数组拼接字符串，比逐个创建 DOM 节点快得多
            let htmlContent = "";
            for (let i = 0; i < dataArray.length; i++) {
                const row = dataArray[i];
                htmlContent += `<tr>
                    <td>${row.id}</td>
                    <td>${row.name}</td>
                    <td>${row.cidr}</td>
                    <td>${row.cloud}</td>
                </tr>`;
            }
            // 一次性刷新 DOM
            tbody.innerHTML = htmlContent;
            countSpan.innerText = dataArray.length;
        }

        // IP 范围重叠计算 (保持不变)
        function isIpRangeOverlap(inputIpStr, cidr) {
            try {
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

                const [range, bits = 32] = cidr.split('/');
                const rangeInt = ipToBigInt(range.split('.').map(Number));
                const shift = BigInt(32 - bits);
                
                const invMask = (1n << shift) - 1n;
                const netMask = ((1n << 32n) - 1n) ^ invMask; 

                const cidrMin = rangeInt & netMask;
                const cidrMax = cidrMin | invMask;

                return inputMin <= cidrMax && inputMax >= cidrMin;
            } catch (e) {
                return false;
            }
        }

        // 【核心优化2】在内存中过滤 JSON 数据，而不是操作 DOM
        function executeSearch() {
            const keyword = document.getElementById("ipInput").value.trim().toLowerCase();
            
            if (keyword === "") {
                renderTable(globalVswData);
                return;
            }

            const isIpLike = /^[0-9\.]+$/.test(keyword);
            
            // 使用 Array.filter 高效过滤出符合条件的数据
            const filteredData = globalVswData.filter(row => {
                let isMatch = false;

                if (isIpLike) {
                    const cidrs = row.cidr.split(/[\s,，]+/).filter(c => c.length > 0);
                    for (let cidr of cidrs) {
                        if (isIpRangeOverlap(keyword, cidr)) {
                            return true;
                        }
                    }
                }
                
                // 降级文本模糊搜索
                const rowText = `${row.id} ${row.name} ${row.cidr} ${row.cloud}`.toLowerCase();
                if (rowText.includes(keyword)) {
                    return true;
                }

                return false;
            });

            // 将过滤后的结果重新渲染
            renderTable(filteredData);
        }

        function clearFilter() {
            document.getElementById("ipInput").value = "";
            renderTable(globalVswData); // 恢复全量数据
        }

        // 【核心优化3】防抖机制：避免快速连敲键盘时频繁触发计算
        let searchTimeout;
        document.getElementById("ipInput").addEventListener("keyup", function(event) {
            if (event.key === "Enter") {
                clearTimeout(searchTimeout);
                executeSearch();
            } else {
                // 如果用户连续输入，则取消上一次的任务，重新倒计时 300ms
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    executeSearch();
                }, 300); 
            }
        });
    </script>
</body>
</html>