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

// 确保目录存在
if (!file_exists($jsonFilesDir)) {
    mkdir($jsonFilesDir, 0755, true);
}

// 获取所有JSON文件
$files = [];
if ($handle = opendir($jsonFilesDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'json') {
            $filePath = $jsonFilesDir . $entry;
            $files[] = [
                'name' => $entry,
                'size' => filesize($filePath),
                'time' => filemtime($filePath)
            ];
        }
    }
    closedir($handle);
}

// 按修改时间排序（最新的在前）
usort($files, function($a, $b) {
    return $b['time'] - $a['time'];
});

echo json_encode(['success' => true, 'files' => $files]);
?>