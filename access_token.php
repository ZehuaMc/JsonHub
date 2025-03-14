<?php
session_start();
header('Content-Type: application/json');

// 获取配置文件
$config = include 'config.php';
$accessTokensFile = $config['accessTokensFile'];

// 确保令牌文件存在
if (!file_exists($accessTokensFile)) {
    file_put_contents($accessTokensFile, json_encode([]));
}

// 读取现有令牌
$tokens = json_decode(file_get_contents($accessTokensFile), true);
if (!is_array($tokens)) {
    $tokens = [];
}

// GET请求 - 返回所有令牌
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 检查认证
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        echo json_encode(['success' => false, 'message' => '未授权访问']);
        exit;
    }
    
    // 检查并移除已过期的令牌
    $currentTokens = [];
    $now = time();
    
    foreach ($tokens as $tokenKey => $tokenData) {
        $expiry = isset($tokenData['expiry']) ? $tokenData['expiry'] : null;
        if ($expiry === null || $expiry > $now) {
            $currentTokens[] = [
                'token' => $tokenKey,
                'file' => isset($tokenData['file']) ? $tokenData['file'] : '',
                'created' => isset($tokenData['created']) ? $tokenData['created'] : 0,
                'expiry' => $expiry,
                'replacement' => isset($tokenData['replacement']) ? $tokenData['replacement'] : ''
            ];
        } else {
            // 令牌已过期，从文件中删除
            unset($tokens[$tokenKey]);
        }
    }
    
    // 如果有过期令牌被移除，更新令牌文件
    if (count($currentTokens) != count($tokens)) {
        file_put_contents($accessTokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
    }
    
    echo json_encode(['success' => true, 'tokens' => $currentTokens]);
    exit;
}

// POST请求 - 处理令牌操作
$input = json_decode(file_get_contents('php://input'), true);

// 检查是否是撤销令牌请求
if (isset($input['action']) && $input['action'] === 'revoke') {
    // 检查认证
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        echo json_encode(['success' => false, 'message' => '未授权访问']);
        exit;
    }
    
    $tokenToRevoke = isset($input['token']) ? $input['token'] : '';
    
    if (empty($tokenToRevoke)) {
        echo json_encode(['success' => false, 'message' => '未指定要撤销的令牌']);
        exit;
    }
    
    if (isset($tokens[$tokenToRevoke])) {
        unset($tokens[$tokenToRevoke]);
        
        if (file_put_contents($accessTokensFile, json_encode($tokens, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true, 'message' => '令牌已成功撤销']);
        } else {
            echo json_encode(['success' => false, 'message' => '撤销令牌失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '指定的令牌不存在']);
    }
    exit;
}

// 处理创建令牌请求
// 检查认证
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

$token = isset($input['token']) ? $input['token'] : '';
$file = isset($input['file']) ? $input['file'] : '';
$replacement = isset($input['replacement']) ? $input['replacement'] : '';
$expiry = isset($input['expiry']) ? intval($input['expiry']) : 0;

// 验证数据
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => '令牌不能为空']);
    exit;
}

// 检查文件是否存在（如果指定了文件）
if (!empty($file)) {
    $filePath = $config['jsonFilesDir'] . $file;
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => '指定的文件不存在']);
        exit;
    }
}

// 如果指定了替换规则，检查它是否存在
if (!empty($replacement)) {
    $replacementsFile = $config['replacementsFile'];
    if (file_exists($replacementsFile)) {
        $replacements = json_decode(file_get_contents($replacementsFile), true);
        if (!isset($replacements[$replacement])) {
            echo json_encode(['success' => false, 'message' => '指定的替换规则不存在']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '替换规则文件不存在']);
        exit;
    }
}

// 计算过期时间
$expiryTime = null;
if ($expiry > 0) {
    $expiryTime = time() + $expiry;
}

// 保存令牌
$tokens[$token] = [
    'file' => $file,
    'replacement' => $replacement,
    'created' => time(),
    'expiry' => $expiryTime
];

// 写入令牌文件
if (file_put_contents($accessTokensFile, json_encode($tokens, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => '保存令牌失败']);
}
?>