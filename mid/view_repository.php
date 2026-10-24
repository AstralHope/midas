<?php
// ============================================
// view_repository.php - 介质源速览小工具
// 解析并展示介质机 tree 目录结构，支持文件点击直接下载与实时检索
// ============================================

require_once __DIR__ . '/auth.php';
checkAuth();

// 文件路径（支持容器内挂载与本地相对路径）
$treeFile = '/data/deskecc/baseinfo/repository.tree';
if (!file_exists($treeFile)) {
    $altPath = __DIR__ . '/../data/deskecc/baseinfo/repository.tree';
    if (file_exists($altPath)) {
        $treeFile = $altPath;
    }
}

$fileFound = file_exists($treeFile) && is_readable($treeFile);
$fileMtime = $fileFound ? filemtime($treeFile) : 0;
$rawContent = $fileFound ? file_get_contents($treeFile) : '';

// 纯文本输出支持（curl / CLI / raw=1）
$isCurl = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'curl') !== false;
$isRaw = isset($_GET['raw']) && $_GET['raw'] == '1';
$wantsPlain = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/plain') !== false;

if (($isCurl || $isRaw || $wantsPlain) && !isset($_GET['html'])) {
    header('Content-Type: text/plain; charset=utf-8');
    if ($fileFound) {
        echo $rawContent;
    } else {
        echo "Error: repository.tree not found at " . $treeFile . "\n";
    }
    exit;
}

// ----------------- 解析 repository.tree -----------------
$baseUrl = '';
$rootPath = '';
$summaryText = '';
$treeNodes = [];

if ($fileFound && !empty($rawContent)) {
    $lines = preg_split('/\r\n|\r|\n/', $rawContent);
    $cleanLines = [];
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $cleanLines[] = $line;
        }
    }

    if (count($cleanLines) >= 1) {
        $baseUrl = rtrim(trim($cleanLines[0]), '/');
    }
    if (count($cleanLines) >= 2) {
        $rootPath = trim($cleanLines[1]);
    }

    // 匹配树形节点正则：捕获层级缩进前缀、分支连接符与节点名称
    // 兼容 UTF-8 (├──, └──) 与 ASCII (|--, \--, +--)
    $pattern = '/^(.*?)(├──|└──|\|--|\\\\--|\+--)\s*(.*)$/u';

    $parsedItems = [];
    for ($i = 2; $i < count($cleanLines); $i++) {
        $line = $cleanLines[$i];
        if (preg_match($pattern, $line, $m)) {
            $indent = $m[1];
            $connector = $m[2];
            $nodeName = trim($m[3]);

            // 替换非换行空格 (\u00a0) 为普通空格以准确计算缩进层级
            $normIndent = preg_replace('/[\x{00a0}\x{c2}\x{a0}]/u', ' ', $indent);
            $level = (int)(mb_strlen($normIndent) / 4) + 1;

            $parsedItems[] = [
                'level'       => $level,
                'raw_prefix'  => $indent . $connector . ' ',
                'name'        => $nodeName,
                'is_dir'      => false,
                'full_path'   => '',
                'download_url'=> ''
            ];
        } else {
            // 匹配末尾统计汇总行（例如：2 directories, 3 files）
            if (stripos($line, 'director') !== false || stripos($line, 'file') !== false) {
                $summaryText = trim($line);
            }
        }
    }

    // 智能识别目录与文件：若下一节点的层级大于当前节点，或当前名称以 / 结尾，则为目录
    for ($i = 0; $i < count($parsedItems); $i++) {
        $isDir = false;
        if ($i + 1 < count($parsedItems) && $parsedItems[$i + 1]['level'] > $parsedItems[$i]['level']) {
            $isDir = true;
        } elseif (substr($parsedItems[$i]['name'], -1) === '/') {
            $isDir = true;
        }
        $parsedItems[$i]['is_dir'] = $isDir;
    }

    // 依据树状层级堆栈构建完整相对路径与下载 URL
    $pathStack = [];
    foreach ($parsedItems as &$item) {
        $lvl = $item['level'];
        $pathStack = array_slice($pathStack, 0, $lvl - 1);
        $cleanName = rtrim($item['name'], '/');
        $pathStack[] = $cleanName;
        $item['full_path'] = implode('/', $pathStack);

        if (!$item['is_dir'] && !empty($baseUrl)) {
            // URL 路径各分段进行 URL 编码
            $pathParts = explode('/', $item['full_path']);
            $encodedParts = array_map('rawurlencode', $pathParts);
            $item['download_url'] = $baseUrl . '/' . implode('/', $encodedParts);
        }
    }
    unset($item);

    $treeNodes = $parsedItems;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>介质源速览 - MIDAS</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="js/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/iconfont.css">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/jquery-1.12.4.min.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
            padding: 20px 25px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #333;
        }
        .repo-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title i {
            color: #1890ff;
            font-size: 22px;
        }
        .meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .meta-item strong {
            color: #64748b;
            margin-right: 6px;
        }
        .meta-item span, .meta-item a {
            color: #0f172a;
            font-weight: 500;
        }
        .meta-item a {
            color: #1890ff;
            text-decoration: none;
        }
        .meta-item a:hover {
            text-decoration: underline;
        }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .search-wrap {
            position: relative;
            flex: 1;
            max-width: 450px;
        }
        .search-wrap input {
            width: 100%;
            height: 36px;
            padding: 6px 12px 6px 34px;
            font-size: 13px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            outline: none;
            transition: all 0.2s;
        }
        .search-wrap input:focus {
            border-color: #1890ff;
            box-shadow: 0 0 0 2px rgba(24,144,255,0.2);
        }
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
        }
        .btn-view-toggle {
            height: 36px;
            padding: 0 14px;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #374151;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-view-toggle:hover, .btn-view-toggle.active {
            color: #1890ff;
            border-color: #1890ff;
            background: #e6f7ff;
        }
        /* 树形代码区域 */
        .tree-container {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 6px;
            padding: 16px 20px;
            font-family: "Consolas", "Monaco", "Menlo", "Courier New", monospace;
            font-size: 13px;
            line-height: 1.8;
            overflow-x: auto;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.3);
        }
        .tree-line {
            white-space: pre;
            transition: background 0.15s;
            padding: 1px 4px;
            border-radius: 3px;
            display: block;
        }
        .tree-line:hover {
            background: rgba(255,255,255,0.08);
        }
        .tree-glyph {
            color: #6e7681;
            user-select: none;
        }
        .dir-label {
            color: #e5c07b;
            font-weight: 600;
            user-select: text;
        }
        .file-link {
            color: #61afef;
            text-decoration: none;
            font-weight: 500;
            padding: 1px 4px;
            border-radius: 3px;
            transition: all 0.15s;
        }
        .file-link:hover {
            color: #ffffff;
            background: #1890ff;
            text-decoration: none;
        }
        .download-badge {
            font-size: 11px;
            color: #98c379;
            margin-left: 8px;
            opacity: 0.8;
            user-select: none;
        }
        .copy-btn {
            font-size: 11px;
            color: #abb2bf;
            background: transparent;
            border: 1px solid #4b5263;
            border-radius: 3px;
            padding: 0 4px;
            margin-left: 6px;
            cursor: pointer;
            display: none;
        }
        .tree-line:hover .copy-btn {
            display: inline-block;
        }
        .copy-btn:hover {
            color: #fff;
            border-color: #61afef;
        }
        .tree-summary {
            color: #98c379;
            font-weight: 600;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px dashed #3e4451;
        }
        /* 表格清单模式 */
        .table-view-container {
            display: none;
        }
        .table-modern {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .table-modern th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 10px 14px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .table-modern td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .table-modern tr:hover td {
            background: #f1f5f9;
        }
        .btn-download-sm {
            padding: 4px 10px;
            background: #1890ff;
            color: #fff;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-download-sm:hover {
            background: #096dd9;
            color: #fff;
            text-decoration: none;
        }
        .highlight {
            background: #ffe58f !important;
            color: #000 !important;
            border-radius: 2px;
        }
        .empty-tip {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    <div class="repo-card">
        <h1 class="page-title">
            <i class="iconfont icon-previewright"></i>
            <span>介质源速览 (Repository Explorer)</span>
        </h1>

        <!-- 数据状态与获取时间条 -->
        <div class="meta-bar">
            <div class="meta-item">
                <strong>数据文件：</strong>
                <span><?php echo htmlspecialchars($treeFile); ?></span>
            </div>
            <div class="meta-item">
                <strong>介质机前置 URL：</strong>
                <?php if (!empty($baseUrl)): ?>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>" target="_blank"><?php echo htmlspecialchars($baseUrl); ?></a>
                <?php else: ?>
                    <span style="color:#d9534f;">未配置</span>
                <?php endif; ?>
            </div>
            <div class="meta-item">
                <strong>服务器根目录：</strong>
                <span><?php echo htmlspecialchars($rootPath ?: '--'); ?></span>
            </div>
            <div class="meta-item">
                <strong>数据更新时间：</strong>
                <span id="dataFetchTime">--</span>
            </div>
        </div>

        <?php if (!$fileFound): ?>
            <div class="alert alert-warning" style="margin-top:15px;">
                <strong>提示：</strong> 尚未在 <code><?php echo htmlspecialchars($treeFile); ?></code> 找到介质树文件。请先运行采集同步脚本生成该文件。
            </div>
        <?php else: ?>
            <!-- 控制与检索栏 -->
            <div class="action-bar">
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="filterInput" placeholder="输入关键词实时过滤文件名或目录..." oninput="filterNodes()">
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-view-toggle active" id="btnTree" onclick="switchView('tree')">
                        🌲 树状视图
                    </button>
                    <button class="btn-view-toggle" id="btnList" onclick="switchView('list')">
                        📋 文件清单
                    </button>
                    <button class="btn-view-toggle" onclick="location.reload()">
                        🔄 刷新
                    </button>
                </div>
            </div>

            <!-- 视图 1：经典树状图模式 -->
            <div class="tree-container" id="treeView">
                <div style="color:#61afef; font-weight:600; margin-bottom:6px;">
                    📁 <?php echo htmlspecialchars($rootPath ?: 'Root'); ?>
                </div>
                <div id="treeLinesList">
                    <?php foreach ($treeNodes as $idx => $node): ?>
                        <div class="tree-line" data-name="<?php echo htmlspecialchars(strtolower($node['name'])); ?>" data-path="<?php echo htmlspecialchars(strtolower($node['full_path'])); ?>">
                            <span class="tree-glyph"><?php echo htmlspecialchars($node['raw_prefix']); ?></span>
                            <?php if ($node['is_dir']): ?>
                                <span class="dir-label">📁 <?php echo htmlspecialchars($node['name']); ?></span>
                            <?php else: ?>
                                <span>📄 </span><a href="<?php echo htmlspecialchars($node['download_url']); ?>" class="file-link" target="_blank" download title="点击直接下载: <?php echo htmlspecialchars($node['download_url']); ?>"><?php echo htmlspecialchars($node['name']); ?></a>
                                <span class="download-badge">⬇️ 可下载</span>
                                <button type="button" class="copy-btn" onclick="copyLink('<?php echo htmlspecialchars($node['download_url']); ?>', this)">复制链接</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($summaryText)): ?>
                    <div class="tree-summary">📊 <?php echo htmlspecialchars($summaryText); ?></div>
                <?php endif; ?>
            </div>

            <!-- 视图 2：表格平铺清单模式 -->
            <div class="table-view-container" id="listView">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 280px;">文件名</th>
                            <th>相对路径</th>
                            <th style="width: 130px; text-align: center;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="listTableBody">
                        <?php 
                        $fileCount = 0;
                        foreach ($treeNodes as $node): 
                            if ($node['is_dir']) continue;
                            $fileCount++;
                        ?>
                            <tr class="list-row" data-name="<?php echo htmlspecialchars(strtolower($node['name'])); ?>" data-path="<?php echo htmlspecialchars(strtolower($node['full_path'])); ?>">
                                <td><?php echo $fileCount; ?></td>
                                <td><strong>📄 <?php echo htmlspecialchars($node['name']); ?></strong></td>
                                <td><code style="color:#475569; background:#f1f5f9; padding:2px 6px; border-radius:3px;"><?php echo htmlspecialchars($node['full_path']); ?></code></td>
                                <td style="text-align: center;">
                                    <a href="<?php echo htmlspecialchars($node['download_url']); ?>" target="_blank" download class="btn-download-sm">
                                        ⬇️ 下载
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($fileCount === 0): ?>
                            <tr><td colspan="4" class="empty-tip">暂无可下载的文件</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // 本地更新时间格式化
        (function() {
            var mtime = <?php echo (int)$fileMtime; ?>;
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
        })();

        // 切换树状视图 / 列表清单
        function switchView(type) {
            if (type === 'tree') {
                document.getElementById('treeView').style.display = 'block';
                document.getElementById('listView').style.display = 'none';
                document.getElementById('btnTree').classList.add('active');
                document.getElementById('btnList').classList.remove('active');
            } else {
                document.getElementById('treeView').style.display = 'none';
                document.getElementById('listView').style.display = 'block';
                document.getElementById('btnList').classList.add('active');
                document.getElementById('btnTree').classList.remove('active');
            }
        }

        // 实时过滤搜索
        function filterNodes() {
            var keyword = document.getElementById('filterInput').value.trim().toLowerCase();
            var treeLines = document.querySelectorAll('.tree-line');
            var listRows = document.querySelectorAll('.list-row');

            // 过滤树状图
            treeLines.forEach(function(line) {
                var name = line.getAttribute('data-name') || '';
                var path = line.getAttribute('data-path') || '';
                if (keyword === '' || name.indexOf(keyword) !== -1 || path.indexOf(keyword) !== -1) {
                    line.style.display = 'block';
                } else {
                    line.style.display = 'none';
                }
            });

            // 过滤清单表格
            listRows.forEach(function(row) {
                var name = row.getAttribute('data-name') || '';
                var path = row.getAttribute('data-path') || '';
                if (keyword === '' || name.indexOf(keyword) !== -1 || path.indexOf(keyword) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // 复制链接快捷功能
        function copyLink(url, btn) {
            if (!url) return;
            navigator.clipboard.writeText(url).then(function() {
                var original = btn.textContent;
                btn.textContent = '已复制!';
                btn.style.color = '#98c379';
                btn.style.borderColor = '#98c379';
                setTimeout(function() {
                    btn.textContent = original;
                    btn.style.color = '';
                    btn.style.borderColor = '';
                }, 1500);
            }).catch(function(err) {
                prompt("请手动复制下载地址：", url);
            });
        }
    </script>
</body>
</html>
