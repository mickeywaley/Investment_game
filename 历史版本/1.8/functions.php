<?php
// 确保目录存在
function ensureDirectoryExists($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// 获取用户数据
function getUsers() {
    ensureDirectoryExists(__DIR__ . '/data');
    $usersFile = __DIR__ . '/data/auth.json';
    
    if (file_exists($usersFile)) {
        $content = file_get_contents($usersFile);
        return json_decode($content, true) ?: [];
    }
    
    // 如果文件不存在，创建默认管理员账户
    $defaultUser = [
        [
            'id' => 1,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'isAdmin' => true
        ]
    ];
    
    file_put_contents($usersFile, json_encode($defaultUser, JSON_PRETTY_PRINT));
    return $defaultUser;
}

// 保存用户数据
function saveUsers($users) {
    ensureDirectoryExists(__DIR__ . '/data');
    $usersFile = __DIR__ . '/data/auth.json';
    return file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 获取任务数据
function getTasks() {
    ensureDirectoryExists(__DIR__ . '/data');
    $tasksFile = __DIR__ . '/data/data.json';
    
    if (file_exists($tasksFile)) {
        $content = file_get_contents($tasksFile);
        return json_decode($content, true) ?: [];
    }
    
    return [];
}

// 保存任务数据
function saveTasks($tasks) {
    ensureDirectoryExists(__DIR__ . '/data');
    $tasksFile = __DIR__ . '/data/data.json';
    return file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 上传文件
function uploadFiles($inputName, $allowedExtensions) {
    $uploadedFiles = [];
    
    ensureDirectoryExists(__DIR__ . '/uploads');
    
    if (!empty($_FILES[$inputName]['name'][0])) {
        for ($i = 0; $i < count($_FILES[$inputName]['name']); $i++) {
            $fileName = $_FILES[$inputName]['name'][$i];
            $fileTmpName = $_FILES[$inputName]['tmp_name'][$i];
            $fileSize = $_FILES[$inputName]['size'][$i];
            $fileError = $_FILES[$inputName]['error'][$i];
            
            if ($fileError !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                continue;
            }
            
            $newFileName = uniqid() . '.' . $fileExtension;
            $destination = __DIR__ . '/uploads/' . $newFileName;
            
            if (move_uploaded_file($fileTmpName, $destination)) {
                $uploadedFiles[] = $newFileName;
            }
        }
    }
    
    return $uploadedFiles;
}
?>
    