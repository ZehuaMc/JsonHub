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
$settingsFile = $config['settingsFile'];

// 确保目录存在
if (!file_exists($jsonFilesDir)) {
    mkdir($jsonFilesDir, 0755, true);
}

if (!file_exists($historyDir)) {
    mkdir($historyDir, 0755, true);
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 检查是否是删除操作
if (isset($input['action']) && $input['action'] === 'delete') {
    $file = isset($input['file']) ? basename($input['file']) : '';
    
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
    
    // 删除文件
    if (unlink($filePath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '删除文件失败']);
    }
    exit;
}

// 获取文件名和内容
$file = isset($input['file']) ? basename($input['file']) : '';
$content = isset($input['content']) ? $input['content'] : '';

if (empty($file)) {
    echo json_encode(['success' => false, 'message' => '未指定文件名']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => '内容不能为空']);
    exit;
}

// 确保内容是有效的JSON
$json = json_decode($content);
if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON格式无效: ' . json_last_error_msg()]);
    exit;
}

$filePath = $jsonFilesDir . $file;
$isNewFile = !file_exists($filePath);

// 如果文件已存在，创建历史版本
if (!$isNewFile) {
    // 读取配置获取备份数量限制
    $backupCount = 10; // 默认值
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (isset($settings['backupCount']) && is_numeric($settings['backupCount'])) {
            $backupCount = (int)$settings['backupCount'];
        }
    }
    
    // 清理过多的历史版本
    if ($backupCount > 0) {
        $pattern = $historyDir . pathinfo($file, PATHINFO_FILENAME) . '_*.json';
        $historyFiles = glob($pattern);
        
        if (count($historyFiles) >= $backupCount) {
            // 按修改时间排序
            usort($historyFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // 删除最旧的，直到数量小于限制
            $deleteCount = count($historyFiles) - $backupCount + 1;
            for ($i = 0; $i < $deleteCount; $i++) {
                unlink($historyFiles[$i]);
            }
        }
    }
    
    // 创建历史版本
    $timestamp = date('YmdHis');
    $historyFile = $historyDir . pathinfo($file, PATHINFO_FILENAME) . '_' . $timestamp . '.json';
    copy($filePath, $historyFile);
}

// 写入新内容
if (file_put_contents($filePath, $content)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => '保存文件失败']);
}
?>