<?php
// 开启会话
session_start();

// 设置字符编码
header('Content-Type: text/html; charset=utf-8');

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

// 格式化日期为带中文星期的格式
function formatDateWithWeekday($date) {
    $timestamp = strtotime($date);
    $weekMap = ['日', '一', '二', '三', '四', '五', '六'];
    $weekday = $weekMap[date('w', $timestamp)];
    return date('Y-m-d', $timestamp) . " 星期{$weekday}";
}

// 处理添加记录逻辑
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addRecord'])) {
    $taskDate = $_POST['taskDate'];
    $taskDate = formatDateWithWeekday($taskDate);

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
    $taskDate = formatDateWithWeekday($taskDate);

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
            width: calc(33.333% - 10px);
            /* 原宽度的2/3 */
            max-width: 133px;
            /* 原最大宽度200px的2/3 */
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
            width: 40px;
            /* 按比例缩小播放按钮 */
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
            border-width: 10px 0 10px 15px;
            /* 按比例缩小三角形 */
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
            <label for="taskDate">任务日期:</label><br>
            <input type="date" id="taskDate" name="taskDate" required><br>
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
                    <li><a href="#record-<?php echo $index; ?>">任务 <?php echo $index + 1; ?>: <?php echo $record['taskDate']; ?> - <?php echo $isAdmin || $record['taskNamePublic'] === '是' ? substr($record['taskName'], 0, 30) : '【内容隐藏】'; if (strlen($record['taskName']) > 30) echo '...'; ?></a></li>
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
                            <div style="display: flex; flex-direction: column;">
                                <div style="display: flex; align-items: center;">
                                    <?php echo htmlspecialchars($record['taskDate']); ?>
                                    <form method="post" style="margin-left: 10px;">
                                        <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                        <input type="hidden" name="taskDate" value="<?php echo date('Y-m-d', strtotime($record['taskDate'])); ?>">
                                        <input type="submit" name="editDate" value="编辑日期">
                                    </form>
                                </div>
                                <?php if (isset($_POST['editDate']) && $_POST['recordIndex'] == $originalIndex): ?>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                        <input type="date" name="taskDate" value="<?php echo date('Y-m-d', strtotime($record['taskDate'])); ?>" required>
                                        <input type="submit" name="editRecord" value="保存">
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php echo htmlspecialchars($record['taskDate']); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isAdmin || $record['taskNamePublic'] === '是'): ?>
                            <?php echo htmlspecialchars($record['taskName']); ?>
                        <?php else: ?>
                            【内容隐藏】
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <form method="post">
                                <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                <select name="taskNamePublic" onchange="this.form.submit()">
                                    <option value="是" <?php echo $record['taskNamePublic'] === '是' ? 'selected' : ''; ?>>是</option>
                                    <option value="否" <?php echo $record['taskNamePublic'] === '否' ? 'selected' : ''; ?>>否</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="recordIndex" value="<?php echo $originalIndex; ?>">
                                <select name="taskContentPublic" onchange="this.form.submit()">
                                    <option value="是" <?php echo $record['taskContentPublic'] === '是' ? 'selected' : ''; ?>>是</option>
                                    <option value="否" <?php echo $record['taskContentPublic'] === '否' ? 'selected' : ''; ?>>否</option>
                                </select>
                            </form>
                        </td>
                    <?php endif; ?>
                    <?php if ($isAdmin || (isset($record['taskContentPublic']) && $record['taskContentPublic'] === '是')): ?>
                        <td>
                            <div class="media-container">
                                <?php foreach ($record['mediaFiles'] as $mediaIndex => $mediaFile): ?>
                                    <div class="media-item">
                                        <?php if (pathinfo($mediaFile, PATHINFO_EXTENSION) === 'mp4'): ?>
                                            <div class="video-placeholder">
                                                <video controls preload="none">
                                                    <source src="<?php echo $mediaFile; ?>" type="video/mp4">
                                                    您的浏览器不支持视频播放。
                                                </video>
                                                <div class="play-button"></div>
                                            </div>
                                        <?php else: ?>
                                            <img src="<?php echo $mediaFile; ?>" alt="媒体文件">
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <a href="?deleteMedia=<?php echo $mediaIndex; ?>&recordIndex=<?php echo $originalIndex; ?>"
                                                onclick="return confirm('确定要删除这个媒体文件吗？')">删除</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php echo $record['triggerExposure']; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <a href="?deleteRecord=<?php echo $originalIndex; ?>"
                                onclick="return confirm('确定要删除这条记录吗？')">删除</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="lightbox">
        <div id="lightbox-content">
            <!-- 图片或视频将在这里显示 -->
        </div>
        <div class="lightbox-nav">
            <button class="lightbox-btn" id="prev-media" disabled>上一个</button>
            <div class="lightbox-task-name" id="lightbox-task-name"></div>
            <button class="lightbox-btn" id="next-media" disabled>下一个</button>
        </div>
        <div class="swipe-hint" id="swipe-hint">左右滑动切换媒体</div>
    </div>

    <script>
        const lightbox = document.getElementById('lightbox');
        const lightboxContent = document.getElementById('lightbox-content');
        const prevMediaButton = document.getElementById('prev-media');
        const nextMediaButton = document.getElementById('next-media');
        const lightboxTaskName = document.getElementById('lightbox-task-name');
        const swipeHint = document.getElementById('swipe-hint');

        let currentMediaIndex = 0;
        let currentRecordIndex = 0;
        let allMediaFiles = [];

        function openLightbox(mediaFiles, recordIndex, taskName, startIndex) {
            allMediaFiles = mediaFiles;
            currentRecordIndex = recordIndex;
            currentMediaIndex = startIndex;
            lightboxTaskName.textContent = taskName;
            showMedia(currentMediaIndex);
            lightbox.style.display = 'flex';
            setTimeout(() => {
                swipeHint.classList.add('visible');
            }, 1000);
            setTimeout(() => {
                swipeHint.classList.remove('visible');
            }, 3000);
        }

        function showMedia(index) {
            lightboxContent.innerHTML = '';
            const mediaFile = allMediaFiles[index];
            if (mediaFile.endsWith('.mp4')) {
                const video = document.createElement('video');
                video.controls = true;
                video.preload = 'none';
                const source = document.createElement('source');
                source.src = mediaFile;
                source.type = 'video/mp4';
                video.appendChild(source);
                lightboxContent.appendChild(video);
            } else {
                const img = document.createElement('img');
                img.src = mediaFile;
                lightboxContent.appendChild(img);
            }
            prevMediaButton.disabled = index === 0;
            nextMediaButton.disabled = index === allMediaFiles.length - 1;
        }

        prevMediaButton.addEventListener('click', () => {
            if (currentMediaIndex > 0) {
                currentMediaIndex--;
                showMedia(currentMediaIndex);
            }
        });

        nextMediaButton.addEventListener('click', () => {
            if (currentMediaIndex < allMediaFiles.length - 1) {
                currentMediaIndex++;
                showMedia(currentMediaIndex);
            }
        });

        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                lightbox.style.display = 'none';
            }
        });

        const mediaItems = document.querySelectorAll('.media-item img, .media-item video');
        mediaItems.forEach((item, index) => {
            item.addEventListener('click', () => {
                const recordIndex = parseInt(item.closest('tr').id.split('-')[1]);
                const record = <?php echo json_encode($records); ?>[recordIndex];
                const taskName = record.taskName;
                const mediaFiles = record.mediaFiles;
                const startIndex = Array.from(item.closest('.media-container').children).indexOf(item.closest('.media-item'));
                openLightbox(mediaFiles, recordIndex, taskName, startIndex);
            });
        });

        const toggleAllIndex = document.getElementById('toggle-all-index');
        const allIndexContent = document.getElementById('all-index-content');
        toggleAllIndex.addEventListener('click', () => {
            toggleAllIndex.classList.toggle('expanded');
            allIndexContent.classList.toggle('expanded');
        });
    </script>
</body>

</html>
