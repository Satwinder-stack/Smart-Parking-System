// Handle profile photo upload
document.querySelector('.upload-overlay').addEventListener('click', function() {
    document.getElementById('profile-photo-input').click();
});

let isUploading = false;

document.getElementById('profile-photo-input').addEventListener('change', function() {
    if (this.files && this.files[0] && !isUploading) {
        isUploading = true;
        
        // Show loading state
        const avatar = document.querySelector('.profile-avatar');
        avatar.innerHTML = '<div class="default-icon"><img src="images/icon.png" alt="Loading..."></div>';
        
        // Submit the form
        this.form.submit();
    }
});

// Reset upload state when page loads
window.addEventListener('load', function() {
    isUploading = false;
});