<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_articles':
        $stmt = $conn->prepare("SELECT * FROM articles ORDER BY date DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $articles = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($articles);
        break;

        case 'save_article':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['id']) && $data['id']) {
                $stmt = $conn->prepare("UPDATE articles SET title = ?, date = ?, author = ?, category = ?, image = ?, content = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $data['title'], $data['date'], $data['author'], $data['category'], $data['image'], $data['content'], $data['id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO articles (title, date, author, category, image, content) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $data['title'], $data['date'], $data['author'], $data['category'], $data['image'], $data['content']);
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