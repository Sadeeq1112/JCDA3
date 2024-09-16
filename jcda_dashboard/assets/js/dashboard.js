document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }

    // Fetch and display announcements
    fetchAnnouncements();

    // Fetch and display payment status
    fetchPaymentStatus();
});

function fetchAnnouncements() {
    fetch('api/announcements.php')
        .then(response => response.json())
        .then(data => {
            const announcementsList = document.getElementById('announcements-list');
            if (announcementsList) {
                announcementsList.innerHTML = '';
                data.forEach(announcement => {
                    const li = document.createElement('li');
                    li.innerHTML = `<h4>${announcement.title}</h4><p>${announcement.content}</p>`;
                    announcementsList.appendChild(li);
                });
            }
        })
        .catch(error => console.error('Error fetching announcements:', error));
}

function fetchPaymentStatus() {
    fetch('api/payment-status.php')
        .then(response => response.json())
        .then(data => {
            const paymentStatus = document.getElementById('payment-status');
            if (paymentStatus) {
                paymentStatus.textContent = data.status;
                paymentStatus.className = `status ${data.status}`;
            }
        })
        .catch(error => console.error('Error fetching payment status:', error));
}