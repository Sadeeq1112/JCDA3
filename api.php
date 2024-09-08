<?php
require_once 'config.php';
require_once 'auth.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Define actions that don't require authentication
$public_actions = ['get_articles', 'get_article'];

// Check authentication for non-public actions
if (!in_array($action, $public_actions) && !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'get_articles':
        $last_fetch_time = isset($_GET['last_fetch']) ? intval($_GET['last_fetch']) : 0;

        $stmt = $conn->prepare("SELECT * FROM articles WHERE last_modified > ? ORDER BY date DESC");
        $stmt->bind_param("i", $last_fetch_time);
        $stmt->execute();
        $result = $stmt->get_result();
        $articles = $result->fetch_all(MYSQLI_ASSOC);

        $response = [
            'articles' => $articles,
            'server_time' => time()
        ];

        echo json_encode($response);
        break;

    case 'get_article':
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $article = $result->fetch_assoc();
        
        if ($article) {
            echo json_encode($article);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found']);
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
            echo json_encode(['success' => true, 'id' => $stmt->insert_id ?? $data['id'], 'last_modified' => $current_time]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
        }
        break;

    case 'delete_article':
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>