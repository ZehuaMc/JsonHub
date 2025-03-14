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

// 确保目录存在
if (!file_exists($jsonFilesDir)) {
    mkdir($jsonFilesDir, 0755, true);
}

if (!file_exists($historyDir)) {
    mkdir($historyDir, 0755, true);
}

// 检查是否有文件上传
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => '未上传文件']);
    exit;
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败，错误码: ' . $_FILES['file']['error']]);
    exit;
}

// 获取上传的文件
$uploadedFile = $_FILES['file']['tmp_name'];
$fileName = basename($_FILES['file']['name']);

// 检查文件类型，只允许JSON文件
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($fileExt !== 'json') {
    echo json_encode(['success' => false, 'message' => '只能上传JSON文件']);
    exit;
}

// 自定义文件名（如果提供）
if (isset($_POST['fileName']) && !empty($_POST['fileName'])) {
    $fileName = basename($_POST['fileName']);
    // 确保文件名以.json结尾
    if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'json') {
        $fileName .= '.json';
    }
}

$filePath = $jsonFilesDir . $fileName;
$isNewFile = !file_exists($filePath);

// 检查上传的文件是否是有效的JSON
$content = file_get_contents($uploadedFile);
$json = json_decode($content);
if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '上传的文件不是有效的JSON格式: ' . json_last_error_msg()]);
    exit;
}

// 如果文件已存在，创建历史版本
if (!$isNewFile) {
    $timestamp = date('YmdHis');
    $historyFile = $historyDir . pathinfo($fileName, PATHINFO_FILENAME) . '_' . $timestamp . '.json';
    copy($filePath, $historyFile);
}

// 移动上传的文件到目标目录
if (move_uploaded_file($uploadedFile, $filePath)) {
    echo json_encode(['success' => true, 'fileName' => $fileName]);
} else {
    echo json_encode(['success' => false, 'message' => '保存文件失败']);
}
?>