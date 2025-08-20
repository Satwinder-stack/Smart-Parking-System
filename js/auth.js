// Check if user is logged in
function checkAuth() {
    const isLoggedIn = localStorage.getItem('isLoggedIn');
    const currentPage = window.location.pathname.split('/').pop();
    
    if (!isLoggedIn && currentPage !== 'login.html' && currentPage !== 'signup.html') {
        window.location.href = 'login.html';
    } else if (isLoggedIn && (currentPage === 'login.html' || currentPage === 'signup.html')) {
        window.location.href = 'index.html';
    }
}

// Handle login
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const logoutBtn = document.getElementById('logoutBtn');
    const mainContent = document.getElementById('mainContent');

    // Show main content if logged in
    if (localStorage.getItem('isLoggedIn')) {
        if (mainContent) mainContent.style.display = 'flex';
    }

    // Login form handler
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'login',
                        username: username,
                        password: password
                    })
                });

                const data = await response.json();
                
                if (data.message === "Login successful") {
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('username', data.user.username);
                    localStorage.setItem('userId', data.user.id);
                    window.location.href = 'index.html';
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred during login');
            }
        });
    }

    // Signup form handler
    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fullname = document.getElementById('fullname').value;
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'signup',
                        fullname: fullname,
                        username: username,
                        email: email,
                        password: password
                    })
                });

                const data = await response.json();
                
                if (data.message === "User created successfully") {
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('username', data.user.username);
                    localStorage.setItem('userId', data.user.id);
                    window.location.href = 'index.html';
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred during signup');
            }
        });
    }

    // Logout handler
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('username');
            localStorage.removeItem('userId');
            window.location.href = 'login.html';
        });
    }
});

// Check authentication on page load
checkAuth(); 