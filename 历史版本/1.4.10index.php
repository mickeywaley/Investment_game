<?php
// 开启会话
session_start();

// 定义数据库文件路径
$dbFile = 'data.json';
$authFile = 'auth.json';

// 初始化认证文件
if (!file_exists($authFile)) {
    $authData = [
        'username' => 'admin',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)
    ];
    file_put_contents($authFile, json_encode($authData));
}

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
    
    $authData = json_decode(file_get_contents($authFile), true);
    
    if ($username === $authData['username'] && password_verify($password, $authData['password_hash'])) {
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

// 处理修改密码逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePassword'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    $authData = json_decode(file_get_contents($authFile), true);
    
    // 验证当前密码
    if (!password_verify($currentPassword, $authData['password_hash'])) {
        $passwordError = '当前密码不正确';
    } 
    // 验证新密码和确认密码是否一致
    elseif ($newPassword !== $confirmPassword) {
        $passwordError = '新密码和确认密码不一致';
    } 
    // 验证新密码强度
    elseif (strlen($newPassword) < 8) {
        $passwordError = '新密码长度至少需要8个字符';
    } 
    else {
        // 更新密码
        $authData['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        file_put_contents($authFile, json_encode($authData));
        $passwordMessage = '密码修改成功';
    }
}

// 处理添加记录逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addRecord'])) {
    $taskDate = date('Y-m-d l');
    $taskName = $_POST['taskName'];
    $triggerExposure = isset($_POST['triggerExposure']) ? '是' : '否';
    $taskNamePublic = isset($_POST['taskNamePublic']) ? '是' : '否';
    $taskContentPublic = isset($_POST['taskContentPublic']) ? '是' : '否';

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
        'triggerExposure' => $triggerExposure,
        'taskNamePublic' => $taskNamePublic,
        'taskContentPublic' => $taskContentPublic
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
    $taskNamePublic = isset($_POST['taskNamePublic']) ? '是' : '否';
    $taskContentPublic = isset($_POST['taskContentPublic']) ? '是' : '否';

    $records = json_decode(file_get_contents($dbFile), true);
    $record = $records[$recordIndex];
    $record['taskDate'] = $taskDate;
    $record['taskName'] = $taskName;
    $record['triggerExposure'] = $triggerExposure;
    $record['taskNamePublic'] = $taskNamePublic;
    $record['taskContentPublic'] = $taskContentPublic;

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

// 处理批量设置公开状态逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batchSetPublic'])) {
    $taskNamePublic = $_POST['batchTaskNamePublic'];
    $taskContentPublic = $_POST['batchTaskContentPublic'];

    $records = json_decode(file_get_contents($dbFile), true);
    foreach ($records as &$record) {
        $record['taskNamePublic'] = $taskNamePublic;
        $record['taskContentPublic'] = $taskContentPublic;
    }
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
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .media-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* 预览尺寸调整为原来的2/3（小1/3） */
        .media-item {
            width: calc(33.333% - 10px); /* 原宽度的2/3 */
            max-width: 133px; /* 原最大宽度200px的2/3 */
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: auto;
            cursor: pointer;
        }

        /* 视频封面样式 */
        .video-placeholder {
            position: relative;
            width: 100%;
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .play-button {
            position: absolute;
            width: 40px; /* 按比例缩小播放按钮 */
            height: 40px;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .play-button::after {
            content: "";
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 0 10px 15px; /* 按比例缩小三角形 */
            border-color: transparent transparent transparent white;
            margin-left: 3px;
        }

        .video-placeholder:hover .play-button {
            background-color: rgba(255, 255, 255, 0.7);
        }

        .video-placeholder:hover .play-button::after {
            border-color: transparent transparent transparent black;
        }

        #lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.95);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #lightbox-content {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        #lightbox-content img,
        #lightbox-content video {
            max-width: 100%;
            max-height: calc(100vh - 100px);
            object-fit: contain;
        }

        .lightbox-nav {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            z-index: 1001;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .lightbox-btn {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            flex: 1;
            max-width: 150px;
        }

        .lightbox-btn:hover {
            background-color: rgba(255, 255, 255, 0.7);
            color: black;
        }

        .lightbox-btn:disabled {
            background-color: rgba(100, 100, 100, 0.5);
            cursor: not-allowed;
        }

        .task-index {
            margin-bottom: 20px;
        }

        .task-index-header {
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .task-index-header::before {
            content: '▶ ';
            font-size: 12px;
            margin-right: 5px;
        }

        .task-index-header.expanded::before {
            content: '▼ ';
        }

        .task-index-content {
            display: none;
            padding-left: 20px;
        }

        .task-index-content.expanded {
            display: block;
        }

        .back-to-top {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            z-index: 100;
        }

        .back-to-top:hover {
            background-color: #45a049;
        }

        .batch-settings {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .lightbox-task-name {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
            margin: 0 10px;
            flex: 2;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .password-change-form {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .password-change-form h3 {
            margin-top: 0;
        }
        
        .password-message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        
        .password-message.success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .password-message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* 添加滑动提示 */
        .swipe-hint {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(255, 255, 255, 0.5);
            font-size: 18px;
            pointer-events: none;
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .swipe-hint.visible {
            opacity: 1;
        }
    </style>
</head>

<body>
    <a href="#" class="back-to-top">返回首页</a>

    <?php if (!$isAdmin): ?>
        <h2>登录</h2>
        <?php if (isset($error)): ?>
            <p style="color: red;">
                <?php echo $error; ?>
            </p>
        <?php endif; ?>
        <form method="post">
            <label for="username">用户名:</label>
            <input type="text" id="username" name="username" required><br>
            <label for="password">密码:</label>
            <input type="password" id="password" name="password" required><br>
            <input type="submit" name="login" value="登录">
        </form>
    <?php else: ?>
        <h2>欢迎，管理员！</h2>
        <a href="?logout">退出登录</a>
        
        <!-- 修改密码表单 -->
        <div class="password-change-form">
            <h3>修改密码</h3>
            <?php if (isset($passwordMessage)): ?>
                <div class="password-message success">
                    <?php echo $passwordMessage; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($passwordError)): ?>
                <div class="password-message error">
                    <?php echo $passwordError; ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <label for="currentPassword">当前密码:</label><br>
                <input type="password" id="currentPassword" name="currentPassword" required><br>
                <label for="newPassword">新密码:</label><br>
                <input type="password" id="newPassword" name="newPassword" required><br>
                <label for="confirmPassword">确认新密码:</label><br>
                <input type="password" id="confirmPassword" name="confirmPassword" required><br>
                <p style="color: #666; font-size: 14px;">密码长度至少需要8个字符</p>
                <input type="submit" name="changePassword" value="修改密码">
            </form>
        </div>
        
        <!-- 批量设置公开状态 -->
        <div class="batch-settings">
            <h3>批量设置公开状态</h3>
            <form method="post">
                <label>
                    <input type="radio" name="batchTaskNamePublic" value="是" checked>
                    任务名称全部公开
                </label>
                <label>
                    <input type="radio" name="batchTaskNamePublic" value="否">
                    任务名称全部隐藏
                </label>
                <br>
                <label>
                    <input type="radio" name="batchTaskContentPublic" value="是" checked>
                    任务内容全部公开
                </label>
                <label>
                    <input type="radio" name="batchTaskContentPublic" value="否">
                    任务内容全部隐藏
                </label>
                <br>
                <input type="submit" name="batchSetPublic" value="批量设置">
            </form>
        </div>

        <h2>添加记录</h2>
        <form method="post" enctype="multipart/form-data">
            <label for="taskName">任务名称:</label><br>
            <textarea id="taskName" name="taskName" rows="4" cols="50" required></textarea><br>
            <label for="media">任务内容（图片或视频）:</label><br>
            <input type="file" id="media" name="media[]" multiple><br>
            <label for="triggerExposure">触发曝光:</label>
            <input type="checkbox" id="triggerExposure" name="triggerExposure"><br>
            <label>
                <input type="checkbox" id="taskNamePublic" name="taskNamePublic" checked>
                任务名称公开
            </label><br>
            <label>
                <input type="checkbox" id="taskContentPublic" name="taskContentPublic" checked>
                任务内容公开
            </label><br>
            <input type="submit" name="addRecord" value="添加记录">
        </form>
    <?php endif; ?>

    <h2 class="task-index-header" id="toggle-all-index">所有任务快捷索引</h2>
    <div class="task-index-content" id="all-index-content">
        <ul>
            <?php foreach ($records as $index => $record): ?>
                <?php if ($isAdmin || $record['taskNamePublic'] === '是'): ?>
                    <li><a href="#record-<?php echo $index; ?>">任务 <?php echo $index + 1; ?>: <?php echo date('Y-m-d', strtotime($record['taskDate'])); ?> - <?php echo $isAdmin || $record['taskNamePublic'] === '是' ? substr($record['taskName'], 0, 30) : '【内容隐藏】'; if (strlen($record['taskName']) > 30) echo '...'; ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2>最近的 20 条记录</h2>
    <table>
        <thead>
            <tr>
                <th>任务时间</th>
                <th>任务名称</th>
                <?php if ($isAdmin): ?>
                    <th>任务名称公开</th>
                    <th>任务内容公开</th>
                <?php endif; ?>
                <?php if ($isAdmin || (isset($record['taskContentPublic']) && $record['taskContentPublic'] === '是')): ?>
                    <th>任务内容</th>
                <?php endif; ?>
                <th>触发曝光</th>
                <?php if ($isAdmin): ?>
                    <th>操作</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentRecords as $index => $record): ?>
                <?php $originalIndex = count($records) - 1 - $index; ?>
                <tr id="record-<?php echo $originalIndex; ?>">
                    <td>
                        <?php if ($isAdmin): ?>
                            <input type="text" name="taskDate" value="<?php echo $record['taskDate']; ?>" required>
                        <?php else: ?>
                            <?php echo $record['taskDate']; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isAdmin): ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                <textarea name="taskName" rows="4" cols="50"><?php echo $record['taskName']; ?></textarea><br>
                        <?php else: ?>
                            <?php echo $record['taskNamePublic'] === '是' ? nl2br($record['taskName']) : '【内容隐藏】'; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <input type="checkbox" name="taskNamePublic"
                                <?php echo $record['taskNamePublic'] === '是' ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <input type="checkbox" name="taskContentPublic"
                                <?php echo $record['taskContentPublic'] === '是' ? 'checked' : ''; ?>>
                        </td>
                    <?php endif; ?>
                    <?php if ($isAdmin || (isset($record['taskContentPublic']) && $record['taskContentPublic'] === '是')): ?>
                        <td>
                            <?php if ($isAdmin || $record['taskContentPublic'] === '是'): ?>
                                <div class="media-container">
                                    <?php foreach ($record['mediaFiles'] as $mediaIndex => $mediaFile): ?>
                                        <?php
                                        $fileExtension = pathinfo($mediaFile, PATHINFO_EXTENSION);
                                        if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                            <div class="media-item">
                                                <img src="<?php echo $mediaFile; ?>" alt="Media"
                                                    onclick="openLightbox('<?php echo $mediaFile; ?>', 'image', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                <?php if ($isAdmin): ?>
                                                    <a href="#"
                                                        onclick="deleteMedia(<?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>); return false;">删除</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (in_array(strtolower($fileExtension), ['mp4', 'webm', 'ogg'])): ?>
                                            <div class="media-item">
                                                <!-- 视频预览使用封面图代替，不预先加载 -->
                                                <div class="video-placeholder" 
                                                     onclick="openLightbox('<?php echo $mediaFile; ?>', 'video', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                    <!-- 视频封面图 -->
                                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='133' height='100' %3E%3Crect width='100%25' height='100%25' fill='%23333'/%3E%3C/svg%3E" 
                                                         alt="视频预览" class="video-thumbnail">
                                                    <div class="play-button"></div>
                                                </div>
                                                <?php if ($isAdmin): ?>
                                                    <a href="#"
                                                        onclick="deleteMedia(<?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>); return false;">删除</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <label for="media">添加更多媒体:</label><br>
                                    <input type="file" id="media" name="media[]" multiple><br>
                                <?php endif; ?>
                            <?php else: ?>
                                【内容隐藏】
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($isAdmin): ?>
                            <input type="checkbox" name="triggerExposure"
                                <?php echo $record['triggerExposure'] === '是' ? 'checked' : ''; ?>>
                        <?php else: ?>
                            <?php echo $record['triggerExposure']; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <input type="submit" name="editRecord" value="保存修改">
                            </form>
                            <a href="#" onclick="deleteRecord(<?php echo $originalIndex; ?>); return false;">删除任务</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="lightbox" onclick="closeLightbox()">
        <div id="lightbox-content"></div>
        <div class="swipe-hint" id="swipe-hint">左右滑动切换图片，上下滑动切换任务</div>
        <div class="lightbox-nav">
            <button class="lightbox-btn" id="prev-task" onclick="navigateTask(-1); event.stopPropagation();">上一个任务</button>
            <button class="lightbox-btn" id="prev-media" onclick="navigateMedia(-1); event.stopPropagation();">上一张</button>
            <div class="lightbox-task-name" id="current-task-name"></div>
            <button class="lightbox-btn" id="next-media" onclick="navigateMedia(1); event.stopPropagation();">下一张</button>
            <button class="lightbox-btn" id="next-task" onclick="navigateTask(1); event.stopPropagation();">下一个任务</button>
        </div>
    </div>

    <script>
        // 存储当前查看的媒体信息
        let currentRecordIndex = -1;
        let currentMediaIndex = -1;
        let startX = 0;
        let startY = 0;
        let isSwiping = false;
        let swipeHintTimeout;
        
        // 初始化快捷索引状态
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('toggle-all-index');
            const content = document.getElementById('all-index-content');
            
            header.addEventListener('click', function() {
                content.classList.toggle('expanded');
                header.classList.toggle('expanded');
            });
        });

        // 任务名称数组
        const taskNames = <?php echo json_encode(array_column($records, 'taskName')); ?>;
        // 媒体文件数据
        const mediaData = <?php echo json_encode(array_map(function($record) { 
            return array_map(function($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return [
                    'path' => $file,
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video'
                ];
            }, $record['mediaFiles']);
        }, $records)); ?>;

        function openLightbox(src, type, recordIndex, mediaIndex) {
            // 保存当前查看的媒体位置信息
            currentRecordIndex = recordIndex;
            currentMediaIndex = mediaIndex;
            
            const lightbox = document.getElementById('lightbox');
            const lightboxContent = document.getElementById('lightbox-content');
            lightboxContent.innerHTML = '';
            
            if (type === 'image') {
                const img = document.createElement('img');
                img.src = src;
                img.onload = function() {
                    updateLightboxContent();
                };
                lightboxContent.appendChild(img);
            } else if (type === 'video') {
                // 灯箱中加载并播放视频
                const video = document.createElement('video');
                video.src = src;
                video.controls = true;
                video.autoplay = true; // 灯箱中打开时自动播放
                video.onloadedmetadata = function() {
                    updateLightboxContent();
                };
                lightboxContent.appendChild(video);
            }
            
            // 更新任务名称显示
            document.getElementById('current-task-name').textContent = 
                taskNames[recordIndex] || '未知任务';
            
            // 更新导航按钮状态
            updateNavButtons();
            
            lightbox.style.display = 'flex';
            window.addEventListener('resize', updateLightboxContent);
            
            // 显示滑动提示并在几秒后隐藏
            showSwipeHint();
        }

        function updateLightboxContent() {
            const lightboxContent = document.getElementById('lightbox-content');
            const mediaElement = lightboxContent.querySelector('img, video');
            
            if (mediaElement) {
                mediaElement.style.maxHeight = 'calc(100vh - 100px)';
            }
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            const video = lightbox.querySelector('video');
            
            // 关闭时暂停视频并释放资源
            if (video) {
                video.pause();
                video.src = ''; // 清空视频源，停止加载
            }
            
            lightbox.style.display = 'none';
            currentRecordIndex = -1;
            currentMediaIndex = -1;
            window.removeEventListener('resize', updateLightboxContent);
            
            // 清除提示超时
            clearTimeout(swipeHintTimeout);
        }

        function updateNavButtons() {
            if (currentRecordIndex === -1) return;
            
            // 获取当前任务的媒体文件数量
            const mediaCount = mediaData[currentRecordIndex] ? mediaData[currentRecordIndex].length : 0;
            // 获取总任务数量
            const totalRecords = mediaData.length;
            
            // 更新上一张/下一张按钮状态
            document.getElementById('prev-media').disabled = currentMediaIndex <= 0;
            document.getElementById('next-media').disabled = currentMediaIndex >= mediaCount - 1;
            
            // 更新上一个/下一个任务按钮状态
            document.getElementById('prev-task').disabled = currentRecordIndex <= 0;
            document.getElementById('next-task').disabled = currentRecordIndex >= totalRecords - 1;
        }

        function navigateMedia(direction) {
            if (currentRecordIndex === -1 || currentMediaIndex === -1) return;
            
            // 获取当前任务的媒体文件信息
            const mediaFiles = mediaData[currentRecordIndex];
            
            if (!mediaFiles || mediaFiles.length === 0) return;
            
            // 计算新的媒体索引
            const newMediaIndex = currentMediaIndex + direction;
            
            // 检查索引是否有效
            if (newMediaIndex >= 0 && newMediaIndex < mediaFiles.length) {
                const newMedia = mediaFiles[newMediaIndex];
                openLightbox(newMedia.path, newMedia.type, currentRecordIndex, newMediaIndex);
            }
        }

        function navigateTask(direction) {
            if (currentRecordIndex === -1) return;
            
            // 计算新的任务索引
            const newRecordIndex = currentRecordIndex + direction;
            const totalRecords = mediaData.length;
            
            // 检查任务索引是否有效
            if (newRecordIndex >= 0 && newRecordIndex < totalRecords) {
                // 获取新任务的媒体文件信息
                const mediaFiles = mediaData[newRecordIndex];
                
                // 如果新任务有媒体文件，显示第一个媒体
                if (mediaFiles && mediaFiles.length > 0) {
                    openLightbox(mediaFiles[0].path, mediaFiles[0].type, newRecordIndex, 0);
                }
            }
        }

        function deleteMedia(recordIndex, mediaIndex) {
            if (confirm('确定要删除此媒体文件吗？')) {
                window.location.href = `index.php?deleteMedia=${mediaIndex}&recordIndex=${recordIndex}`;
            }
        }

        function deleteRecord(recordIndex) {
            if (confirm('确定要删除此任务吗？此操作将删除所有相关媒体文件！')) {
                window.location.href = `index.php?deleteRecord=${recordIndex}`;
            }
        }

        // 显示滑动提示
        function showSwipeHint() {
            const hint = document.getElementById('swipe-hint');
            hint.classList.add('visible');
            
            // 5秒后隐藏提示
            clearTimeout(swipeHintTimeout);
            swipeHintTimeout = setTimeout(() => {
                hint.classList.remove('visible');
            }, 5000);
        }

        // 返回首页按钮功能
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 触摸事件 - 滑动开始
        document.getElementById('lightbox-content').addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isSwiping = true;
            e.stopPropagation();
        }, false);

        // 触摸事件 - 滑动移动
        document.getElementById('lightbox-content').addEventListener('touchmove', function(e) {
            if (!isSwiping) return;
            e.preventDefault(); // 防止页面滚动
            e.stopPropagation();
        }, false);

        // 触摸事件 - 滑动结束
        document.getElementById('lightbox-content').addEventListener('touchend', function(e) {
            if (!isSwiping) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = endX - startX;
            const diffY = endY - startY;
            
            // 确定滑动方向 - 水平滑动优先于垂直滑动
            if (Math.abs(diffX) > Math.abs(diffY)) {
                // 水平滑动 - 切换图片
                if (Math.abs(diffX) > 50) { // 最小滑动距离
                    if (diffX > 0) {
                        // 向右滑动 - 上一张图片
                        navigateMedia(-1);
                    } else {
                        // 向左滑动 - 下一张图片
                        navigateMedia(1);
                    }
                }
            } else {
                // 垂直滑动 - 切换任务
                if (Math.abs(diffY) > 50) { // 最小滑动距离
                    if (diffY > 0) {
                        // 向下滑动 - 上一个任务
                        navigateTask(-1);
                    } else {
                        // 向上滑动 - 下一个任务
                        navigateTask(1);
                    }
                }
            }
            
            isSwiping = false;
            e.stopPropagation();
        }, false);

        // 键盘导航
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('lightbox').style.display === 'flex') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft' && document.getElementById('lightbox').style.display === 'flex') {
                navigateMedia(-1);
                e.preventDefault();
            } else if (e.key === 'ArrowRight' && document.getElementById('lightbox').style.display === 'flex') {
                navigateMedia(1);
                e.preventDefault();
            } else if (e.key === 'ArrowUp' && document.getElementById('lightbox').style.display === 'flex') {
                navigateTask(1);
                e.preventDefault();
            } else if (e.key === 'ArrowDown' && document.getElementById('lightbox').style.display === 'flex') {
                navigateTask(-1);
                e.preventDefault();
            }
        });
    </script>
</body>

</html>
