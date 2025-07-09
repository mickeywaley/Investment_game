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
    <title>任务记录表</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 15px;
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        h1, h2, h3 {
            margin: 15px 0;
            color: #333;
        }

        .container {
            width: 100%;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin: 5px 0;
        }

        .btn:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input, textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .login-form {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .error {
            color: #f44336;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .media-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }

        .media-item {
            /* 响应式媒体预览 */
            width: calc(25% - 10px);
            max-width: 150px;
        }

        @media (max-width: 768px) {
            .media-item {
                width: calc(33.333% - 10px);
            }
            
            /* 手机端表格优化 */
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                margin-bottom: 15px;
                border: 1px solid #ddd;
            }
            
            td {
                position: relative;
                padding-left: 50%;
            }
            
            td:before {
                position: absolute;
                top: 12px;
                left: 12px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                content: attr(data-label);
            }
        }

        @media (max-width: 480px) {
            .media-item {
                width: calc(50% - 10px);
            }
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: auto;
            cursor: pointer;
            border-radius: 4px;
        }

        /* 视频封面样式 */
        .video-placeholder {
            position: relative;
            width: 100%;
            background-color: #000;
            border-radius: 4px;
            overflow: hidden;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .play-button::after {
            content: "";
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 0 10px 15px;
            border-color: transparent transparent transparent white;
            margin-left: 3px;
        }

        .batch-settings {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .index-list {
            margin: 15px 0;
            padding-left: 20px;
        }

        .index-list li {
            margin: 5px 0;
        }

        .index-toggle {
            cursor: pointer;
            color: #2196F3;
        }

        .index-content {
            display: none;
            margin: 10px 0;
        }

        .index-content.show {
            display: block;
        }

        /* 灯箱样式 */
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
            gap: 10px;
            z-index: 1001;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .lightbox-btn {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            flex: 1;
            max-width: 120px;
        }

        .lightbox-task-name {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
            margin: 0 5px;
            flex: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            z-index: 100;
        }

        /* 管理功能区域 */
        .admin-actions {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .action-buttons {
            margin: 10px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
    <header>
        <h1>任务记录表</h1>
        <?php if ($isAdmin): ?>
            <div>
                <span>欢迎管理员</span>
                <a href="?logout" class="btn btn-danger">退出登录</a>
            </div>
        <?php endif; ?>
    </header>

    <a href="#" class="back-to-top">↑</a>

    <?php if (!$isAdmin): ?>
        <div class="login-form">
            <h2>管理员登录</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <input type="submit" name="login" value="登录" class="btn">
            </form>
        </div>
    <?php else: ?>
        <div class="admin-actions">
            <h3>管理功能</h3>
            
            <!-- 批量设置公开状态 -->
            <div class="batch-settings">
                <h4>批量设置公开状态</h4>
                <form method="post">
                    <div class="form-group">
                        <label>
                            <input type="radio" name="batchTaskNamePublic" value="是" checked>
                            任务名称全部公开
                        </label>
                        <label>
                            <input type="radio" name="batchTaskNamePublic" value="否">
                            任务名称全部隐藏
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="batchTaskContentPublic" value="是" checked>
                            任务内容全部公开
                        </label>
                        <label>
                            <input type="radio" name="batchTaskContentPublic" value="否">
                            任务内容全部隐藏
                        </label>
                    </div>
                    <input type="submit" name="batchSetPublic" value="批量设置" class="btn">
                </form>
            </div>

            <!-- 添加记录表单 -->
            <div class="add-record">
                <h3>添加新记录</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="taskName">任务名称:</label>
                        <textarea id="taskName" name="taskName" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="media">任务内容（图片或视频）:</label>
                        <input type="file" id="media" name="media[]" multiple>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="triggerExposure" name="triggerExposure">
                            触发曝光
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="taskNamePublic" name="taskNamePublic" checked>
                            任务名称公开
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="taskContentPublic" name="taskContentPublic" checked>
                            任务内容公开
                        </label>
                    </div>
                    
                    <input type="submit" name="addRecord" value="添加记录" class="btn">
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 任务索引 -->
    <div>
        <h2>
            <span class="index-toggle" id="toggle-all-index">所有任务快捷索引</span>
        </h2>
        <div class="index-content" id="all-index-content">
            <ul class="index-list">
                <?php foreach ($records as $index => $record): ?>
                    <?php if ($isAdmin || $record['taskNamePublic'] === '是'): ?>
                        <li>
                            <a href="#record-<?php echo $index; ?>">
                                任务 <?php echo $index + 1; ?>: 
                                <?php echo date('Y-m-d', strtotime($record['taskDate'])); ?> - 
                                <?php 
                                $displayName = $isAdmin || $record['taskNamePublic'] === '是' ? $record['taskName'] : '【内容隐藏】';
                                echo strlen($displayName) > 30 ? substr($displayName, 0, 30) . '...' : $displayName;
                                ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h2>最近的 20 条记录</h2>
    <table>
        <thead>
            <tr>
                <th>任务时间</th>
                <th>任务名称</th>
                <?php if ($isAdmin): ?>
                    <th>名称公开</th>
                    <th>内容公开</th>
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
                    <td data-label="任务时间">
                        <?php if ($isAdmin): ?>
                            <input type="text" name="taskDate" value="<?php echo $record['taskDate']; ?>" required>
                        <?php else: ?>
                            <?php echo $record['taskDate']; ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="任务名称">
                        <?php if ($isAdmin): ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                <textarea name="taskName"><?php echo $record['taskName']; ?></textarea>
                        <?php else: ?>
                            <?php echo $record['taskNamePublic'] === '是' ? nl2br($record['taskName']) : '【内容隐藏】'; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td data-label="名称公开">
                            <input type="checkbox" name="taskNamePublic"
                                <?php echo $record['taskNamePublic'] === '是' ? 'checked' : ''; ?>>
                        </td>
                        <td data-label="内容公开">
                            <input type="checkbox" name="taskContentPublic"
                                <?php echo $record['taskContentPublic'] === '是' ? 'checked' : ''; ?>>
                        </td>
                    <?php endif; ?>
                    <?php if ($isAdmin || (isset($record['taskContentPublic']) && $record['taskContentPublic'] === '是')): ?>
                        <td data-label="任务内容">
                            <?php if ($isAdmin || $record['taskContentPublic'] === '是'): ?>
                                <div class="media-container">
                                    <?php foreach ($record['mediaFiles'] as $mediaIndex => $mediaFile): ?>
                                        <?php
                                        $fileExtension = pathinfo($mediaFile, PATHINFO_EXTENSION);
                                        if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                            <div class="media-item">
                                                <img src="<?php echo $mediaFile; ?>" alt="图片"
                                                    onclick="openLightbox('<?php echo $mediaFile; ?>', 'image', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                <?php if ($isAdmin): ?>
                                                    <a href="#" 
                                                       onclick="deleteMedia(<?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>); return false;"
                                                       class="btn btn-danger">删除</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (in_array(strtolower($fileExtension), ['mp4', 'webm', 'ogg'])): ?>
                                            <div class="media-item">
                                                <div class="video-placeholder" 
                                                     onclick="openLightbox('<?php echo $mediaFile; ?>', 'video', <?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>)">
                                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='100' %3E%3Crect width='100%25' height='100%25' fill='%23333'/%3E%3C/svg%3E" 
                                                         alt="视频封面">
                                                    <div class="play-button"></div>
                                                </div>
                                                <?php if ($isAdmin): ?>
                                                    <a href="#" 
                                                       onclick="deleteMedia(<?php echo $originalIndex; ?>, <?php echo $mediaIndex; ?>); return false;"
                                                       class="btn btn-danger">删除</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <div class="form-group">
                                        <label for="media-<?php echo $originalIndex; ?>">添加更多媒体:</label>
                                        <input type="file" id="media-<?php echo $originalIndex; ?>" name="media[]" multiple>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                【内容隐藏】
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td data-label="触发曝光">
                        <?php if ($isAdmin): ?>
                            <input type="checkbox" name="triggerExposure"
                                <?php echo $record['triggerExposure'] === '是' ? 'checked' : ''; ?>>
                        <?php else: ?>
                            <?php echo $record['triggerExposure']; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td data-label="操作">
                            <input type="submit" name="editRecord" value="保存" class="btn">
                            </form>
                            <a href="#" 
                               onclick="deleteRecord(<?php echo $originalIndex; ?>); return false;"
                               class="btn btn-danger">删除</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="lightbox" onclick="closeLightbox()">
        <div id="lightbox-content"></div>
        <div class="lightbox-nav">
            <button class="lightbox-btn" id="prev-task" onclick="navigateTask(-1); event.stopPropagation();">上一任务</button>
            <button class="lightbox-btn" id="prev-media" onclick="navigateMedia(-1); event.stopPropagation();">上一张</button>
            <div class="lightbox-task-name" id="current-task-name"></div>
            <button class="lightbox-btn" id="next-media" onclick="navigateMedia(1); event.stopPropagation();">下一张</button>
            <button class="lightbox-btn" id="next-task" onclick="navigateTask(1); event.stopPropagation();">下一任务</button>
        </div>
    </div>

    <script>
        // 存储当前查看的媒体信息
        let currentRecordIndex = -1;
        let currentMediaIndex = -1;
        
        // 初始化快捷索引
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('toggle-all-index');
            const content = document.getElementById('all-index-content');
            
            header.addEventListener('click', function() {
                content.classList.toggle('show');
            });

            // 移动端表格优化 - 为每个td添加data-label属性
            const tableCells = document.querySelectorAll('td');
            tableCells.forEach(cell => {
                if (!cell.hasAttribute('data-label')) {
                    const thIndex = Array.from(cell.parentNode.children).indexOf(cell);
                    const thText = cell.parentNode.parentNode.parentNode.querySelector('th:nth-child(' + (thIndex + 1) + ')').textContent;
                    cell.setAttribute('data-label', thText);
                }
            });
        });

        // 任务名称数组
        const taskNames = <?php echo json_encode(array_column($records, 'taskName')); ?>;

        function openLightbox(src, type, recordIndex, mediaIndex) {
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
                const video = document.createElement('video');
                video.src = src;
                video.controls = true;
                video.autoplay = true;
                video.onloadedmetadata = function() {
                    updateLightboxContent();
                };
                lightboxContent.appendChild(video);
            }
            
            document.getElementById('current-task-name').textContent = 
                (taskNames[recordIndex] || '未知任务').substring(0, 20) + (taskNames[recordIndex].length > 20 ? '...' : '');
            
            updateNavButtons();
            lightbox.style.display = 'flex';
            window.addEventListener('resize', updateLightboxContent);
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
            
            if (video) {
                video.pause();
                video.src = '';
            }
            
            lightbox.style.display = 'none';
            currentRecordIndex = -1;
            currentMediaIndex = -1;
            window.removeEventListener('resize', updateLightboxContent);
        }

        function updateNavButtons() {
            const mediaCount = <?php echo json_encode(array_map(function($record) { return count($record['mediaFiles']); }, $records)); ?>[currentRecordIndex] || 0;
            const totalRecords = <?php echo count($records); ?>;
            
            document.getElementById('prev-media').disabled = currentMediaIndex <= 0;
            document.getElementById('next-media').disabled = currentMediaIndex >= mediaCount - 1;
            
            document.getElementById('prev-task').disabled = currentRecordIndex <= 0;
            document.getElementById('next-task').disabled = currentRecordIndex >= totalRecords - 1;
        }

        function navigateMedia(direction) {
            if (currentRecordIndex === -1 || currentMediaIndex === -1) return;
            
            const mediaFiles = <?php echo json_encode(array_map(function($record) { 
                return array_map(function($file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    return [
                        'path' => $file,
                        'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video'
                    ];
                }, $record['mediaFiles']);
            }, $records)); ?>[currentRecordIndex] || [];
            
            const newMediaIndex = currentMediaIndex + direction;
            
            if (newMediaIndex >= 0 && newMediaIndex < mediaFiles.length) {
                const newMedia = mediaFiles[newMediaIndex];
                openLightbox(newMedia.path, newMedia.type, currentRecordIndex, newMediaIndex);
            }
        }

        function navigateTask(direction) {
            if (currentRecordIndex === -1) return;
            
            const newRecordIndex = currentRecordIndex + direction;
            const totalRecords = <?php echo count($records); ?>;
            
            if (newRecordIndex >= 0 && newRecordIndex < totalRecords) {
                const mediaFiles = <?php echo json_encode(array_map(function($record) { 
                    return array_map(function($file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        return [
                            'path' => $file,
                            'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video'
                        ];
                    }, $record['mediaFiles']);
                }, $records)); ?>[newRecordIndex] || [];
                
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

        // 返回顶部按钮
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

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
            }
        });
    </script>
</body>

</html>
