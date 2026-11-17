<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => '未登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '请求方法错误']);
    exit;
}

$message_id = (int)($_POST['message_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($message_id <= 0) {
    echo json_encode(['error' => '无效的留言ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$message_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => '操作失败']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => '服务器错误']);
}
?>
