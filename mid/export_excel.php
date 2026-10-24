<?php
// ============================================
// export_excel.php - 原生高效生成 Microsoft Excel (.xlsx) 导出
// 100% 原生 OpenXML 格式，无外部第三方库依赖，支持超大数据与中文
// ============================================

require_once __DIR__ . '/auth.php';
checkAuth();

ini_set('memory_limit', '512M');
set_time_limit(300);

/**
 * 列索引转 Excel 列字母 (0 -> A, 1 -> B, 26 -> AA)
 */
function getColLetter($colIdx) {
    $letter = '';
    while ($colIdx >= 0) {
        $letter = chr($colIdx % 26 + 65) . $letter;
        $colIdx = intdiv($colIdx, 26) - 1;
    }
    return $letter;
}

/**
 * 转义 XML 字符并过滤低位非法控制字符
 */
function escapeXml($str) {
    if ($str === null) return '';
    $str = (string)$str;
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * 核心：使用 ZipArchive 原生生成标准 .xlsx 文件
 */
function generateXlsx($rows, $filename = 'export.xlsx') {
    if (!class_exists('ZipArchive')) {
        die('PHP ZipArchive 扩展未启用');
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('无法创建 Excel 临时文件');
    }

    // 1. [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    // 2. _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    // 3. xl/_rels/workbook.xml.rels
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

    // 4. xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="20480" windowHeight="10240"/></bookViews>'
        . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    // 5. xl/styles.xml (表头纯白加粗黑字居中，细黑边框；数据行浅绿与白色交替斑马纹)
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><name val="微软雅黑"/><sz val="10"/><color rgb="FF000000"/></font>'
        . '<font><b/><name val="微软雅黑"/><sz val="10"/><color rgb="FF000000"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD3EAD6"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FF000000"/></left>'
        . '<right style="thin"><color rgb="FF000000"/></right>'
        . '<top style="thin"><color rgb="FF000000"/></top>'
        . '<bottom style="thin"><color rgb="FF000000"/></bottom>'
        . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '</cellXfs>'
        . '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    // 6. xl/worksheets/sheet1.xml
    // 计算列宽
    $colWidths = [];
    $totalCols = !empty($rows) ? count($rows[0]) : 0;
    for ($c = 0; $c < $totalCols; $c++) {
        $colWidths[$c] = 12;
    }
    $sampleRows = array_slice($rows, 0, 100);
    foreach ($sampleRows as $row) {
        foreach ($row as $c => $val) {
            $len = function_exists('mb_strwidth') ? mb_strwidth((string)$val, 'UTF-8') : strlen((string)$val);
            if ($len + 4 > $colWidths[$c]) {
                $colWidths[$c] = min($len + 4, 50);
            }
        }
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/>';

    if (!empty($colWidths)) {
        $sheetXml .= '<cols>';
        foreach ($colWidths as $c => $w) {
            $colNum = $c + 1;
            $sheetXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $w . '" customWidth="1"/>';
        }
        $sheetXml .= '</cols>';
    }

    $sheetXml .= '<sheetData>';
    foreach ($rows as $rIdx => $row) {
        $rowNum = $rIdx + 1;
        $isHeader = ($rIdx === 0);
        if ($isHeader) {
            $styleId = 1;
            $rowHeight = 26;
        } else {
            // Excel行号：第2行为偶数(白色)，第3行为奇数(浅绿交替斑马纹)
            $styleId = ($rowNum % 2 === 0) ? 2 : 3;
            $rowHeight = 22;
        }

        $sheetXml .= '<row r="' . $rowNum . '" ht="' . $rowHeight . '" customHeight="1">';
        foreach ($row as $cIdx => $val) {
            $cellRef = getColLetter($cIdx) . $rowNum;
            $valEscaped = escapeXml($val);
            $sheetXml .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="inlineStr"><is><t>' . $valEscaped . '</t></is></c>';
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData></worksheet>';

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    if (ob_get_level()) {
        ob_end_clean();
    }

    $downloadName = preg_replace('/\.csv$/i', '', $filename);
    if (!preg_match('/\.xlsx$/i', $downloadName)) {
        $downloadName .= '.xlsx';
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    readfile($tempFile);
    unlink($tempFile);
    exit;
}

// =================== 请求处理 ===================
$rows = [];
if (!empty($_POST['excel_data'])) {
    $decoded = json_decode($_POST['excel_data'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $rows = $decoded;
    }
}

// 降级与超大数据兼容：若 POST 数据被截断或空，且传了服务端 file，直接从文件读取
if (empty($rows) && !empty($_POST['file'])) {
    $filePath = $_POST['file'];
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $hiddenCols = isset($_POST['hiddenColumns']) && $_POST['hiddenColumns'] !== '' 
        ? array_map('intval', explode(',', $_POST['hiddenColumns'])) 
        : [];

    if (file_exists($filePath) && is_readable($filePath)) {
        if (($handle = fopen($filePath, 'r')) !== false) {
            $lineIdx = 0;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $cleanRow = array_map(function($val) {
                    return $val !== null ? preg_replace('/^\xEF\xBB\xBF/', '', (string)$val) : '';
                }, $row);

                if ($lineIdx === 0) {
                    $headerRow = [];
                    foreach ($cleanRow as $i => $cell) {
                        if (in_array($i + 1, $hiddenCols)) continue;
                        $headerRow[] = $cell;
                    }
                    $rows[] = $headerRow;
                } else {
                    if ($search !== '') {
                        $match = false;
                        foreach ($cleanRow as $cell) {
                            if (stripos($cell, $search) !== false) {
                                $match = true;
                                break;
                            }
                        }
                        if (!$match) {
                            $lineIdx++;
                            continue;
                        }
                    }
                    $dataRow = [];
                    foreach ($cleanRow as $i => $cell) {
                        if (in_array($i + 1, $hiddenCols)) continue;
                        $dataRow[] = $cell;
                    }
                    $rows[] = $dataRow;
                }
                $lineIdx++;
            }
            fclose($handle);
        }
    }
}

$filename = !empty($_POST['filename']) ? basename($_POST['filename']) : 'export.xlsx';

if (!empty($rows)) {
    generateXlsx($rows, $filename);
} else {
    echo '无数据可导出';
}
