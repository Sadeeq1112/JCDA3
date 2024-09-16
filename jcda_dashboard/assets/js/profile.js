document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profile-form');

    if (profileForm) {
        profileForm.addEventListener('submit', function(event) {
            event.preventDefault();
            updateProfile();
        });
    }

    // Enable/disable edit mode for profile fields
    const editButton = document.getElementById('edit-profile');
    const saveButton = document.getElementById('save-profile');
    const profileFields = document.querySelectorAll('.profile-field');

    if (editButton && saveButton) {
        editButton.addEventListener('click', function() {
            profileFields.forEach(field => field.removeAttribute('readonly'));
            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
        });
    }
});

function updateProfile() {
    const formData = new FormData(document.getElementById('profile-form'));

    fetch('api/update-profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Profile updated successfully', 'success');
            disableEditMode();
        } else {
            showMessage(data.message || 'Failed to update profile', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating profile:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

function disableEditMode() {
    const editButton = document.getElementById('edit-profile');
    const saveButton = document.getElementById('save-profile');
    const profileFields = document.querySelectorAll('.profile-field');

    profileFields.forEach(field => field.setAttribute('readonly', true));
    editButton.style.display = 'inline-block';
    saveButton.style.display = 'none';
}

function showMessage(message, type) {
    const messageElement = document.getElementById('message');
    messageElement.textContent = message;
    messageElement.className = `message ${type}`;
    messageElement.style.display = 'block';

    setTimeout(() => {
        messageElement.style.display = 'none';
    }, 5000);
}