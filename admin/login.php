<?php
/**
 * GNC Admin Panel - Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';

initSession(); // Changed back to your original function!

// Redirect if already logged in
if (isLoggedIn()) { // Changed back to your original function!
    header('Location: /admin/admin-dash.php'); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNC Admin Panel - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/login-style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1>Admin Panel</h1>
                <p>Guagua National Colleges, Inc.</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <!-- Timeout Alert -->
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning timeout-alert">
                        <i class="bi bi-exclamation-triangle"></i> Your session has expired. Please login again.
                    </div>
                <?php endif; ?>

                <!-- Error Alert -->
                <div id="alert-container"></div>

                <!-- Login Form -->
                <form id="login-form" method="POST">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password" 
                            required
                        >
                    </div>

                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="remember-me" 
                            name="remember_me"
                        >
                        <label class="form-check-label" for="remember-me">
                            Remember this device for 30 days
                        </label>
                    </div>

                    <button type="submit" class="btn-login">
                        <span class="loading-spinner" id="loading-spinner"></span>
                        <span id="btn-text">Login</span>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                &copy; 2026 Guagua National Colleges, Inc. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('login-form');
        const alertContainer = document.getElementById('alert-container');
        const loadingSpinner = document.getElementById('loading-spinner');
        const btnText = document.getElementById('btn-text');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('remember-me').checked;

            // Show loading state
            loadingSpinner.style.display = 'inline-block';
            btnText.textContent = 'Logging in...';
            loginForm.querySelector('button').disabled = true;

            try {
                const response = await fetch('/admin/auth/auth_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'login',
                        email: email,
                        password: password,
                        remember_me: rememberMe ? '1' : '0'
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showAlert('success', data.message);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showAlert('danger', data.error);
                }
            } catch (error) {
                showAlert('danger', 'An error occurred. Please try again.');
                console.error('Login error:', error);
            } finally {
                loadingSpinner.style.display = 'none';
                btnText.textContent = 'Login';
                loginForm.querySelector('button').disabled = false;
            }
        });

        function showAlert(type, message) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;

            if (type !== 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }
    </script>
</body>
</html>