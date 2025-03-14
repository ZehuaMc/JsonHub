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
$accessLogsFile = $config['accessLogsFile'];

// 获取图表类型
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === 'access') {
    // 访问量统计
    $days = 7; // 统计过去7天的数据
    $accessData = [];
    $labels = [];
    $data = [];
    
    // 初始化数据
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = $date;
        $data[] = 0;
    }
    
    // 读取访问日志
    if (file_exists($accessLogsFile)) {
        $logs = json_decode(file_get_contents($accessLogsFile), true);
        if (is_array($logs)) {
            foreach ($logs as $log) {
                $date = date('Y-m-d', $log['time']);
                $index = array_search($date, $labels);
                if ($index !== false) {
                    $data[$index]++;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $data
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '未知的图表类型']);
}
?>