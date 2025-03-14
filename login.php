<?php
session_start();
header('Content-Type: application/json');

// 获取配置文件
$config = include 'config.php';

// 获取提交的密码
$input = json_decode(file_get_contents('php://input'), true);
$password = isset($input['password']) ? $input['password'] : '';

// 初始化响应
$response = ['success' => false];

// 验证密码
if ($password && password_verify($password, $config['loginPasswordHash'])) {
    // 密码正确，设置会话状态
    $_SESSION['authenticated'] = true;
    // 设置会话过期时间（12小时）
    $_SESSION['auth_time'] = time();
    $_SESSION['auth_expiry'] = time() + (12 * 3600);
    
    $response['success'] = true;
    $response['message'] = '登录成功';
} else {
    $response['message'] = '密码错误';
}

echo json_encode($response);
?>