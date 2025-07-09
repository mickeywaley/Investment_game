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
        /* 全局样式 */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #f9f9f9;
        }

        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 30px;
            display: flex;
            align-items: center;
        }

        h2::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 24px;
            background-color: #3498db;
            margin-right: 10px;
            border-radius: 4px;
        }

        a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
        }

        a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* 按钮样式 */
        button,
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        button:hover,
        input[type="submit"]:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        button:disabled,
        input[type="submit"]:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* 表单样式 */
        form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        form:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* 表格样式 */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-radius: 8px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: #2c3e50;
        }

        tr {
            transition: background-color 0.3s;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        /* 媒体容器样式 */
        .media-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .media-item {
            width: calc(25% - 15px);
            min-width: 180px;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
        }

        .media-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: auto;
            cursor: pointer;
            display: block;
            transition: filter 0.3s;
        }

        .media-item:hover img,
        .media-item:hover video {
            filter: brightness(1.05);
        }

        .media-item a {
            display: block;
            text-align: center;
            padding: 8px 0;
            background-color: #f5f5f5;
            color: #e74c3c;
            font-size: 13px;
            transition: all 0.3s;
        }

        .media-item a:hover {
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
        }

        /* 灯箱样式 */
        #lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s;
            backdrop-filter: blur(5px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        #lightbox-content {
            max-width: 90%;
            max-height: 85vh;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #lightbox-content img,
        #lightbox-content video {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            animation: scaleIn 0.3s;
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .lightbox-nav {
            position: fixed;
            bottom: 40px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            z-index: 1001;
            padding: 0 20px;
        }

        .lightbox-btn {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 120px;
            backdrop-filter: blur(5px);
        }

        .lightbox-btn:hover {
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
            transform: translateY(-2px);
        }

        .lightbox-btn:disabled {
            background-color: rgba(100, 100, 100, 0.5);
            cursor: not-allowed;
            color: #ddd;
            transform: none;
        }

        /* 任务索引样式 */
        .task-index {
            margin-bottom: 30px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .task-index:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .task-index-header {
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            transition: color 0.3s;
        }

        .task-index-header:hover {
            color: #3498db;
        }

        .task-index-header::before {
            content: '▶ ';
            font-size: 14px;
            margin-right: 8px;
            color: #3498db;
            transition: transform 0.3s;
        }

        .task-index-header.expanded::before {
            transform: rotate(90deg);
        }

        .task-index-content {
            display: none;
            padding-left: 25px;
            max-height: 200px;
            overflow-y: auto;
            transition: all 0.3s;
        }

        .task-index-content.expanded {
            display: block;
        }

        .task-index-content ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .task-index-content li {
            margin-bottom: 8px;
        }

        .task-index-content a {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .task-index-content a:hover {
            background-color: rgba(52, 152, 219, 0.1);
            text-decoration: none;
        }

        /* 返回顶部按钮 */
        .back-to-top {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2ecc71;
            color: white;
            padding: 10px 15px;
            border-radius: 50px;
            text-decoration: none;
            z-index: 99;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            opacity: 0.8;
        }

        .back-to-top:hover {
            background-color: #27ae60;
            text-decoration: none;
            opacity: 1;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        /* 批量设置区域 */
        .batch-settings {
            margin: 30px 0;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .batch-settings:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .batch-settings h3 {
            margin-top: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }

        .batch-settings h3::before {
            content: '⚙️';
            margin-right: 10px;
        }

        .batch-settings label {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .batch-settings label:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .batch-settings input[type="radio"] {
            margin-right: 5px;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .media-item {
                width: calc(50% - 15px);
            }

            .lightbox-nav {
                gap: 10px;
                bottom: 20px;
            }

            .lightbox-btn {
                padding: 10px 15px;
                font-size: 14px;
                min-width: 100px;
            }

            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                margin: 0 0 1rem 0;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }

            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
            }

            td:before {
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                color: #2c3e50;
            }

            td:nth-of-type(1):before { content: "任务时间:"; }
            td:nth-of-type(2):before { content: "任务名称:"; }
            td:nth-of-type(3):before { content: "任务名称公开:"; }
            td:nth-of-type(4):before { content: "任务内容公开:"; }
            td:nth-of-type(5):before { content: "任务内容:"; }
            td:nth-of-type(6):before { content: "触发曝光:"; }
            td:nth-of-type(7):before { content: "操作:"; }
        }

        /* 错误消息 */
        .error-message {
            color: #e74c3c;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>

<body>
    <a href="#" class="back-to-top">返回首页</a>

    <?php if (!$isAdmin): ?>
        <div class="error-message">
            <?php if (isset($error)): ?>
                <?php echo $error; ?>
            <?php endif; ?>
        </div>
        <h2>管理员登录</h2>
        <form method="post">
            <div>
                <label for="username">用户名:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">密码:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="submit" name="login" value="登录">
        </form>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>欢迎，管理员！</h2>
            <a href="?logout" style="color: #e74c3c;">退出登录</a>
        </div>
        
        <!-- 批量设置公开状态 -->
        <div class="batch-settings">
            <h3>批量设置公开状态</h3>
            <form method="post">
                <div>
                    <label>
                        <input type="radio" name="batchTaskNamePublic" value="是" checked>
                        任务名称全部公开
                    </label>
                    <label>
                        <input type="radio" name="batchTaskNamePublic" value="否">
                        任务名称全部隐藏
                    </label>
                </div>
                <div>
                    <label>
                        <input type="radio" name="batchTaskContentPublic" value="是" checked>
                        任务内容全部公开
                    </label>
                    <label>
                        <input type="radio" name="batchTaskContentPublic" value="否">
                        任务内容全部隐藏
                    </label>
                </div>
                <input type="submit" name="batchSetPublic" value="批量设置">
            </form>
        </div>

        <h2>添加新记录</h2>
        <form method="post" enctype="multipart/form-data">
            <div>
                <label for="taskName">任务名称:</label>
                <textarea id="taskName" name="taskName" rows="4" cols="50" required></textarea>
            </div>
            <div>
                <label for="media">任务内容（图片或视频）:</label>
                <input type="file" id="media" name="media[]" multiple>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="triggerExposure" name="triggerExposure">
                    触发曝光
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="taskNamePublic" name="taskNamePublic" checked>
                    任务名称公开
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" id="taskContentPublic" name="taskContentPublic" checked>
                    任务内容公开
                </label>
            </div>
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
                                <textarea name="taskName" rows="4" cols="50"><?php echo $record['taskName']; ?></textarea>
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
                                                <img src="<?php echo $mediaFile; ?>" alt="任务图片"
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
                                    <div>
                                        <label for="media">添加更多媒体:</label>
                                        <input type="file" id="media" name="media[]" multiple>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="color: #999; font-style: italic;">【内容隐藏】</div>
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
                            <a href="#" onclick="deleteRecord(<?php echo $originalIndex; ?>); return false;" style="color: #e74c3c;">删除任务</a>
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
            
            // 为所有任务索引项添加动画效果
            const taskLinks = document.querySelectorAll('.task-index-content a');
            taskLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // 添加点击效果
                    e.target.classList.add('clicked');
                    setTimeout(() => {
                        e.target.classList.remove('clicked');
                    }, 300);
                });
            });
            
            // 添加ESC键关闭灯箱的功能
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeLightbox();
                }
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
                img.alt = '任务媒体内容';
                img.onload = function() {
                    // 图片加载完成后，确保图片完整显示
                    updateLightboxContent();
                };
                lightboxContent.appendChild(img);
            } else if (type === 'video') {
                const video = document.createElement('video');
                video.src = src;
                video.controls = true;
                video.alt = '任务视频内容';
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
                mediaElement.style.maxHeight = 'calc(100vh - 120px)';
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
            const mediaCounts = <?php echo json_encode(array_map(function($record) { return count($record['mediaFiles']); }, $records)); ?>;
            const mediaCount = mediaCounts[currentRecordIndex] || 0;
            // 获取总任务数量
            const totalRecords = <?php echo count($records); ?>;
            
            // 更新上一张/下一张按钮状态
            document.getElementById('prev-media').disabled = currentMediaIndex <= 0 || mediaCount <= 1;
            document.getElementById('next-media').disabled = currentMediaIndex >= mediaCount - 1 || mediaCount <= 1;
            
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
                        'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video',
                        'ext' => $ext
                    ];
                }, $record['mediaFiles']);
            }, $records)); ?>[currentRecordIndex] || [];
            
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
                }, $records)); ?>[newRecordIndex] || [];
                
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
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>

</html>    
