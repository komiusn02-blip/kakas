<?php
session_start();

// Konfigurasi Firebase
$firebaseConfig = [
    'apiKey' => "AIzaSyD5xCQYLUGnbuS1v5wHn1-k5uovAd8lKe4",
    'authDomain' => "chat-riletime.firebaseapp.com",
    'databaseURL' => "https://chat-riletime-default-rtdb.firebaseio.com/",
    'projectId' => "chat-riletime",
    'storageBucket' => "chat-riletime.firebasestorage.app",
    'messagingSenderId' => "467675334077",
    'appId' => "1:467675334077:web:71cb18759056471059fed4"
];

// Database sederhana untuk users dan login history
$usersFile = 'users.json';
$loginHistoryFile = 'login_history.json';
$statusFile = 'status.json';

// Fungsi untuk membaca users dari file
function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) {
        file_put_contents($usersFile, json_encode([]));
    }
    return json_decode(file_get_contents($usersFile), true) ?: [];
}

// Fungsi untuk menulis users ke file
function writeUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Fungsi untuk membaca login history
function readLoginHistory() {
    global $loginHistoryFile;
    if (!file_exists($loginHistoryFile)) {
        file_put_contents($loginHistoryFile, json_encode([]));
    }
    return json_decode(file_get_contents($loginHistoryFile), true) ?: [];
}

// Fungsi untuk menulis login history
function writeLoginHistory($history) {
    global $loginHistoryFile;
    file_put_contents($loginHistoryFile, json_encode($history, JSON_PRETTY_PRINT));
}

// Fungsi untuk membaca status
function readStatus() {
    global $statusFile;
    if (!file_exists($statusFile)) {
        file_put_contents($statusFile, json_encode([]));
    }
    return json_decode(file_get_contents($statusFile), true) ?: [];
}

// Fungsi untuk menulis status
function writeStatus($status) {
    global $statusFile;
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
}

// Fungsi untuk membersihkan input
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Fungsi untuk upload file
function uploadFile($file, $type = 'image') {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($file["name"]);
    $targetFile = $targetDir . $fileName;
    
    if ($type === 'image') {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 5000000; // 5MB
    } else { // video
        $allowedTypes = ['mp4', 'mov', 'avi', 'mkv'];
        $maxSize = 20000000; // 20MB
    }
    
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check jika file adalah gambar/video yang valid
    if ($type === 'image') {
        $check = getimagesize($file["tmp_name"]);
        if ($check === false) {
            return ['success' => false, 'error' => 'File bukan gambar.'];
        }
    } else {
        // Check video type
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Format video tidak didukung.'];
        }
    }
    
    // Check ukuran file
    if ($file["size"] > $maxSize) {
        return ['success' => false, 'error' => 'Ukuran file terlalu besar.'];
    }
    
    // Allow certain file formats
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Format file tidak diizinkan.'];
    }
    
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ['success' => true, 'file_path' => $targetFile, 'file_type' => $type];
    } else {
        return ['success' => false, 'error' => 'Terjadi kesalahan saat upload.'];
    }
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'register') {
        $username = cleanInput($_POST['username']);
        $password = $_POST['password'];
        $profile_name = cleanInput($_POST['profile_name']);
        
        $users = readUsers();
        
        // Check jika username sudah ada
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                echo json_encode(['success' => false, 'error' => 'Username sudah terdaftar.']);
                exit;
            }
        }
        
        // Handle upload gambar
        $profile_image = 'assets/default-avatar.png';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $uploadResult = uploadFile($_FILES['profile_image'], 'image');
            if ($uploadResult['success']) {
                $profile_image = $uploadResult['file_path'];
            }
        }
        
        // Tambah user baru
        $newUser = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'profile_name' => $profile_name,
            'profile_image' => $profile_image,
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => md5($username . date('Y-m-d H:i:s'))
        ];
        
        $users[] = $newUser;
        writeUsers($users);
        
        echo json_encode(['success' => true, 'message' => 'Registrasi berhasil!']);
        exit;
    }
    
    if ($_POST['action'] === 'login') {
        $username = cleanInput($_POST['username']);
        $password = $_POST['password'];
        
        $users = readUsers();
        $userFound = null;
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $userFound = $user;
                break;
            }
        }
        
        if ($userFound) {
            $_SESSION['user'] = [
                'username' => $userFound['username'],
                'profile_name' => $userFound['profile_name'],
                'profile_image' => $userFound['profile_image'],
                'user_id' => $userFound['user_id'],
                'session_id' => uniqid()
            ];
            
            echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Username atau password salah.']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_all_users') {
        $users = readUsers();
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }
    
    if ($_POST['action'] === 'add_status') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
            exit;
        }
        
        $user_id = $_SESSION['user']['user_id'];
        $status_text = cleanInput($_POST['status_text'] ?? '');
        $status_type = $_POST['status_type'] ?? 'text';
        
        $statusData = [
            'user_id' => $user_id,
            'username' => $_SESSION['user']['username'],
            'profile_name' => $_SESSION['user']['profile_name'],
            'profile_image' => $_SESSION['user']['profile_image'],
            'type' => $status_type,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'viewed_by' => []
        ];
        
        if ($status_type === 'text' && !empty($status_text)) {
            $statusData['content'] = $status_text;
        } elseif (($status_type === 'image' || $status_type === 'video') && isset($_FILES['status_file'])) {
            $uploadResult = uploadFile($_FILES['status_file'], $status_type);
            if (!$uploadResult['success']) {
                echo json_encode(['success' => false, 'error' => $uploadResult['error']]);
                exit;
            }
            $statusData['content'] = $uploadResult['file_path'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Data status tidak valid.']);
            exit;
        }
        
        $allStatus = readStatus();
        $status_id = uniqid();
        $allStatus[$status_id] = $statusData;
        writeStatus($allStatus);
        
        echo json_encode(['success' => true, 'status_id' => $status_id]);
        exit;
    }
    
    if ($_POST['action'] === 'get_all_status') {
        $allStatus = readStatus();
        $current_time = date('Y-m-d H:i:s');
        
        // Filter status yang masih valid (belum expired)
        $activeStatus = array_filter($allStatus, function($status) use ($current_time) {
            return $status['expires_at'] > $current_time;
        });
        
        // Kelompokkan status berdasarkan user
        $statusByUser = [];
        foreach ($activeStatus as $status_id => $status) {
            $user_id = $status['user_id'];
            if (!isset($statusByUser[$user_id])) {
                $statusByUser[$user_id] = [
                    'user_info' => [
                        'username' => $status['username'],
                        'profile_name' => $status['profile_name'],
                        'profile_image' => $status['profile_image']
                    ],
                    'statuses' => []
                ];
            }
            $statusByUser[$user_id]['statuses'][$status_id] = $status;
        }
        
        echo json_encode(['success' => true, 'status' => $statusByUser]);
        exit;
    }
    
    if ($_POST['action'] === 'mark_status_viewed') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
            exit;
        }
        
        $status_id = $_POST['status_id'];
        $viewer_id = $_SESSION['user']['user_id'];
        
        $allStatus = readStatus();
        
        if (isset($allStatus[$status_id])) {
            if (!in_array($viewer_id, $allStatus[$status_id]['viewed_by'])) {
                $allStatus[$status_id]['viewed_by'][] = $viewer_id;
                writeStatus($allStatus);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Status tidak ditemukan.']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'upload_media') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
            exit;
        }
        
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileType = $file['type'];
            
            if (strpos($fileType, 'image/') === 0) {
                $uploadResult = uploadFile($file, 'image');
            } else if (strpos($fileType, 'video/') === 0) {
                $uploadResult = uploadFile($file, 'video');
            } else {
                echo json_encode(['success' => false, 'error' => 'Format file tidak didukung.']);
                exit;
            }
            
            if ($uploadResult['success']) {
                echo json_encode(['success' => true, 'file_path' => $uploadResult['file_path']]);
            } else {
                echo json_encode(['success' => false, 'error' => $uploadResult['error']]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Tidak ada file yang diupload.']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'logout') {
        if (isset($_SESSION['user'])) {
            session_destroy();
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get all registered users (for display)
$allUsers = readUsers();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat App - Professional Messenger</title>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Auth Container */
        .auth-container {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
            padding: 5px;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .auth-tab.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .file-input {
            padding: 12px;
        }
        
        .preview-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
            margin: 10px 0;
            display: none;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .error-message {
            color: #ff4757;
            font-size: 13px;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #fff5f5;
            border-radius: 8px;
            border: 1px solid #ffcccc;
        }
        
        .success-message {
            color: #2ed573;
            font-size: 13px;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #f0fff4;
            border-radius: 8px;
            border: 1px solid #c6f6d5;
        }
        
        /* Mobile First Design */
        .mobile-container {
            width: 100%;
            max-width: 100%;
            height: 100vh;
            background: white;
            position: relative;
            overflow: hidden;
            display: none;
        }
        
        .mobile-container.active {
            display: block;
        }
        
        /* Header Styles */
        .mobile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 100;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .header-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
        }
        
        /* Accounts Screen */
        .accounts-screen {
            height: calc(100vh - 60px);
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .user-profile-card {
            background: white;
            margin: 20px;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            margin: 0 auto 15px;
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-username {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        /* Status Section */
        .status-section {
            background: white;
            margin: 20px;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-list {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 5px 0;
        }
        
        .status-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .status-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            padding: 2px;
            margin-bottom: 5px;
        }
        
        .status-avatar.new-status {
            border: 3px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 20px;
        }
        
        .status-name {
            font-size: 12px;
            color: #333;
            max-width: 60px;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Contacts List */
        .contacts-section {
            background: white;
            margin: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .contact-item:last-child {
            border-bottom: none;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
        }
        
        .contact-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #e9ecef;
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            font-size: 15px;
        }
        
        .contact-username {
            font-size: 13px;
            color: #666;
        }
        
        .contact-status {
            font-size: 12px;
            color: #2ed573;
        }
        
        /* Chat Screen */
        .chat-screen {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
        }
        
        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .chat-user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        
        .chat-user-details {
            flex: 1;
        }
        
        .chat-user-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .chat-user-status {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message.received {
            background: white;
            color: #333;
            border-bottom-left-radius: 6px;
            align-self: flex-start;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .message.sent {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 6px;
            align-self: flex-end;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }
        
        .message-media {
            max-width: 250px;
            border-radius: 12px;
            margin-top: 5px;
        }
        
        .message-text {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
            text-align: right;
        }
        
        .chat-input-container {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .chat-input-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        .attachment-btn, .send-btn {
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .attachment-btn {
            color: #666;
        }
        
        .attachment-btn:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            background: #f8f9fa;
            resize: none;
            max-height: 100px;
            min-height: 45px;
        }
        
        .chat-input:focus {
            border-color: #667eea;
            background: white;
        }
        
        /* Status Viewer */
        .status-viewer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: none;
            flex-direction: column;
        }
        
        .status-viewer.active {
            display: flex;
        }
        
        .status-header {
            padding: 15px 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .status-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        .status-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .status-text {
            color: white;
            font-size: 24px;
            text-align: center;
            max-width: 80%;
        }
        
        .status-image, .status-video {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 10px;
        }
        
        .status-video {
            width: 100%;
        }
        
        .status-progress {
            display: flex;
            gap: 5px;
            padding: 15px 20px;
            background: rgba(0,0,0,0.7);
        }
        
        .progress-bar {
            flex: 1;
            height: 3px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 0.1s linear;
        }
        
        /* Add Status Modal */
        .add-status-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .add-status-modal.active {
            display: flex;
        }
        
        .status-modal-content {
            background: white;
            margin: 20px;
            padding: 25px;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }
        
        .status-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-type-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .status-type-btn.active {
            border-color: #667eea;
            background: #f0f3ff;
        }
        
        .status-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 15px;
            resize: none;
            min-height: 100px;
        }
        
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: none;
        }
        
        .file-input {
            width: 100%;
            padding: 12px;
            border: 2px dashed #e9ecef;
            border-radius: 12px;
            margin-bottom: 15px;
            cursor: pointer;
            text-align: center;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Utility Classes */
        .hidden {
            display: none !important;
        }
        
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Auth Container -->
    <div class="auth-container" id="authContainer">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #667eea; margin-bottom: 10px;">Chat App</h1>
            <p style="color: #666;">Login atau Daftar untuk melanjutkan</p>
        </div>
        
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="showAuthTab('login')">Login</button>
            <button class="auth-tab" onclick="showAuthTab('register')">Daftar</button>
        </div>
        
        <!-- Login Form -->
        <form id="loginForm" class="auth-form active">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Masukkan username" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Masuk
            </button>
            <div class="error-message" id="loginError"></div>
        </form>
        
        <!-- Register Form -->
        <form id="registerForm" class="auth-form" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Pilih username" required minlength="3">
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Buat password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nama Profil</label>
                <input type="text" name="profile_name" class="form-input" placeholder="Nama yang akan ditampilkan" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Foto Profil</label>
                <input type="file" name="profile_image" class="form-input file-input" accept="image/*">
                <img id="previewImage" class="preview-image" alt="Preview">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Daftar
            </button>
            <div class="error-message" id="registerError"></div>
            <div class="success-message" id="registerSuccess"></div>
        </form>
    </div>

    <!-- Mobile Main Screen -->
    <div class="mobile-container" id="mainScreen">
        <div class="mobile-header">
            <div class="header-title">Chat App</div>
            <div class="header-actions">
                <button class="header-btn" onclick="openAddStatusModal()">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="header-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
        
        <div class="accounts-screen">
            <!-- Current User Profile -->
            <div class="user-profile-card">
                <img id="currentProfileAvatar" class="profile-avatar" src="" alt="Profile">
                <div class="profile-name" id="currentProfileName"></div>
                <div class="profile-username" id="currentProfileUsername"></div>
                <div class="profile-actions">
                    <button class="action-btn btn-primary" onclick="openChatList()">
                        <i class="fas fa-comments"></i> Chat
                    </button>
                    <button class="action-btn btn-secondary" onclick="openStatusList()">
                        <i class="fas fa-circle"></i> Status
                    </button>
                </div>
            </div>
            
            <!-- Status Section -->
            <div class="status-section">
                <div class="section-title">
                    <span>Status Terbaru</span>
                    <button class="header-btn" style="color: #667eea; font-size: 14px;" onclick="openAddStatusModal()">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </div>
                <div class="status-list" id="statusList">
                    <!-- Status items will be loaded here -->
                </div>
            </div>
            
            <!-- Contacts Section -->
            <div class="contacts-section">
                <div class="section-title" style="padding: 15px; margin: 0;">Kontak Tersedia</div>
                <div id="contactsList">
                    <!-- Contacts will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Chat List Screen -->
    <div class="mobile-container" id="chatListScreen">
        <div class="mobile-header">
            <button class="back-btn" onclick="backToMain()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="header-title">Percakapan</div>
            <div class="header-actions">
                <button class="header-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <div class="accounts-screen">
            <div id="chatContactsList">
                <!-- Chat contacts will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Chat Screen -->
    <div class="mobile-container" id="chatScreen">
        <div class="chat-screen">
            <div class="chat-header">
                <button class="back-btn" onclick="backToChatList()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="chat-user-info">
                    <img class="chat-user-avatar" id="currentChatAvatar" src="assets/default-avatar.png" alt="Avatar">
                    <div class="chat-user-details">
                        <div class="chat-user-name" id="currentChatName">Loading...</div>
                        <div class="chat-user-status" id="currentChatStatus">Online</div>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="header-btn">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button class="header-btn">
                        <i class="fas fa-video"></i>
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div style="text-align: center; padding: 40px 20px; color: #666;">
                    <i class="fas fa-comments" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                    <h3>Mulai Percakapan</h3>
                    <p>Kirim pesan untuk memulai chat</p>
                </div>
            </div>
            
            <div class="chat-input-container">
                <div class="chat-input-wrapper">
                    <button class="attachment-btn" onclick="openMediaPicker()" title="Lampirkan File">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <textarea class="chat-input" id="messageInput" placeholder="Ketik pesan..." maxlength="1000" rows="1"></textarea>
                </div>
                <button class="send-btn" id="sendButton" onclick="sendMessage()" title="Kirim Pesan">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Status Viewer -->
    <div class="status-viewer" id="statusViewer">
        <div class="status-header">
            <button class="status-close" onclick="closeStatusViewer()">
                <i class="fas fa-times"></i>
            </button>
            <img id="viewerAvatar" class="status-avatar" src="" alt="Avatar" style="width: 40px; height: 40px;">
            <div>
                <div id="viewerName" style="font-weight: 600;"></div>
                <div id="viewerTime" style="font-size: 12px; opacity: 0.8;"></div>
            </div>
        </div>
        <div class="status-content" id="statusContent">
            <!-- Status content will be loaded here -->
        </div>
        <div class="status-progress" id="statusProgress">
            <!-- Progress bars will be loaded here -->
        </div>
    </div>

    <!-- Add Status Modal -->
    <div class="add-status-modal" id="addStatusModal">
        <div class="status-modal-content">
            <div class="modal-title">Tambah Status</div>
            
            <div class="status-type-selector">
                <button class="status-type-btn active" data-type="text" onclick="selectStatusType('text')">
                    <i class="fas fa-font"></i><br>Teks
                </button>
                <button class="status-type-btn" data-type="image" onclick="selectStatusType('image')">
                    <i class="fas fa-image"></i><br>Gambar
                </button>
                <button class="status-type-btn" data-type="video" onclick="selectStatusType('video')">
                    <i class="fas fa-video"></i><br>Video
                </button>
            </div>
            
            <div id="textStatusInput">
                <textarea class="status-input" id="statusText" placeholder="Apa yang sedang Anda pikirkan?"></textarea>
            </div>
            
            <div id="mediaStatusInput" class="hidden">
                <input type="file" id="statusFile" class="file-input" accept="image/*,video/*" onchange="previewStatusFile(this)">
                <img id="filePreview" class="file-preview" alt="Preview">
            </div>
            
            <div class="modal-actions">
                <button class="action-btn btn-secondary" style="flex: 1;" onclick="closeAddStatusModal()">
                    Batal
                </button>
                <button class="action-btn btn-primary" style="flex: 1;" onclick="postStatus()">
                    Posting
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden file input for chat media -->
    <input type="file" id="mediaInput" accept="image/*,video/*" style="display: none;" onchange="handleMediaUpload(this.files[0])">

    <script>
        // Inisialisasi Firebase
        const firebaseConfig = <?php echo json_encode($firebaseConfig); ?>;
        
        // Initialize Firebase
        if (firebase.apps.length === 0) {
            firebase.initializeApp(firebaseConfig);
        }
        
        const database = firebase.database();
        let currentUser = <?php echo isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'null'; ?>;
        let currentChatUser = null;
        let allContacts = [];
        let currentStatus = [];
        let currentStatusIndex = 0;
        let statusProgressInterval;

        // DOM Elements
        const authContainer = document.getElementById('authContainer');
        const mainScreen = document.getElementById('mainScreen');
        const chatListScreen = document.getElementById('chatListScreen');
        const chatScreen = document.getElementById('chatScreen');
        const statusViewer = document.getElementById('statusViewer');
        const addStatusModal = document.getElementById('addStatusModal');

        // Initialize jika user sudah login
        if (currentUser) {
            showMainScreen();
        }

        // Fungsi untuk menampilkan tab auth
        function showAuthTab(tabName) {
            document.querySelectorAll('.auth-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
            
            document.querySelector(`.auth-tab[onclick="showAuthTab('${tabName}')"]`).classList.add('active');
            document.getElementById(tabName + 'Form').classList.add('active');
        }

        // Preview image untuk register form
        document.querySelector('input[name="profile_image"]')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('previewImage');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Handle login form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'login');
            
            const errorDiv = document.getElementById('loginError');
            errorDiv.style.display = 'none';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentUser = data.user;
                    showMainScreen();
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Terjadi kesalahan. Coba lagi.';
                errorDiv.style.display = 'block';
            });
        });

        // Handle register form
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'register');
            
            const errorDiv = document.getElementById('registerError');
            const successDiv = document.getElementById('registerSuccess');
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message;
                    successDiv.style.display = 'block';
                    this.reset();
                    document.getElementById('previewImage').style.display = 'none';
                    setTimeout(() => {
                        showAuthTab('login');
                    }, 2000);
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Terjadi kesalahan. Coba lagi.';
                errorDiv.style.display = 'block';
            });
        });

        // Show main screen
        function showMainScreen() {
            authContainer.style.display = 'none';
            mainScreen.classList.add('active');
            
            // Update user profile
            document.getElementById('currentProfileAvatar').src = currentUser.profile_image;
            document.getElementById('currentProfileName').textContent = currentUser.profile_name;
            document.getElementById('currentProfileUsername').textContent = '@' + currentUser.username;
            
            // Load data
            loadAllContacts();
            loadAllStatus();
            
            // Set user online
            database.ref('onlineUsers/' + currentUser.user_id).set({
                username: currentUser.username,
                profile_name: currentUser.profile_name,
                profile_image: currentUser.profile_image,
                lastSeen: Date.now(),
                status: 'online'
            });
        }

        // Navigation functions
        function openChatList() {
            mainScreen.classList.remove('active');
            chatListScreen.classList.add('active');
        }

        function backToMain() {
            chatListScreen.classList.remove('active');
            mainScreen.classList.add('active');
        }

        function backToChatList() {
            chatScreen.classList.remove('active');
            chatListScreen.classList.add('active');
            currentChatUser = null;
        }

        function openStatusList() {
            // Implement status list view if needed
            alert('Fitur daftar status sedang dikembangkan');
        }

        // Load all contacts
        function loadAllContacts() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allContacts = data.users.filter(user => user.username !== currentUser.username);
                    displayContacts(allContacts);
                    displayChatContacts(allContacts);
                }
            })
            .catch(error => {
                console.error('Error loading contacts:', error);
            });
        }

        // Display contacts in main screen
        function displayContacts(contacts) {
            const contactsList = document.getElementById('contactsList');
            contactsList.innerHTML = '';
            
            if (contacts.length === 0) {
                contactsList.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <i class="fas fa-user-friends" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>Tidak ada kontak tersedia</p>
                    </div>
                `;
                return;
            }
            
            contacts.forEach(contact => {
                const contactItem = document.createElement('div');
                contactItem.className = 'contact-item';
                contactItem.onclick = () => openChat(contact);
                
                contactItem.innerHTML = `
                    <img src="${contact.profile_image}" alt="Avatar" class="contact-avatar" onerror="this.src='assets/default-avatar.png'">
                    <div class="contact-info">
                        <div class="contact-name">${contact.profile_name}</div>
                        <div class="contact-username">@${contact.username}</div>
                    </div>
                    <div class="contact-status">Online</div>
                `;
                
                contactsList.appendChild(contactItem);
            });
        }

        // Display contacts in chat list
        function displayChatContacts(contacts) {
            const chatContactsList = document.getElementById('chatContactsList');
            chatContactsList.innerHTML = '';
            
            contacts.forEach(contact => {
                const contactItem = document.createElement('div');
                contactItem.className = 'contact-item';
                contactItem.onclick = () => openChat(contact);
                
                contactItem.innerHTML = `
                    <img src="${contact.profile_image}" alt="Avatar" class="contact-avatar" onerror="this.src='assets/default-avatar.png'">
                    <div class="contact-info">
                        <div class="contact-name">${contact.profile_name}</div>
                        <div class="contact-username">@${contact.username}</div>
                    </div>
                    <div class="contact-status">Online</div>
                `;
                
                chatContactsList.appendChild(contactItem);
            });
        }

        // Open chat with contact
        function openChat(contact) {
            currentChatUser = contact;
            chatListScreen.classList.remove('active');
            chatScreen.classList.add('active');
            
            // Update chat header
            document.getElementById('currentChatAvatar').src = contact.profile_image;
            document.getElementById('currentChatName').textContent = contact.profile_name;
            
            // Load messages
            loadChatMessages(contact);
            
            // Setup event listeners
            setupChatEventListeners();
        }

        // Setup chat event listeners
        function setupChatEventListeners() {
            const messageInput = document.getElementById('messageInput');
            const sendButton = document.getElementById('sendButton');
            
            // Remove existing listeners
            messageInput.removeEventListener('keypress', handleKeyPress);
            sendButton.removeEventListener('click', sendMessage);
            
            // Add new listeners
            messageInput.addEventListener('keypress', handleKeyPress);
            sendButton.addEventListener('click', sendMessage);
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

        // Handle enter key in message input
        function handleKeyPress(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        }

        // Send message
        function sendMessage() {
            if (!currentChatUser) return;
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (message === '') return;
            
            const messageData = {
                senderId: currentUser.user_id,
                senderName: currentUser.profile_name,
                senderUsername: currentUser.username,
                senderAvatar: currentUser.profile_image,
                receiverId: currentChatUser.user_id,
                receiverName: currentChatUser.profile_name,
                message: message,
                timestamp: Date.now(),
                type: 'text',
                status: 'sent'
            };
            
            database.ref('messages').push(messageData)
                .then(() => {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('Gagal mengirim pesan. Coba lagi.');
                });
        }

        // Load chat messages
        function loadChatMessages(contact) {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '';
            
            // Listen for new messages
            database.ref('messages').orderByChild('timestamp').on('child_added', (snapshot) => {
                const message = snapshot.val();
                
                if ((message.senderId === currentUser.user_id && message.receiverId === contact.user_id) ||
                    (message.senderId === contact.user_id && message.receiverId === currentUser.user_id)) {
                    displayMessage(message);
                }
            });
        }

        // Display message
        function displayMessage(message) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            const isOwnMessage = message.senderId === currentUser.user_id;
            
            messageDiv.className = `message ${isOwnMessage ? 'sent' : 'received'}`;
            
            const time = new Date(message.timestamp).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let messageContent = '';
            if (message.type === 'text') {
                messageContent = `<div class="message-text">${escapeHtml(message.message)}</div>`;
            } else if (message.type === 'image') {
                messageContent = `
                    <img src="${message.content}" class="message-media" alt="Gambar">
                    ${message.message ? `<div class="message-text">${escapeHtml(message.message)}</div>` : ''}
                `;
            } else if (message.type === 'video') {
                messageContent = `
                    <video controls class="message-media">
                        <source src="${message.content}" type="video/mp4">
                        Browser Anda tidak mendukung video.
                    </video>
                    ${message.message ? `<div class="message-text">${escapeHtml(message.message)}</div>` : ''}
                `;
            }
            
            messageDiv.innerHTML = `
                ${!isOwnMessage ? `<div class="message-sender">${message.senderName}</div>` : ''}
                ${messageContent}
                <div class="message-time">${time}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Open media picker for chat
        function openMediaPicker() {
            document.getElementById('mediaInput').click();
        }

        // Handle media upload for chat
        function handleMediaUpload(file) {
            if (!file || !currentChatUser) return;
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload_media');
            
            // Determine file type
            const fileType = file.type.startsWith('image/') ? 'image' : 
                           file.type.startsWith('video/') ? 'video' : 'file';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messageData = {
                        senderId: currentUser.user_id,
                        senderName: currentUser.profile_name,
                        senderUsername: currentUser.username,
                        senderAvatar: currentUser.profile_image,
                        receiverId: currentChatUser.user_id,
                        receiverName: currentChatUser.profile_name,
                        message: '',
                        content: data.file_path,
                        type: fileType,
                        timestamp: Date.now(),
                        status: 'sent'
                    };
                    
                    return database.ref('messages').push(messageData);
                } else {
                    throw new Error(data.error);
                }
            })
            .then(() => {
                console.log('Media message sent successfully');
            })
            .catch(error => {
                console.error('Error sending media message:', error);
                alert('Gagal mengirim media. Coba lagi.');
            });
        }

        // Status functions
        function loadAllStatus() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayStatus(data.status);
                }
            })
            .catch(error => {
                console.error('Error loading status:', error);
            });
        }

        function displayStatus(statusData) {
            const statusList = document.getElementById('statusList');
            statusList.innerHTML = '';
            
            // Add "My Status" item
            const myStatusItem = document.createElement('div');
            myStatusItem.className = 'status-item';
            myStatusItem.onclick = openAddStatusModal;
            
            myStatusItem.innerHTML = `
                <div class="status-avatar new-status">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="status-name">Status Saya</div>
            `;
            
            statusList.appendChild(myStatusItem);
            
            // Add other users' status
            Object.entries(statusData).forEach(([userId, userData]) => {
                const statusItem = document.createElement('div');
                statusItem.className = 'status-item';
                statusItem.onclick = () => viewUserStatus(userId, userData);
                
                // Check if user has unviewed status
                const hasUnviewed = Object.values(userData.statuses).some(status => 
                    !status.viewed_by.includes(currentUser.user_id)
                );
                
                statusItem.innerHTML = `
                    <img src="${userData.user_info.profile_image}" alt="Avatar" class="status-avatar" 
                         style="${hasUnviewed ? 'border-color: #667eea;' : 'border-color: #ccc;'}">
                    <div class="status-name">${userData.user_info.profile_name}</div>
                `;
                
                statusList.appendChild(statusItem);
            });
        }

        function openAddStatusModal() {
            addStatusModal.classList.add('active');
        }

        function closeAddStatusModal() {
            addStatusModal.classList.remove('active');
            document.getElementById('statusText').value = '';
            document.getElementById('statusFile').value = '';
            document.getElementById('filePreview').style.display = 'none';
            selectStatusType('text');
        }

        function selectStatusType(type) {
            document.querySelectorAll('.status-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.status-type-btn[data-type="${type}"]`).classList.add('active');
            
            if (type === 'text') {
                document.getElementById('textStatusInput').classList.remove('hidden');
                document.getElementById('mediaStatusInput').classList.add('hidden');
            } else {
                document.getElementById('textStatusInput').classList.add('hidden');
                document.getElementById('mediaStatusInput').classList.remove('hidden');
            }
        }

        function previewStatusFile(input) {
            const file = input.files[0];
            const preview = document.getElementById('filePreview');
            
            if (file) {
                if (file.type.startsWith('image/')) {
                    preview.src = URL.createObjectURL(file);
                    preview.style.display = 'block';
                } else if (file.type.startsWith('video/')) {
                    preview.src = URL.createObjectURL(file);
                    preview.style.display = 'block';
                }
            }
        }

        function postStatus() {
            const type = document.querySelector('.status-type-btn.active').dataset.type;
            const formData = new FormData();
            formData.append('action', 'add_status');
            formData.append('status_type', type);
            
            if (type === 'text') {
                const text = document.getElementById('statusText').value.trim();
                if (!text) {
                    alert('Masukkan teks status');
                    return;
                }
                formData.append('status_text', text);
            } else {
                const file = document.getElementById('statusFile').files[0];
                if (!file) {
                    alert('Pilih file untuk status');
                    return;
                }
                formData.append('status_file', file);
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddStatusModal();
                    loadAllStatus();
                    alert('Status berhasil diposting!');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error posting status:', error);
                alert('Gagal memposting status. Coba lagi.');
            });
        }

        function viewUserStatus(userId, userData) {
            currentStatus = Object.entries(userData.statuses);
            currentStatusIndex = 0;
            
            if (currentStatus.length === 0) return;
            
            showStatusViewer();
        }

        function showStatusViewer() {
            if (currentStatus.length === 0) return;
            
            const [statusId, status] = currentStatus[currentStatusIndex];
            const statusViewer = document.getElementById('statusViewer');
            const statusContent = document.getElementById('statusContent');
            const statusProgress = document.getElementById('statusProgress');
            
            // Update viewer info
            document.getElementById('viewerAvatar').src = status.profile_image;
            document.getElementById('viewerName').textContent = status.profile_name;
            document.getElementById('viewerTime').textContent = formatTime(status.created_at);
            
            // Clear previous content
            statusContent.innerHTML = '';
            statusProgress.innerHTML = '';
            
            // Create progress bars
            currentStatus.forEach((_, index) => {
                const progressBar = document.createElement('div');
                progressBar.className = 'progress-bar';
                const progressFill = document.createElement('div');
                progressFill.className = 'progress-fill';
                progressFill.style.width = index < currentStatusIndex ? '100%' : '0%';
                progressBar.appendChild(progressFill);
                statusProgress.appendChild(progressBar);
            });
            
            // Display current status
            if (status.type === 'text') {
                statusContent.innerHTML = `<div class="status-text">${status.content}</div>`;
            } else if (status.type === 'image') {
                statusContent.innerHTML = `<img src="${status.content}" class="status-image" alt="Status Image">`;
            } else if (status.type === 'video') {
                statusContent.innerHTML = `
                    <video controls autoplay class="status-video">
                        <source src="${status.content}" type="video/mp4">
                        Browser Anda tidak mendukung video.
                    </video>
                `;
            }
            
            // Mark as viewed
            markStatusAsViewed(statusId);
            
            // Start progress animation
            startStatusProgress();
            
            // Show viewer
            statusViewer.classList.add('active');
            
            // Add click listeners for navigation
            statusContent.onclick = nextStatus;
        }

        function startStatusProgress() {
            clearInterval(statusProgressInterval);
            
            const progressBars = document.querySelectorAll('.progress-fill');
            if (currentStatusIndex >= progressBars.length) return;
            
            const currentProgress = progressBars[currentStatusIndex];
            let width = 0;
            
            statusProgressInterval = setInterval(() => {
                if (width >= 100) {
                    clearInterval(statusProgressInterval);
                    nextStatus();
                    return;
                }
                width += 0.5;
                currentProgress.style.width = width + '%';
            }, 50);
        }

        function nextStatus() {
            clearInterval(statusProgressInterval);
            currentStatusIndex++;
            
            if (currentStatusIndex < currentStatus.length) {
                showStatusViewer();
            } else {
                closeStatusViewer();
            }
        }

        function closeStatusViewer() {
            clearInterval(statusProgressInterval);
            statusViewer.classList.remove('active');
            currentStatus = [];
            currentStatusIndex = 0;
        }

        function markStatusAsViewed(statusId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_status_viewed&status_id=${statusId}`
            })
            .catch(error => console.error('Error marking status as viewed:', error));
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Baru saja';
            if (diff < 3600000) return Math.floor(diff / 60000) + ' menit lalu';
            if (diff < 86400000) return Math.floor(diff / 3600000) + ' jam lalu';
            return date.toLocaleDateString('id-ID');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function logout() {
            if (currentUser) {
                database.ref('onlineUsers/' + currentUser.user_id).remove();
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=logout'
            })
            .then(() => {
                location.reload();
            });
        }

        // Handle page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && currentUser) {
                database.ref('onlineUsers/' + currentUser.user_id).update({
                    lastSeen: Date.now(),
                    status: 'away'
                });
            } else if (currentUser) {
                database.ref('onlineUsers/' + currentUser.user_id).update({
                    lastSeen: Date.now(),
                    status: 'online'
                });
            }
        });
    </script>
</body>

</html>
