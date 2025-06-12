// Update popup functions
function showPopup(message) {
    const popup = document.getElementById('customPopup');
    const overlay = document.getElementById('popupOverlay');
    const messageElement = document.getElementById('popupMessage');
    
    if (popup && overlay && messageElement) {
        messageElement.textContent = message;
        popup.style.display = 'block';
        overlay.style.display = 'block';
    } else {
        console.error('Popup elements not found');
        alert(message); // Fallback to alert if popup elements are missing
    }
}

function closePopup() {
    const popup = document.getElementById('customPopup');
    const overlay = document.getElementById('popupOverlay');
    
    if (popup && overlay) {
        popup.style.display = 'none';
        overlay.style.display = 'none';
    }
}

// Add styles for popup
const style = document.createElement('style');
style.textContent = `
    .popup {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        min-width: 300px;
    }

    .popup-content {
        position: relative;
        text-align: center;
    }

    .popup-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }

    .close {
        position: absolute;
        right: -5px;
        top: 5px;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .close:hover {
        color: #000;
    }

    #popupMessage {
        margin: 0;
        padding: 10px;
        color: #333;
    }
`;
document.head.appendChild(style);

// Add event listeners for time inputs
document.getElementById('start_time').addEventListener('change', function() {
    const selectedDate = document.getElementById('date').value;
    const selectedTime = this.value;
    const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
    const currentTime = new Date();
    
    // Calculate time difference in minutes
    const timeDiff = (selectedDateTime - currentTime) / (1000 * 60);
    
    if (timeDiff < -10) {
        showPopup('This time slot has passed. Please choose a different start date.');
        this.value = formattedTime; // Reset to current time
    }
    updateEndDate();
    calculateCost();
});

document.getElementById('end_time').addEventListener('change', function() {
    updateEndDate();
    calculateCost();
});

document.getElementById('date').addEventListener('change', function() {
    const selectedDate = this.value;
    const selectedTime = document.getElementById('start_time').value;
    const selectedDateTime = new Date(`${selectedDate}T${selectedTime}`);
    const currentTime = new Date();
    
    // Calculate time difference in minutes
    const timeDiff = (selectedDateTime - currentTime) / (1000 * 60);
    
    if (timeDiff < -10) {
        showPopup('This time slot has passed. Please choose a different start date.');
        this.value = formattedDate; // Reset to current date
        document.getElementById('start_time').value = formattedTime; // Reset to current time
    } else {
        // Set end date to match start date
        document.getElementById('end_date').value = selectedDate;
    }
    updateEndDate();
    calculateCost();
});

// Calculate cost
function calculateCost() {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const startDate = document.getElementById('date').value;
    const endDate = document.getElementById('end_date').value;

    if (startTime && endTime && startDate && endDate) {
        const start = new Date(startDate + 'T' + startTime);
        const end = new Date(endDate + 'T' + endTime);
        
        if (end > start) {
            // Calculate total hours including minutes
            const diffMs = end - start;
            const diffHours = diffMs / (1000 * 60 * 60);
            
            // Round up to the nearest hour
            const totalHours = Math.ceil(diffHours);
            
            // Calculate cost (₱50 per hour)
            const cost = totalHours * 50;
            
            document.getElementById('cost_display').textContent = 
                `Estimated Cost: ₱${cost.toFixed(2)}`;
        } else {
            document.getElementById('cost_display').textContent = 
                'Invalid time selection';
        }
    } else {
        document.getElementById('cost_display').textContent = 
            'Please select start and end times';
    }
}

// Set default date and time when page loads
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    
    // Get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const dateParam = urlParams.get('date');
    const startParam = urlParams.get('start');
    const endParam = urlParams.get('end');
    const spotParam = urlParams.get('spot');
    
    // Format date as YYYY-MM-DD
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const formattedDate = dateParam || `${year}-${month}-${day}`; // Use dateParam if available
    
    // Format time as HH:MM
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const formattedTime = startParam || `${hours}:${minutes}`;
    
    // Set the default values
    document.getElementById('date').value = formattedDate;
    document.getElementById('end_date').value = formattedDate; // Set end date same as start date initially
    document.getElementById('start_time').value = formattedTime;
    if (endParam) {
        document.getElementById('end_time').value = endParam;
        // Check if end time is before start time and update end date if needed
        updateEndDate();
    } else {
        // Set default end time to 1 hour after start time
        const endTime = new Date(now.getTime() + 60 * 60 * 1000);
        document.getElementById('end_time').value = 
            `${String(endTime.getHours()).padStart(2, '0')}:${String(endTime.getMinutes()).padStart(2, '0')}`;
    }
    
    // If spot parameter is provided, select the corresponding spot
    if (spotParam) {
        const spotItems = document.querySelectorAll('.spot-item');
        spotItems.forEach(item => {
            if (item.textContent.trim() === spotParam) {
                item.click();
            }
        });
    }
    
    // Calculate initial cost
    calculateCost();
});

// Update form submission handler
document.getElementById('reservationForm').addEventListener('submit', function(e) {
    // Make sure end_date is set before submitting
    updateEndDate();
    
    // Validate form before submission
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const startDate = document.getElementById('date').value;
    const endDate = document.getElementById('end_date').value;
    const spotId = document.getElementById('selected_spot').value;

    if (!spotId) {
        e.preventDefault();
        showPopup('Please select a parking spot');
        return;
    }

    if (!startTime || !endTime || !startDate || !endDate) {
        e.preventDefault();
        showPopup('All fields are required');
        return;
    }

    const start = new Date(`${startDate}T${startTime}`);
    const end = new Date(`${endDate}T${endTime}`);
    const currentTime = new Date();
    
    // Calculate time difference in minutes
    const timeDiff = (start - currentTime) / (1000 * 60);

    if (timeDiff < -10) {
        e.preventDefault();
        showPopup('This time slot has passed. Please choose a different start date.');
        return;
    }

    // For future dates, ensure end date matches start date
    if (startDate > currentTime.toISOString().split('T')[0]) {
        if (endDate !== startDate) {
            e.preventDefault();
            showPopup('For future dates, the end date must be the same as the start date');
            return;
        }
    }

    if (end <= start) {
        e.preventDefault();
        showPopup('End time must be after start time');
        return;
    }

    // Calculate final cost before submission
    calculateCost();

    // Log form data for debugging
    console.log('Submitting form with data:', {
        spotId,
        startDate,
        endDate,
        startTime,
        endTime,
        start: start.toISOString(),
        end: end.toISOString(),
        currentTime: currentTime.toISOString()
    });
});

// Function to update end date based on time selection
function updateEndDate() {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const startDate = document.getElementById('date').value;
    
    if (startTime && endTime) {
        const start = new Date(startDate + 'T' + startTime);
        const end = new Date(startDate + 'T' + endTime);
        
        // If end time is before start time, set end date to next day
        if (end <= start) {
            const nextDay = new Date(startDate);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('end_date').value = nextDay.toISOString().split('T')[0];
        } else {
            // If end time is after start time, keep same day
            document.getElementById('end_date').value = startDate;
        }
    }
}

// Function to fetch and display spot details
async function fetchSpotDetails(spotId) {
    try {
        const response = await fetch(`get_spot_details.php?spot_id=${spotId}`);
        const data = await response.json();
        
        const detailsContainer = document.getElementById('spotDetailsContent');
        const spotDetails = document.getElementById('spotDetails');
        
        if (data.reservations && data.reservations.length > 0) {
            let html = '';
            data.reservations.forEach(reservation => {
                // Convert times to AM/PM format
                const formatTime = (time) => {
                    const [hours, minutes] = time.split(':');
                    const hour = parseInt(hours);
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const hour12 = hour % 12 || 12;
                    return `${hour12}:${minutes} ${ampm}`;
                };

                html += `
                    <div class="reserved-spot-item">
                        <div class="date">${reservation.reservation_date} - ${reservation.end_date}</div>
                        <div class="time"><h4>Time: ${formatTime(reservation.start_time)} - ${formatTime(reservation.end_time)}</h4></div>
                        <div class="status status-badge ${reservation.current_status.toLowerCase()}">
                            ${reservation.current_status}
                        </div>
                    </div>
                `;
            });
            detailsContainer.innerHTML = html;
        } else {
            detailsContainer.innerHTML = '<p>No reservations found for this spot.</p>';
        }
        
        spotDetails.classList.add('active');
    } catch (error) {
        console.error('Error fetching spot details:', error);
    }
}

// Track the currently selected spot
let currentSpotId = null;

// Modify spot click handler
document.querySelectorAll('.spot-item').forEach(spot => {
    spot.addEventListener('click', function() {
        const spotId = this.dataset.spotId;
        const spotDetails = document.getElementById('spotDetails');
        const mainContainer = document.querySelector('.main-container');
        
        // If clicking the same spot, toggle the side tab
        if (currentSpotId === spotId) {
            if (spotDetails.classList.contains('active')) {
                // Closing animation
                spotDetails.style.transform = 'translateX(100%)';
                spotDetails.style.opacity = '0';
                mainContainer.classList.remove('has-selected-spot');
                setTimeout(() => {
                    spotDetails.classList.remove('active');
                    spotDetails.style.visibility = 'hidden';
                }, 300);
            } else {
                // Opening animation
                spotDetails.style.visibility = 'visible';
                spotDetails.classList.add('active');
                mainContainer.classList.add('has-selected-spot');
                // Force a reflow to ensure the animation triggers
                void spotDetails.offsetWidth;
                spotDetails.style.transform = 'translateX(0)';
                spotDetails.style.opacity = '1';
            }
            this.classList.toggle('selected');
            if (!spotDetails.classList.contains('active')) {
                currentSpotId = null;
            }
        } else {
            // If clicking a different spot, update the selection
            document.querySelectorAll('.spot-item').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selected_spot').value = spotId;
            
            // Handle animation for new spot selection
            if (spotDetails.classList.contains('active')) {
                // If panel is already open, animate it out first
                spotDetails.style.transform = 'translateX(100%)';
                spotDetails.style.opacity = '0';
                setTimeout(() => {
                    fetchSpotDetails(spotId);
                    spotDetails.style.visibility = 'visible';
                    mainContainer.classList.add('has-selected-spot');
                    // Force a reflow to ensure the animation triggers
                    void spotDetails.offsetWidth;
                    spotDetails.style.transform = 'translateX(0)';
                    spotDetails.style.opacity = '1';
                }, 300);
            } else {
                // If panel is closed, just open it with animation
                spotDetails.style.visibility = 'visible';
                spotDetails.classList.add('active');
                mainContainer.classList.add('has-selected-spot');
                fetchSpotDetails(spotId);
                // Force a reflow to ensure the animation triggers
                void spotDetails.offsetWidth;
                spotDetails.style.transform = 'translateX(0)';
                spotDetails.style.opacity = '1';
            }
            currentSpotId = spotId;
        }
    });
});
