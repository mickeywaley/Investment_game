<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

// 数据文件路径
define('DATA_FILE', __DIR__ . '/data.json');

// 上传目录
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// 确保上传目录存在
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// 默认管理员账号
$defaultAdmin = [
    'username' => 'admin',
    'password' => password_hash('admin123', PASSWORD_DEFAULT)
];

// 初始化数据文件
function initDataFile() {
    if (!file_exists(DATA_FILE)) {
        $data = [
            'admin' => $GLOBALS['defaultAdmin'],
            'records' => []
        ];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// 获取数据
function getData() {
    initDataFile();
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true);
}

// 保存数据
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 验证登录
function verifyLogin($username, $password) {
    $data = getData();
    if ($username === $data['admin']['username'] && password_verify($password, $data['admin']['password'])) {
        $_SESSION['admin'] = true;
        return true;
    }
    return false;
}

// 处理上传文件
function handleUpload() {
    if (empty($_FILES['media']['name'])) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm'];
    $fileType = $_FILES['media']['type'];

    if (!in_array($fileType, $allowedTypes)) {
        return ['error' => '不支持的文件类型'];
    }

    $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
    $targetPath = UPLOAD_DIR . $fileName;

    if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
        return [
            'name' => $fileName,
            'type' => $fileType,
            'isImage' => strpos($fileType, 'image/') === 0
        ];
    } else {
        return ['error' => '文件上传失败'];
    }
}

// 删除文件
function deleteFile($fileName) {
    if ($fileName && file_exists(UPLOAD_DIR . $fileName)) {
        unlink(UPLOAD_DIR . $fileName);
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        if (verifyLogin($_POST['username'], $_POST['password'])) {
            header('Location: index.php');
            exit;
        } else {
            $loginError = '用户名或密码错误';
        }
    } elseif (isset($_POST['logout'])) {
        unset($_SESSION['admin']);
        header('Location: index.php');
        exit;
    } elseif (isset($_POST['addRecord']) && isset($_SESSION['admin'])) {
        $data = getData();
        
        $uploadResult = handleUpload();
        if (isset($uploadResult['error'])) {
            $uploadError = $uploadResult['error'];
        } else {
            $newRecord = [
                'id' => uniqid(),
                'time' => date('Y-m-d H:i:s'),
                'weekday' => ['日', '一', '二', '三', '四', '五', '六'][date('w')],
                'title' => $_POST['title'],
                'media' => $uploadResult,
                'exposure' => isset($_POST['exposure']) ? true : false
            ];
            
            array_unshift($data['records'], $newRecord);
            saveData($data);
            header('Location: index.php');
            exit;
        }
    } elseif (isset($_POST['editRecord']) && isset($_SESSION['admin'])) {
        $data = getData();
        $recordId = $_POST['recordId'];
        $recordIndex = null;
        
        // 查找记录索引
        foreach ($data['records'] as $index => $record) {
            if ($record['id'] === $recordId) {
                $recordIndex = $index;
                break;
            }
        }
        
        if ($recordIndex !== null) {
            $uploadResult = handleUpload();
            
            // 如果有新文件上传，删除旧文件
            if ($uploadResult && !isset($uploadResult['error']) && isset($data['records'][$recordIndex]['media'])) {
                deleteFile($data['records'][$recordIndex]['media']['name']);
            }
            
            if (isset($uploadResult['error'])) {
                $uploadError = $uploadResult['error'];
            } else {
                // 更新记录
                $data['records'][$recordIndex]['title'] = $_POST['title'];
                $data['records'][$recordIndex]['exposure'] = isset($_POST['exposure']) ? true : false;
                
                if ($uploadResult) {
                    $data['records'][$recordIndex]['media'] = $uploadResult;
                }
                
                saveData($data);
                header('Location: index.php');
                exit;
            }
        }
    } elseif (isset($_POST['deleteRecord']) && isset($_SESSION['admin'])) {
        $data = getData();
        $recordId = $_POST['recordId'];
        $recordIndex = null;
        
        // 查找记录索引
        foreach ($data['records'] as $index => $record) {
            if ($record['id'] === $recordId) {
                $recordIndex = $index;
                break;
            }
        }
        
        if ($recordIndex !== null) {
            // 删除关联文件
            if (isset($data['records'][$recordIndex]['media'])) {
                deleteFile($data['records'][$recordIndex]['media']['name']);
            }
            
            // 从数组中删除记录
            array_splice($data['records'], $recordIndex, 1);
            saveData($data);
        }
        
        header('Location: index.php');
        exit;
    }
}

// 获取记录
$data = getData();
$records = array_slice($data['records'], 0, 20);

// 处理编辑表单显示
$editRecord = null;
if (isset($_GET['edit']) && isset($_SESSION['admin'])) {
    $editId = $_GET['edit'];
    foreach ($records as $record) {
        if ($record['id'] === $editId) {
            $editRecord = $record;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>骰子游戏记录表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { text-align: center; }
        .login-form, .add-form, .edit-form, .records { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #45a049; }
        .error { color: red; margin-bottom: 10px; }
        .record { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
        .record-header { font-weight: bold; margin-bottom: 10px; }
        .record-media { margin-top: 10px; max-width: 300px; }
        .record-media img, .record-media video { max-width: 100%; height: auto; }
        .admin-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .pagination { text-align: center; margin-top: 20px; }
        .exposure-tag { background-color: #ffeb3b; color: #333; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; margin-left: 10px; }
        .record-actions { margin-top: 10px; }
        .record-actions button { margin-right: 10px; }
        .edit-form { display: none; }
    </style>
</head>
<body>
    <h1>骰子游戏记录表</h1>

    <?php if (!isset($_SESSION['admin'])): ?>
        <div class="login-form">
            <h2>管理员登录</h2>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo $loginError; ?></div>
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
                <button type="submit" name="login">登录</button>
            </form>
        </div>
    <?php else: ?>
        <div class="admin-options">
            <button id="showAddFormBtn">添加记录</button>
            <form method="post" style="display: inline;">
                <button type="submit" name="logout">退出登录</button>
            </form>
        </div>

        <div id="addForm" class="add-form" style="display: none;">
            <h2>添加记录</h2>
            <?php if (isset($uploadError)): ?>
                <div class="error"><?php echo $uploadError; ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">任务名称:</label>
                    <textarea id="title" name="title" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="media">任务图片或视频:</label>
                    <input type="file" id="media" name="media" accept="image/*,video/*">
                    <div id="mediaPreview" class="record-media" style="display: none;"></div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="exposure"> 是否触发曝光
                    </label>
                </div>
                <button type="submit" name="addRecord">保存记录</button>
                <button type="button" id="cancelAddBtn">取消</button>
            </form>
        </div>

        <div id="editForm" class="edit-form">
            <h2>编辑记录</h2>
            <?php if (isset($uploadError)): ?>
                <div class="error"><?php echo $uploadError; ?></div>
            <?php endif; ?>
            <?php if ($editRecord): ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="recordId" value="<?php echo $editRecord['id']; ?>">
                    <div class="form-group">
                        <label for="editTitle">任务名称:</label>
                        <textarea id="editTitle" name="title" rows="4" required><?php echo htmlspecialchars($editRecord['title']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editMedia">任务图片或视频:</label>
                        <input type="file" id="editMedia" name="media" accept="image/*,video/*">
                        <div class="record-media">
                            <?php if (isset($editRecord['media'])): ?>
                                <?php if ($editRecord['media']['isImage']): ?>
                                    <img src="uploads/<?php echo $editRecord['media']['name']; ?>" alt="当前图片" style="max-width: 300px;">
                                <?php else: ?>
                                    <video controls style="max-width: 300px;">
                                        <source src="uploads/<?php echo $editRecord['media']['name']; ?>" type="<?php echo $editRecord['media']['type']; ?>">
                                        您的浏览器不支持视频播放
                                    </video>
                                <?php endif; ?>
                                <p>当前文件: <?php echo $editRecord['media']['name']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="exposure" <?php echo $editRecord['exposure'] ? 'checked' : ''; ?>> 是否触发曝光
                        </label>
                    </div>
                    <button type="submit" name="editRecord">更新记录</button>
                    <button type="button" id="cancelEditBtn">取消</button>
                </form>
            <?php else: ?>
                <p>记录不存在</p>
                <button type="button" id="cancelEditBtn">返回</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="records">
        <h2>最近记录</h2>
        <?php foreach ($records as $record): ?>
            <div class="record">
                <div class="record-header">
                    <?php echo date('Y年m月d日', strtotime($record['time'])) . ' 星期' . $record['weekday']; ?>
                    <?php if ($record['exposure']): ?>
                        <span class="exposure-tag">已曝光</span>
                    <?php endif; ?>
                </div>
                <div class="record-content">
                    <pre><?php echo htmlspecialchars($record['title']); ?></pre>
                    <?php if (isset($record['media']) && isset($_SESSION['admin'])): ?>
                        <div class="record-media">
                            <?php if ($record['media']['isImage']): ?>
                                <img src="uploads/<?php echo $record['media']['name']; ?>" alt="任务图片" onclick="openFullscreen('uploads/<?php echo $record['media']['name']; ?>', 'image')">
                            <?php else: ?>
                                <video controls onclick="openFullscreen('uploads/<?php echo $record['media']['name']; ?>', 'video')">
                                    <source src="uploads/<?php echo $record['media']['name']; ?>" type="<?php echo $record['media']['type']; ?>">
                                    您的浏览器不支持视频播放
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['admin'])): ?>
                    <div class="record-actions">
                        <button onclick="location.href='index.php?edit=<?php echo $record['id']; ?>'">编辑</button>
                        <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除这条记录吗？')">
                            <input type="hidden" name="recordId" value="<?php echo $record['id']; ?>">
                            <button type="submit" name="deleteRecord">删除</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($records)): ?>
            <p>暂无记录</p>
        <?php endif; ?>
    </div>

    <div id="fullscreenModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
        <div style="position: relative; max-width: 90%; max-height: 90%;">
            <button id="closeModalBtn" style="position: absolute; top: -30px; right: 0; background-color: #fff; color: #000; padding: 5px 10px; border-radius: 3px;">关闭</button>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        // 显示/隐藏添加表单
        document.getElementById('showAddFormBtn').addEventListener('click', function() {
            const addForm = document.getElementById('addForm');
            const editForm = document.getElementById('editForm');
            
            addForm.style.display = 'block';
            editForm.style.display = 'none';
        });

        // 取消添加
        document.getElementById('cancelAddBtn').addEventListener('click', function() {
            document.getElementById('addForm').style.display = 'none';
        });

        // 取消编辑
        document.getElementById('cancelEditBtn').addEventListener('click', function() {
            document.getElementById('editForm').style.display = 'none';
        });

        // 文件上传预览 - 添加表单
        document.getElementById('media').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const preview = document.getElementById('mediaPreview');
            preview.innerHTML = '';
            preview.style.display = 'block';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = '上传预览';
                img.style.maxWidth = '300px';
                preview.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.controls = true;
                video.src = URL.createObjectURL(file);
                video.style.maxWidth = '300px';
                preview.appendChild(video);
            }
        });

        // 文件上传预览 - 编辑表单
        document.getElementById('editMedia').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const preview = this.parentElement.querySelector('.record-media');
            preview.innerHTML = '';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = '上传预览';
                img.style.maxWidth = '300px';
                preview.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.controls = true;
                video.src = URL.createObjectURL(file);
                video.style.maxWidth = '300px';
                preview.appendChild(video);
            }
            
            preview.appendChild(document.createElement('p')).textContent = '新上传的文件将替换当前文件';
        });

        // 全屏查看媒体
        function openFullscreen(src, type) {
            const modal = document.getElementById('fullscreenModal');
            const modalContent = document.getElementById('modalContent');
            
            modalContent.innerHTML = '';
            
            if (type === 'image') {
                const img = document.createElement('img');
                img.src = src;
                img.alt = '全屏查看';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '80vh';
                modalContent.appendChild(img);
            } else if (type === 'video') {
                const video = document.createElement('video');
                video.controls = true;
                video.autoplay = true;
                video.src = src;
                video.style.maxWidth = '100%';
                video.style.maxHeight = '80vh';
                modalContent.appendChild(video);
            }
            
            modal.style.display = 'flex';
        }

        // 关闭全屏模态框
        document.getElementById('closeModalBtn').addEventListener('click', function() {
            const modal = document.getElementById('fullscreenModal');
            const video = modal.querySelector('video');
            if (video) video.pause();
            modal.style.display = 'none';
        });

        // 点击模态框背景关闭
        document.getElementById('fullscreenModal').addEventListener('click', function(e) {
            if (e.target === this) {
                const video = this.querySelector('video');
                if (video) video.pause();
                this.style.display = 'none';
            }
        });

        // 页面加载时处理编辑表单显示
        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.getElementById('editForm');
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('edit') && <?php echo isset($_SESSION['admin']) ? 'true' : 'false'; ?>) {
                editForm.style.display = 'block';
            }
        });
    </script>
</body>
</html>
    
