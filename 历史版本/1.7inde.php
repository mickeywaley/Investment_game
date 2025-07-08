<?php
// 开启会话
session_start();

// 定义数据库文件路径
$dbFile = 'data.json';

// 若数据库文件不存在，创建一个空数组并保存为 JSON 文件
if (!file_exists($dbFile)) {
    file_put_contents($dbFile, json_encode([]));
}

// 检查用户是否已登录
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'];

// 处理登录逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['isAdmin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}

// 处理退出登录逻辑
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 处理添加记录逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addRecord'])) {
    $taskDate = date('Y-m-d l');
    $taskName = $_POST['taskName'];
    $triggerExposure = isset($_POST['triggerExposure']) ? '是' : '否';

    $mediaFiles = [];
    if (!empty($_FILES['media']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        foreach ($_FILES['media']['tmp_name'] as $key => $tmpName) {
            $fileName = $_FILES['media']['name'][$key];
            $filePath = $uploadDir . uniqid() . '_' . $fileName;
            if (move_uploaded_file($tmpName, $filePath)) {
                $mediaFiles[] = $filePath;
            }
        }
    }

    $newRecord = [
        'taskDate' => $taskDate,
        'taskName' => $taskName,
        'mediaFiles' => $mediaFiles,
        'triggerExposure' => $triggerExposure
    ];

    $records = json_decode(file_get_contents($dbFile), true);
    array_push($records, $newRecord);
    file_put_contents($dbFile, json_encode($records));
    header('Location: index.php');
    exit;
}

// 处理编辑记录逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editRecord'])) {
    $recordIndex = $_POST['recordIndex'];
    $taskDate = $_POST['taskDate'];
    $taskName = $_POST['taskName'];
    $triggerExposure = isset($_POST['triggerExposure']) ? '是' : '否';

    $records = json_decode(file_get_contents($dbFile), true);
    $record = $records[$recordIndex];
    $record['taskDate'] = $taskDate;
    $record['taskName'] = $taskName;
    $record['triggerExposure'] = $triggerExposure;

    $mediaFiles = $record['mediaFiles'];
    if (!empty($_FILES['media']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        foreach ($_FILES['media']['tmp_name'] as $key => $tmpName) {
            $fileName = $_FILES['media']['name'][$key];
            $filePath = $uploadDir . uniqid() . '_' . $fileName;
            if (move_uploaded_file($tmpName, $filePath)) {
                $mediaFiles[] = $filePath;
            }
        }
    }
    $record['mediaFiles'] = $mediaFiles;

    $records[$recordIndex] = $record;
    file_put_contents($dbFile, json_encode($records));
    header('Location: index.php');
    exit;
}

// 处理删除记录逻辑
if ($isAdmin && isset($_GET['deleteRecord'])) {
    $recordIndex = $_GET['deleteRecord'];
    $records = json_decode(file_get_contents($dbFile), true);
    
    // 删除记录前先删除相关媒体文件
    if (isset($records[$recordIndex]['mediaFiles'])) {
        foreach ($records[$recordIndex]['mediaFiles'] as $mediaFile) {
            if (file_exists($mediaFile)) {
                unlink($mediaFile);
            }
        }
    }
    
    // 删除记录
    array_splice($records, $recordIndex, 1);
    file_put_contents($dbFile, json_encode($records));
    header('Location: index.php');
    exit;
}

// 处理删除媒体文件逻辑
if ($isAdmin && isset($_GET['deleteMedia']) && isset($_GET['recordIndex'])) {
    $recordIndex = $_GET['recordIndex'];
    $mediaIndex = $_GET['deleteMedia'];

    $records = json_decode(file_get_contents($dbFile), true);
    $record = $records[$recordIndex];
    $mediaFiles = $record['mediaFiles'];
    if (isset($mediaFiles[$mediaIndex])) {
        $fileToDelete = $mediaFiles[$mediaIndex];
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
        unset($mediaFiles[$mediaIndex]);
        $mediaFiles = array_values($mediaFiles);
        $record['mediaFiles'] = $mediaFiles;
        $records[$recordIndex] = $record;
        file_put_contents($dbFile, json_encode($records));
    }
    header('Location: index.php');
    exit;
}

// 获取最近的 20 条记录
$records = json_decode(file_get_contents($dbFile), true);
$recentRecords = array_slice(array_reverse($records), 0, 20);
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>骰子游戏记录表</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS 配置 -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', // 主色调：靛蓝色
                        secondary: '#10B981', // 辅助色：emerald
                        accent: '#F59E0B', // 强调色：琥珀色
                        dark: '#1F2937', // 深色
                        light: '#F9FAFB', // 浅色
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- 自定义工具类 -->
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .card-shadow {
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
            .card-hover {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .card-hover:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }
            .aspect-9-6 {
                aspect-ratio: 9/6;
            }
            .lightbox-control {
                @apply absolute top-1/2 -translate-y-1/2 bg-black/30 hover:bg-black/50 text-white w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300;
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-sans text-dark">
    <!-- 返回首页按钮 -->
    <a href="#" id="back-to-top" class="fixed top-4 right-4 bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg shadow-lg transition-all duration-300 z-50 flex items-center">
        <i class="fa fa-home mr-2"></i> 返回首页
    </a>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- 头部 -->
        <header class="mb-8 text-center">
            <h1 class="text-[clamp(2rem,5vw,3rem)] font-bold text-primary mb-2">骰子游戏记录表</h1>
            <p class="text-gray-600 max-w-2xl mx-auto">记录和管理你的骰子游戏任务，支持图片和视频记录，轻松追踪游戏进度</p>
        </header>

        <!-- 登录区域 -->
        <?php if (!$isAdmin): ?>
            <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-6 mb-8 transform transition-all duration-500 hover:shadow-xl">
                <h2 class="text-xl font-semibold mb-4 text-center text-dark">管理员登录</h2>
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
                        <i class="fa fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                        <input type="text" id="username" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary transition duration-300">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                        <input type="password" id="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary transition duration-300">
                    </div>
                    <button type="submit" name="login" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                        <i class="fa fa-sign-in mr-2"></i> 登录
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- 管理员操作区 -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 transform transition-all duration-500 hover:shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-dark">添加新任务</h2>
                    <a href="?logout" class="text-gray-600 hover:text-red-600 transition duration-300 flex items-center">
                        <i class="fa fa-sign-out mr-1"></i> 退出登录
                    </a>
                </div>
                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="taskName" class="block text-sm font-medium text-gray-700 mb-1">任务名称</label>
                        <textarea id="taskName" name="taskName" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary transition duration-300" placeholder="请输入任务描述..."></textarea>
                    </div>
                    <div>
                        <label for="media" class="block text-sm font-medium text-gray-700 mb-1">任务内容（图片或视频）</label>
                        <input type="file" id="media" name="media[]" multiple class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary transition duration-300">
                        <p class="text-xs text-gray-500 mt-1">支持上传多张图片或视频</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="triggerExposure" name="triggerExposure" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="triggerExposure" class="ml-2 block text-sm text-gray-700">触发曝光</label>
                    </div>
                    <button type="submit" name="addRecord" class="bg-secondary hover:bg-secondary/90 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center">
                        <i class="fa fa-plus-circle mr-2"></i> 添加记录
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- 所有任务快捷索引 -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8 transform transition-all duration-500 hover:shadow-xl">
            <div class="flex justify-between items-center cursor-pointer" id="toggle-all-index">
                <h2 class="text-xl font-semibold text-dark flex items-center">
                    <i class="fa fa-list-ul mr-2 text-primary"></i> 所有任务快捷索引
                    <span class="ml-2 text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full">
                        <?php echo count($records); ?> 个任务
                    </span>
                </h2>
                <i class="fa fa-chevron-down text-gray-500 transition-transform duration-300" id="index-chevron"></i>
            </div>
            <div class="mt-4 overflow-hidden transition-all duration-500 max-h-0" id="all-index-content">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($records as $index => $record): ?>
                        <a href="#record-<?php echo $index; ?>" class="p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition duration-300 flex items-center">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold mr-3">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo substr($record['taskName'], 0, 25); if (strlen($record['taskName']) > 25) echo '...'; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('Y-m-d', strtotime($record['taskDate'])); ?>
                                </div>
                            </div>
                            <i class="fa fa-angle-right text-gray-400"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 最近的 20 条记录 -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8 transform transition-all duration-500 hover:shadow-xl">
            <h2 class="text-xl font-semibold text-dark mb-6">最近的 <?php echo min(20, count($records)); ?> 条记录</h2>
            
            <?php if (empty($recentRecords)): ?>
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-file-text-o text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">暂无记录</h3>
                    <p class="text-gray-500 max-w-md mx-auto">添加你的第一条骰子游戏记录，开始追踪你的游戏进度</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($recentRecords as $index => $record): ?>
                        <?php $originalIndex = count($records) - 1 - $index; ?>
                        <div id="record-<?php echo $originalIndex; ?>" class="border border-gray-200 rounded-xl overflow-hidden transition-all duration-500 hover:border-primary/30 card-shadow card-hover">
                            <div class="p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            任务 <?php echo $originalIndex + 1; ?>
                                            <span class="ml-2 text-xs font-normal bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">
                                                <?php echo date('Y-m-d', strtotime($record['taskDate'])); ?>
                                            </span>
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-1">
                                            <?php echo $record['taskDate']; ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center">
                                        <?php if ($record['triggerExposure'] === '是'): ?>
                                            <span class="bg-accent/10 text-accent text-xs font-medium px-2.5 py-0.5 rounded">
                                                触发曝光
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <?php if ($isAdmin): ?>
                                        <form method="post" enctype="multipart/form-data" class="space-y-4">
                                            <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">任务时间</label>
                                                <input type="text" name="taskDate" value="<?php echo $record['taskDate']; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">任务名称</label>
                                                <textarea name="taskName" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"><?php echo $record['taskName']; ?></textarea>
                                            </div>
                                    <?php else: ?>
                                        <h4 class="text-base font-medium text-gray-900 mb-2">任务详情</h4>
                                        <div class="prose prose-sm max-w-none text-gray-700">
                                            <?php echo nl2br(htmlspecialchars($record['taskName'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($isAdmin && !empty($record['mediaFiles'])): ?>
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">现有媒体</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                            <?php foreach ($record['mediaFiles'] as $mediaIndex => $mediaFile): ?>
                                                <div class="bg-white rounded-lg overflow-hidden shadow-sm">
                                                    <?php
                                                    $fileExtension = pathinfo($mediaFile, PATHINFO_EXTENSION);
                                                    if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])):
                                                    ?>
                                                        <div class="cursor-pointer aspect-9-6 overflow-hidden" onclick="openLightbox('<?php echo $mediaFile; ?>', 'image', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                            <img src="<?php echo $mediaFile; ?>" alt="任务媒体" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                                        </div>
                                                    <?php elseif (in_array(strtolower($fileExtension), ['mp4', 'webm', 'ogg'])): ?>
                                                        <div class="cursor-pointer aspect-9-6 overflow-hidden relative" onclick="openLightbox('<?php echo $mediaFile; ?>', 'video', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                            <img src="https://picsum.photos/seed/video<?php echo $mediaIndex; ?>/400/300" alt="视频封面" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30">
                                                                <div class="w-12 h-12 rounded-full bg-white bg-opacity-80 flex items-center justify-center">
                                                                    <i class="fa fa-play text-primary text-xl"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="p-2 flex justify-between items-center">
                                                        <span class="text-xs text-gray-500">
                                                            <?php echo pathinfo($mediaFile, PATHINFO_BASENAME); ?>
                                                        </span>
                                                        <button type="button" onclick="deleteMedia(<?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)" class="text-red-500 hover:text-red-700 transition-colors duration-300">
                                                            <i class="fa fa-trash"></i> 删除
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($isAdmin): ?>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">添加更多媒体</label>
                                        <input type="file" name="media[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                    </div>
                                    
                                    <div class="flex items-center mb-4">
                                        <input type="checkbox" id="triggerExposure-<?php echo $originalIndex; ?>" name="triggerExposure" 
                                            <?php echo $record['triggerExposure'] === '是' ? 'checked' : ''; ?>
                                            class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                        <label for="triggerExposure-<?php echo $originalIndex; ?>" class="ml-2 block text-sm text-gray-700">触发曝光</label>
                                    </div>
                                    
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" name="editRecord" class="bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center">
                                            <i class="fa fa-save mr-2"></i> 保存修改
                                        </button>
                                        <button type="button" onclick="deleteRecord(<?php echo $originalIndex; ?>)" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center">
                                            <i class="fa fa-trash mr-2"></i> 删除任务
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php if (!empty($record['mediaFiles'])): ?>
                                        <h4 class="text-base font-medium text-gray-900 mb-2">任务媒体</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                            <?php foreach ($record['mediaFiles'] as $mediaIndex => $mediaFile): ?>
                                                <?php
                                                $fileExtension = pathinfo($mediaFile, PATHINFO_EXTENSION);
                                                if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])):
                                                ?>
                                                    <div class="bg-white rounded-lg overflow-hidden shadow-sm cursor-pointer" onclick="openLightbox('<?php echo $mediaFile; ?>', 'image', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                        <div class="aspect-9-6 overflow-hidden">
                                                            <img src="<?php echo $mediaFile; ?>" alt="任务媒体" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                                        </div>
                                                        <div class="p-2">
                                                            <span class="text-xs text-gray-500">
                                                                <?php echo pathinfo($mediaFile, PATHINFO_BASENAME); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php elseif (in_array(strtolower($fileExtension), ['mp4', 'webm', 'ogg'])): ?>
                                                    <div class="bg-white rounded-lg overflow-hidden shadow-sm cursor-pointer" onclick="openLightbox('<?php echo $mediaFile; ?>', 'video', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                        <div class="aspect-9-6 overflow-hidden relative">
                                                            <img src="https://picsum.photos/seed/video<?php echo $mediaIndex; ?>/400/300" alt="视频封面" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                                            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30">
                                                                <div class="w-12 h-12 rounded-full bg-white bg-opacity-80 flex items-center justify-center">
                                                                    <i class="fa fa-play text-primary text-xl"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-2">
                                                            <span class="text-xs text-gray-500">
                                                                <?php echo pathinfo($mediaFile, PATHINFO_BASENAME); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($isAdmin): ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 灯箱视图 -->
    <div id="lightbox" class="fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <button id="close-lightbox" class="absolute top-6 right-6 text-white text-3xl hover:text-gray-300 transition-colors duration-300">
            <i class="fa fa-times"></i>
        </button>
        
        <div id="lightbox-controls" class="absolute bottom-6 left-0 right-0 flex justify-center items-center space-x-4 flex-wrap">
            <button id="prev-task" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg flex items-center transition-all duration-300">
                上一个任务 <i class="fa fa-arrow-left ml-2"></i>
            </button>
            <button id="prev-media" class="bg-black/50 hover:bg-black/70 text-white px-4 py-2 rounded-lg flex items-center transition-all duration-300">
                <i class="fa fa-chevron-left mr-2"></i> 上一张
            </button>
            <span id="lightbox-title" class="text-white text-lg font-medium max-w-md truncate my-2"></span>
            <button id="next-media" class="bg-black/50 hover:bg-black/70 text-white px-4 py-2 rounded-lg flex items-center transition-all duration-300">
                下一张 <i class="fa fa-chevron-right ml-2"></i>
            </button>
            <button id="next-task" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg flex items-center transition-all duration-300">
                <i class="fa fa-arrow-right mr-2"></i> 下一个任务
            </button>
        </div>
        
        <button id="prev-button" class="lightbox-control left-4">
            <i class="fa fa-chevron-left text-xl"></i>
        </button>
        <button id="next-button" class="lightbox-control right-4">
            <i class="fa fa-chevron-right text-xl"></i>
        </button>
        
        <div class="max-w-6xl w-full max-h-[90vh] flex items-center justify-center">
            <div id="lightbox-content" class="w-full h-full flex items-center justify-center">
                <!-- 内容将通过JavaScript动态添加 -->
            </div>
        </div>
    </div>

    <script>
        // 返回顶部按钮功能
        document.addEventListener('DOMContentLoaded', function() {
            const backToTopButton = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopButton.classList.add('opacity-100');
                    backToTopButton.classList.remove('opacity-0');
                } else {
                    backToTopButton.classList.add('opacity-0');
                    backToTopButton.classList.remove('opacity-100');
                }
            });
            
            backToTopButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // 初始化快捷索引折叠/展开功能
            const toggleButton = document.getElementById('toggle-all-index');
            const content = document.getElementById('all-index-content');
            const chevron = document.getElementById('index-chevron');
            
            toggleButton.addEventListener('click', function() {
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                    chevron.classList.remove('rotate-180');
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    chevron.classList.add('rotate-180');
                }
            });
            
            // 平滑滚动
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });

        // 当前查看的媒体信息
        let currentRecordIndex = null;
        let currentMediaIndex = null;
        let allRecords = <?php echo json_encode($records); ?>;

        // 打开灯箱
        function openLightbox(src, type, recordIndex, mediaIndex) {
            currentRecordIndex = recordIndex;
            currentMediaIndex = mediaIndex;
            
            const lightbox = document.getElementById('lightbox');
            const lightboxContent = document.getElementById('lightbox-content');
            const lightboxTitle = document.getElementById('lightbox-title');
            lightboxContent.innerHTML = '';
            
            // 设置任务名称
            const taskName = allRecords[recordIndex]['taskName'];
            lightboxTitle.textContent = taskName;
            
            if (type === 'image') {
                const img = document.createElement('img');
                img.src = src;
                img.className = 'max-w-full max-h-[80vh] object-contain';
                lightboxContent.appendChild(img);
            } else if (type === 'video') {
                const video = document.createElement('video');
                video.src = src;
                video.controls = true;
                video.autoplay = true;
                video.className = 'max-w-full max-h-[80vh]';
                lightboxContent.appendChild(video);
            }
            
            // 更新按钮状态
            updateLightboxButtons();
            
            lightbox.classList.remove('opacity-0', 'pointer-events-none');
            document.body.style.overflow = 'hidden';
        }

        // 更新灯箱按钮状态
        function updateLightboxButtons() {
            const prevMediaButton = document.getElementById('prev-media');
            const nextMediaButton = document.getElementById('next-media');
            const prevTaskButton = document.getElementById('prev-task');
            const nextTaskButton = document.getElementById('next-task');
            
            // 检查是否有上一张
            if (currentMediaIndex > 0) {
                prevMediaButton.removeAttribute('disabled');
                prevMediaButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                prevMediaButton.setAttribute('disabled', 'true');
                prevMediaButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
            
            // 检查是否有下一张
            if (currentMediaIndex < allRecords[currentRecordIndex]['mediaFiles'].length - 1) {
                nextMediaButton.removeAttribute('disabled');
                nextMediaButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                nextMediaButton.setAttribute('disabled', 'true');
                nextMediaButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
            
            // 检查是否有上一个任务
            if (currentRecordIndex > 0) {
                prevTaskButton.removeAttribute('disabled');
                prevTaskButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                prevTaskButton.setAttribute('disabled', 'true');
                prevTaskButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
            
            // 检查是否有下一个任务
            if (currentRecordIndex < allRecords.length - 1) {
                nextTaskButton.removeAttribute('disabled');
                nextTaskButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                nextTaskButton.setAttribute('disabled', 'true');
                nextTaskButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // 上一张媒体
        document.getElementById('prev-media').addEventListener('click', function() {
            if (currentMediaIndex > 0) {
                currentMediaIndex--;
                const mediaFile = allRecords[currentRecordIndex]['mediaFiles'][currentMediaIndex];
                const fileExtension = pathinfo(mediaFile, 'extension');
                
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension.toLowerCase())) {
                    openLightbox(mediaFile, 'image', currentRecordIndex, currentMediaIndex);
                } else if (['mp4', 'webm', 'ogg'].includes(fileExtension.toLowerCase())) {
                    openLightbox(mediaFile, 'video', currentRecordIndex, currentMediaIndex);
                }
            }
        });

        // 下一张媒体
        document.getElementById('next-media').addEventListener('click', function() {
            if (currentMediaIndex < allRecords[currentRecordIndex]['mediaFiles'].length - 1) {
                currentMediaIndex++;
                const mediaFile = allRecords[currentRecordIndex]['mediaFiles'][currentMediaIndex];
                const fileExtension = pathinfo(mediaFile, 'extension');
                
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension.toLowerCase())) {
                    openLightbox(mediaFile, 'image', currentRecordIndex, currentMediaIndex);
                } else if (['mp4', 'webm', 'ogg'].includes(fileExtension.toLowerCase())) {
                    openLightbox(mediaFile, 'video', currentRecordIndex, currentMediaIndex);
                }
            }
        });

        // 上一个任务
        document.getElementById('prev-task').addEventListener('click', function() {
            if (currentRecordIndex > 0) {
                currentRecordIndex--;
                currentMediaIndex = 0;
                
                // 检查是否有媒体文件
                if (allRecords[currentRecordIndex]['mediaFiles'].length > 0) {
                    const mediaFile = allRecords[currentRecordIndex]['mediaFiles'][currentMediaIndex];
                    const fileExtension = pathinfo(mediaFile, 'extension');
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension.toLowerCase())) {
                        openLightbox(mediaFile, 'image', currentRecordIndex, currentMediaIndex);
                    } else if (['mp4', 'webm', 'ogg'].includes(fileExtension.toLowerCase())) {
                        openLightbox(mediaFile, 'video', currentRecordIndex, currentMediaIndex);
                    }
                }
            }
        });

        // 下一个任务
        document.getElementById('next-task').addEventListener('click', function() {
            if (currentRecordIndex < allRecords.length - 1) {
                currentRecordIndex++;
                currentMediaIndex = 0;
                
                // 检查是否有媒体文件
                if (allRecords[currentRecordIndex]['mediaFiles'].length > 0) {
                    const mediaFile = allRecords[currentRecordIndex]['mediaFiles'][currentMediaIndex];
                    const fileExtension = pathinfo(mediaFile, 'extension');
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension.toLowerCase())) {
                        openLightbox(mediaFile, 'image', currentRecordIndex, currentMediaIndex);
                    } else if (['mp4', 'webm', 'ogg'].includes(fileExtension.toLowerCase())) {
                        openLightbox(mediaFile, 'video', currentRecordIndex, currentMediaIndex);
                    }
                }
            }
        });

        // 左侧快捷按钮（上一张）
        document.getElementById('prev-button').addEventListener('click', function() {
            document.getElementById('prev-media').click();
        });

        // 右侧快捷按钮（下一张）
        document.getElementById('next-button').addEventListener('click', function() {
            document.getElementById('next-media').click();
        });

        // 关闭灯箱
        document.getElementById('close-lightbox').addEventListener('click', function() {
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.add('opacity-0', 'pointer-events-none');
            document.body.style.overflow = '';
            
            // 停止所有视频
            const videos = document.querySelectorAll('video');
            videos.forEach(video => {
                video.pause();
            });
        });

        // 点击灯箱背景关闭
        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                const lightbox = document.getElementById('lightbox');
                lightbox.classList.add('opacity-0', 'pointer-events-none');
                document.body.style.overflow = '';
                
                // 停止所有视频
                const videos = document.querySelectorAll('video');
                videos.forEach(video => {
                    video.pause();
                });
            }
        });

        // 键盘导航
        document.addEventListener('keydown', function(e) {
            // 仅在灯箱打开时响应键盘事件
            if (document.getElementById('lightbox').classList.contains('opacity-0')) {
                return;
            }
            
            switch(e.key) {
                case 'ArrowLeft':
                    document.getElementById('prev-media').click();
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                    document.getElementById('next-media').click();
                    e.preventDefault();
                    break;
                case 'ArrowUp':
                    document.getElementById('prev-task').click();
                    e.preventDefault();
                    break;
                case 'ArrowDown':
                    document.getElementById('next-task').click();
                    e.preventDefault();
                    break;
                case 'Escape':
                    document.getElementById('close-lightbox').click();
                    e.preventDefault();
                    break;
            }
        });

        // 删除媒体文件
        function deleteMedia(recordIndex, mediaIndex) {
            if (confirm('确定要删除此媒体文件吗？此操作不可撤销。')) {
                window.location.href = `index.php?deleteMedia=${mediaIndex}&recordIndex=${recordIndex}`;
            }
        }

        // 删除任务
        function deleteRecord(recordIndex) {
            if (confirm('确定要删除此任务吗？此操作将删除所有相关媒体文件，且不可撤销。')) {
                window.location.href = `index.php?deleteRecord=${recordIndex}`;
            }
        }

        // 辅助函数：获取文件扩展名
        function pathinfo(path, option) {
            if (option === 'extension') {
                return path.split('.').pop().toLowerCase();
            }
            return '';
        }
    </script>
</body>

</html>
