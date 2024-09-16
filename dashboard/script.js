// script.js
const API_BASE_URL = '/api'; // Replace with your actual API URL
let currentUser = null;

// Utility functions
function showError(message) {
    const alertArea = document.getElementById('alertArea');
    alertArea.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

function showSuccess(message) {
    const alertArea = document.getElementById('alertArea');
    alertArea.innerHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

async function fetchAPI(endpoint, options = {}) {
    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('jcdaToken')}`,
                ...options.headers,
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API request failed:', error);
        showError('An error occurred while fetching data. Please try again later.');
        throw error;
    }
}

// Page load event listener
document.addEventListener('DOMContentLoaded', function() {
    // Navigation handling
    document.getElementById('homeLink').addEventListener('click', loadHome);
    document.getElementById('profileLink').addEventListener('click', loadProfile);
    document.getElementById('paymentsLink').addEventListener('click', loadPayments);
    document.getElementById('logoutLink').addEventListener('click', logout);
    document.getElementById('generateCardBtn').addEventListener('click', generateMembershipCard);

    // Check if user is logged in
    const token = localStorage.getItem('jcdaToken');
    if (token) {
        validateToken(token);
    } else {
        window.location.href = 'login.html';
    }
});

async function validateToken(token) {
    try {
        const userData = await fetchAPI('/user/validate', {
            method: 'POST',
            body: JSON.stringify({ token }),
        });
        currentUser = userData;
        loadHome();
    } catch (error) {
        console.error('Token validation failed:', error);
        logout();
    }
}

async function loadHome() {
    try {
        const dashboardData = await fetchAPI('/dashboard');
        const homeHTML = `
            <h2>Welcome, ${currentUser.name}!</h2>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Membership Status</h5>
                            <p class="card-text">${dashboardData.membershipStatus}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Next Due Date</h5>
                            <p class="card-text">${dashboardData.nextDueDate}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Activities</h5>
                            <ul class="list-group list-group-flush">
                                ${dashboardData.recentActivities.map(activity => `
                                    <li class="list-group-item">${activity}</li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('content').innerHTML = homeHTML;
    } catch (error) {
        console.error('Error loading home page:', error);
        showError('Failed to load dashboard data. Please try again later.');
    }
}

async function loadProfile() {
    try {
        const profileData = await fetchAPI('/user/profile');
        const profileHTML = `
            <h2>My Profile</h2>
            <form id="profileForm">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" value="${profileData.name}" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="${profileData.email}" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" value="${profileData.phone}" required>
                </div>
                <div class="mb-3">
                    <label for="membershipId" class="form-label">Membership ID</label>
                    <input type="text" class="form-control" id="membershipId" value="${profileData.membershipId}" readonly>
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        `;
        document.getElementById('content').innerHTML = profileHTML;
        
        // Add event listener for form submission
        document.getElementById('profileForm').addEventListener('submit', updateProfile);
    } catch (error) {
        console.error('Error loading profile:', error);
        showError('Failed to load profile data. Please try again later.');
    }
}

async function updateProfile(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const profileData = Object.fromEntries(formData.entries());

    try {
        await fetchAPI('/user/profile', {
            method: 'PUT',
            body: JSON.stringify(profileData),
        });
        showSuccess('Profile updated successfully');
    } catch (error) {
        console.error('Error updating profile:', error);
        showError('Failed to update profile. Please try again.');
    }
}

async function loadPayments() {
    try {
        const paymentsData = await fetchAPI('/payments');
        const paymentsHTML = `
            <h2>My Payments</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${paymentsData.map(payment => `
                        <tr>
                            <td>${payment.date}</td>
                            <td>${payment.amount}</td>
                            <td>${payment.status}</td>
                            <td>
                                ${payment.status === 'Paid' 
                                    ? `<button class="btn btn-sm btn-secondary viewReceipt" data-id="${payment.id}">View Receipt</button>`
                                    : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <button id="payDuesBtn" class="btn btn-primary">Pay Annual Dues</button>
        `;
        document.getElementById('content').innerHTML = paymentsHTML;
        
        // Add event listeners
        document.getElementById('payDuesBtn').addEventListener('click', initializePayment);
        document.querySelectorAll('.viewReceipt').forEach(button => {
            button.addEventListener('click', () => viewReceipt(button.dataset.id));
        });
    } catch (error) {
        console.error('Error loading payments:', error);
        showError('Failed to load payment data. Please try again later.');
    }
}

async function initializePayment() {
    try {
        const paymentInfo = await fetchAPI('/payments/initialize', { method: 'POST' });
        // Assuming you're using Flutterwave for payment processing
        FlutterwaveCheckout({
            public_key: paymentInfo.publicKey,
            tx_ref: paymentInfo.transactionReference,
            amount: paymentInfo.amount,
            currency: paymentInfo.currency,
            payment_options: "card, banktransfer, ussd",
            customer: {
                email: currentUser.email,
                phone_number: currentUser.phone,
                name: currentUser.name,
            },
            callback: async function(transaction) {
                try {
                    await fetchAPI('/payments/verify', {
                        method: 'POST',
                        body: JSON.stringify({ transaction }),
                    });
                    showSuccess('Payment successful!');
                    loadPayments();
                } catch (error) {
                    console.error('Payment verification failed:', error);
                    showError('Payment verification failed. Please contact support.');
                }
            },
            onclose: function() {
                // Handle when the modal is closed
            },
            customizations: {
                title: "JCDA Annual Dues",
                description: "Payment for JCDA annual membership dues",
                logo: "https://jcda.org/logo.png",
            },
        });
    } catch (error) {
        console.error('Error initializing payment:', error);
        showError('Failed to initialize payment. Please try again later.');
    }
}

async function viewReceipt(paymentId) {
    try {
        const receiptData = await fetchAPI(`/payments/${paymentId}/receipt`);
        const receiptHTML = `
            <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="receiptModalLabel">Payment Receipt</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Transaction ID:</strong> ${receiptData.transactionId}</p>
                            <p><strong>Date:</strong> ${receiptData.date}</p>
                            <p><strong>Amount:</strong> ${receiptData.amount}</p>
                            <p><strong>Payment Method:</strong> ${receiptData.paymentMethod}</p>
                            <p><strong>Status:</strong> ${receiptData.status}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="printReceipt()">Print</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', receiptHTML);
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        receiptModal.show();
    } catch (error) {
        console.error('Error viewing receipt:', error);
        showError('Failed to load receipt. Please try again later.');
    }
}

function printReceipt() {
    window.print();
}

function logout() {
    localStorage.removeItem('jcdaToken');
    currentUser = null;
    window.location.href = 'login.html';
}

async function generateMembershipCard() {
    try {
        const cardData = await fetchAPI('/user/membership-card');
        const cardHTML = `
            <div id="membershipCard" style="width: 350px; height: 200px; border: 1px solid #000; padding: 20px; font-family: Arial, sans-serif;">
                <h2 style="margin: 0 0 10px 0;">JCDA Membership Card</h2>
                <p><strong>Name:</strong> ${cardData.name}</p>
                <p><strong>Membership ID:</strong> ${cardData.membershipId}</p>
                <p><strong>Expiry Date:</strong> ${cardData.expiryDate}</p>
                <img src="${cardData.qrCode}" alt="QR Code" style="position: absolute; bottom: 10px; right: 10px; width: 50px; height: 50px;">
            </div>
        `;
        
        const cardWindow = window.open('', '_blank');
        cardWindow.document.write(cardHTML);
        cardWindow.document.close();
        cardWindow.print();
    } catch (error) {
        console.error('Error generating membership card:', error);
        showError('Failed to generate membership card. Please try again later.');
    }
}