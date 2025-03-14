<?php
session_start();
header('Content-Type: application/json');

// 清除会话数据
session_unset();
session_destroy();

// 返回成功响应
echo json_encode(['success' => true]);
?>