// register.js
const API_BASE_URL = ''; // Replace with your actual API URL

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (password !== confirmPassword) {
        alert("Passwords don't match. Please try again.");
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}dashboard/api/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, email, phone, password }),
        });

        const responseText = await response.text();
        console.log('Server response:', responseText);

        if (response.ok) {
            const data = JSON.parse(responseText);
            alert('Registration successful! Please login.');
            window.location.href = 'login.html';
        } else {
            console.error('Registration failed:', responseText);
            alert('Registration failed. Please check the console for more details.');
        }
    } catch (error) {
        console.error('Registration error:', error);
        alert('An error occurred. Please try again later.');
    }
});