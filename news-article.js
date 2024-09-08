document.addEventListener('DOMContentLoaded', function() {
    // Get the article ID from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const articleId = urlParams.get('id');

    // Fetch the article data from the server
    fetch(`api.php?action=get_article&id=${articleId}`)
        .then(response => response.json())
        .then(article => {
            if (article) {
                // Update the DOM with the article data
                document.title = `JCDA News - ${article.title}`;
                document.querySelector('.jcda-article-title').textContent = article.title;
                document.querySelector('.jcda-article-meta').innerHTML = `Published on <time datetime="${article.date}">${formatDate(article.date)}</time> by ${article.author}`;
                document.querySelector('.jcda-article-category').textContent = article.category;
                document.querySelector('.jcda-article-image').src = article.image;
                document.querySelector('.jcda-article-image').alt = article.title;
                document.querySelector('.jcda-article-content').innerHTML = article.content;
            } else {
                // Handle case where article is not found
                document.querySelector('.jcda-article').innerHTML = '<h1>Article not found</h1>';
            }
        })
        .catch(error => {
            console.error('Error fetching article:', error);
            document.querySelector('.jcda-article').innerHTML = '<h1>Error loading article</h1>';
        });
});

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}