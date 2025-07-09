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

        .media-item {
            width: calc(25% - 10px);
            max-width: 200px;
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: auto;
            cursor: pointer;
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
                                                <video controls onclick="openLightbox('<?php echo $mediaFile; ?>', 'video', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                    <source src="<?php echo $mediaFile; ?>"
                                                        type="video/<?php echo $fileExtension; ?>">
                                                    你的浏览器不支持播放此视频。
                                                </video>
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
        <div class="lightbox-nav">
            <button class="lightbox-btn" id="prev-task" onclick="navigateTask(-1); event.stopPropagation();">上一个任务</button>
            <button class="lightbox-btn" id="prev-media" onclick="navigateMedia(-1); event.stopPropagation();">上一张</button>
            <button class="lightbox-btn" id="next-media" onclick="navigateMedia(1); event.stopPropagation();">下一张</button>
            <button class="lightbox-btn" id="next-task" onclick="navigateTask(1); event.stopPropagation();">下一个任务</button>
        </div>
    </div>

    <script>
        // 存储当前查看的媒体信息
        let currentRecordIndex = -1;
        let currentMediaIndex = -1;
        
        // 初始化快捷索引状态
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('toggle-all-index');
            const content = document.getElementById('all-index-content');
            
            header.addEventListener('click', function() {
                content.classList.toggle('expanded');
                header.classList.toggle('expanded');
            });
        });

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
                    // 图片加载完成后，确保图片完整显示
                    updateLightboxContent();
                };
                lightboxContent.appendChild(img);
            } else if (type === 'video') {
                const video = document.createElement('video');
                video.src = src;
                video.controls = true;
                video.onloadedmetadata = function() {
                    // 视频元数据加载完成后，确保视频完整显示
                    updateLightboxContent();
                };
                lightboxContent.appendChild(video);
            }
            
            // 更新导航按钮状态
            updateNavButtons();
            
            lightbox.style.display = 'flex';
            // 窗口大小变化时重新调整图片显示
            window.addEventListener('resize', updateLightboxContent);
        }

        function updateLightboxContent() {
            const lightboxContent = document.getElementById('lightbox-content');
            const mediaElement = lightboxContent.querySelector('img, video');
            
            if (mediaElement) {
                // 确保媒体元素完整显示在容器内
                mediaElement.style.maxHeight = 'calc(100vh - 100px)';
            }
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.style.display = 'none';
            // 重置当前查看的媒体信息
            currentRecordIndex = -1;
            currentMediaIndex = -1;
            // 移除窗口大小变化事件监听
            window.removeEventListener('resize', updateLightboxContent);
        }

        function updateNavButtons() {
            // 获取当前任务的媒体文件数量
            const mediaCount = <?php echo json_encode(array_map(function($record) { return count($record['mediaFiles']); }, $records)); ?>[currentRecordIndex];
            // 获取总任务数量
            const totalRecords = <?php echo count($records); ?>;
            
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
            const mediaFiles = <?php echo json_encode(array_map(function($record) { 
                return array_map(function($file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    return [
                        'path' => $file,
                        'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video'
                    ];
                }, $record['mediaFiles']);
            }, $records)); ?>[currentRecordIndex];
            
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
            const totalRecords = <?php echo count($records); ?>;
            
            // 检查任务索引是否有效
            if (newRecordIndex >= 0 && newRecordIndex < totalRecords) {
                // 获取新任务的媒体文件信息
                const mediaFiles = <?php echo json_encode(array_map(function($record) { 
                    return array_map(function($file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        return [
                            'path' => $file,
                            'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video'
                        ];
                    }, $record['mediaFiles']);
                }, $records)); ?>[newRecordIndex];
                
                // 如果新任务有媒体文件，显示第一个媒体
                if (mediaFiles.length > 0) {
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

        // 返回首页按钮功能
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 按ESC键关闭lightbox
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('lightbox').style.display === 'flex') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft' && document.getElementById('lightbox').style.display === 'flex') {
                navigateMedia(-1);
                e.preventDefault();
            } else if (e.key === 'ArrowRight' && document.getElementById('lightbox').style.display === 'flex') {
                navigateMedia(1);
                e.preventDefault();
            }
        });
    </script>
</body>

</html>
