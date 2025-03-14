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
$accessLogsFile = $config['accessLogsFile'];

// 检查POST请求 - 处理清除日志操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';
    
    if ($action === 'clear') {
        // 清空日志
        if (file_put_contents($accessLogsFile, '[]')) {
            echo json_encode(['success' => true, 'message' => '访问日志已清空']);
        } else {
            echo json_encode(['success' => false, 'message' => '清空访问日志失败']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => '未知操作']);
    exit;
}

// 检查日志文件是否存在
if (!file_exists($accessLogsFile)) {
    echo json_encode(['success' => true, 'logs' => []]);
    exit;
}

// 读取访问日志
$logs = json_decode(file_get_contents($accessLogsFile), true);
if (!is_array($logs)) {
    $logs = [];
}

// 按时间排序（最新的在前）
usort($logs, function($a, $b) {
    return $b['time'] - $a['time'];
});

echo json_encode(['success' => true, 'logs' => $logs]);
?>