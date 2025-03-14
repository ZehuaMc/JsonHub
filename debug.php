<?php
session_start();

// 检查认证状态，如果未登录则拒绝访问
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if (isset($_GET['json']) && $_GET['json'] == 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '未授权访问，请先登录']);
        exit;
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>未授权访问</h1>";
        echo "<p>您需要先<a href='index.html'>登录</a>才能访问此页面。</p>";
        exit;
    }
}

header('Content-Type: application/json');

// JSON响应模式
$jsonResponse = isset($_GET['json']) && $_GET['json'] == 1;

// 如果请求是初始化系统
if (isset($_GET['init_system']) && $_GET['init_system'] == 1) {
    $result = initializeSystem();
    
    if ($jsonResponse) {
        echo json_encode($result);
        exit;
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>系统初始化结果</h1>";
        echo "<div style='color: " . ($result['success'] ? "green" : "red") . "'>";
        echo $result['message'];
        echo "</div>";
        echo "<p><a href='debug.php'>返回调试页面</a></p>";
        exit;
    }
}

// 如果需要JSON响应
if ($jsonResponse) {
    // 获取配置文件
    $config = include 'config.php';
    $jsonFilesDir = $config['jsonFilesDir'];
    $historyDir = $config['historyDir'];
    $replacementsFile = $config['replacementsFile'];
    $accessTokensFile = $config['accessTokensFile'];
    $accessLogsFile = $config['accessLogsFile'];
    $settingsFile = $config['settingsFile'];
    
    // 收集系统信息
    $system = [
        'phpVersion' => phpversion(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'dataDir' => realpath('./'),
        'time' => date('Y-m-d H:i:s'),
        'filesCount' => 0,
        'historyCount' => 0,
        'replacementsCount' => 0,
        'tokensCount' => 0,
        'logsCount' => 0,
        'authStatus' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true ? "已登录" : "未登录"
    ];
    
    // 检查文件和目录
    $dirs = [
        'jsonFiles' => [
            'path' => $jsonFilesDir,
            'exists' => file_exists($jsonFilesDir),
            'isDir' => file_exists($jsonFilesDir) && is_dir($jsonFilesDir),
            'readable' => file_exists($jsonFilesDir) && is_readable($jsonFilesDir),
            'writable' => file_exists($jsonFilesDir) && is_writable($jsonFilesDir)
        ],
        'history' => [
            'path' => $historyDir,
            'exists' => file_exists($historyDir),
            'isDir' => file_exists($historyDir) && is_dir($historyDir),
            'readable' => file_exists($historyDir) && is_readable($historyDir),
            'writable' => file_exists($historyDir) && is_writable($historyDir)
        ]
    ];
    
    $files = [
        'replacements' => [
            'path' => $replacementsFile,
            'exists' => file_exists($replacementsFile),
            'readable' => file_exists($replacementsFile) && is_readable($replacementsFile),
            'writable' => file_exists($replacementsFile) && is_writable($replacementsFile),
            'size' => file_exists($replacementsFile) ? filesize($replacementsFile) : 0
        ],
        'tokens' => [
            'path' => $accessTokensFile,
            'exists' => file_exists($accessTokensFile),
            'readable' => file_exists($accessTokensFile) && is_readable($accessTokensFile),
            'writable' => file_exists($accessTokensFile) && is_writable($accessTokensFile),
            'size' => file_exists($accessTokensFile) ? filesize($accessTokensFile) : 0
        ],
        'logs' => [
            'path' => $accessLogsFile,
            'exists' => file_exists($accessLogsFile),
            'readable' => file_exists($accessLogsFile) && is_readable($accessLogsFile),
            'writable' => file_exists($accessLogsFile) && is_writable($accessLogsFile),
            'size' => file_exists($accessLogsFile) ? filesize($accessLogsFile) : 0
        ],
        'settings' => [
            'path' => $settingsFile,
            'exists' => file_exists($settingsFile),
            'readable' => file_exists($settingsFile) && is_readable($settingsFile),
            'writable' => file_exists($settingsFile) && is_writable($settingsFile),
            'size' => file_exists($settingsFile) ? filesize($settingsFile) : 0
        ]
    ];
    
    // 计算各种文件数量
    if ($dirs['jsonFiles']['exists'] && $dirs['jsonFiles']['isDir']) {
        $jsonFiles = array_filter(scandir($jsonFilesDir), function($file) {
            return $file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'json';
        });
        $system['filesCount'] = count($jsonFiles);
    }
    
    if ($dirs['history']['exists'] && $dirs['history']['isDir']) {
        $historyFiles = array_filter(scandir($historyDir), function($file) {
            return $file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'json';
        });
        $system['historyCount'] = count($historyFiles);
    }
    
    if ($files['replacements']['exists'] && $files['replacements']['readable']) {
        $replacements = json_decode(file_get_contents($replacementsFile), true);
        $system['replacementsCount'] = is_array($replacements) ? count($replacements) : 0;
    }
    
    if ($files['tokens']['exists'] && $files['tokens']['readable']) {
        $tokens = json_decode(file_get_contents($accessTokensFile), true);
        $system['tokensCount'] = is_array($tokens) ? count($tokens) : 0;
    }
    
    if ($files['logs']['exists'] && $files['logs']['readable']) {
        $logs = json_decode(file_get_contents($accessLogsFile), true);
        $system['logsCount'] = is_array($logs) ? count($logs) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'system' => $system,
        'directories' => $dirs,
        'files' => $files
    ]);
    exit;
}

// 如果不是JSON响应，显示HTML调试页面
header('Content-Type: text/html; charset=utf-8');

echo "<h1>JSON配置管理平台 - 调试信息</h1>";

// 检查PHP版本
echo "<h2>系统信息</h2>";
echo "PHP版本: " . phpversion() . "<br>";
echo "操作系统: " . PHP_OS . "<br>";
echo "Web服务器: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "当前时间: " . date('Y-m-d H:i:s') . "<br><br>";

// 检查会话状态
echo "<h2>会话状态</h2>";
echo "会话ID: " . session_id() . "<br>";
echo "会话状态: " . (session_status() == PHP_SESSION_ACTIVE ? '活跃' : '非活跃') . "<br>";
echo "会话保存路径: " . session_save_path() . "<br>";
echo "认证状态: " . (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true ? "已登录" : "未登录") . "<br><br>";

// 检查配置
echo "<h2>配置检查</h2>";
if (file_exists('config.php')) {
    $config = include 'config.php';
    echo "配置文件: 存在<br>";
    echo "登录密码哈希: " . (isset($config['loginPasswordHash']) ? '已设置' : '未设置') . "<br>";
    echo "JSON文件目录: " . (isset($config['jsonFilesDir']) ? $config['jsonFilesDir'] : '未设置') . "<br>";
    echo "历史版本目录: " . (isset($config['historyDir']) ? $config['historyDir'] : '未设置') . "<br>";
    echo "替换规则文件: " . (isset($config['replacementsFile']) ? $config['replacementsFile'] : '未设置') . "<br>";
    echo "访问令牌文件: " . (isset($config['accessTokensFile']) ? $config['accessTokensFile'] : '未设置') . "<br>";
    echo "访问日志文件: " . (isset($config['accessLogsFile']) ? $config['accessLogsFile'] : '未设置') . "<br>";
    echo "设置文件: " . (isset($config['settingsFile']) ? $config['settingsFile'] : '未设置') . "<br><br>";
} else {
    echo "配置文件不存在!<br><br>";
}

// 检查目录和文件权限
echo "<h2>目录和文件权限</h2>";
$dirsToCheck = [
    'json_files' => isset($config['jsonFilesDir']) ? $config['jsonFilesDir'] : 'json_files/',
    'history' => isset($config['historyDir']) ? $config['historyDir'] : 'history/'
];

foreach ($dirsToCheck as $name => $dir) {
    if (!file_exists($dir)) {
        echo "$name ($dir): 不存在 <a href='?create_dir=$name'>创建</a><br>";
    } elseif (!is_dir($dir)) {
        echo "$name ($dir): 存在但不是目录!<br>";
    } else {
        echo "$name ($dir): 存在<br>";
        echo "- 可读: " . (is_readable($dir) ? '是' : '否') . "<br>";
        echo "- 可写: " . (is_writable($dir) ? '是' : '否') . "<br>";
        echo "- 权限: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
    }
    echo "<br>";
}

$filesToCheck = [
    'replacements' => isset($config['replacementsFile']) ? $config['replacementsFile'] : 'replacements.json',
    'access_tokens' => isset($config['accessTokensFile']) ? $config['accessTokensFile'] : 'access_tokens.json',
    'access_logs' => isset($config['accessLogsFile']) ? $config['accessLogsFile'] : 'access_logs.json',
    'settings' => isset($config['settingsFile']) ? $config['settingsFile'] : 'settings.json'
];

foreach ($filesToCheck as $name => $file) {
    if (!file_exists($file)) {
        echo "$name ($file): 不存在 <a href='?create_file=$name'>创建</a><br>";
    } else {
        echo "$name ($file): 存在<br>";
        echo "- 可读: " . (is_readable($file) ? '是' : '否') . "<br>";
        echo "- 可写: " . (is_writable($file) ? '是' : '否') . "<br>";
        echo "- 大小: " . filesize($file) . " 字节<br>";
        echo "- 修改时间: " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";
        if (is_readable($file)) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            if ($json === null) {
                echo "- 内容: 不是有效的JSON! <a href='?fix_file=$name'>修复</a><br>";
            } else {
                echo "- JSON有效: 是<br>";
            }
        }
    }
    echo "<br>";
}

// 创建目录或文件（根据GET参数）
if (isset($_GET['create_dir'])) {
    $dirName = $_GET['create_dir'];
    if (isset($dirsToCheck[$dirName])) {
        $dir = $dirsToCheck[$dirName];
        if (mkdir($dir, 0755, true)) {
            echo "<div style='color: green'>目录 $dir 创建成功!</div>";
        } else {
            echo "<div style='color: red'>目录 $dir 创建失败!</div>";
        }
    }
}

if (isset($_GET['create_file'])) {
    $fileName = $_GET['create_file'];
    if (isset($filesToCheck[$fileName])) {
        $file = $filesToCheck[$fileName];
        $content = "{}";
        if (file_put_contents($file, $content)) {
            echo "<div style='color: green'>文件 $file 创建成功!</div>";
        } else {
            echo "<div style='color: red'>文件 $file 创建失败!</div>";
        }
    }
}

if (isset($_GET['fix_file'])) {
    $fileName = $_GET['fix_file'];
    if (isset($filesToCheck[$fileName])) {
        $file = $filesToCheck[$fileName];
        $content = "{}";
        if (file_put_contents($file, $content)) {
            echo "<div style='color: green'>文件 $file 已被修复!</div>";
        } else {
            echo "<div style='color: red'>修复文件 $file 失败!</div>";
        }
    }
}

// 登录测试表单
echo "<h2>登录测试</h2>";
echo "<form method='post' action='debug.php'>";
echo "密码: <input type='password' name='test_password'> ";
echo "<input type='submit' name='test_login' value='测试登录'>";
echo "</form><br>";

// 处理登录测试
if (isset($_POST['test_login']) && isset($_POST['test_password'])) {
    $password = $_POST['test_password'];
    
    if (empty($password)) {
        echo "<div style='color: red'>错误: 请输入密码</div>";
    } elseif (!isset($config['loginPasswordHash'])) {
        echo "<div style='color: red'>错误: 配置文件中未设置密码哈希</div>";
    } elseif (password_verify($password, $config['loginPasswordHash'])) {
        echo "<div style='color: green'>密码验证成功!</div>";
    } else {
        echo "<div style='color: red'>密码验证失败!</div>";
    }
}

// 初始化系统功能
echo "<h2>系统初始化</h2>";
echo "<a href='?init_system=1' class='btn'>初始化系统文件和目录</a><br><br>";

// 显示文件列表
echo "<h2>JSON文件列表</h2>";
$jsonFilesDir = isset($config['jsonFilesDir']) ? $config['jsonFilesDir'] : 'json_files/';
if (file_exists($jsonFilesDir) && is_dir($jsonFilesDir)) {
    $files = scandir($jsonFilesDir);
    $jsonFiles = array_filter($files, function($file) {
        return $file != "." && $file != ".." && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'json';
    });
    
    if (count($jsonFiles) > 0) {
        echo "<ul>";
        foreach ($jsonFiles as $file) {
            $filePath = $jsonFilesDir . $file;
            $fileSize = filesize($filePath);
            $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
            echo "<li>$file - 大小: " . round($fileSize / 1024, 2) . " KB, 修改时间: $fileTime</li>";
        }
        echo "</ul>";
    } else {
        echo "没有找到JSON文件";
    }
} else {
    echo "JSON文件目录不存在或不可读";
}

// 添加刷新按钮
echo "<p><a href='debug.php' class='btn'>刷新页面</a></p>";

echo "<style>
.btn {
  display: inline-block;
  padding: 8px 16px;
  background-color: #0070f3;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  margin-right: 8px;
  font-weight: 500;
}
.btn:hover {
  background-color: #0051a2;
}
</style>";

// 初始化系统函数
function initializeSystem() {
    global $dirsToCheck, $filesToCheck, $config;
    
    $initSuccess = true;
    $messages = [];
    
    // 创建必要的目录
    foreach ($dirsToCheck as $name => $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                $messages[] = "- 目录 $dir 创建成功";
            } else {
                $messages[] = "- 目录 $dir 创建失败";
                $initSuccess = false;
            }
        } else {
            $messages[] = "- 目录 $dir 已存在";
        }
    }
    
    // 创建必要的文件
    foreach ($filesToCheck as $name => $file) {
        if (!file_exists($file)) {
            $defaultContent = "{}";
            if (file_put_contents($file, $defaultContent)) {
                $messages[] = "- 文件 $file 创建成功";
            } else {
                $messages[] = "- 文件 $file 创建失败";
                $initSuccess = false;
            }
        } else {
            // 验证文件内容
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            if ($json === null) {
                if (file_put_contents($file, "{}")) {
                    $messages[] = "- 文件 $file 已修复";
                } else {
                    $messages[] = "- 文件 $file 修复失败";
                    $initSuccess = false;
                }
            } else {
                $messages[] = "- 文件 $file 已存在且格式有效";
            }
        }
    }
    
    if ($initSuccess) {
        $statusMessage = "系统初始化成功!";
        $messages[] = $statusMessage;
    } else {
        $statusMessage = "系统初始化过程中有错误，请查看详情。";
        $messages[] = $statusMessage;
    }
    
    return [
        'success' => $initSuccess,
        'message' => implode('<br>', $messages)
    ];
}
?>