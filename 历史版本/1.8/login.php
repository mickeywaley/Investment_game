<?php
session_start();
require_once 'functions.php';

// 如果用户已登录，重定向到首页
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        $users = getUsers();
        $user = null;
        
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $user = $u;
                break;
            }
        }
        
        if ($user && password_verify($password, $user['password'])) {
            // 登录成功
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 骰子游戏任务记录表</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        dark: '#1F2937',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .card-shadow {
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
            .btn-primary {
                @apply bg-primary text-white font-medium py-2 px-4 rounded-lg shadow-md hover:bg-primary/90 transition-all duration-200;
            }
            .form-input {
                @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all duration-200;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-primary text-white">
                <div class="flex items-center">
                    <i class="fa fa-dice text-2xl mr-2"></i>
                    <h2 class="text-xl font-bold">骰子游戏任务记录表</h2>
                </div>
            </div>
            
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">登录</h3>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                        <input type="text" id="username" name="username" class="form-input" placeholder="输入用户名" value="admin">
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="输入密码" value="admin123">
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">
                            <i class="fa fa-sign-in mr-1"></i> 登录
                        </button>
                    </div>
                </form>
                
                <p class="text-sm text-gray-500 mt-4 text-center">
                    默认账号: <span class="font-medium">admin</span><br>
                    默认密码: <span class="font-medium">admin123</span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
    