<?php
session_start();
require_once 'functions.php';

// 获取任务数据
$tasks = getTasks();
$totalTasks = count($tasks);

// 分页处理
$tasksPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$totalPages = ceil($totalTasks / $tasksPerPage);
$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $tasksPerPage;
$paginatedTasks = array_slice($tasks, $offset, $tasksPerPage);

// 检查用户是否已登录
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = $isLoggedIn && $_SESSION['user']['isAdmin'];

// 搜索处理
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    if (!empty($searchTerm)) {
        $paginatedTasks = array_filter($paginatedTasks, function($task) use ($searchTerm) {
            return stripos($task['name'], $searchTerm) !== false;
        });
        $paginatedTasks = array_values($paginatedTasks);
    }
}

// 批量操作处理
$batchAction = $_GET['batch_action'] ?? '';
$taskIds = $_GET['task_ids'] ?? [];

if ($isAdmin && !empty($batchAction) && !empty($taskIds)) {
    // 处理批量操作
    $taskIds = explode(',', $taskIds);
    
    foreach ($taskIds as $taskId) {
        $taskIndex = array_search($taskId, array_column($tasks, 'id'));
        
        if ($taskIndex !== false) {
            switch ($batchAction) {
                case 'hide_name':
                    $tasks[$taskIndex]['hidden'] = true;
                    break;
                case 'show_name':
                    $tasks[$taskIndex]['hidden'] = false;
                    break;
                case 'hide_content':
                    $tasks[$taskIndex]['content_hidden'] = true;
                    break;
                case 'show_content':
                    $tasks[$taskIndex]['content_hidden'] = false;
                    break;
                case 'set_exposure':
                    $tasks[$taskIndex]['exposure'] = 1;
                    break;
                case 'unset_exposure':
                    $tasks[$taskIndex]['exposure'] = 0;
                    break;
            }
        }
    }
    
    // 保存任务
    saveTasks($tasks);
    
    // 刷新当前页
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>骰子游戏任务记录表</title>
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
            .btn-outline {
                @apply border border-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg shadow-sm hover:bg-gray-50 transition-all duration-200;
            }
            .form-input {
                @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all duration-200;
            }
            .pagination-link {
                @apply px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50;
            }
            .pagination-link-active {
                @apply bg-primary text-white border-primary;
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
                        <?php if ($isLoggedIn): ?>
                            <a href="add_task.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 border-b-2 px-1 pt-1 inline-flex items-center text-sm font-medium">
                                <i class="fa fa-plus mr-1"></i> 添加任务
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if ($isLoggedIn): ?>
                        <div class="flex-shrink-0 relative">
                            <button type="button" class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" id="user-menu-button">
                                <span class="sr-only">打开用户菜单</span>
                                <i class="fa fa-user-circle text-xl text-gray-500"></i>
                            </button>
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 py-1 z-50" id="user-menu">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fa fa-user mr-2"></i>
                                    <?php echo htmlspecialchars($_SESSION['user']['username']); ?>
                                    <?php if ($isAdmin): ?>
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
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">任务列表</h2>
                        
                        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                            <?php if ($isAdmin): ?>
                                <div class="relative">
                                    <select id="batch-action" class="form-input pr-10 appearance-none bg-white">
                                        <option value="">批量操作</option>
                                        <option value="hide_name">隐藏选中任务名称</option>
                                        <option value="show_name">显示选中任务名称</option>
                                        <option value="hide_content">隐藏选中任务内容</option>
                                        <option value="show_content">显示选中任务内容</option>
                                        <option value="set_exposure">设置选中任务曝光</option>
                                        <option value="unset_exposure">取消选中任务曝光</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                        <i class="fa fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                                <button id="apply-batch" class="btn-outline">
                                    <i class="fa fa-check mr-1"></i> 应用
                                </button>
                            <?php endif; ?>
                            
                            <div class="relative">
                                <input type="text" id="search-input" placeholder="搜索任务名称..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="form-input pr-10">
                                <button id="search-button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-700 hover:text-primary transition-colors duration-200">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                            <p>操作成功！</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($paginatedTasks)): ?>
                        <div class="text-center py-12">
                            <i class="fa fa-inbox text-gray-400 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-lg">暂无任务记录</p>
                            <?php if ($isLoggedIn): ?>
                                <p class="text-gray-500 mt-2">点击上方"添加任务"按钮创建第一个任务</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <?php if ($isAdmin): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <input type="checkbox" id="select-all" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                            </th>
                                        <?php endif; ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">任务时间</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">任务名称</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">内容预览</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">曝光</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($paginatedTasks as $task): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <?php if ($isAdmin): ?>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input type="checkbox" class="task-checkbox h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" data-id="<?php echo htmlspecialchars($task['id']); ?>">
                                                </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($task['time']))); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?php if ($task['hidden'] && !$isAdmin): ?>
                                                        <span class="text-gray-400 italic">[已隐藏]</span>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars(nl2br($task['name'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($task['content_hidden'] && !$isAdmin): ?>
                                                    <span class="text-gray-400 italic">[内容已隐藏]</span>
                                                <?php else: ?>
                                                    <div class="grid grid-cols-4 gap-2">
                                                        <?php if (isset($task['images']) && count($task['images']) > 0): ?>
                                                            <?php foreach (array_slice($task['images'], 0, 4) as $image): ?>
                                                                <div class="relative group cursor-pointer view-media" data-task-id="<?php echo htmlspecialchars($task['id']); ?>" data-media-type="image" data-media-index="<?php echo array_search($image, $task['images']); ?>">
                                                                    <img src="uploads/<?php echo htmlspecialchars($image); ?>" alt="任务图片" class="w-full h-16 object-cover rounded">
                                                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                                                                        <i class="fa fa-search-plus text-white"></i>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($task['videos']) && count($task['videos']) > 0): ?>
                                                            <?php foreach (array_slice($task['videos'], 0, 4 - (isset($task['images']) ? count($task['images']) : 0)) as $video): ?>
                                                                <div class="relative group cursor-pointer view-media" data-task-id="<?php echo htmlspecialchars($task['id']); ?>" data-media-type="video" data-media-index="<?php echo array_search($video, $task['videos']); ?>">
                                                                    <div class="w-full h-16 bg-gray-100 rounded flex items-center justify-center">
                                                                        <i class="fa fa-film text-gray-400"></i>
                                                                    </div>
                                                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center">
                                                                        <span class="text-white text-sm">点击播放</span>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ((!isset($task['images']) || count($task['images']) == 0) && (!isset($task['videos']) || count($task['videos']) == 0)): ?>
                                                            <div class="col-span-4 text-gray-400 italic text-sm">无内容</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($task['exposure']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        <i class="fa fa-check-circle mr-1"></i> 是
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        <i class="fa fa-times-circle mr-1"></i> 否
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button class="text-indigo-600 hover:text-indigo-900 mr-3 view-task" data-id="<?php echo htmlspecialchars($task['id']); ?>">
                                                    <i class="fa fa-eye mr-1"></i> 查看
                                                </button>
                                                
                                                <?php if ($isLoggedIn && ($isAdmin || $task['creator_id'] == $_SESSION['user']['id'])): ?>
                                                    <a href="edit_task.php?id=<?php echo htmlspecialchars($task['id']); ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                        <i class="fa fa-pencil mr-1"></i> 编辑
                                                    </a>
                                                    
                                                    <button class="text-red-600 hover:text-red-900 delete-task" data-id="<?php echo htmlspecialchars($task['id']); ?>">
                                                        <i class="fa fa-trash mr-1"></i> 删除
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <div class="flex items-center justify-between mt-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-link">上一页</a>
                                <?php endif; ?>
                                
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-link">下一页</a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        显示第 <span class="font-medium"><?php echo $offset + 1; ?></span> 到 <span class="font-medium"><?php echo min($offset + $tasksPerPage, $totalTasks); ?></span> 条，共 <span class="font-medium"><?php echo $totalTasks; ?></span> 条记录
                                    </p>
                                </div>
                                
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-link">
                                                <span class="sr-only">上一页</span>
                                                <i class="fa fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $startPage + 4);
                                        
                                        if ($endPage - $startPage < 4 && $startPage > 1) {
                                            $startPage = max(1, $endPage - 4);
                                        }
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $currentPage ? 'pagination-link-active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-link">
                                                <span class="sr-only">下一页</span>
                                                <i class="fa fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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

    <!-- 媒体查看模态框 -->
    <div id="media-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="relative w-full max-w-6xl px-4">
            <button id="close-media-modal" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fa fa-times text-2xl"></i>
            </button>
            
            <div class="flex items-center justify-between text-white mb-2">
                <button id="prev-task" class="text-white hover:text-gray-300 transition-colors duration-200">
                    <i class="fa fa-step-backward mr-1"></i> 上一个任务
                </button>
                
                <button id="prev-media" class="text-white hover:text-gray-300 transition-colors duration-200">
                    <i class="fa fa-arrow-left mr-1"></i> 上一张
                </button>
                
                <h3 id="modal-task-name" class="text-white text-lg font-medium mx-4"></h3>
                
                <button id="next-media" class="text-white hover:text-gray-300 transition-colors duration-200">
                    下一张 <i class="fa fa-arrow-right ml-1"></i>
                </button>
                
                <button id="next-task" class="text-white hover:text-gray-300 transition-colors duration-200">
                    下一个任务 <i class="fa fa-step-forward ml-1"></i>
                </button>
            </div>
            
            <div id="modal-media-container" class="bg-gray-900 rounded-lg overflow-hidden">
                <img id="modal-image" class="max-h-[80vh] mx-auto" src="" alt="任务图片" style="display: none;">
                <video id="modal-video-player" class="max-h-[80vh] mx-auto" controls style="display: none;"></video>
            </div>
            
            <div class="mt-4 grid grid-cols-4 sm:grid-cols-8 gap-2">
                <div id="modal-thumbnails"></div>
            </div>
        </div>
    </div>

    <!-- 任务查看模态框 -->
    <div id="task-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="relative w-full max-w-4xl p-4">
            <div class="bg-white rounded-lg shadow-xl overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900" id="task-modal-title"></h3>
                    <button id="close-task-modal" class="text-gray-400 hover:text-gray-500">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                
                <div class="p-6" id="task-modal-content">
                    <!-- 内容将通过JS动态填充 -->
                </div>
                
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                    <button id="close-task-details" class="btn-outline">
                        关闭
                    </button>
                </div>
            </div>
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

        // 全选/取消全选
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // 批量操作
        document.getElementById('apply-batch').addEventListener('click', function() {
            const batchAction = document.getElementById('batch-action').value;
            if (!batchAction) {
                alert('请选择批量操作类型');
                return;
            }
            
            const selectedTasks = [];
            document.querySelectorAll('.task-checkbox:checked').forEach(checkbox => {
                selectedTasks.push(checkbox.getAttribute('data-id'));
            });
            
            if (selectedTasks.length === 0) {
                alert('请选择要操作的任务');
                return;
            }
            
            // 确认操作
            if (confirm(`确定要对选中的 ${selectedTasks.length} 个任务执行"${document.getElementById('batch-action').options[document.getElementById('batch-action').selectedIndex].text}"操作吗？`)) {
                window.location.href = `index.php?batch_action=${encodeURIComponent(batchAction)}&task_ids=${encodeURIComponent(selectedTasks.join(','))}`;
            }
        });

        // 搜索功能
        document.getElementById('search-button').addEventListener('click', function() {
            const searchTerm = document.getElementById('search-input').value.trim();
            if (searchTerm) {
                window.location.href = `index.php?search=${encodeURIComponent(searchTerm)}`;
            } else {
                window.location.href = 'index.php';
            }
        });

        // 回车搜索
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('search-button').click();
            }
        });

        // 删除任务
        document.querySelectorAll('.delete-task').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-id');
                if (confirm('确定要删除这个任务吗？此操作不可撤销！')) {
                    fetch('delete_task.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            id: taskId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('任务删除成功');
                            location.reload();
                        } else {
                            alert('任务删除失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('发生错误: ' + error.message);
                    });
                }
            });
        });

        // 媒体查看模态框
        const mediaModal = document.getElementById('media-modal');
        const modalImage = document.getElementById('modal-image');
        const modalVideoPlayer = document.getElementById('modal-video-player');
        const closeMediaModal = document.getElementById('close-media-modal');
        const prevTask = document.getElementById('prev-task');
        const nextTask = document.getElementById('next-task');
        const prevMedia = document.getElementById('prev-media');
        const nextMedia = document.getElementById('next-media');
        const modalTaskName = document.getElementById('modal-task-name');
        const modalThumbnails = document.getElementById('modal-thumbnails');
        
        let currentTaskIndex = 0;
        let currentMediaIndex = 0;
        let currentMediaType = 'image';
        
        // 打开媒体模态框
        document.querySelectorAll('.view-media').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id');
                currentMediaType = this.getAttribute('data-media-type');
                currentMediaIndex = parseInt(this.getAttribute('data-media-index'));
                
                // 找到任务索引
                currentTaskIndex = <?php echo json_encode(array_column($tasks, 'id')); ?>.indexOf(taskId);
                
                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
            });
        });
        
        // 关闭媒体模态框
        closeMediaModal.addEventListener('click', function() {
            mediaModal.classList.add('hidden');
            modalImage.style.display = 'none';
            modalVideoPlayer.style.display = 'none';
            modalVideoPlayer.pause();
        });
        
        // 上一个任务
        prevTask.addEventListener('click', function() {
            if (currentTaskIndex > 0) {
                currentTaskIndex--;
                currentMediaIndex = 0;
                currentMediaType = 'image';
                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
            }
        });
        
        // 下一个任务
        nextTask.addEventListener('click', function() {
            if (currentTaskIndex < <?php echo count($tasks) - 1; ?>) {
                currentTaskIndex++;
                currentMediaIndex = 0;
                currentMediaType = 'image';
                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
            }
        });
        
        // 上一个媒体
        prevMedia.addEventListener('click', function() {
            const task = <?php echo json_encode($tasks); ?>[currentTaskIndex];
            const allMedia = [...(task.images || []), ...(task.videos || [])];
            
            if (allMedia.length > 0) {
                currentMediaIndex = (currentMediaIndex - 1 + allMedia.length) % allMedia.length;
                currentMediaType = (currentMediaIndex < (task.images || []).length) ? 'image' : 'video';
                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
            }
        });
        
        // 下一个媒体
        nextMedia.addEventListener('click', function() {
            const task = <?php echo json_encode($tasks); ?>[currentTaskIndex];
            const allMedia = [...(task.images || []), ...(task.videos || [])];
            
            if (allMedia.length > 0) {
                currentMediaIndex = (currentMediaIndex + 1) % allMedia.length;
                currentMediaType = (currentMediaIndex < (task.images || []).length) ? 'image' : 'video';
                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
            }
        });
        
        // 显示媒体
        function showMedia(taskIndex, mediaIndex, mediaType) {
            const task = <?php echo json_encode($tasks); ?>[taskIndex];
            
            if (!task) return;
            
            // 清空缩略图
            modalThumbnails.innerHTML = '';
            
            // 设置任务名称
            modalTaskName.textContent = task.hidden ? '[已隐藏]' : task.name.replace(/\n/g, ' ');
            
            // 生成缩略图
            if (task.images && task.images.length > 0) {
                task.images.forEach((image, index) => {
                    const thumbnail = document.createElement('div');
                    thumbnail.className = `cursor-pointer ${index === mediaIndex && mediaType === 'image' ? 'ring-2 ring-primary' : ''}`;
                    thumbnail.innerHTML = `
                        <img src="uploads/${image}" alt="任务图片" class="w-full h-16 object-cover rounded">
                    `;
                    thumbnail.addEventListener('click', () => {
                        showMedia(taskIndex, index, 'image');
                    });
                    modalThumbnails.appendChild(thumbnail);
                });
            }
            
            if (task.videos && task.videos.length > 0) {
                task.videos.forEach((video, index) => {
                    const thumbnail = document.createElement('div');
                    thumbnail.className = `cursor-pointer ${index === mediaIndex - (task.images ? task.images.length : 0) && mediaType === 'video' ? 'ring-2 ring-primary' : ''}`;
                    thumbnail.innerHTML = `
                        <div class="w-full h-16 bg-gray-100 rounded flex items-center justify-center">
                            <i class="fa fa-film text-gray-400"></i>
                        </div>
                    `;
                    thumbnail.addEventListener('click', () => {
                        showMedia(taskIndex, (task.images ? task.images.length : 0) + index, 'video');
                    });
                    modalThumbnails.appendChild(thumbnail);
                });
            }
            
            // 显示主媒体
            if (mediaType === 'image' && task.images && mediaIndex < task.images.length) {
                modalImage.src = 'uploads/' + task.images[mediaIndex];
                modalImage.style.display = 'block';
                modalVideoPlayer.style.display = 'none';
                modalVideoPlayer.pause();
            } else if (mediaType === 'video' && task.videos && mediaIndex - (task.images ? task.images.length : 0) < task.videos.length) {
                const videoIndex = mediaIndex - (task.images ? task.images.length : 0);
                modalVideoPlayer.src = 'uploads/' + task.videos[videoIndex];
                modalVideoPlayer.style.display = 'block';
                modalImage.style.display = 'none';
                modalVideoPlayer.play();
            }
            
            // 显示模态框
            mediaModal.classList.remove('hidden');
        }
        
        // 点击模态框背景关闭
        mediaModal.addEventListener('click', function(e) {
            if (e.target === mediaModal) {
                closeMediaModal.click();
            }
        });

        // 任务查看模态框
        const taskModal = document.getElementById('task-modal');
        const closeTaskModal = document.getElementById('close-task-modal');
        const closeTaskDetails = document.getElementById('close-task-details');
        const taskModalTitle = document.getElementById('task-modal-title');
        const taskModalContent = document.getElementById('task-modal-content');
        
        // 打开任务模态框
        document.querySelectorAll('.view-task').forEach(button => {
            button.addEventListener('click', function() {
                const taskId = this.getAttribute('data-id');
                const taskIndex = <?php echo json_encode(array_column($tasks, 'id')); ?>.indexOf(taskId);
                
                if (taskIndex !== -1) {
                    const task = <?php echo json_encode($tasks); ?>[taskIndex];
                    
                    // 设置标题
                    taskModalTitle.textContent = task.hidden ? '[已隐藏]' : task.name;
                    
                    // 设置内容
                    let content = `
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">任务时间</h4>
                                <p class="text-gray-900">${task.time}</p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">任务名称</h4>
                                <p class="text-gray-900 whitespace-pre-line">${task.hidden ? '<span class="text-gray-400 italic">[已隐藏]</span>' : task.name}</p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">任务内容</h4>
                                ${task.content_hidden ? '<p class="text-gray-400 italic">[内容已隐藏]</p>' : ''}
                            </div>
                    `;
                    
                    if (!task.content_hidden) {
                        if (task.images && task.images.length > 0) {
                            content += `
                                <div class="grid grid-cols-4 gap-4 mt-2">
                                    <h4 class="col-span-full text-sm font-medium text-gray-500">图片</h4>
                            `;
                            
                            task.images.forEach((image, index) => {
                                content += `
                                    <div class="cursor-pointer view-modal-media" data-task-id="${task.id}" data-media-type="image" data-media-index="${index}">
                                        <img src="uploads/${image}" alt="任务图片" class="w-full h-32 object-cover rounded-lg">
                                    </div>
                                `;
                            });
                            
                            content += `
                                </div>
                            `;
                        }
                        
                        if (task.videos && task.videos.length > 0) {
                            content += `
                                <div class="grid grid-cols-4 gap-4 mt-4">
                                    <h4 class="col-span-full text-sm font-medium text-gray-500">视频</h4>
                            `;
                            
                            task.videos.forEach((video, index) => {
                                content += `
                                    <div class="cursor-pointer view-modal-media" data-task-id="${task.id}" data-media-type="video" data-media-index="${index}">
                                        <div class="w-full h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <i class="fa fa-film text-gray-400"></i>
                                            <span class="text-gray-600">点击播放视频</span>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            content += `
                                </div>
                            `;
                        }
                    }
                    
                    content += `
                            <div class="flex items-center">
                                <h4 class="text-sm font-medium text-gray-500 mr-2">曝光:</h4>
                                ${task.exposure ? 
                                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><i class="fa fa-check-circle mr-1"></i> 是</span>' : 
                                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"><i class="fa fa-times-circle mr-1"></i> 否</span>'
                                }
                            </div>
                        </div>
                    `;
                    
                    taskModalContent.innerHTML = content;
                    
                    // 为模态框内的媒体添加点击事件
                    document.querySelectorAll('.view-modal-media').forEach(element => {
                        element.addEventListener('click', function() {
                            const taskId = this.getAttribute('data-task-id');
                            const mediaType = this.getAttribute('data-media-type');
                            const mediaIndex = parseInt(this.getAttribute('data-media-index'));
                            
                            // 找到任务索引
                            const taskIndex = <?php echo json_encode(array_column($tasks, 'id')); ?>.indexOf(taskId);
                            
                            if (taskIndex !== -1) {
                                taskModal.classList.add('hidden');
                                currentTaskIndex = taskIndex;
                                currentMediaIndex = mediaIndex;
                                currentMediaType = mediaType;
                                showMedia(currentTaskIndex, currentMediaIndex, currentMediaType);
                            }
                        });
                    });
                    
                    // 显示模态框
                    taskModal.classList.remove('hidden');
                }
            });
        });
        
        // 关闭任务模态框
        closeTaskModal.addEventListener('click', function() {
            taskModal.classList.add('hidden');
        });
        
        closeTaskDetails.addEventListener('click', function() {
            taskModal.classList.add('hidden');
        });
        
        // 点击模态框背景关闭
        taskModal.addEventListener('click', function(e) {
            if (e.target === taskModal) {
                taskModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
    