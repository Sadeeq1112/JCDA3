<?php
require_once 'config.php';
require_once 'auth.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_articles':
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? 
            strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;

        $stmt = $conn->prepare("SELECT * FROM articles WHERE last_modified > ? ORDER BY date DESC");
        $stmt->bind_param("i", $if_modified_since);
        $stmt->execute();
        $result = $stmt->get_result();
        $articles = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($articles)) {
            http_response_code(304); // Not Modified
        } else {
            echo json_encode($articles);
        }
        break;

    case 'save_article':
        $data = json_decode(file_get_contents('php://input'), true);
        $current_time = time();
        if (isset($data['id']) && $data['id']) {
            $stmt = $conn->prepare("UPDATE articles SET title = ?, date = ?, author = ?, category = ?, image = ?, content = ?, last_modified = ? WHERE id = ?");
            $stmt->bind_param("ssssssii", $data['title'], $data['date'], $data['author'], $data['category'], $data['image'], $data['content'], $current_time, $data['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO articles (title, date, author, category, image, content, last_modified) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $data['title'], $data['date'], $data['author'], $data['category'], $data['image'], $data['content'], $current_time);
        }
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id ?? $data['id']]);
        } else {
            echo json_encode(['error' => $stmt->error]);
        }
        break;

    case 'delete_article':
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>