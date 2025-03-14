<?php
session_start();
header('Content-Type: application/json');

// 检查是否已认证
$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// 检查会话是否过期
if ($authenticated && isset($_SESSION['auth_expiry']) && time() > $_SESSION['auth_expiry']) {
    // 会话已过期，清除会话数据
    session_unset();
    session_destroy();
    $authenticated = false;
}

// 返回认证状态
echo json_encode(['authenticated' => $authenticated]);
?>