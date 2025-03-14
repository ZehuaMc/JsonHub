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
$settingsFile = $config['settingsFile'];

// 如果是POST请求，处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 检查是否是重置设置
    if (isset($input['settings']['reset']) && $input['settings']['reset'] === true) {
        // 重置为默认设置
        $settings = [
            'defaultExpire' => 86400,
            'historyCount' => 10,
            'logDays' => 30,
            'theme' => 'light',
            'editorTheme' => 'default',
            'accessLogEnabled' => true,
            'notificationsEnabled' => true
        ];
        
        if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => '重置设置失败']);
            exit;
        }
    }
    
    // 读取当前设置
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (!is_array($settings)) {
            $settings = [];
        }
    }
    
    // 更新设置
    if (isset($input['settings'])) {
        // 处理密码更改
        if (isset($input['settings']['password']) && !empty($input['settings']['password'])) {
            // 创建新的密码哈希
            $newPasswordHash = password_hash($input['settings']['password'], PASSWORD_BCRYPT);
            
            // 创建一个新的配置数组
            $newConfig = $config;
            $newConfig['loginPasswordHash'] = $newPasswordHash;
            
            // 创建新的配置文件内容
            $configContent = "<?php\n/**\n * 配置文件\n */\nreturn " . var_export($newConfig, true) . ";\n?>";
            
            // 保存到临时文件
            $tempFile = tempnam(sys_get_temp_dir(), 'cfg');
            if (file_put_contents($tempFile, $configContent)) {
                // 验证临时文件内容是否有效
                $testConfig = include $tempFile;
                if (is_array($testConfig) && isset($testConfig['loginPasswordHash'])) {
                    // 备份原始配置文件
                    $backupFile = 'config_backup_' . date('YmdHis') . '.php';
                    copy('config.php', $backupFile);
                    
                    // 将临时文件移动到目标位置
                    if (!rename($tempFile, 'config.php')) {
                        // 如果移动失败，尝试直接写入
                        if (!file_put_contents('config.php', $configContent)) {
                            // 删除临时文件
                            @unlink($tempFile);
                            echo json_encode(['success' => false, 'message' => '更新密码失败：无法写入配置文件']);
                            exit;
                        }
                    }
                } else {
                    // 删除临时文件
                    @unlink($tempFile);
                    echo json_encode(['success' => false, 'message' => '更新密码失败：生成的配置文件无效']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => '更新密码失败：无法创建临时文件']);
                exit;
            }
        }
        
        // 移除密码字段，不保存到设置文件
        unset($input['settings']['password']);
        
        // 更新其他设置
        foreach ($input['settings'] as $key => $value) {
            $settings[$key] = $value;
        }
        
        // 保存设置
        if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '保存设置失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '无效的设置数据']);
    }
    
    exit;
}

// GET请求，返回当前设置
if (!file_exists($settingsFile)) {
    // 创建默认设置
    $settings = [
        'defaultExpire' => 86400,
        'historyCount' => 10,
        'logDays' => 30,
        'theme' => 'light',
        'editorTheme' => 'default',
        'accessLogEnabled' => true,
        'notificationsEnabled' => true
    ];
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
} else {
    $settings = json_decode(file_get_contents($settingsFile), true);
    
    if (!is_array($settings)) {
        $settings = [
            'defaultExpire' => 86400,
            'historyCount' => 10,
            'logDays' => 30,
            'theme' => 'light',
            'editorTheme' => 'default',
            'accessLogEnabled' => true,
            'notificationsEnabled' => true
        ];
        
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }
}

echo json_encode(['success' => true, 'settings' => $settings]);
?>