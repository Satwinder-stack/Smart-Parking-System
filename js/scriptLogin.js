function toggleTable() {
    const infoSection = document.getElementById('infoSection');
    infoSection.classList.toggle('collapsed');
}

// Modify the form submission
document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        // First check credentials
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
            // Update welcome message with username
            const welcomeText = document.getElementById('welcomeText');
            welcomeText.textContent = `Welcome to Smart Parking System, ${username}!`;
            
            // Show loading screen
            const loadingScreen = document.getElementById('loadingScreen');
            loadingScreen.classList.add('active');
            
            // Submit the form after showing the loading screen
            setTimeout(() => {
                this.submit();
            }, 5000); // Wait 5 seconds before submitting
        } else {
            // Show error message without animation
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = data.message;
            
            // Remove any existing error message
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            // Insert new error message after the h2
            const h2 = document.querySelector('.auth-box h2');
            h2.insertAdjacentElement('afterend', errorDiv);
        }
    } catch (error) {
        console.error('Error:', error);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = 'An error occurred during login';
        
        // Remove any existing error message
        const existingError = document.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Insert new error message after the h2
        const h2 = document.querySelector('.auth-box h2');
        h2.insertAdjacentElement('afterend', errorDiv);
    }
});

// If we're coming from a successful login
if (window.location.search.includes('success=1')) {
    // Get username from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const username = urlParams.get('username');
    
    // Update welcome message with username if available
    const welcomeText = document.getElementById('welcomeText');
    if (username) {
        welcomeText.textContent = `Welcome to Parking System, ${username}`;
    }
    
    const loadingScreen = document.getElementById('loadingScreen');
    loadingScreen.classList.add('active');
    
    // Redirect to index.php after 5 seconds
    setTimeout(() => {
        window.location.href = 'index.php';
    }, 5000);
}