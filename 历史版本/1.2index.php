<?php
// 骰子游戏 记录表 - 单文件精简版统计程序

// 配置信息
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('DATA_FILE', __DIR__ . '/data.json');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// 初始化会话
session_start();

// 确保上传目录存在
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// 验证管理员身份
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// 登录处理
if (isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USERNAME && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 登出处理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 数据操作函数
function loadData() {
    return file_exists(DATA_FILE) ? 
        json_decode(file_get_contents(DATA_FILE), true) ?: [] : [];
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 添加记录处理
$addError = '';
if (isAdmin() && isset($_POST['add_record'])) {
    try {
        $records = loadData();
        
        $newRecord = [
            'id' => uniqid(),
            'publish_time' => date('Y-m-d H:i:s'),
            'name' => $_POST['name'],
            'complete_time' => $_POST['complete_time'] ?: date('Y-m-d H:i:s'),
            'exposure' => isset($_POST['exposure']) ? 1 : 0,
            'media' => ''
        ];
        
        // 处理文件上传
        if (!empty($_FILES['media']['name'])) {
            $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
            $targetFile = UPLOAD_DIR . $fileName;
            $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('不支持的文件类型');
            }
            
            if (!move_uploaded_file($_FILES['media']['tmp_name'], $targetFile)) {
                throw new Exception('文件上传失败');
            }
            
            $newRecord['media'] = $fileName;
        }
        
        array_unshift($records, $newRecord);
        saveData($records);
        
        $_SESSION['success_message'] = '记录添加成功';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $addError = $e->getMessage();
        error_log('添加记录错误: ' . $e->getMessage());
    }
}

// 删除记录处理
if (isAdmin() && isset($_GET['delete'])) {
    try {
        $records = loadData();
        $id = $_GET['delete'];
        
        foreach ($records as $key => $record) {
            if ($record['id'] === $id) {
                if (!empty($record['media'])) {
                    $filePath = UPLOAD_DIR . $record['media'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                unset($records[$key]);
                break;
            }
        }
        
        saveData($records);
        $_SESSION['success_message'] = '记录删除成功';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        error_log('删除记录错误: ' . $e->getMessage());
        $_SESSION['error_message'] = '删除记录失败';
        header('Location: index.php');
        exit;
    }
}

// 获取记录数据
$records = loadData();
$recentRecords = array_slice($records, 0, 20);

// 格式化星期几
function formatWeekday($dateString) {
    $weekday = ['日', '一', '二', '三', '四', '五', '六'];
    return '星期' . $weekday[date('w', strtotime($dateString))];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>骰子游戏 记录表</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                        accent: '#EC4899',
                        neutral: '#1F2937',
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
            .backdrop-blur-sm {
                backdrop-filter: blur(4px);
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .text-shadow {
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .img-cover {
                object-fit: cover;
            }
            .img-contain {
                object-fit: contain;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- 顶部导航 -->
    <header class="bg-white shadow-md sticky top-0 z-50 transition-all duration-300">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fa fa-dice text-primary text-2xl"></i>
                <h1 class="text-xl font-bold text-neutral">骰子游戏 记录表</h1>
            </div>
            
            <?php if (isAdmin()): ?>
            <div class="flex items-center space-x-4">
                <button id="addRecordBtn" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg shadow transition-all duration-300 flex items-center">
                    <i class="fa fa-plus mr-2"></i> 添加记录
                </button>
                <a href="?logout" class="text-gray-600 hover:text-red-500 transition-colors duration-300">
                    <i class="fa fa-sign-out mr-1"></i> 退出
                </a>
            </div>
            <?php else: ?>
            <button id="loginBtn" class="bg-gray-200 hover:bg-gray-300 text-neutral px-4 py-2 rounded-lg shadow transition-all duration-300 flex items-center">
                <i class="fa fa-user mr-2"></i> 登录管理
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center animate-fade-in">
        <i class="fa fa-check-circle mr-2"></i>
        <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
        <button onclick="this.parentElement.style.display='none'" class="ml-4 text-white hover:text-gray-200">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center animate-fade-in">
        <i class="fa fa-exclamation-circle mr-2"></i>
        <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
        <button onclick="this.parentElement.style.display='none'" class="ml-4 text-white hover:text-gray-200">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>

    <!-- 主要内容 -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <!-- 记录列表 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($recentRecords as $record): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1">
                <div class="p-5">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <span class="text-sm text-gray-500"><?php echo date('Y年m月d日', strtotime($record['publish_time'])); ?></span>
                            <span class="text-sm text-gray-400 ml-2"><?php echo formatWeekday($record['publish_time']); ?></span>
                        </div>
                        <?php if ($record['exposure']): ?>
                        <span class="bg-accent/10 text-accent text-xs px-2 py-1 rounded-full">曝光</span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="font-semibold text-lg mb-3 leading-relaxed break-words">
                        <?php echo nl2br(htmlspecialchars($record['name'])); ?>
                    </h3>
                    
                    <?php if (isAdmin() && !empty($record['media'])): ?>
                    <div class="mb-4 rounded-lg overflow-hidden">
                        <?php
                        $fileExt = strtolower(pathinfo($record['media'], PATHINFO_EXTENSION));
                        $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                        $isVideo = in_array($fileExt, ['mp4', 'webm']);
                        ?>
                        
                        <?php if ($isImage): ?>
                        <div class="relative cursor-pointer group" onclick="openFullscreen('<?php echo htmlspecialchars($record['media']); ?>', 'image')">
                            <img src="uploads/<?php echo htmlspecialchars($record['media']); ?>" alt="任务图片" class="w-full h-48 img-contain transition-transform duration-500 group-hover:scale-105">
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i class="fa fa-search-plus text-white text-3xl"></i>
                            </div>
                        </div>
                        <?php elseif ($isVideo): ?>
                        <div class="relative">
                            <video controls poster="https://picsum.photos/400/225?random=<?php echo $record['id']; ?>" class="w-full h-48 object-cover">
                                <source src="uploads/<?php echo htmlspecialchars($record['media']); ?>" type="video/<?php echo $fileExt; ?>">
                                您的浏览器不支持视频播放
                            </video>
                            <button class="absolute bottom-2 right-2 bg-black/60 hover:bg-black/80 text-white p-2 rounded-full transition-all duration-300" onclick="openFullscreen('<?php echo htmlspecialchars($record['media']); ?>', 'video')">
                                <i class="fa fa-expand"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-center text-sm text-gray-500">
                        <span>完成时间: <?php echo date('Y-m-d', strtotime($record['complete_time'])); ?></span>
                        
                        <?php if (isAdmin()): ?>
                        <div class="flex space-x-2">
                            <button class="text-gray-400 hover:text-blue-500 transition-colors duration-300" onclick="shareRecord('<?php echo htmlspecialchars(json_encode($record)); ?>')">
                                <i class="fa fa-share-alt"></i>
                            </button>
                            <a href="?download=<?php echo htmlspecialchars($record['media']); ?>" class="text-gray-400 hover:text-green-500 transition-colors duration-300">
                                <i class="fa fa-download"></i>
                            </a>
                            <a href="?delete=<?php echo htmlspecialchars($record['id']); ?>" class="text-gray-400 hover:text-red-500 transition-colors duration-300" onclick="return confirm('确定要删除这条记录吗?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 无记录提示 -->
        <?php if (empty($records)): ?>
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <i class="fa fa-folder-open-o text-5xl mb-4"></i>
            <p class="text-lg">暂无记录</p>
            <?php if (isAdmin()): ?>
            <p class="text-sm mt-2">点击上方"添加记录"按钮来创建第一条记录</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white border-t border-gray-200 py-4">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            <p>骰子游戏 记录表 &copy; 2025</p>
        </div>
    </footer>

    <!-- 登录模态框 -->
    <div id="loginModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all duration-300 scale-95 opacity-0" id="loginModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-neutral">管理员登录</h3>
                <button id="closeLoginBtn" class="text-gray-400 hover:text-gray-600">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="post" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                    <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300" required>
                </div>
                
                <?php if (isset($loginError)): ?>
                <div class="text-red-500 text-sm"><?php echo $loginError; ?></div>
                <?php endif; ?>
                
                <button type="submit" name="login" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2.5 px-4 rounded-lg shadow transition-all duration-300">
                    登录
                </button>
            </form>
        </div>
    </div>

    <!-- 添加记录模态框 -->
    <div id="addRecordModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 transform transition-all duration-300 scale-95 opacity-0" id="addRecordModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-neutral">添加记录</h3>
                <button id="closeAddRecordBtn" class="text-gray-400 hover:text-gray-600">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">任务名称</label>
                    <textarea id="name" name="name" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 resize-none" placeholder="输入任务名称..." required></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="publish_time" class="block text-sm font-medium text-gray-700 mb-1">发布时间</label>
                        <input type="datetime-local" id="publish_time" name="publish_time" value="<?php echo date('Y-m-d\TH:i'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300">
                    </div>
                    
                    <div>
                        <label for="complete_time" class="block text-sm font-medium text-gray-700 mb-1">完成时间</label>
                        <input type="datetime-local" id="complete_time" name="complete_time" value="<?php echo date('Y-m-d\TH:i'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">是否触发曝光</label>
                    <div class="flex items-center">
                        <input type="checkbox" id="exposure" name="exposure" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="exposure" class="ml-2 block text-sm text-gray-700">是</label>
                    </div>
                </div>
                
                <div>
                    <label for="media" class="block text-sm font-medium text-gray-700 mb-1">上传图片或视频</label>
                    <div class="relative">
                        <input type="file" id="media" name="media" accept="image/*,video/*" class="hidden" onchange="previewMedia(event)">
                        <button type="button" onclick="document.getElementById('media').click()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-4 rounded-lg border border-gray-300 transition-all duration-300 flex items-center justify-center">
                            <i class="fa fa-upload mr-2"></i> 选择文件
                        </button>
                        
                        <?php if (isset($addError)): ?>
                        <div class="text-red-500 text-sm mt-1"><?php echo $addError; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="mediaPreview" class="hidden">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">预览</h4>
                        <div id="previewContent" class="w-full aspect-video bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                            <!-- 预览内容将在这里动态显示 -->
                        </div>
                        <button type="button" onclick="clearPreview()" class="mt-2 text-red-500 text-sm hover:underline">
                            <i class="fa fa-times-circle mr-1"></i> 清除预览
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="cancelAddRecordBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-300">
                        取消
                    </button>
                    <button type="submit" name="add_record" class="px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg shadow transition-all duration-300">
                        保存记录
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 全屏查看模态框 -->
    <div id="fullscreenModal" class="fixed inset-0 bg-black z-50 flex items-center justify-center hidden">
        <button id="closeFullscreenBtn" class="absolute top-6 right-6 text-white text-3xl hover:text-gray-300 transition-colors duration-300 z-10">
            <i class="fa fa-times"></i>
        </button>
        <div class="relative w-full max-w-4xl mx-4">
            <div id="fullscreenContent" class="w-full aspect-video bg-gray-900 rounded-lg overflow-hidden flex items-center justify-center">
                <!-- 全屏内容将在这里动态显示 -->
            </div>
        </div>
    </div>

    <!-- 分享模态框 -->
    <div id="shareModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all duration-300 scale-95 opacity-0" id="shareModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-neutral">分享记录</h3>
                <button id="closeShareBtn" class="text-gray-400 hover:text-gray-600">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">分享链接</label>
                    <div class="relative">
                        <input type="text" id="shareLink" value="" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 bg-gray-50">
                        <button onclick="copyShareLink()" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-primary hover:bg-primary/90 text-white px-3 py-1 rounded-lg text-sm transition-all duration-300">
                            复制
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">分享到</label>
                    <div class="flex space-x-4">
                        <a href="#" onclick="shareTo('weixin'); return false;" class="flex flex-col items-center justify-center p-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <i class="fa fa-weixin text-green-500 text-2xl"></i>
                            <span class="text-xs mt-1">微信</span>
                        </a>
                        <a href="#" onclick="shareTo('weibo'); return false;" class="flex flex-col items-center justify-center p-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <i class="fa fa-weibo text-red-500 text-2xl"></i>
                            <span class="text-xs mt-1">微博</span>
                        </a>
                        <a href="#" onclick="shareTo('qq'); return false;" class="flex flex-col items-center justify-center p-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <i class="fa fa-qq text-blue-400 text-2xl"></i>
                            <span class="text-xs mt-1">QQ</span>
                        </a>
                        <a href="#" onclick="shareTo('email'); return false;" class="flex flex-col items-center justify-center p-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <i class="fa fa-envelope text-gray-500 text-2xl"></i>
                            <span class="text-xs mt-1">邮件</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 模态框动画效果
        document.addEventListener('DOMContentLoaded', function() {
            // 登录模态框
            const loginBtn = document.getElementById('loginBtn');
            const loginModal = document.getElementById('loginModal');
            const loginModalContent = document.getElementById('loginModalContent');
            const closeLoginBtn = document.getElementById('closeLoginBtn');
            
            if (loginBtn) {
                loginBtn.addEventListener('click', () => {
                    loginModal.classList.remove('hidden');
                    setTimeout(() => {
                        loginModalContent.classList.remove('scale-95', 'opacity-0');
                        loginModalContent.classList.add('scale-100', 'opacity-100');
                    }, 10);
                });
            }
            
            function closeLoginModal() {
                loginModalContent.classList.remove('scale-100', 'opacity-100');
                loginModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    loginModal.classList.add('hidden');
                }, 300);
            }
            
            if (closeLoginBtn) {
                closeLoginBtn.addEventListener('click', closeLoginModal);
            }
            
            // 添加记录模态框
            const addRecordBtn = document.getElementById('addRecordBtn');
            const addRecordModal = document.getElementById('addRecordModal');
            const addRecordModalContent = document.getElementById('addRecordModalContent');
            const closeAddRecordBtn = document.getElementById('closeAddRecordBtn');
            const cancelAddRecordBtn = document.getElementById('cancelAddRecordBtn');
            
            if (addRecordBtn) {
                addRecordBtn.addEventListener('click', () => {
                    addRecordModal.classList.remove('hidden');
                    setTimeout(() => {
                        addRecordModalContent.classList.remove('scale-95', 'opacity-0');
                        addRecordModalContent.classList.add('scale-100', 'opacity-100');
                    }, 10);
                });
            }
            
            function closeAddRecordModal() {
                addRecordModalContent.classList.remove('scale-100', 'opacity-100');
                addRecordModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    addRecordModal.classList.add('hidden');
                    document.getElementById('mediaPreview').classList.add('hidden');
                    document.getElementById('media').value = '';
                }, 300);
            }
            
            if (closeAddRecordBtn) {
                closeAddRecordBtn.addEventListener('click', closeAddRecordModal);
            }
            
            if (cancelAddRecordBtn) {
                cancelAddRecordBtn.addEventListener('click', closeAddRecordModal);
            }
            
            // 媒体预览
            window.previewMedia = function(event) {
                const file = event.target.files[0];
                if (!file) return;
                
                const previewContent = document.getElementById('previewContent');
                const mediaPreview = document.getElementById('mediaPreview');
                
                previewContent.innerHTML = '';
                mediaPreview.classList.remove('hidden');
                
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.alt = '预览图片';
                    img.classList.add('w-full', 'h-full', 'img-contain');
                    previewContent.appendChild(img);
                } else if (fileType.startsWith('video/')) {
                    const video = document.createElement('video');
                    video.controls = true;
                    video.src = URL.createObjectURL(file);
                    video.poster = 'https://picsum.photos/400/225?random=preview';
                    video.classList.add('w-full', 'h-full', 'object-cover');
                    previewContent.appendChild(video);
                }
            }
            
            window.clearPreview = function() {
                document.getElementById('mediaPreview').classList.add('hidden');
                document.getElementById('media').value = '';
            }
            
            // 全屏查看
            const fullscreenModal = document.getElementById('fullscreenModal');
            const fullscreenContent = document.getElementById('fullscreenContent');
            const closeFullscreenBtn = document.getElementById('closeFullscreenBtn');
            
            window.openFullscreen = function(mediaFile, type) {
                fullscreenContent.innerHTML = '';
                fullscreenModal.classList.remove('hidden');
                
                if (type === 'image') {
                    const img = document.createElement('img');
                    img.src = 'uploads/' + mediaFile;
                    img.alt = '任务图片';
                    img.classList.add('max-w-full', 'max-h-screen', 'img-contain');
                    fullscreenContent.appendChild(img);
                } else if (type === 'video') {
                    const video = document.createElement('video');
                    video.controls = true;
                    video.autoplay = true;
                    video.src = 'uploads/' + mediaFile;
                    video.classList.add('w-full', 'h-auto');
                    fullscreenContent.appendChild(video);
                }
                
                document.body.style.overflow = 'hidden';
            }
            
            function closeFullscreen() {
                fullscreenModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            
            if (closeFullscreenBtn) {
                closeFullscreenBtn.addEventListener('click', closeFullscreen);
            }
            
            if (fullscreenModal) {
                fullscreenModal.addEventListener('click', (e) => {
                    if (e.target === fullscreenModal) {
                        closeFullscreen();
                    }
                });
            }
            
            // 分享功能
            const shareModal = document.getElementById('shareModal');
            const shareModalContent = document.getElementById('shareModalContent');
            const closeShareBtn = document.getElementById('closeShareBtn');
            const shareLink = document.getElementById('shareLink');
            
            window.shareRecord = function(recordJson) {
                const record = JSON.parse(recordJson);
                const baseUrl = window.location.origin + window.location.pathname;
                const recordUrl = `${baseUrl}?view=${record.id}`;
                
                shareLink.value = recordUrl;
                
                shareModal.classList.remove('hidden');
                setTimeout(() => {
                    shareModalContent.classList.remove('scale-95', 'opacity-0');
                    shareModalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
            
            function closeShareModal() {
                shareModalContent.classList.remove('scale-100', 'opacity-100');
                shareModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    shareModal.classList.add('hidden');
                }, 300);
            }
            
            if (closeShareBtn) {
                closeShareBtn.addEventListener('click', closeShareModal);
            }
            
            window.copyShareLink = function() {
                shareLink.select();
                document.execCommand('copy');
                
                const button = document.querySelector('#shareLink + button');
                const originalText = button.textContent;
                button.textContent = '已复制';
                
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            }
            
            window.shareTo = function(platform) {
                const url = shareLink.value;
                let shareUrl = '';
                
                switch (platform) {
                    case 'weixin':
                        alert('请使用微信内置浏览器分享此链接');
                        break;
                    case 'weibo':
                        shareUrl = `https://service.weibo.com/share/share.php?url=${encodeURIComponent(url)}&title=骰子游戏记录分享&appkey=`;
                        window.open(shareUrl, '_blank');
                        break;
                    case 'qq':
                        shareUrl = `https://connect.qq.com/widget/shareqq/index.html?url=${encodeURIComponent(url)}&title=骰子游戏记录分享&source=`;
                        window.open(shareUrl, '_blank');
                        break;
                    case 'email':
                        shareUrl = `mailto:?subject=骰子游戏记录分享&body=${encodeURIComponent(url)}`;
                        window.open(shareUrl, '_blank');
                        break;
                }
                
                closeShareModal();
            }
            
            // 滚动时导航栏效果
            window.addEventListener('scroll', () => {
                const header = document.querySelector('header');
                if (header) {
                    if (window.scrollY > 10) {
                        header.classList.add('py-2');
                        header.classList.remove('py-3');
                    } else {
                        header.classList.add('py-3');
                        header.classList.remove('py-2');
                    }
                }
            });
            
            // 自动关闭消息提示
            setTimeout(() => {
                const messages = document.querySelectorAll('.fixed.top-20');
                messages.forEach(msg => {
                    msg.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>    
