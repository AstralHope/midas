<?php
require_once __DIR__ . '/auth.php';
checkAuth();

// ============================================
// display_csv_new.php
// 完整版 CSV 显示页面
// 支持：分页、搜索、隐藏列、导出 CSV、额外处理列（如生成超链接）
// ============================================

function displayCsv($filePath, $extraColumnIndex = null, $extraFunction = null, $hiddenColumns = '') {
    // 基础路径常量
    $BASEPATH = '';
    $fullFilePath = $BASEPATH . $filePath;
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);

    if (!file_exists($fullFilePath) || !is_readable($fullFilePath)) {
        return '文件不存在或不可读取';
    }

    // 读取 CSV 数据
    $data = [];
    if (($handle = fopen($fullFilePath, 'r')) !== false) {
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // 确保每单元格均为合法 UTF-8 编码，并剔除可能的前导 BOM
            $cleanRow = array_map(function($val) {
                if ($val === null) return '';
                $val = preg_replace('/^\xEF\xBB\xBF/', '', $val);
                if (!mb_check_encoding($val, 'UTF-8')) {
                    $val = mb_convert_encoding($val, 'UTF-8', ['GBK', 'GB2312', 'GB18030', 'BIG5', 'ISO-8859-1']);
                }
                return $val;
            }, $row);
            $data[] = $cleanRow;
        }
        fclose($handle);
    } else {
        return '无法打开文件';
    }

    // 转为 JSON 以便 JS 使用
    $jsonData = json_encode($data);
    $hiddenColumnsArray = array_map('intval', explode(',', $hiddenColumns));
    $mtime = filemtime($fullFilePath);

    // ============ 输出 HTML ==============
    $output = '<div>';
    $output .= '<div class="data-time-bar" style="margin: 10px 0 15px 0; padding: 6px 12px; background: #f8f9fa; border-left: 4px solid #1890ff; font-size: 13px; color: #555;"><strong>数据获取时间：</strong><span id="dataFetchTime">--</span></div>';
    $output .= '<h1>' . htmlspecialchars($fileName) . '</h1>';
    $output .= '<input type="text" id="searchInput" placeholder="搜索..." onkeydown="if(event.key==\'Enter\') filterTable()" style="margin-right:10px;">';
    $output .= '<button onclick="filterTable()">筛选</button>';
    $output .= '<button onclick="clearSearch()">清除筛选</button>';
    $output .= '<button onclick="exportTableToExcel()">导出为Excel</button>';

    $output .= '<table border="1"><thead><tr>';
    foreach ($data[0] as $index => $header) {
        if (in_array($index + 1, $hiddenColumnsArray)) continue;
        $output .= '<th onclick="sortTable(' . $index . ')">' . htmlspecialchars($header) . '</th>';
    }
    $output .= '</tr></thead><tbody id="tableBody"></tbody></table>';

    $output .= '共 <span id="totalItems">0</span> 项 每页显示: 
        <select id="rowsPerPage" onchange="changeRowsPerPage()">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <div id="pagination"></div>';

    // 解析额外处理列映射（支持单列配置及多列逗号分隔配置）
    $columnActions = [];
    if ($extraColumnIndex !== null && $extraFunction !== null) {
        $colIndices = is_array($extraColumnIndex) ? $extraColumnIndex : explode(',', (string)$extraColumnIndex);
        $funcNames = is_array($extraFunction) ? $extraFunction : explode(',', (string)$extraFunction);
        foreach ($colIndices as $i => $colIdx) {
            $colIdx = trim((string)$colIdx);
            if ($colIdx !== '' && is_numeric($colIdx)) {
                $fn = isset($funcNames[$i]) ? trim($funcNames[$i]) : (isset($funcNames[0]) ? trim($funcNames[0]) : '');
                if ($fn !== '') {
                    $columnActions[(int)$colIdx] = $fn;
                }
            }
        }
    }

    // 针对节点 pod 信息表格（如 All_nodes_info.csv 等）智能兼容：
    // 若第2列（索引1）表头为 cluster_name 且尚未绑定函数，自动绑定 makeLinkforCluster 实现点击跳转至集群所有节点
    if (isset($data[0][1]) && trim($data[0][1]) === 'cluster_name' && !isset($columnActions[1])) {
        $columnActions[1] = 'makeLinkforCluster';
    }
    // 若第4列（索引3）表头为 node_name 且尚未绑定函数，自动绑定 makeLinkforNode 实现点击跳转查看节点文本数据
    if (isset($data[0][3]) && trim($data[0][3]) === 'node_name' && !isset($columnActions[3])) {
        $columnActions[3] = 'makeLinkforNode';
    }

    // ============ JS 脚本 ============
    $output .= '<script>
        // 数据初始化
        var data = ' . $jsonData . ';
        var originalData = JSON.parse(JSON.stringify(data));
        var currentPage = 1;
        var rowsPerPage = 10;
        var extraColumnMap = ' . json_encode($columnActions) . '; // 列索引 => 处理函数映射表
        var hiddenColumns = ' . json_encode($hiddenColumnsArray) . ';
        var sortAscending = true;

        // ===== 额外处理列函数（可修改或增加） =====
        function makeLinkforCluster(cell, row, index) {
            if (cell === null || cell === undefined || String(cell).trim() === "") {
                return "";
            }
            var cleanCell = String(cell).trim();
            var BASEPATH = "/data/deskecc/ack/clusterinfo/";
            var filePath = BASEPATH + cleanCell + ".csv";
            return "<form method=\'POST\' action=\'display_csv_new.php\' style=\'display:inline; white-space:nowrap;\'>" +
                   "<input type=\'hidden\' name=\'file\' value=\'" + filePath + "\'>" +
                   "<input type=\'hidden\' name=\'columnIndex\' value=\'3\'>" +
                   "<input type=\'hidden\' name=\'function\' value=\'makeLinkforNode\'>" +
                   "<input type=\'hidden\' name=\'hiddenClumnIndex\' value=\'1,2\'>" +
                   "<button type=\'submit\' style=\'color:blue;text-decoration:underline;background:none;border:none;padding:0;cursor:pointer;white-space:nowrap;\'>" +
                   cleanCell + "</button></form>";
        }

        function makeLinkforNode(cell, row, index) {
            if (cell === null || cell === undefined || String(cell).trim() === "") {
                return "";
            }
            var cleanCell = String(cell).trim();
            var encoded = encodeURIComponent(cleanCell) + ".txt";
            return "<a href=\'view_txt.php?file=" + encoded + "\'>" + cleanCell + "</a>";
        }

        function makeLinkforNginx(cell, row, index) {
            var colT  = row[index - 3] || "";
            var colT1 = row[index - 2] || "";
            var colT3 = row[index] || "";
            var encoded = colT + "_" + colT1 + "_" + colT3 + ".conf";
            return "<a href=\'view_conf.php?file=" + encoded + "\'>" + cell + "</a>";
        }

        // ===== 渲染表格 =====
        function renderTable(page=1) {
            var start = (page-1)*rowsPerPage + 1;
            var end = Math.min(start + rowsPerPage - 1, data.length - 1);
            document.getElementById("totalItems").textContent = data.length - 1;
            updatePagination(page);

            var tableBody = document.getElementById("tableBody");
            tableBody.innerHTML = "";

            for (var i=start;i<=end;i++){
                var row = data[i];
                var tr = document.createElement("tr");
                row.forEach(function(cell,index){
                    if(hiddenColumns.includes(index+1)) return;
                    var td = document.createElement("td");
                    var fnName = extraColumnMap[index];
                    if(fnName && typeof window[fnName] === "function"){
                        td.innerHTML = window[fnName](cell,row,index); // ⭐ 调用对应列的 JS 处理函数
                    } else {
                        td.textContent = cell;
                    }
                    tr.appendChild(td);
                });
                tableBody.appendChild(tr);
            }
        }

        // ===== 分页 =====
        function updatePagination(page) {
            var totalPages = Math.ceil((data.length-1)/rowsPerPage);
            var div = document.getElementById("pagination"); div.innerHTML="";
            if(page>1){ var b=document.createElement("button"); b.textContent="上一页"; b.onclick=function(){renderTable(page-1)}; div.appendChild(b);}
            div.appendChild(document.createTextNode(" 第 "+page+" 页 / 共 "+totalPages+" 页 "));
            if(page<totalPages){ var b=document.createElement("button"); b.textContent="下一页"; b.onclick=function(){renderTable(page+1)}; div.appendChild(b);}
        }

        // ===== 排序 =====
        function sortTable(colIndex){
            if (data.length <= 1) return;
            var rows = data.slice(1);
            var isNum = data.length > 1 && data[1][colIndex] !== null && data[1][colIndex] !== "" && !isNaN(data[1][colIndex]);
            rows.sort(function(a,b){
                var v1 = (a[colIndex] !== null && a[colIndex] !== undefined) ? a[colIndex] : "";
                var v2 = (b[colIndex] !== null && b[colIndex] !== undefined) ? b[colIndex] : "";
                if(isNum) return sortAscending ? (Number(v1) - Number(v2)) : (Number(v2) - Number(v1));
                else return sortAscending ? String(v1).localeCompare(String(v2)) : String(v2).localeCompare(String(v1));
            });
            data=[data[0]].concat(rows); sortAscending=!sortAscending; renderTable(currentPage);
        }

        // ===== 搜索 =====
        function filterTable(){
            var f=document.getElementById("searchInput").value.toLowerCase();
            if(f===""){ data=JSON.parse(JSON.stringify(originalData)); }
            else {
                data = originalData.filter(function(row,i){
                    if(i===0) return true;
                    return row.some(c => c !== null && c !== undefined && String(c).toLowerCase().includes(f));
                });
            }
            currentPage=1; renderTable(currentPage);
        }

        function clearSearch(){ document.getElementById("searchInput").value=""; data=JSON.parse(JSON.stringify(originalData)); currentPage=1; renderTable(currentPage);}
        function changeRowsPerPage(){ rowsPerPage=parseInt(document.getElementById("rowsPerPage").value); renderTable(currentPage); }

        // ===== 导出为原生 Excel (.xlsx) =====
        function exportTableToExcel(){
            var rows=[];
            rows.push(data[0].filter((c,i)=>!hiddenColumns.includes(i+1)));
            for(var i=1;i<data.length;i++){
                rows.push(data[i].filter((c,i)=>!hiddenColumns.includes(i+1)));
            }

            var form = document.createElement("form");
            form.method = "POST";
            form.action = "export_excel.php";
            form.style.display = "none";

            // 1. 发送前端过滤及排序后的行数据
            var inputData = document.createElement("input");
            inputData.type = "hidden";
            inputData.name = "excel_data";
            inputData.value = JSON.stringify(rows);
            form.appendChild(inputData);

            // 2. 发送服务端文件与查询参数 (超大数据防截断兜底)
            var inputPath = document.createElement("input");
            inputPath.type = "hidden";
            inputPath.name = "file";
            inputPath.value = ' . json_encode($fullFilePath) . ';
            form.appendChild(inputPath);

            var inputSearch = document.createElement("input");
            inputSearch.type = "hidden";
            inputSearch.name = "search";
            inputSearch.value = document.getElementById("searchInput").value;
            form.appendChild(inputSearch);

            var inputHidden = document.createElement("input");
            inputHidden.type = "hidden";
            inputHidden.name = "hiddenColumns";
            inputHidden.value = hiddenColumns.join(",");
            form.appendChild(inputHidden);

            var inputFilename = document.createElement("input");
            inputFilename.type = "hidden";
            inputFilename.name = "filename";
            inputFilename.value = "' . $fileName . '_filtered.xlsx";
            form.appendChild(inputFilename);

            // 使用隐藏 iframe 提交，保证页面不跳转、不刷新
            var iframe = document.getElementById("excel_download_iframe");
            if (!iframe) {
                iframe = document.createElement("iframe");
                iframe.id = "excel_download_iframe";
                iframe.name = "excel_download_iframe";
                iframe.style.display = "none";
                document.body.appendChild(iframe);
            }
            form.target = "excel_download_iframe";

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // 格式化本地时间（精确到分）
        var mtime = ' . (int)$mtime . ';
        if (mtime > 0) {
            var d = new Date(mtime * 1000);
            var Y = d.getFullYear();
            var M = String(d.getMonth() + 1).padStart(2, "0");
            var D = String(d.getDate()).padStart(2, "0");
            var h = String(d.getHours()).padStart(2, "0");
            var m = String(d.getMinutes()).padStart(2, "0");
            var el = document.getElementById("dataFetchTime");
            if (el) el.textContent = Y + "-" + M + "-" + D + " " + h + ":" + m;
        }

        renderTable(currentPage); // 初始渲染
    </script>';

    return $output;
}

// =================== 表单提交处理（支持 POST 与 GET） ===================
$filePath = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
$extraColumnIndex = isset($_REQUEST['columnIndex']) ? $_REQUEST['columnIndex'] : null;
$extraFunction = isset($_REQUEST['function']) ? $_REQUEST['function'] : null;
$hiddenColumns = isset($_REQUEST['hiddenClumnIndex']) ? $_REQUEST['hiddenClumnIndex'] : '';

if (empty($filePath)) {
    echo '<form method="POST">';
    echo 'CSV 文件路径: <input type="text" value="/data/deskecc/ack/index/All_cluster_info.csv" name="file" required>';
    echo ' 额外处理列 (从0开始): <input type="number" name="columnIndex" min="0">';
    echo '函数: <input type="text" name="function" value="makeLinkforCluster">';
    echo '隐藏列: <input type="text" name="hiddenClumnIndex">';
    echo '<button type="submit">显示数据</button>';
    echo '</form>';
} else {
    echo displayCsv($filePath, $extraColumnIndex, $extraFunction, $hiddenColumns);
}
?>
