<?php
session_start();

// Verify admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Debug query to check spots 1 and 7 specifically
$debug_query = "SELECT o.*, a.spot_number 
    FROM occupied_spots o 
    JOIN available_spots a ON o.spot_id = a.id 
    WHERE a.spot_number IN ('P1', 'P7') 
    AND o.status != 'completed' 
    AND (
        (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time)
        OR (o.status = 'reserved' AND (
            o.reservation_date > CURDATE()
            OR (o.reservation_date = CURDATE() AND o.start_time > CURTIME())
        ))
    )";
$debug_stmt = $db->prepare($debug_query);
$debug_stmt->execute();
$debug_results = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);

error_log("Debug - Spots 1 and 7 occupancy check:");
error_log("Current time: " . date('Y-m-d H:i:s'));
foreach ($debug_results as $result) {
    error_log("Spot: " . $result['spot_number'] . 
              ", Status: " . $result['status'] . 
              ", Date: " . $result['reservation_date'] . 
              ", Time: " . $result['start_time'] . " - " . $result['end_time']);
}

// Get all parking spots with their current status
$spots_query = "SELECT a.*, 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM occupied_spots o 
            WHERE o.spot_id = a.id 
            AND o.status != 'completed'
            AND (
                (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time)
                OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time)
                OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time)
                OR (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
            )
        ) THEN 'occupied'
        WHEN EXISTS (
            SELECT 1 FROM occupied_spots o 
            WHERE o.spot_id = a.id 
            AND o.status = 'reserved'
            AND (
                o.reservation_date > CURDATE()
                OR (o.reservation_date = CURDATE() AND o.start_time > CURTIME())
                OR (o.reservation_date < CURDATE() AND o.end_date >= CURDATE())
            )
        ) THEN 'reserved'
        ELSE 'available'
    END as current_status,
    CAST(SUBSTRING(spot_number, 2) AS UNSIGNED) as spot_number_numeric
    FROM available_spots a
    ORDER BY spot_number_numeric ASC";

$spots_stmt = $db->prepare($spots_query);
$spots_stmt->execute();
$spots = $spots_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug the final status of spots 1 and 7
foreach ($spots as $spot) {
    if ($spot['spot_number'] === 'P1' || $spot['spot_number'] === 'P7') {
        error_log("Final status for " . $spot['spot_number'] . ": " . $spot['current_status']);
    }
}

// Get today's reservations
$today_query = "SELECT o.*, a.spot_number, u.username, u.fullname,
    CASE 
        WHEN (o.reservation_date = CURDATE() AND CURTIME() BETWEEN o.start_time AND o.end_time) OR
             (o.reservation_date < CURDATE() AND o.end_date >= CURDATE() AND CURTIME() <= o.end_time) OR
             (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() >= o.start_time) OR
             (o.reservation_date = CURDATE() AND o.start_time > o.end_time AND CURTIME() <= o.end_time)
        THEN 'ongoing'
        ELSE 'reserved'
    END as current_status
    FROM occupied_spots o
    JOIN available_spots a ON o.spot_id = a.id
    JOIN users u ON o.user_id = u.id
    WHERE DATE(o.reservation_date) = CURDATE()
    AND o.status != 'completed'
    ORDER BY o.start_time";

$today_stmt = $db->prepare($today_query);
$today_stmt->execute();
$today_reservations = $today_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get financial reports
$financial_query = "
    SELECT 
        DATE_FORMAT(reservation_date, '%Y-%m') as month,
        COUNT(*) as total_reservations,
        SUM(cost) as total_revenue,
        AVG(cost) as average_revenue,
        COUNT(DISTINCT user_id) as unique_users
    FROM occupied_spots
    WHERE status != 'cancelled'
    AND reservation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(reservation_date, '%Y-%m')
    ORDER BY month DESC";

$financial_stmt = $db->prepare($financial_query);
$financial_stmt->execute();
$financial_data = $financial_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$user_stats_query = "
    SELECT 
        COUNT(DISTINCT user_id) as total_users,
        COUNT(DISTINCT CASE WHEN reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN user_id END) as active_users_30d,
        COUNT(DISTINCT CASE WHEN reservation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN user_id END) as active_users_7d
    FROM occupied_spots
    WHERE status != 'cancelled'";

$user_stats_stmt = $db->prepare($user_stats_query);
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get peak hours analysis
$peak_hours_query = "
    SELECT 
        HOUR(start_time) as hour,
        COUNT(*) as reservation_count
    FROM occupied_spots
    WHERE reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND status != 'cancelled'
    GROUP BY HOUR(start_time)
    ORDER BY hour";

$peak_hours_stmt = $db->prepare($peak_hours_query);
$peak_hours_stmt->execute();
$peak_hours_data = $peak_hours_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get spot utilization
$spot_utilization_query = "
    SELECT 
        a.spot_number,
        COALESCE(COUNT(o.id), 0) as total_reservations,
        COALESCE(SUM(CASE WHEN o.status = 'ongoing' THEN 1 ELSE 0 END), 0) as ongoing_reservations,
        COALESCE(SUM(o.cost), 0) as total_revenue
    FROM available_spots a
    LEFT JOIN occupied_spots o ON a.id = o.spot_id 
        AND o.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND o.status != 'cancelled'
    GROUP BY a.id, a.spot_number
    ORDER BY total_reservations DESC";

$spot_utilization_stmt = $db->prepare($spot_utilization_query);
$spot_utilization_stmt->execute();
$spot_utilization_data = $spot_utilization_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total number of spots
$total_spots_query = "SELECT COUNT(*) as total_spots FROM available_spots";
$total_spots_stmt = $db->prepare($total_spots_query);
$total_spots_stmt->execute();
$total_spots = $total_spots_stmt->fetch(PDO::FETCH_ASSOC)['total_spots'];

// Get current ongoing reservations count
$current_ongoing_query = "
    SELECT COUNT(*) as current_ongoing
    FROM occupied_spots o
    JOIN available_spots a ON o.spot_id = a.id
    WHERE o.status = 'ongoing'";
$current_ongoing_stmt = $db->prepare($current_ongoing_query);
$current_ongoing_stmt->execute();
$current_ongoing = $current_ongoing_stmt->fetch(PDO::FETCH_ASSOC)['current_ongoing'];

$admin_username = $_SESSION['admin_username'];

// Calculate financial summary for JavaScript functions
$total_revenue = 0;
$total_reservations = 0;
foreach ($financial_data as $data) {
    $total_revenue += $data['total_revenue'];
    $total_reservations += $data['total_reservations'];
}
$avg_revenue = $total_reservations > 0 ? $total_revenue / $total_reservations : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Parking System</title>

    <link rel="stylesheet" href="css/stylesAdmin.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
    // Navigation function - must be available before HTML loads
    function showSection(sectionId) {
        // Hide all content sections
        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => {
            section.classList.remove('active');
        });

        // Remove active class from all nav boxes
        const navBoxes = document.querySelectorAll('.nav-box');
        navBoxes.forEach(box => {
            box.classList.remove('active');
        });

        // Show the selected section
        document.getElementById(sectionId).classList.add('active');

        // Add active class to the clicked nav box
        event.currentTarget.classList.add('active');
    }
    </script>
    
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($admin_username); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Navigation Boxes -->
        <div class="admin-navigation">
            <div class="nav-box active" onclick="showSection('parking-management')">
                <i class="fas fa-car"></i>
                <h3>Parking Management</h3>
                <p>Manage parking spots and reservations</p>
            </div>
            <div class="nav-box" onclick="showSection('system-report')">
                <i class="fas fa-chart-line"></i>
                <h3>System Report</h3>
                <p>View analytics and system reports</p>
            </div>
            <div class="nav-box" onclick="showSection('download-report')">
                <i class="fas fa-download"></i>
                <h3>Download Report</h3>
                <p>Generate and download PDF reports</p>
            </div>
        </div>

        <div class="admin-content">
            <div class="welcome-message">
                Welcome to the Smart Parking System Admin Dashboard
            </div>

            <!-- Parking Management Section -->
            <div id="parking-management" class="content-section active">
                <div class="management-section">
                    <div class="section-header">
                        <h2><i class="fas fa-car"></i> Parking Management</h2>
                    </div>

                    <div class="spots-grid">
                        <?php foreach ($spots as $spot): ?>
                            <div class="spot-card" data-spot="<?php echo htmlspecialchars($spot['spot_number']); ?>" onclick="viewSpotReservations('<?php echo htmlspecialchars($spot['spot_number']); ?>')">
                                <h3>
                                    <i class="fas fa-parking"></i>
                                    <?php echo htmlspecialchars($spot['spot_number']); ?>
                                </h3>
                                <span class="spot-status status-<?php echo strtolower($spot['current_status']); ?>">
                                    <?php echo ucfirst($spot['current_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-header">
                        <h2><i class="fas fa-calendar-alt"></i> Today's Reservations</h2>
                    </div>

                    <?php if (empty($today_reservations)): ?>
                        <div class="no-reservations">
                            No reservations for today
                        </div>
                    <?php else: ?>
                        <table class="reservations-table">
                            <thead>
                                <tr>
                                    <th>Spot</th>
                                    <th>User</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['spot_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($reservation['username']); ?>
                                            <?php if (!empty($reservation['fullname'])): ?>
                                                <br><small>(<?php echo htmlspecialchars($reservation['fullname']); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo date('h:i A', strtotime($reservation['start_time'])) . ' - ' . 
                                                 date('h:i A', strtotime($reservation['end_time']));
                                            ?>
                                        </td>
                                        <td>
                                            <span id="currentStatus" class="reservation-status status-<?php echo strtolower($reservation['current_status']); ?>">
                                                <?php echo ucfirst($reservation['current_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="view-btn" onclick="viewReservation(<?php echo htmlspecialchars(json_encode($reservation)); ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Report Section -->
            <div id="system-report" class="content-section">
                <div class="reports-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-line"></i> System Reports & Analytics</h2>
                    </div>

                    <!-- Financial Overview -->
                    <div class="revenue-trend">
                        <h3><i class="fas fa-money-bill-wave"></i> Financial Overview</h3>
                        <div class="revenue-grid">
                            <div class="revenue-item">
                                <div class="revenue-value">&#8369;<?php echo number_format($total_revenue, 2); ?></div>
                                <div class="revenue-label">Total Revenue (6 Months)</div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-value"><?php echo number_format($total_reservations); ?></div>
                                <div class="revenue-label">Total Reservations</div>
                            </div>
                            <div class="revenue-item">
                                <div class="revenue-value">&#8369;<?php echo number_format($avg_revenue, 2); ?></div>
                                <div class="revenue-label">Average Revenue per Reservation</div>
                            </div>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="reports-grid">
                        <div class="report-card">
                            <h3><i class="fas fa-users"></i> User Statistics</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <div class="metric-value"><?php echo number_format($user_stats['total_users']); ?></div>
                                    <div class="metric-label">Total Users</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-value"><?php echo number_format($user_stats['active_users_30d']); ?></div>
                                    <div class="metric-label">Active Users (30 Days)</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-value"><?php echo number_format($user_stats['active_users_7d']); ?></div>
                                    <div class="metric-label">Active Users (7 Days)</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-value">
                                        <?php 
                                        echo $user_stats['total_users'] > 0 
                                            ? round(($user_stats['active_users_30d'] / $user_stats['total_users']) * 100) 
                                            : 0; 
                                        ?>%
                                    </div>
                                    <div class="metric-label">User Retention Rate</div>
                                </div>
                            </div>
                        </div>

                        <!-- Peak Hours Analysis -->
                        <div class="report-card">
                            <h3><i class="fas fa-clock"></i> Peak Hours Analysis</h3>
                            <div class="chart-container">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Spot Performance -->
                    <div class="spot-performance">
                        <h3><i class="fas fa-chart-bar"></i> Spot Performance (Last 30 Days)</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Spot</th>
                                    <th>Total Reservations</th>
                                    <th>Current Occupancy</th>
                                    <th>Revenue</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_reservations = 0;
                                foreach ($spot_utilization_data as $spot) {
                                    $max_reservations = max($max_reservations, $spot['total_reservations']);
                                }
                                foreach ($spot_utilization_data as $spot): 
                                    $utilization_percentage = ($spot['total_reservations'] / $max_reservations) * 100;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($spot['spot_number']); ?></td>
                                    <td><?php echo number_format($spot['total_reservations']); ?></td>
                                    <td><?php echo number_format($spot['ongoing_reservations']); ?></td>
                                    <td>&#8369;<?php echo number_format($spot['total_revenue'], 2); ?></td>
                                    <td>
                                        <div class="performance-bar">
                                            <div class="performance-bar-fill" style="width: <?php echo $utilization_percentage; ?>%"></div>
                                        </div>
                                        <?php echo round($utilization_percentage); ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Download Report Section -->
            <div id="download-report" class="content-section">
                <div class="reports-section">
                    <div class="section-header">
                        <h2><i class="fas fa-download"></i> Download Reports</h2>
                    </div>

                    <div class="download-options">
                        <div class="download-card">
                            <h3><i class="fas fa-file-pdf"></i> System Report</h3>
                            <p>Generate a comprehensive PDF report containing all system analytics, financial data, performance metrics, and detailed insights about the parking system operations.</p>
                            <button class="download-btn" onclick="generateSystemReport()">
                                <i class="fas fa-download"></i> Download System Report
                            </button>
                        </div>
                    </div>

                    <div class="report-preview">
                        <h3><i class="fas fa-eye"></i> Report Preview</h3>
                        <div id="reportPreview" class="preview-content">
                            <p>Click the download button above to generate and preview the comprehensive system report.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="reservationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Reservation Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="reservation-details">
                <div class="detail-group">
                    <h3><i class="fas fa-parking"></i> Parking Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Spot Number:</span>
                        <span class="detail-value" id="modalSpotNumber"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" id="modalStatus"></span>
                    </div>
                </div>
                
                <div class="detail-group">
                    <h3><i class="fas fa-user"></i> User Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value" id="modalUsername"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value" id="modalFullname"></span>
                    </div>
                </div>

                <div class="detail-group">
                    <h3><i class="fas fa-clock"></i> Time Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value" id="modalDate"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Start Time:</span>
                        <span class="detail-value" id="modalStartTime"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">End Time:</span>
                        <span class="detail-value" id="modalEndTime"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value" id="modalDuration"></span>
                    </div>
                </div>

                <div class="detail-group">
                    <h3><i class="fas fa-money-bill-wave"></i> Payment Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Cost:</span>
                        <span class="detail-value" id="modalCost"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value" id="modalPaymentStatus"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($reservation['status'] === 'ongoing'): ?>
                    <button class="modal-btn danger" onclick="endReservation(currentReservationId)">
                        <i class="fas fa-stop-circle"></i> End Reservation
                    </button>
                <?php endif; ?>
                <button class="modal-btn primary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Add Spot Reservations Modal -->
    <div id="spotReservationsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-parking"></i> <span id="spotModalTitle">Spot Reservations</span></h2>
                <button class="close-modal" onclick="closeSpotModal()">&times;</button>
            </div>
            <div class="reservation-details">
                <div id="spotReservationsList" class="spot-reservations">
                    <!-- Reservations will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn primary" onclick="closeSpotModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    let currentReservationId = null;

    function viewReservation(reservation) {
        currentReservationId = reservation.id;
        
        // Format the date
        const reservationDate = new Date(reservation.reservation_date);
        const formattedDate = reservationDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Calculate duration
        const startTime = new Date(`2000-01-01T${reservation.start_time}`);
        const endTime = new Date(`2000-01-01T${reservation.end_time}`);
        let duration = (endTime - startTime) / (1000 * 60 * 60); // in hours
        if (duration < 0) duration += 24; // Handle overnight reservations
        const durationText = `${duration.toFixed(1)} hours`;

        // Update modal content
        document.getElementById('modalSpotNumber').textContent = reservation.spot_number;
        document.getElementById('modalStatus').innerHTML = `<span class="reservation-status status-${reservation.current_status.toLowerCase()}">${reservation.current_status.charAt(0).toUpperCase() + reservation.current_status.slice(1)}</span>`;
        document.getElementById('modalUsername').textContent = reservation.username;
        document.getElementById('modalFullname').textContent = reservation.fullname || 'N/A';
        document.getElementById('modalDate').textContent = formattedDate;
        document.getElementById('modalStartTime').textContent = new Date(`2000-01-01T${reservation.start_time}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        document.getElementById('modalEndTime').textContent = new Date(`2000-01-01T${reservation.end_time}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        document.getElementById('modalDuration').textContent = durationText;
        document.getElementById('modalCost').innerHTML = `₱${parseFloat(reservation.cost).toFixed(2)}`;
        document.getElementById('modalPaymentStatus').textContent = reservation.payment_status || 'Pending';

        // Show modal
        document.getElementById('reservationModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('reservationModal').style.display = 'none';
        currentReservationId = null;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('reservationModal');
        if (event.target === modal) {
            closeModal();
        }
    }

    function endReservation(reservationId) {
        if (confirm('Are you sure you want to end this reservation?')) {
            fetch('update_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reservation_id=${reservationId}&action=end`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the reservation.');
            });
        }
    }

    function viewSpotReservations(spotNumber) {
        console.log('Fetching reservations for spot:', spotNumber);
        
        // Show loading state
        document.getElementById('spotModalTitle').textContent = `Spot ${spotNumber} Reservations`;
        document.getElementById('spotReservationsList').innerHTML = '<div class="no-reservations">Loading reservations...</div>';
        document.getElementById('spotReservationsModal').style.display = 'block';

        // Fetch reservations for this spot
        fetch('get_spot_reservations.php?spot=' + encodeURIComponent(spotNumber), {
            method: 'GET',
            credentials: 'same-origin', // Include cookies
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            console.log('Response status:', response.status);
            const data = await response.json();
            
            if (!response.ok) {
                // Handle different error status codes
                switch (response.status) {
                    case 401:
                        throw new Error('Please log in as admin to view reservations');
                    case 400:
                        throw new Error(data.error || 'Invalid request');
                    case 500:
                        throw new Error(data.error || 'Server error occurred');
                    default:
                        throw new Error(data.error || 'Failed to load reservations');
                }
            }
            
            return data;
        })
        .then(data => {
            console.log('Received data:', data);
            
            const container = document.getElementById('spotReservationsList');
            
            if (!Array.isArray(data) || data.length === 0) {
                container.innerHTML = '<div class="no-reservations">No reservations found for this spot</div>';
                return;
            }

            container.innerHTML = data.map(reservation => {
                try {
                    const date = new Date(reservation.reservation_date);
                    const formattedDate = date.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    const startTime = new Date(`2000-01-01T${reservation.start_time}`).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    
                    const endTime = new Date(`2000-01-01T${reservation.end_time}`).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });

                    return `
                        <div class="reservation-card">
                            <div class="reservation-card-header">
                                <span class="reservation-card-time">${startTime} - ${endTime}</span>
                                <span class="reservation-status status-${reservation.status.toLowerCase()}">${reservation.status}</span>
                            </div>
                            <div class="reservation-card-user">
                                <i class="fas fa-user"></i>
                                ${reservation.fullname || reservation.username || 'Unknown User'}
                            </div>
                            <div class="reservation-card-details">
                                <div class="reservation-card-detail">
                                    <i class="fas fa-calendar"></i>
                                    ${formattedDate}
                                </div>
                                <div class="reservation-card-detail">
                                    <i class="fas fa-money-bill-wave"></i>
                                    ₱${reservation.cost}
                                </div>
                                <div class="reservation-card-detail">
                                    <i class="fas fa-clock"></i>
                                    ${calculateDuration(reservation.start_time, reservation.end_time)}
                                </div>
                            </div>
                        </div>
                    `;
                } catch (error) {
                    console.error('Error processing reservation:', error, reservation);
                    return '';
                }
            }).filter(Boolean).join('');
        })
        .catch(error => {
            console.error('Fetch error:', error);
            let errorMessage = error.message;
            
            // Try to parse the error details from the response
            if (error.response) {
                error.response.json().then(data => {
                    if (data.details) {
                        errorMessage = `${data.error}: ${data.details}`;
                    }
                }).catch(() => {
                    // If parsing fails, use the original error message
                });
            }
            
            document.getElementById('spotReservationsList').innerHTML = 
                `<div class="no-reservations">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="error-message">${errorMessage}</div>
                    <div class="error-help">Please check the database connection and try again.</div>
                </div>`;
        });
    }

    function calculateDuration(startTime, endTime) {
        const start = new Date(`2000-01-01T${startTime}`);
        const end = new Date(`2000-01-01T${endTime}`);
        let duration = (end - start) / (1000 * 60 * 60); // in hours
        if (duration < 0) duration += 24; // Handle overnight reservations
        return `${duration.toFixed(1)} hours`;
    }

    function closeSpotModal() {
        document.getElementById('spotReservationsModal').style.display = 'none';
    }

    // Initialize Peak Hours Chart
    const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
    const peakHoursData = <?php echo json_encode($peak_hours_data); ?>;
    
    new Chart(peakHoursCtx, {
        type: 'bar',
        data: {
            labels: peakHoursData.map(item => {
                const hour = parseInt(item.hour);
                return hour === 0 ? '12 AM' : 
                       hour === 12 ? '12 PM' : 
                       hour > 12 ? (hour - 12) + ' PM' : 
                       hour + ' AM';
            }),
            datasets: [{
                label: 'Number of Reservations',
                data: peakHoursData.map(item => item.reservation_count),
                backgroundColor: 'rgba(52, 152, 219, 0.5)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Reservation Distribution by Hour'
                }
            }
        }
    });

    // Download Report Functions
    function generateSystemReport() {
        showLoadingState('Generating Comprehensive System Report...');
        
        // Get PHP variables for JavaScript
        const totalRevenue = <?php echo $total_revenue; ?>;
        const totalReservations = <?php echo $total_reservations; ?>;
        const avgRevenue = <?php echo $avg_revenue; ?>;
        const userStats = <?php echo json_encode($user_stats); ?>;
        const spotUtilizationData = <?php echo json_encode($spot_utilization_data); ?>;
        const peakHoursData = <?php echo json_encode($peak_hours_data); ?>;
        const totalSpots = <?php echo $total_spots; ?>;
        const currentOngoing = <?php echo $current_ongoing; ?>;
        
        // Create a new window for the report
        const reportWindow = window.open('', '_blank');
        reportWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Comprehensive System Report - Smart Parking System</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .section { margin-bottom: 25px; }
                    .section h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
                    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
                    .metric-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
                    .metric-value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
                    .metric-label { color: #6c757d; font-size: 0.9em; }
                    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                    th { background: #f8f9fa; font-weight: bold; }
                    .footer { margin-top: 30px; text-align: center; color: #6c757d; font-size: 0.9em; }
                    .utilization-bar { background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; }
                    .utilization-fill { background: #3498db; height: 100%; transition: width 0.3s ease; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Smart Parking System - Comprehensive System Report</h1>
                    <p>Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                </div>
                
                <div class="section">
                    <h2>Financial Overview (Last 6 Months)</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value">₱${totalRevenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                            <div class="metric-label">Total Revenue</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${totalReservations.toLocaleString()}</div>
                            <div class="metric-label">Total Reservations</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">₱${avgRevenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                            <div class="metric-label">Average Revenue per Reservation</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${userStats.total_users > 0 ? Math.round((userStats.active_users_30d / userStats.total_users) * 100) : 0}%</div>
                            <div class="metric-label">User Retention Rate</div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>User Statistics & Activity</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value">${userStats.total_users.toLocaleString()}</div>
                            <div class="metric-label">Total Users</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${userStats.active_users_30d.toLocaleString()}</div>
                            <div class="metric-label">Active Users (30 Days)</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${userStats.active_users_7d.toLocaleString()}</div>
                            <div class="metric-label">Active Users (7 Days)</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${peakHoursData.length > 0 ? Math.max(...peakHoursData.map(item => item.reservation_count)) : 0}</div>
                            <div class="metric-label">Peak Hour Reservations</div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>Peak Hours Analysis (Last 30 Days)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Hour</th>
                                <th>Reservations</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${peakHoursData.map(item => {
                                const hour = parseInt(item.hour);
                                const hourLabel = hour === 0 ? '12 AM' : 
                                               hour === 12 ? '12 PM' : 
                                               hour > 12 ? (hour - 12) + ' PM' : 
                                               hour + ' AM';
                                const totalReservations = peakHoursData.reduce((sum, h) => sum + h.reservation_count, 0);
                                const percentage = totalReservations > 0 ? ((item.reservation_count / totalReservations) * 100).toFixed(1) : 0;
                                return `
                                    <tr>
                                        <td>${hourLabel}</td>
                                        <td>${item.reservation_count}</td>
                                        <td>${percentage}%</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <h2>Spot Performance & Utilization (Last 30 Days)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Spot</th>
                                <th>Total Reservations</th>
                                <th>Current Occupancy</th>
                                <th>Revenue Generated</th>
                                <th>Utilization Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${spotUtilizationData.map(spot => {
                                const maxReservations = Math.max(...spotUtilizationData.map(s => s.total_reservations));
                                const utilizationRate = maxReservations > 0 ? (spot.total_reservations / maxReservations) * 100 : 0;
                                return `
                                    <tr>
                                        <td>${spot.spot_number}</td>
                                        <td>${spot.total_reservations.toLocaleString()}</td>
                                        <td>${spot.ongoing_reservations.toLocaleString()}</td>
                                        <td>₱${parseFloat(spot.total_revenue).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                        <td>
                                            <div class="utilization-bar">
                                                <div class="utilization-fill" style="width: ${utilizationRate.toFixed(1)}%"></div>
                                            </div>
                                            ${utilizationRate.toFixed(1)}%
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <h2>System Performance Summary</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value">${totalSpots}</div>
                            <div class="metric-label">Total Parking Spots</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${currentOngoing}</div>
                            <div class="metric-label">Currently Occupied</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${totalSpots > 0 ? ((currentOngoing / totalSpots) * 100).toFixed(1) : 0}%</div>
                            <div class="metric-label">Current Occupancy Rate</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">${peakHoursData.length > 0 ? (() => {
                                const peakHour = peakHoursData.find(item => item.reservation_count === Math.max(...peakHoursData.map(h => h.reservation_count)));
                                const hour = parseInt(peakHour.hour);
                                return hour === 0 ? '12 AM' : 
                                       hour === 12 ? '12 PM' : 
                                       hour > 12 ? (hour - 12) + ' PM' : 
                                       hour + ' AM';
                            })() : 'N/A'}</div>
                            <div class="metric-label">Peak Hour</div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Peak Performance Analysis -->
                <div style="margin-top: 25px;">
                    <h3 style="color: #2c3e50; border-bottom: 1px solid #3498db; padding-bottom: 5px; margin-bottom: 15px;">Detailed Peak Performance Analysis</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #3498db;">
                            <div style="font-size: 1.2em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                ${peakHoursData.length > 0 ? Math.max(...peakHoursData.map(item => item.reservation_count)) : 0}
                            </div>
                            <div style="color: #6c757d; font-size: 0.9em;">Peak Hour Reservations</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #e74c3c;">
                            <div style="font-size: 1.2em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                ${peakHoursData.length > 0 ? Math.min(...peakHoursData.map(item => item.reservation_count)) : 0}
                            </div>
                            <div style="color: #6c757d; font-size: 0.9em;">Lowest Hour Reservations</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #f39c12;">
                            <div style="font-size: 1.2em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                ${peakHoursData.length > 0 ? (peakHoursData.reduce((sum, item) => sum + item.reservation_count, 0) / peakHoursData.length).toFixed(1) : 0}
                            </div>
                            <div style="color: #6c757d; font-size: 0.9em;">Average Reservations per Hour</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #27ae60;">
                            <div style="font-size: 1.2em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                ${peakHoursData.length > 0 ? peakHoursData.filter(item => item.reservation_count > (peakHoursData.reduce((sum, h) => sum + h.reservation_count, 0) / peakHoursData.length)).length : 0}
                            </div>
                            <div style="color: #6c757d; font-size: 0.9em;">Peak Hours (Above Average)</div>
                        </div>
                    </div>
                    
                    <!-- Peak Hours Breakdown -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">Peak Hours Performance Breakdown</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                            ${(() => {
                                if (peakHoursData.length === 0) return '<p style="color: #6c757d; text-align: center; grid-column: 1 / -1;">No peak hours data available</p>';
                                
                                const maxReservations = Math.max(...peakHoursData.map(item => item.reservation_count));
                                const avgReservations = peakHoursData.reduce((sum, item) => sum + item.reservation_count, 0) / peakHoursData.length;
                                
                                return peakHoursData
                                    .filter(item => item.reservation_count >= avgReservations)
                                    .sort((a, b) => b.reservation_count - a.reservation_count)
                                    .map(item => {
                                        const hour = parseInt(item.hour);
                                        const hourLabel = hour === 0 ? '12 AM' : 
                                                       hour === 12 ? '12 PM' : 
                                                       hour > 12 ? (hour - 12) + ' PM' : 
                                                       hour + ' AM';
                                        const performanceRatio = (item.reservation_count / maxReservations * 100).toFixed(1);
                                        
                                        return `
                                            <div style="background: white; padding: 12px; border-radius: 5px; border: 1px solid #e9ecef;">
                                                <div style="font-weight: bold; color: #2c3e50; margin-bottom: 5px;">${hourLabel}</div>
                                                <div style="color: #3498db; font-size: 1.1em; margin-bottom: 3px;">${item.reservation_count} reservations</div>
                                                <div style="color: #6c757d; font-size: 0.8em;">${performanceRatio}% of peak</div>
                                            </div>
                                        `;
                                    }).join('');
                            })()}
                        </div>
                    </div>
                    
                    <!-- Utilization Performance Metrics -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">Spot Utilization Performance Metrics</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 1.3em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                    ${spotUtilizationData.length > 0 ? Math.max(...spotUtilizationData.map(spot => spot.total_reservations)) : 0}
                                </div>
                                <div style="color: #6c757d; font-size: 0.9em;">Most Active Spot</div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 1.3em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                    ${spotUtilizationData.length > 0 ? (spotUtilizationData.reduce((sum, spot) => sum + spot.total_reservations, 0) / spotUtilizationData.length).toFixed(1) : 0}
                                </div>
                                <div style="color: #6c757d; font-size: 0.9em;">Average Reservations per Spot</div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 1.3em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                    ${spotUtilizationData.length > 0 ? spotUtilizationData.filter(spot => spot.total_reservations > (spotUtilizationData.reduce((sum, s) => sum + s.total_reservations, 0) / spotUtilizationData.length)).length : 0}
                                </div>
                                <div style="color: #6c757d; font-size: 0.9em;">High-Performance Spots</div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 1.3em; font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                    ${spotUtilizationData.length > 0 ? (spotUtilizationData.reduce((sum, spot) => sum + parseFloat(spot.total_revenue), 0) / spotUtilizationData.length).toFixed(2) : 0}
                                </div>
                                <div style="color: #6c757d; font-size: 0.9em;">Average Revenue per Spot</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This comprehensive system report was generated by the Smart Parking System Admin Dashboard</p>
                    <p>Report includes: Financial overview, user statistics, peak hours analysis, spot performance, and system utilization metrics.</p>
                </div>
            </body>
            </html>
        `);
        
        reportWindow.document.close();
        
        // Automatically trigger print dialog
        setTimeout(() => {
            reportWindow.print();
        }, 500);
        
        // Update preview
        document.getElementById('reportPreview').innerHTML = `
            <h4>Comprehensive System Report Generated Successfully!</h4>
            <p>The comprehensive system report has been opened and the print dialog should appear automatically.</p>
            <p>If the print dialog doesn't appear, you can manually press Ctrl+P (or Cmd+P on Mac) to print/save as PDF.</p>
            <p><strong>Report includes:</strong></p>
            <ul>
                <li>Financial overview for the last 6 months</li>
                <li>User statistics and activity metrics</li>
                <li>Peak hours analysis and trends</li>
                <li>Spot performance and utilization data</li>
                <li>System performance summary</li>
                <li>Revenue and occupancy analytics</li>
            </ul>
        `;
    }

    function generateMonthlyReport() {
        const selectedMonth = document.getElementById('reportMonth').value;
        if (!selectedMonth) {
            alert('Please select a month for the report.');
            return;
        }
        
        showLoadingState('Generating Monthly Report...');
        
        // Create a new window for the monthly report
        const reportWindow = window.open('', '_blank');
        const [year, month] = selectedMonth.split('-');
        const monthName = new Date(year, month - 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        
        reportWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Monthly Report - ${monthName} - Smart Parking System</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .section { margin-bottom: 25px; }
                    .section h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
                    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
                    .metric-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
                    .metric-value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
                    .metric-label { color: #6c757d; font-size: 0.9em; }
                    .footer { margin-top: 30px; text-align: center; color: #6c757d; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Smart Parking System - Monthly Report</h1>
                    <h2>${monthName}</h2>
                    <p>Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                </div>
                
                <div class="section">
                    <h2>Monthly Summary</h2>
                    <p>This report contains the monthly statistics and performance metrics for ${monthName}.</p>
                    <p>Use Ctrl+P (or Cmd+P on Mac) to print this report as a PDF.</p>
                </div>
                
                <div class="footer">
                    <p>This monthly report was generated by the Smart Parking System Admin Dashboard</p>
                </div>
            </body>
            </html>
        `);
        
        reportWindow.document.close();
        
        // Automatically trigger print dialog
        setTimeout(() => {
            reportWindow.print();
        }, 500);
        
        // Update preview
        document.getElementById('reportPreview').innerHTML = `
            <h4>Monthly Report Generated Successfully!</h4>
            <p>The monthly report for <strong>${monthName}</strong> has been opened in a new window.</p>
            <p>You can now print it as a PDF using Ctrl+P (or Cmd+P on Mac).</p>
            <p><strong>Report includes:</strong></p>
            <ul>
                <li>Monthly financial summary</li>
                <li>User activity for the selected month</li>
                <li>Spot utilization metrics</li>
                <li>Performance trends</li>
            </ul>
        `;
    }

    function showLoadingState(message) {
        document.getElementById('reportPreview').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #3498db; margin-bottom: 15px;"></i>
                <p style="color: #6c757d; margin: 0;">${message}</p>
            </div>
        `;
    }
    </script>

</body>
</html> 
