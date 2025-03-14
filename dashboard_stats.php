<?php
session_start();
header('Content-Type: application/json');

// 检查认证
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 获取配置文件
$config = include 'config.php';
$jsonFilesDir = $config['jsonFilesDir'];
$historyDir = $config['historyDir'];
$replacementsFile = $config['replacementsFile'];
$accessLogsFile = $config['accessLogsFile'];

// 统计文件数量
$totalFiles = 0;
if (is_dir($jsonFilesDir)) {
    $files = scandir($jsonFilesDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'json') {
            $totalFiles++;
        }
    }
}

// 统计历史版本数量
$totalHistory = 0;
if (is_dir($historyDir)) {
    $files = scandir($historyDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'json') {
            $totalHistory++;
        }
    }
}

// 统计替换规则数量
$totalReplacements = 0;
if (file_exists($replacementsFile)) {
    $replacements = json_decode(file_get_contents($replacementsFile), true);
    if (is_array($replacements)) {
        $totalReplacements = count($replacements);
    }
}

// 统计访问次数
$totalAccesses = 0;
if (file_exists($accessLogsFile)) {
    $logs = json_decode(file_get_contents($accessLogsFile), true);
    if (is_array($logs)) {
        $totalAccesses = count($logs);
    }
}

echo json_encode([
    'success' => true,
    'totalFiles' => $totalFiles,
    'totalHistory' => $totalHistory,
    'totalReplacements' => $totalReplacements,
    'totalAccesses' => $totalAccesses
]);
?>