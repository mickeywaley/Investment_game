<?php
session_start();
require_once 'functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// 获取任务ID
$taskId = $_GET['id'] ?? '';
if (empty($taskId)) {
    die('任务ID不能为空');
}

// 获取现有任务
$tasks = getTasks();

// 查找任务
$taskIndex = array_search($taskId, array_column($tasks, 'id'));
if ($taskIndex === false) {
    die('任务不存在');
}

$task = $tasks[$taskIndex];

// 检查权限
if (!$task['creator_id'] == $_SESSION['user']['id'] && !$_SESSION['user']['isAdmin']) {
    die('权限不足');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTaskData = [
        'id' => $taskId,
        'creator_id' => $task['creator_id'],
        'time' => $_POST['time'] ?? date('Y-m-d H:i:s'),
        'name' => $_POST['name'] ?? '',
        'hidden' => isset($_POST['hidden']) ? 1 : 0,
        'content_hidden' => isset($_POST['content_hidden']) ? 1 : 0,
        'exposure' => isset($_POST['exposure']) ? 1 : 0,
        'images' => $task['images'] ?? [],
        'videos' => $task['videos'] ?? []
    ];

    // 处理上传的图片
    if (!empty($_FILES['images']['name'][0])) {
        $uploadedImages = uploadFiles('images', ['jpg', 'jpeg', 'png', 'gif']);
        if ($uploadedImages) {
            $newTaskData['images'] = array_merge($newTaskData['images'], $uploadedImages);
        }
    }

    // 处理上传的视频
    if (!empty($_FILES['videos']['name'][0])) {
        $uploadedVideos = uploadFiles('videos', ['mp4', 'webm', 'ogg']);
        if ($uploadedVideos) {
            $newTaskData['videos'] = array_merge($newTaskData['videos'], $uploadedVideos);
        }
    }

    // 更新任务
    $tasks[$taskIndex] = $newTaskData;

    // 保存任务
    if (saveTasks($tasks)) {
        header('Location: index.php?success=1');
        exit;
    } else {
        $error = '保存任务失败';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑任务 - 骰子游戏任务记录表</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        accent: '#8B5CF6',
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
            .btn-secondary {
                @apply bg-secondary text-white font-medium py-2 px-4 rounded-lg shadow-md hover:bg-secondary/90 transition-all duration-200;
            }
            .btn-danger {
                @apply bg-red-500 text-white font-medium py-2 px-4 rounded-lg shadow-md hover:bg-red-600 transition-all duration-200;
            }
            .form-input {
                @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all duration-200;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
                        <i class="fa fa-dice text-primary text-2xl mr-2"></i>
                        <span class="font-bold text-xl text-dark">骰子游戏任务记录表</span>
                    </a>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="index.php" class="border-primary text-dark border-b-2 px-1 pt-1 inline-flex items-center text-sm font-medium">
                            <i class="fa fa-home mr-1"></i> 首页
                        </a>
                        <?php if (isset($_SESSION['user'])): ?>
                            <a href="add_task.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 border-b-2 px-1 pt-1 inline-flex items-center text-sm font-medium">
                                <i class="fa fa-plus mr-1"></i> 添加任务
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if (isset($_SESSION['user'])): ?>
                        <div class="flex-shrink-0 relative">
                            <button type="button" class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" id="user-menu-button">
                                <span class="sr-only">打开用户菜单</span>
                                <i class="fa fa-user-circle text-xl text-gray-500"></i>
                            </button>
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 py-1 z-50" id="user-menu">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fa fa-user mr-2"></i>
                                    <?php echo htmlspecialchars($_SESSION['user']['username']); ?>
                                    <?php if ($_SESSION['user']['isAdmin']): ?>
                                        <span class="text-xs text-red-500 ml-1">(管理员)</span>
                                    <?php endif; ?>
                                </a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fa fa-sign-out mr-2"></i>退出登录
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn-primary ml-3">
                            <i class="fa fa-sign-in mr-1"></i> 登录
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <main class="flex-grow">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">编辑任务</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="time" class="block text-sm font-medium text-gray-700 mb-1">任务时间</label>
                                    <input type="datetime-local" id="time" name="time" value="<?php echo htmlspecialchars($task['time']); ?>" class="form-input">
                                </div>
                                
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">任务名称</label>
                                    <textarea id="name" name="name" rows="3" class="form-input"><?php echo htmlspecialchars($task['name']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="hidden" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" <?php echo $task['hidden'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700">隐藏任务名称</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="content_hidden" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" <?php echo $task['content_hidden'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700">隐藏任务内容</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="exposure" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" <?php echo $task['exposure'] ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-sm text-gray-700">触发曝光</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">现有图片</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-4">
                                    <?php if (isset($task['images']) && count($task['images']) > 0): ?>
                                        <?php foreach ($task['images'] as $index => $image): ?>
                                            <div class="relative group">
                                                <img src="uploads/<?php echo htmlspecialchars($image); ?>" alt="任务图片" class="w-full h-32 object-cover rounded-lg">
                                                <button type="button" class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 delete-image" data-index="<?php echo $index; ?>">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-span-full text-gray-500 italic">没有上传的图片</div>
                                    <?php endif; ?>
                                </div>
                                
                                <label class="block text-sm font-medium text-gray-700 mb-1">添加新图片</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-primary transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <i class="fa fa-cloud-upload text-gray-400 text-3xl"></i>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="images" class="relative cursor-pointer bg-white rounded-md font-medium text-primary hover:text-primary/80 focus-within:outline-none">
                                                <span>上传图片</span>
                                                <input id="images" name="images[]" type="file" multiple accept="image/*" class="sr-only">
                                            </label>
                                            <p class="pl-1">或拖放文件</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            PNG, JPG, GIF 最大 10MB
                                        </p>
                                    </div>
                                </div>
                                <div id="image-previews" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mt-4"></div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">现有视频</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-4">
                                    <?php if (isset($task['videos']) && count($task['videos']) > 0): ?>
                                        <?php foreach ($task['videos'] as $index => $video): ?>
                                            <div class="relative group">
                                                <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <i class="fa fa-film text-gray-400 text-4xl"></i>
                                                    <span class="absolute text-center text-gray-600">点击播放视频</span>
                                                </div>
                                                <button type="button" class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 delete-video" data-index="<?php echo $index; ?>">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-span-full text-gray-500 italic">没有上传的视频</div>
                                    <?php endif; ?>
                                </div>
                                
                                <label class="block text-sm font-medium text-gray-700 mb-1">添加新视频</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-primary transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <i class="fa fa-video-camera text-gray-400 text-3xl"></i>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="videos" class="relative cursor-pointer bg-white rounded-md font-medium text-primary hover:text-primary/80 focus-within:outline-none">
                                                <span>上传视频</span>
                                                <input id="videos" name="videos[]" type="file" multiple accept="video/*" class="sr-only">
                                            </label>
                                            <p class="pl-1">或拖放文件</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            MP4, WebM, OGG 最大 50MB
                                        </p>
                                    </div>
                                </div>
                                <div id="video-previews" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mt-4"></div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <a href="index.php" class="btn-secondary">
                                    <i class="fa fa-arrow-left mr-1"></i> 返回
                                </a>
                                <button type="submit" class="btn-primary">
                                    <i class="fa fa-save mr-1"></i> 保存修改
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">
                &copy; 2025 骰子游戏任务记录表 - 版权所有
            </p>
        </div>
    </footer>

    <!-- 视频查看模态框 -->
    <div id="video-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="relative w-full max-w-4xl px-4">
            <button id="close-video-modal" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fa fa-times text-2xl"></i>
            </button>
            <video id="modal-video" class="w-full" controls autoplay></video>
        </div>
    </div>

    <script>
        // 用户菜单切换
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });

        // 点击其他地方关闭用户菜单
        document.addEventListener('click', function(event) {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // 图片预览
        document.getElementById('images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('image-previews');
            previewContainer.innerHTML = '';
            
            for (let i = 0; i < e.target.files.length; i++) {
                const file = e.target.files[i];
                if (!file.type.match('image.*')) continue;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'relative';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="预览图片" class="w-full h-32 object-cover rounded-lg">
                        <button type="button" class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center delete-preview">
                            <i class="fa fa-times"></i>
                        </button>
                    `;
                    previewContainer.appendChild(preview);
                    
                    // 添加删除预览功能
                    preview.querySelector('.delete-preview').addEventListener('click', function() {
                        preview.remove();
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        // 视频预览
        document.getElementById('videos').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('video-previews');
            previewContainer.innerHTML = '';
            
            for (let i = 0; i < e.target.files.length; i++) {
                const file = e.target.files[i];
                if (!file.type.match('video.*')) continue;
                
                const preview = document.createElement('div');
                preview.className = 'relative';
                preview.innerHTML = `
                    <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fa fa-film text-gray-400 text-4xl"></i>
                        <span class="absolute text-center text-gray-600">视频预览</span>
                    </div>
                    <button type="button" class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center delete-preview">
                        <i class="fa fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(preview);
                
                // 添加删除预览功能
                preview.querySelector('.delete-preview').addEventListener('click', function() {
                    preview.remove();
                });
            }
        });

        // 删除现有图片
        document.querySelectorAll('.delete-image').forEach(button => {
            button.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                this.closest('div').remove();
                
                // 创建隐藏字段标记要删除的图片
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_images[]';
                input.value = index;
                document.querySelector('form').appendChild(input);
            });
        });

        // 删除现有视频
        document.querySelectorAll('.delete-video').forEach(button => {
            button.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                this.closest('div').remove();
                
                // 创建隐藏字段标记要删除的视频
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_videos[]';
                input.value = index;
                document.querySelector('form').appendChild(input);
            });
        });

        // 视频模态框
        const videoModal = document.getElementById('video-modal');
        const modalVideo = document.getElementById('modal-video');
        const closeVideoModal = document.getElementById('close-video-modal');
        
        // 打开视频模态框
        document.querySelectorAll('.grid .group').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.delete-image') || e.target.closest('.delete-video')) return;
                
                const videoElement = this.querySelector('i.fa-film');
                if (videoElement) {
                    const videoIndex = this.querySelector('.delete-video')?.getAttribute('data-index');
                    if (videoIndex !== null) {
                        const videoName = '<?php echo htmlspecialchars(json_encode($task['videos'] ?? [])); ?>'[videoIndex];
                        modalVideo.src = 'uploads/' + videoName;
                        videoModal.classList.remove('hidden');
                    }
                }
            });
        });
        
        // 关闭视频模态框
        closeVideoModal.addEventListener('click', function() {
            videoModal.classList.add('hidden');
            modalVideo.pause();
            modalVideo.src = '';
        });

        // 点击模态框背景关闭
        videoModal.addEventListener('click', function(e) {
            if (e.target === videoModal) {
                videoModal.classList.add('hidden');
                modalVideo.pause();
                modalVideo.src = '';
            }
        });
    </script>
</body>
</html>
    