<?php
session_start();
require_once 'functions.php';

// 检查是否为管理员
if (!isset($_SESSION['user']['isAdmin']) || !$_SESSION['user']['isAdmin']) {
    echo json_encode([
        'success' => false,
        'message' => '权限不足'
    ]);
    exit;
}

// 获取参数
$action = $_POST['action'] ?? '';
$taskIds = isset($_POST['taskIds']) ? json_decode($_POST['taskIds'], true) : [];

if (empty($action) || empty($taskIds)) {
    echo json_encode([
        'success' => false,
        'message' => '参数错误'
    ]);
    exit;
}

// 获取现有任务
$tasks = getTasks();

// 处理批量操作
foreach ($taskIds as $taskId) {
    $taskIndex = array_search($taskId, array_column($tasks, 'id'));
    
    if ($taskIndex !== false) {
        switch ($action) {
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
if (saveTasks($tasks)) {
    echo json_encode([
        'success' => true,
        'message' => '批量操作成功'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '批量操作失败'
    ]);
}
?>
    