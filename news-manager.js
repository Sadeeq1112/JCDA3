tinymce.init({
    selector: '#content',
    plugins: 'link image code lists table',
    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code',
    height: 300,
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
});

async function fetchArticles() {
    try {
        const response = await fetch('api.php?action=get_articles');
        const data = await response.json();
        console.log('API response:', data); // Debug log
        if (Array.isArray(data.articles)) {
            return data.articles;
        } else if (Array.isArray(data)) {
            return data;
        } else {
            console.error('Unexpected data structure:', data);
            return [];
        }
    } catch (error) {
        console.error('Error fetching articles:', error);
        return [];
    }
}


async function saveArticle(article) {
    try {
        console.log('Saving article:', article);
        const response = await fetch('api.php?action=save_article', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(article)
        });
        const result = await response.json();
        console.log('Save result:', result);
        return result;
    } catch (error) {
        console.error('Error saving article:', error);
    }
}

async function deleteArticle(id) {
    try {
        const response = await fetch(`api.php?action=delete_article&id=${id}`);
        return await response.json();
    } catch (error) {
        console.error('Error deleting article:', error);
    }
}

function displayArticles(articles) {
    console.log('Articles to display:', articles); // Debug log
    if (!Array.isArray(articles)) {
        console.error('articles is not an array:', articles);
        return;
    }
    const tbody = document.querySelector('#articleList tbody');
    tbody.innerHTML = '';
    articles.forEach(article => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${article.title || ''}</td>
            <td>${article.date || ''}</td>
            <td>${article.author || ''}</td>
            <td>${article.category || ''}</td>
            <td>
                <button class="btn btn-sm btn-outline-secondary edit-btn" data-id="${article.id}">Edit</button>
                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${article.id}">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => editArticle(e.target.getAttribute('data-id')));
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => confirmDeleteArticle(e.target.getAttribute('data-id')));
    });
}

async function loadArticles() {
    const articles = await fetchArticles();
    console.log('Loaded articles:', articles); // Debug log
    if (Array.isArray(articles)) {
        displayArticles(articles);
    } else {
        console.error('Invalid articles data:', articles);
    }
}

async function editArticle(id) {
    try {
        const response = await fetch(`api.php?action=get_article&id=${id}`);
        const article = await response.json();
        if (article && !article.error) {
            const form = document.getElementById('articleForm');
            form.articleId.value = article.id;
            form.title.value = article.title;
            form.date.value = article.date;
            form.author.value = article.author;
            form.category.value = article.category;
            form.image.value = article.image;
            tinymce.get('content').setContent(article.content);
        } else {
            console.error('Article not found or error occurred');
        }
    } catch (error) {
        console.error('Error fetching article for editing:', error);
    }
}

async function confirmDeleteArticle(id) {
    if (confirm('Are you sure you want to delete this article?')) {
        const result = await deleteArticle(id);
        if (result && result.success) {
            await loadArticles();
        } else {
            console.error('Failed to delete article');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    const form = document.getElementById('articleForm');
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        console.log('Form submitted');
        
        tinymce.triggerSave();
        const content = tinymce.get('content').getContent();
        if (!content.trim()) {
            alert('Please enter some content for the article.');
            return;
        }
        
        const article = {
            id: form.articleId.value || null,
            title: form.title.value,
            date: form.date.value,
            author: form.author.value,
            category: form.category.value,
            image: form.image.value,
            content: content
        };

        const result = await saveArticle(article);
        if (result && result.success) {
            form.reset();
            form.articleId.value = '';
            tinymce.get('content').setContent('');
            await loadArticles();
            alert('Article saved successfully!');
        } else {
            console.error('Failed to save article');
            alert('Failed to save article. Please try again.');
        }
    });

    loadArticles();
});