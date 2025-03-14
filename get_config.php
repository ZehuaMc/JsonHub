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

// 获取文件名
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($file)) {
    echo json_encode(['success' => false, 'message' => '未指定文件名']);
    exit;
}

$filePath = $jsonFilesDir . $file;

// 检查文件是否存在
if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'message' => '文件不存在']);
    exit;
}

// 读取文件内容
$content = file_get_contents($filePath);

// 确保内容是有效的JSON
$json = json_decode($content);
if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '文件内容不是有效的JSON格式']);
    exit;
}

echo json_encode(['success' => true, 'content' => $content]);
?>