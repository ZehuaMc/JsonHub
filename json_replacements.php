<?php
session_start();
header('Content-Type: application/json');

// 检查认证（适用于所有请求类型）
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 获取配置文件
$config = include 'config.php';
$replacementsFile = $config['replacementsFile'];

// 确保替换规则文件存在
if (!file_exists($replacementsFile)) {
    file_put_contents($replacementsFile, json_encode([]));
}

// 读取替换规则
$replacements = json_decode(file_get_contents($replacementsFile), true);
if ($replacements === null) {
    $replacements = [];
}

// GET请求 - 返回所有替换规则
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'replacements' => $replacements]);
    exit;
}

// POST请求 - 处理替换规则操作
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

if ($action === 'add') {
    // 添加新的替换规则
    $name = isset($input['name']) ? trim($input['name']) : '';
    $path = isset($input['path']) ? trim($input['path']) : '';
    $content = isset($input['content']) ? $input['content'] : null;
    
    if (empty($name) || empty($path) || $content === null) {
        echo json_encode(['success' => false, 'message' => '名称、路径和内容不能为空']);
        exit;
    }
    
    $replacements[$name] = [
        'path' => $path,
        'content' => $content
    ];
    
    if (file_put_contents($replacementsFile, json_encode($replacements, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '保存替换规则失败']);
    }
} elseif ($action === 'update') {
    // 更新现有的替换规则
    $oldName = isset($input['oldName']) ? trim($input['oldName']) : '';
    $name = isset($input['name']) ? trim($input['name']) : '';
    $path = isset($input['path']) ? trim($input['path']) : '';
    $content = isset($input['content']) ? $input['content'] : null;
    
    if (empty($oldName) || empty($name) || empty($path) || $content === null) {
        echo json_encode(['success' => false, 'message' => '名称、路径和内容不能为空']);
        exit;
    }
    
    // 如果名称改变，删除旧的规则
    if ($oldName !== $name && isset($replacements[$oldName])) {
        unset($replacements[$oldName]);
    }
    
    $replacements[$name] = [
        'path' => $path,
        'content' => $content
    ];
    
    if (file_put_contents($replacementsFile, json_encode($replacements, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '更新替换规则失败']);
    }
} elseif ($action === 'delete') {
    // 删除替换规则
    $name = isset($input['name']) ? trim($input['name']) : '';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => '未指定规则名称']);
        exit;
    }
    
    if (isset($replacements[$name])) {
        unset($replacements[$name]);
        
        if (file_put_contents($replacementsFile, json_encode($replacements, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '删除替换规则失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '指定的替换规则不存在']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>