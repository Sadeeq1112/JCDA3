<?php
require_once 'config.php';

$article_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();

if (!$article) {
    header("HTTP/1.0 404 Not Found");
    echo "Article not found";
    exit;
}

function formatDate($dateString) {
    return date("F j, Y", strtotime($dateString));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA News - <?php echo htmlspecialchars($article['title']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .jcda-article-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .jcda-article-title {
            font-size: 28px;
            color: #2c3e50;
        }
        .jcda-article-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        .jcda-article-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .jcda-article-content {
            font-size: 16px;
        }
        .jcda-article-category {
            display: inline-block;
            background-color: #378349;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <article class="jcda-article">
        <header class="jcda-article-header">
            
            <h1 class="jcda-article-title"><?php echo htmlspecialchars($article['title']); ?></h1>
            <p class="jcda-article-meta">Published on <time datetime="<?php echo $article['date']; ?>"><?php echo formatDate($article['date']); ?></time> by <?php echo htmlspecialchars($article['author']); ?></p>
            <span class="jcda-article-category"><?php echo htmlspecialchars($article['category']); ?></span>
        </header>
        <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="jcda-article-image">
        <div class="jcda-article-content"><?php echo $article['content']; ?></div>
    </article>
</body>
</html>