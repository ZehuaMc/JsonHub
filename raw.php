<?php
// 获取配置文件
$config = include 'config.php';
$jsonFilesDir = $config['jsonFilesDir'];
$replacementsFile = $config['replacementsFile'];
$accessTokensFile = $config['accessTokensFile'];
$accessLogsFile = $config['accessLogsFile'];

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 获取请求参数
$file = isset($_GET['file']) ? basename($_GET['file']) : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$replace = isset($_GET['replace']) ? $_GET['replace'] : '';

// 检查文件参数
if (empty($file)) {
    $isAllFiles = true;
} else {
    $isAllFiles = false;
}

// 如果不是空文件名，确认文件是否存在
if (!$isAllFiles) {
    $filePath = $jsonFilesDir . $file;
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => '文件不存在: ' . $file]);
        
        // 记录失败的访问日志
        logAccess($file, $token, $replace, 'failed', '文件不存在');
        exit;
    }
}

// 检查是否提供了有效的访问令牌
$authorized = false;
$tokenDetails = null;

if (!empty($token) && file_exists($accessTokensFile)) {
    $tokens = json_decode(file_get_contents($accessTokensFile), true);
    
    if (isset($tokens[$token])) {
        $tokenData = $tokens[$token];
        
        // 检查令牌是否过期
        if ($tokenData['expiry'] === null || $tokenData['expiry'] > time()) {
            // 检查文件是否匹配
            if (empty($tokenData['file']) || $tokenData['file'] === $file) {
                $authorized = true;
                
                // 如果令牌指定了替换规则，优先使用
                if (!empty($tokenData['replacement']) && empty($replace)) {
                    $replace = $tokenData['replacement'];
                }
                
                $tokenDetails = $tokenData;
            }
        } else {
            // 令牌已过期，删除
            unset($tokens[$token]);
            file_put_contents($accessTokensFile, json_encode($tokens, JSON_PRETTY_PRINT));
            
            http_response_code(403);
            echo json_encode(['error' => '访问被拒绝: 令牌已过期']);
            
            // 记录失败的访问日志
            logAccess($file, $token, $replace, 'failed', '令牌已过期');
            exit;
        }
    }
}

// 如果未授权，返回错误
if (!$authorized) {
    http_response_code(403);
    echo json_encode(['error' => '访问被拒绝: 请提供有效的访问令牌']);
    
    // 记录失败的访问日志
    logAccess($file, $token, $replace, 'failed', '未提供有效的访问令牌');
    exit;
}

// 如果是请求所有文件，但使用令牌，需要检查令牌是否允许访问所有文件
if ($isAllFiles && !empty($tokenDetails) && !empty($tokenDetails['file'])) {
    $file = $tokenDetails['file'];
    $isAllFiles = false;
}

// 如果仍然是所有文件请求，则不支持
if ($isAllFiles) {
    http_response_code(400);
    echo json_encode(['error' => '请求错误: 必须指定文件名']);
    
    // 记录失败的访问日志
    logAccess('*', $token, $replace, 'failed', '请求必须指定文件名');
    exit;
}

// 读取JSON文件内容
$filePath = $jsonFilesDir . $file;
$content = file_get_contents($filePath);
$data = json_decode($content, true);

// 检查JSON是否有效
if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => '服务器错误: 文件内容不是有效的JSON格式']);
    
    // 记录失败的访问日志
    logAccess($file, $token, $replace, 'failed', '文件内容不是有效的JSON格式');
    exit;
}

// 应用替换规则 - 支持多个规则，用逗号分隔
if (!empty($replace) && file_exists($replacementsFile)) {
    $replacements = json_decode(file_get_contents($replacementsFile), true);
    
    // 拆分多个替换规则
    $replaceRules = explode(',', $replace);
    
    foreach ($replaceRules as $rule) {
        $rule = trim($rule);
        if (!empty($rule) && isset($replacements[$rule])) {
            $replacement = $replacements[$rule];
            $path = $replacement['path'];
            $newContent = $replacement['content'];
            
            // 解析路径并应用替换
            $pathSegments = explode('.', $path);
            $target = &$data;
            
            foreach ($pathSegments as $segment) {
                if (!isset($target[$segment])) {
                    $target[$segment] = [];
                }
                $target = &$target[$segment];
            }
            
            $target = $newContent;
        }
    }
}

// 记录访问日志
logAccess($file, $token, $replace, 'success');

// 输出处理后的JSON
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * 记录访问日志
 * 
 * @param string $file 文件名
 * @param string $token 访问令牌
 * @param string $replacement 替换规则
 * @param string $status 访问状态 (success/failed)
 * @param string $message 错误信息（仅在失败时）
 */
function logAccess($file, $token, $replacement, $status, $message = '') {
    global $config, $accessLogsFile;
    
    // 检查是否应该记录日志
    if (file_exists($config['settingsFile'])) {
        $settings = json_decode(file_get_contents($config['settingsFile']), true);
        $logEnabled = isset($settings['accessLogEnabled']) ? $settings['accessLogEnabled'] : true;
        
        if (!$logEnabled) {
            return;
        }
    }
    
    $logEntry = [
        'time' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'file' => $file,
        'replacement' => $replacement,
        'token' => $token,
        'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        'status' => $status
    ];
    
    if ($status === 'failed' && !empty($message)) {
        $logEntry['message'] = $message;
    }
    
    // 读取现有日志
    $logs = [];
    if (file_exists($accessLogsFile)) {
        $logs = json_decode(file_get_contents($accessLogsFile), true);
        if (!is_array($logs)) {
            $logs = [];
        }
    }
    
    // 添加新日志并保存
    $logs[] = $logEntry;
    file_put_contents($accessLogsFile, json_encode($logs, JSON_PRETTY_PRINT));
}
?>