<?php
session_start();
require_once 'functions.php';

// 检查用户是否已登录
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('非法请求');
}

// 获取任务ID
$taskId = $_POST['id'] ?? '';
if (empty($taskId)) {
    die('任务ID不能为空');
}

// 获取现有任务
$tasks = getTasks();

// 查找任务索引
$taskIndex = array_search($taskId, array_column($tasks, 'id'));
if ($taskIndex === false) {
    die('任务不存在');
}

// 检查权限
if (!$tasks[$taskIndex]['creator_id'] == $_SESSION['user']['id'] && !$_SESSION['user']['isAdmin']) {
    die('权限不足');
}

// 删除任务相关文件
$task = $tasks[$taskIndex];
if (isset($task['images'])) {
    foreach ($task['images'] as $image) {
        $imagePath = __DIR__ . '/uploads/' . $image;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
}

if (isset($task['videos'])) {
    foreach ($task['videos'] as $video) {
        $videoPath = __DIR__ . '/uploads/' . $video;
        if (file_exists($videoPath)) {
            unlink($videoPath);
        }
    }
}

// 移除任务
unset($tasks[$taskIndex]);
$tasks = array_values($tasks); // 重置索引

// 保存更新后的任务
if (saveTasks($tasks)) {
    echo json_encode([
        'success' => true,
        'message' => '任务删除成功'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '任务删除失败'
    ]);
}
?>
    