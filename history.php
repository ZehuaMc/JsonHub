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
$historyDir = $config['historyDir'];
$jsonFilesDir = $config['jsonFilesDir'];

// 确保目录存在
if (!file_exists($historyDir)) {
    mkdir($historyDir, 0755, true);
}

// GET请求 - 获取历史版本列表或特定历史文件内容
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 如果指定了文件名，返回该文件的内容
    if (isset($_GET['file'])) {
        $file = basename($_GET['file']);
        $filePath = $historyDir . $file;
        
        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => '指定的历史文件不存在']);
            exit;
        }
        
        $content = file_get_contents($filePath);
        echo json_encode(['success' => true, 'content' => $content]);
        exit;
    }
    
    // 如果指定了列出特定文件的历史版本
    if (isset($_GET['list_history_for'])) {
        $originalFile = basename($_GET['list_history_for']);
        $historyFiles = [];
        
        if ($handle = opendir($historyDir)) {
            $fileNameWithoutExt = pathinfo($originalFile, PATHINFO_FILENAME);
            $pattern = "/^" . preg_quote($fileNameWithoutExt) . "_(\d{14})\.json$/";
            
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && preg_match($pattern, $entry, $matches)) {
                    $timestamp = $matches[1];
                    $formatted = DateTime::createFromFormat('YmdHis', $timestamp);
                    
                    if ($formatted) {
                        $filePath = $historyDir . $entry;
                        $historyFiles[] = [
                            'file' => $entry,
                            'original' => $originalFile,
                            'time' => $formatted->getTimestamp(),
                            'formatted_time' => $formatted->format('Y-m-d H:i:s'),
                            'size' => filesize($filePath)
                        ];
                    }
                }
            }
            closedir($handle);
            
            // 按时间排序（最新的在前）
            usort($historyFiles, function($a, $b) {
                return $b['time'] - $a['time'];
            });
            
            echo json_encode(['success' => true, 'file' => $originalFile, 'history' => $historyFiles]);
            exit;
        }
    }
    
    // 否则，返回具有历史版本的文件列表
    $files = [];
    $history = [];
    
    // 获取所有JSON文件
    if ($handle = opendir($jsonFilesDir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'json') {
                $files[] = $entry;
            }
        }
        closedir($handle);
    }
    
    // 对于每个文件，检查是否有历史版本
    foreach ($files as $file) {
        $fileNameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
        $pattern = $historyDir . $fileNameWithoutExt . '_*.json';
        $historyFiles = glob($pattern);
        
        if (!empty($historyFiles)) {
            // 按修改时间排序
            usort($historyFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // 获取最新的历史版本时间（排序后的第一个文件）
            $latestHistoryFile = $historyFiles[0];
            $latestHistoryTime = filemtime($latestHistoryFile);
            
            $history[] = [
                'file' => $file,
                'historyCount' => count($historyFiles),
                'latestHistoryTime' => $latestHistoryTime
            ];
        }
    }
    
    // 按最新历史版本时间排序
    usort($history, function($a, $b) {
        return $b['latestHistoryTime'] - $a['latestHistoryTime'];
    });
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// POST请求 - 处理历史版本操作（恢复或删除）
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';
$file = isset($input['file']) ? basename($input['file']) : '';

if (empty($file)) {
    echo json_encode(['success' => false, 'message' => '未指定文件名']);
    exit;
}

$filePath = $historyDir . $file;

// 检查历史文件是否存在
if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'message' => '指定的历史文件不存在']);
    exit;
}

if ($action === 'restore') {
    // 从文件名提取原始文件名（如果是使用时间戳格式化的）
    $originalFileName = preg_replace('/_\d{14}\.json$/', '.json', $file);
    $targetPath = $jsonFilesDir . $originalFileName;
    
    // 确保目标目录存在
    if (!file_exists($jsonFilesDir)) {
        mkdir($jsonFilesDir, 0755, true);
    }
    
    // 恢复历史文件
    if (copy($filePath, $targetPath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '恢复历史版本失败']);
    }
} elseif ($action === 'delete') {
    // 删除历史文件
    if (unlink($filePath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '删除历史版本失败']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>