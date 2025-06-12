// Update the click event for parking spots
document.querySelectorAll('.parking-spot').forEach(spot => {
    spot.addEventListener('click', function() {
        const spotNumber = this.querySelector('.spot-number').textContent;
        const reservations = this.getAttribute('data-reservations');
        
        // Update panel content
        document.getElementById('spotNumber').textContent = `Parking Spot ${spotNumber}`;
        
        const reservationsList = document.getElementById('reservationsList');
        reservationsList.innerHTML = '';
        
        if (reservations) {
            const reservationArray = reservations.split('||');
            reservationArray.forEach(reservation => {
                const [startDate, endDate, startTime, endTime, status] = reservation.split('|');
                
                // Convert 24-hour time to 12-hour format with AM/PM
                const formatTime = (time) => {
                    const [hours, minutes] = time.split(':');
                    const hour = parseInt(hours);
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const hour12 = hour % 12 || 12;
                    return `${hour12}:${minutes} ${ampm}`;
                };

                const reservationItem = document.createElement('div');
                reservationItem.className = `reservation-item ${status}`;
                
                // Format the date display
                let dateDisplay = startDate;
                if (startDate !== endDate) {
                    dateDisplay = `${startDate} - ${endDate}`;
                }

                // Handle overnight reservations
                let timeDisplay = '';
                if (startTime > endTime) {
                    timeDisplay = `${formatTime(startTime)} - ${formatTime(endTime)} (Next Day)`;
                } else {
                    timeDisplay = `${formatTime(startTime)} - ${formatTime(endTime)}`;
                }

                reservationItem.innerHTML = `
                    <div class="reservation-date">${dateDisplay}</div>
                    <div class="reservation-time">
                        ${timeDisplay}
                        <span class="reservation-status ${status}">${status}</span>
                    </div>
                `;
                reservationsList.appendChild(reservationItem);
            });
        } else {
            reservationsList.innerHTML = `
                <div class="no-reservations">
                    <p>No upcoming reservations</p>
                </div>
            `;
        }
        
        // Show the panel
        const panel = document.getElementById('reservationPanel');
        const overlay = document.getElementById('panelOverlay');
        panel.classList.add('active');
        overlay.classList.add('active');
    });
});

// Update close handlers to handle both panels
document.querySelectorAll('.sliding-panel-close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        const panel = this.closest('.sliding-panel');
        panel.classList.remove('active');
        document.getElementById('panelOverlay').classList.remove('active');
    });
});

document.getElementById('panelOverlay').addEventListener('click', function() {
    document.querySelectorAll('.sliding-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    this.classList.remove('active');
});

// Add this to your existing JavaScript
document.getElementById('findNearestTime').addEventListener('click', function() {
    // Get current date and time
    const now = new Date();
    const currentDate = now.toISOString().split('T')[0];
    const currentTime = now.toTimeString().slice(0, 5);

    // Helper function to compare times properly
    const compareTimes = (time1, time2) => {
        const [hours1, minutes1] = time1.split(':').map(Number);
        const [hours2, minutes2] = time2.split(':').map(Number);
        const totalMinutes1 = hours1 * 60 + minutes1;
        const totalMinutes2 = hours2 * 60 + minutes2;
        return totalMinutes1 - totalMinutes2;
    };

    // Helper function to add minutes to time
    const addMinutesToTime = (time, minutes) => {
        const [hours, mins] = time.split(':').map(Number);
        const totalMinutes = hours * 60 + mins + minutes;
        const newHours = Math.floor(totalMinutes / 60) % 24;
        const newMinutes = totalMinutes % 60;
        return `${String(newHours).padStart(2, '0')}:${String(newMinutes).padStart(2, '0')}`;
    };

    // Helper function to calculate end time with buffer
    const calculateEndTimeWithBuffer = (startTime) => {
        const [hours, minutes] = startTime.split(':').map(Number);
        let totalMinutes = hours * 60 + minutes - 11; // Subtract 11 minutes for buffer
        if (totalMinutes < 0) {
            totalMinutes += 24 * 60; // Handle overnight
        }
        const newHours = Math.floor(totalMinutes / 60);
        const newMinutes = totalMinutes % 60;
        return `${String(newHours).padStart(2, '0')}:${String(newMinutes).padStart(2, '0')}`;
    };

    // Helper function to format time to AM/PM
    const formatTimeToAMPM = (time) => {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    };

    // Helper function to get next day date
    const getNextDay = (date) => {
        const nextDay = new Date(date);
        nextDay.setDate(nextDay.getDate() + 1);
        return nextDay.toISOString().split('T')[0];
    };

    // Helper function to check if time is overnight
    const isOvernight = (startTime, endTime) => {
        return compareTimes(startTime, endTime) > 0;
    };

    // Get all parking spots' reservations
    const spots = document.querySelectorAll('.parking-spot');
    const availableSpots = [];
    const processedSpots = new Set();

    // First, collect all currently available spots and reserved spots
    spots.forEach(spot => {
        const status = spot.querySelector('.spot-status').textContent.trim();
        const spotNumber = spot.querySelector('.spot-number').textContent;
        const reservations = spot.dataset.reservations;
        
        if (status === 'Available' && reservations && !processedSpots.has(spotNumber)) {
            processedSpots.add(spotNumber);
            
            // Get all future reservations for this spot
            const futureReservations = reservations.split('||')
                .map(res => {
                    const [date, endDate, start, end, status] = res.split('|');
                    return { 
                        date, 
                        endDate, 
                        start, 
                        end, 
                        status,
                        isOvernight: isOvernight(start, end)
                    };
                })
                .filter(res => res.date >= currentDate)
                .sort((a, b) => {
                    if (a.date === b.date) {
                        return compareTimes(a.start, b.start);
                    }
                    return a.date.localeCompare(b.date);
                });

            if (futureReservations.length > 0) {
                // Only add the spot if it's available now (before the first reservation)
                const firstReservation = futureReservations[0];
                if (firstReservation.date > currentDate || 
                    (firstReservation.date === currentDate && compareTimes(currentTime, firstReservation.start) < 0)) {
                    let availableDate = currentDate;
                    let availableStartTime = currentTime;
                    let availableEndTime = calculateEndTimeWithBuffer(firstReservation.start);

                    // Handle overnight reservations
                    if (firstReservation.isOvernight) {
                        if (compareTimes(currentTime, firstReservation.start) >= 0) {
                            availableDate = getNextDay(currentDate);
                            availableStartTime = '00:00';
                        }
                    }

                    availableSpots.push({
                        date: availableDate,
                        start: availableStartTime,
                        end: availableEndTime,
                        spotNumber: spotNumber,
                        isAvailable: true,
                        priority: 1,
                        nextReservation: firstReservation
                    });
                }
            } else {
                // If no future reservations, spot is available all day
                availableSpots.push({
                    date: currentDate,
                    start: currentTime,
                    end: '23:59',
                    spotNumber: spotNumber,
                    isAvailable: true,
                    priority: 1
                });
            }
        }
    });

    // Process spots with reservations for future availability
    spots.forEach(spot => {
        const reservations = spot.dataset.reservations;
        const spotNumber = spot.querySelector('.spot-number').textContent;
        
        if (reservations && !processedSpots.has(spotNumber)) {
            processedSpots.add(spotNumber);
            
            const futureReservations = reservations.split('||')
                .map(res => {
                    const [date, endDate, start, end, status] = res.split('|');
                    return { 
                        date, 
                        endDate, 
                        start, 
                        end, 
                        status,
                        isOvernight: isOvernight(start, end)
                    };
                })
                .filter(res => res.date >= currentDate && status !== 'completed')
                .sort((a, b) => {
                    if (a.date === b.date) {
                        return compareTimes(a.start, b.start);
                    }
                    return a.date.localeCompare(b.date);
                });

            // Special handling for spot P10
            if (spotNumber === 'P10' && futureReservations.length > 0) {
                const firstReservation = futureReservations[0];
                // Add availability from current date until the reservation
                availableSpots.push({
                    date: currentDate,
                    start: currentTime,
                    end: '23:59',
                    spotNumber: spotNumber,
                    priority: 1,
                    nextReservation: firstReservation,
                    isAvailable: true
                });
                
                // If the reservation is not on the current date, add availability for the days in between
                if (firstReservation.date > currentDate) {
                    const daysBetween = Math.floor((new Date(firstReservation.date) - new Date(currentDate)) / (1000 * 60 * 60 * 24));
                    for (let i = 1; i < daysBetween; i++) {
                        const nextDate = new Date(currentDate);
                        nextDate.setDate(nextDate.getDate() + i);
                        const dateStr = nextDate.toISOString().split('T')[0];
                        availableSpots.push({
                            date: dateStr,
                            start: '00:00',
                            end: '23:59',
                            spotNumber: spotNumber,
                            priority: 1,
                            nextReservation: firstReservation,
                            isAvailable: true
                        });
                    }
                    // Add availability for the reservation date until the reservation time
                    availableSpots.push({
                        date: firstReservation.date,
                        start: '00:00',
                        end: calculateEndTimeWithBuffer(firstReservation.start),
                        spotNumber: spotNumber,
                        priority: 1,
                        nextReservation: firstReservation,
                        isAvailable: true
                    });
                }
            } else if (futureReservations.length > 0) {
                // Original logic for other spots
                const lastRes = futureReservations[futureReservations.length - 1];
                let availableStartTime = addMinutesToTime(lastRes.end, 11);
                let availableDate = lastRes.date;

                // Handle overnight reservations
                if (lastRes.isOvernight) {
                    availableDate = getNextDay(lastRes.date);
                    if (compareTimes(availableStartTime, lastRes.end) < 0) {
                        availableStartTime = '00:00';
                    }
                } else if (compareTimes(availableStartTime, lastRes.end) < 0) {
                    availableDate = getNextDay(lastRes.date);
                }

                availableSpots.push({
                    date: availableDate,
                    start: availableStartTime,
                    end: '23:59',
                    spotNumber: spotNumber,
                    priority: 2
                });
            }
        }
    });

    // Sort all suggestions by priority first, then by date and time
    availableSpots.sort((a, b) => {
        if (a.priority !== b.priority) {
            return a.priority - b.priority;
        }
        if (a.date !== b.date) {
            return a.date.localeCompare(b.date);
        }
        return compareTimes(a.start, b.start);
    });

    // Take the top 5 unique suggestions
    const topSuggestions = [];
    const suggestedSpots = new Set();
    
    for (const suggestion of availableSpots) {
        if (topSuggestions.length >= 5) break;
        
        // Only add if we haven't suggested this spot before
        if (!suggestedSpots.has(suggestion.spotNumber)) {
            suggestedSpots.add(suggestion.spotNumber);
            topSuggestions.push(suggestion);
        }
    }

    // Update the panel content
    const panel = document.getElementById('nearestTimePanel');
    const overlay = document.getElementById('panelOverlay');
    const resultDiv = document.getElementById('nearestTimeResult');
    
    if (topSuggestions.length > 0) {
        let suggestionsHtml = '<div class="suggestions-list">';
        
        // Show first suggestion (best option)
        const bestOption = topSuggestions[0];
        suggestionsHtml += `
            <div class="suggestion-item highlighted">
                <h3>Best Available Option:</h3>
                <p><strong>Spot Number:</strong> ${bestOption.spotNumber}</p>
                <p><strong>Date:</strong> ${bestOption.date}</p>
                <p><strong>Time:</strong> ${formatTimeToAMPM(bestOption.start)} - ${formatTimeToAMPM(bestOption.end)}</p>
                ${bestOption.nextReservation ? `<p class="reservation-note">Note: This spot has a reservation starting at ${formatTimeToAMPM(bestOption.nextReservation.start)} on ${bestOption.nextReservation.date}</p>` : ''}
                <button onclick="window.location.href='reservation.php?date=${bestOption.date}&start=${bestOption.start}&end=${bestOption.end}&spot=${bestOption.spotNumber}'" 
                        class="reserve-btn">Reserve This Spot</button>
            </div>
        `;

        // Add remaining suggestions if any
        if (topSuggestions.length > 1) {
            suggestionsHtml += `
                <button class="show-more-btn" onclick="toggleMoreSuggestions(this)">
                    Show More Options (${topSuggestions.length - 1})
                </button>
                <div class="more-suggestions" style="display: none;">
            `;

            for (let i = 1; i < topSuggestions.length; i++) {
                const suggestion = topSuggestions[i];
                suggestionsHtml += `
                    <div class="suggestion-item">
                        <p><strong>Spot Number:</strong> ${suggestion.spotNumber}</p>
                        <p><strong>Date:</strong> ${suggestion.date}</p>
                        <p><strong>Time:</strong> ${formatTimeToAMPM(suggestion.start)} - ${formatTimeToAMPM(suggestion.end)}</p>
                        ${suggestion.nextReservation ? `<p class="reservation-note">Note: This spot has a reservation starting at ${formatTimeToAMPM(suggestion.nextReservation.start)} on ${suggestion.nextReservation.date}</p>` : ''}
                        <button onclick="window.location.href='reservation.php?date=${suggestion.date}&start=${suggestion.start}&end=${suggestion.end}&spot=${suggestion.spotNumber}'" 
                                class="reserve-btn">Reserve This Spot</button>
                    </div>
                `;
            }
            suggestionsHtml += '</div>';
        }
        
        suggestionsHtml += '</div>';
        resultDiv.innerHTML = suggestionsHtml;
    } else {
        resultDiv.innerHTML = '<p>No available time slots found.</p>';
    }

    panel.classList.add('active');
    overlay.classList.add('active');
});

// Add this function to handle showing/hiding more suggestions
function toggleMoreSuggestions(button) {
    const moreSuggestions = button.nextElementSibling;
    const isHidden = moreSuggestions.style.display === 'none';
    
    moreSuggestions.style.display = isHidden ? 'flex' : 'none';
    button.textContent = isHidden ? 
        'Show Less Options' : 
        `Show More Options (${moreSuggestions.children.length})`;
}

// Add new event listener for vacant spots button
document.getElementById('findVacantSpots').addEventListener('click', function() {
    const spots = document.querySelectorAll('.parking-spot');
    const vacantSpots = [];
    
    // Get current date and time
    const now = new Date();
    const currentDate = now.toISOString().split('T')[0];
    const currentTime = now.toTimeString().slice(0, 5);

    // Collect all vacant spots
    spots.forEach(spot => {
        const status = spot.querySelector('.spot-status').textContent.trim();
        const spotNumber = spot.querySelector('.spot-number').textContent;
        const reservations = spot.dataset.reservations;

        if (status === 'Available') {
            // Check if the spot has any upcoming reservations today
            let hasUpcomingReservation = false;
            if (reservations) {
                const todayReservations = reservations.split('||')
                    .map(res => {
                        const [date, endDate, start, end, status] = res.split('|');
                        return { date, start, end, status };
                    })
                    .filter(res => res.date === currentDate && compareTimes(res.start, currentTime) > 0);

                hasUpcomingReservation = todayReservations.length > 0;
            }

            if (!hasUpcomingReservation) {
                vacantSpots.push({
                    spotNumber: spotNumber,
                    date: currentDate,
                    start: currentTime,
                    end: '23:59'
                });
            }
        }
    });

    // Show results in the panel
    const panel = document.getElementById('vacantSpotsPanel');
    const overlay = document.getElementById('panelOverlay');
    const resultDiv = document.getElementById('vacantSpotsResult');

    if (vacantSpots.length > 0) {
        let spotsHtml = '<div class="suggestions-list">';
        
        vacantSpots.forEach(spot => {
            spotsHtml += `
                <div class="vacant-spot-item">
                    <h3>Spot ${spot.spotNumber}</h3>
                    <p><strong>Status:</strong> Currently Available</p>
                    <p><strong>Available Until:</strong> End of Day</p>
                    <button onclick="window.location.href='reservation.php?date=${spot.date}&start=${spot.start}&end=${spot.end}&spot=${spot.spotNumber}'" 
                            class="reserve-btn">Reserve This Spot</button>
                </div>
            `;
        });
        
        spotsHtml += '</div>';
        resultDiv.innerHTML = spotsHtml;
    } else {
        // If no vacant spots, show a message and trigger the nearest time finder
        resultDiv.innerHTML = `
            <div class="no-vacant-spots">
                <p>No vacant spots available at the moment.</p>
                <p>Looking for nearest available times...</p>
            </div>
        `;
        
        // After a short delay, trigger the nearest time finder
        setTimeout(() => {
            document.getElementById('findNearestTime').click();
        }, 1500);
    }

    panel.classList.add('active');
    overlay.classList.add('active');
});