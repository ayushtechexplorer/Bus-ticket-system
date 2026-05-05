<?php
session_start();
include('../includes/db.php');

// Restrict access to admin - CORRECTED SESSION CHECK
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Auto-update available seats based on PAID bookings
$updateQuery = $conn->query("
    UPDATE buses b
    SET b.available_seats = b.total_seats - COALESCE(
        (
            SELECT SUM(bk.total_seats) 
            FROM bookings bk 
            WHERE bk.bus_id = b.id 
            AND bk.payment_status = 'Paid' 
            AND bk.ride_status != 'Cancelled'
        ), 0
    )
");

// Delete bus
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if there are any bookings for this bus before deleting
    $checkQuery = $conn->query("SELECT COUNT(*) as booking_count FROM bookings WHERE bus_id = $id");
    $checkResult = $checkQuery->fetch_assoc();
    
    if ($checkResult['booking_count'] > 0) {
        $message = "⚠️ Cannot delete bus. There are existing bookings for this bus.";
    } else {
        $deleteQuery = $conn->query("DELETE FROM buses WHERE id = $id");
        if ($deleteQuery) {
            $message = "🗑️ Bus deleted successfully!";
            
            // Reset auto-increment if table is empty
            $checkEmpty = $conn->query("SELECT COUNT(*) as total FROM buses");
            $emptyResult = $checkEmpty->fetch_assoc();
            if ($emptyResult['total'] == 0) {
                $conn->query("ALTER TABLE buses AUTO_INCREMENT = 1");
            }
        } else {
            $message = "❌ Error deleting bus.";
        }
    }
}

// First, let's check and fix any ID gaps
$fixIdsQuery = $conn->query("
    SET @row_number = 0;
    UPDATE buses 
    SET id = (@row_number := @row_number + 1) 
    ORDER BY id;
");

// Fetch all buses with proper sequential numbering
$result = $conn->query("SELECT *, ROW_NUMBER() OVER (ORDER BY id) as display_id FROM buses ORDER BY departure_time ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Buses | Admin Panel</title>
<style>
    * { box-sizing: border-box; scroll-behavior: smooth; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        margin: 0;
        background: #f4f7fa;
    }

    header {
        background: #009432;
        color: white;
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 999;
    }

    header h1 {
        margin: 0;
        font-size: 22px;
    }

    nav a {
        color: #fff;
        text-decoration: none;
        margin-left: 20px;
        font-weight: bold;
    }

    nav a:hover {
        text-decoration: underline;
    }

    .container {
        max-width: 1300px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    h2 {
        color: #009432;
        border-bottom: 2px solid #009432;
        padding-bottom: 8px;
        margin-bottom: 25px;
    }

    .message {
        background: #e8f5e9;
        color: #007a2a;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-weight: bold;
        text-align: center;
    }
    
    .error-message {
        background: #ffe6e6;
        color: #cc0000;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-weight: bold;
        text-align: center;
    }

    .add-btn {
        display: inline-block;
        background: #009432;
        color: white;
        padding: 10px 18px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
        margin-bottom: 20px;
    }

    .add-btn:hover {
        background: #007b2a;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    th, td {
        border: 1px solid #ddd;
        padding: 12px 10px;
        text-align: center;
        vertical-align: middle;
    }

    th {
        background: #009432;
        color: #fff;
    }

    tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    tr:hover {
        background: #f0f0f0;
    }

    .actions {
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .actions a {
        text-decoration: none;
        padding: 8px 12px;
        border-radius: 6px;
        font-weight: bold;
        display: inline-block;
        min-width: 80px;
        text-align: center;
        transition: 0.3s;
    }

    .edit {
        background: #007bff;
        color: white;
    }

    .edit:hover { background: #0056b3; }

    .delete {
        background: #e74c3c;
        color: white;
    }

    .delete:hover { background: #c0392b; }
    
    .view-bookings {
        background: #17a2b8;
        color: white;
    }
    
    .view-bookings:hover { background: #138496; }

    .driver-phone a {
        color: #009432;
        text-decoration: none;
    }

    .driver-phone a:hover {
        text-decoration: underline;
    }
    
    .seats-info {
        font-weight: bold;
    }
    
    .seats-low {
        color: #e74c3c;
        font-weight: bold;
    }
    
    .seats-ok {
        color: #009432;
        font-weight: bold;
    }

    .footer {
        background: #009432;
        color: white;
        text-align: center;
        padding: 15px;
        margin-top: 40px;
    }

    @media (max-width: 992px) {
        table { font-size: 0.9rem; }
        .actions a { padding: 6px 10px; min-width: 70px; }
    }

    @media (max-width: 600px) {
        .container { padding: 20px 10px; }
        table { font-size: 0.85rem; }
        .actions { flex-direction: column; }
        .actions a { min-width: auto; width: 100%; margin-bottom: 5px; }
    }
</style>
</head>
<body>

<header>
    <h1>🚌 Manage Buses</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_bus.php">Add Bus</a>
        <a href="manage_buses.php">Manage Buses</a>
        <a href="manage_user.php">Manage Users</a>
        <a href="manage_rides.php">Manage Rides</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <h2>All Bus Records</h2>

    <?php if ($message): ?>
        <div class="<?php echo strpos($message, 'Cannot delete') !== false || strpos($message, 'Error') !== false ? 'error-message' : 'message'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div>
        <a href="add_bus.php" class="add-btn">+ Add New Bus</a>
        <a href="manage_buses.php?fix_ids=1" class="add-btn" style="background: #ff9800; margin-left: 10px;" onclick="return confirm('This will renumber all bus IDs sequentially. Continue?');">🔄 Fix IDs</a>
    </div>

    <?php 
    // Handle ID fixing request
    if (isset($_GET['fix_ids'])) {
        // First backup current IDs
        $backupQuery = $conn->query("ALTER TABLE buses ADD COLUMN old_id INT DEFAULT NULL");
        
        // Store old IDs
        $conn->query("UPDATE buses SET old_id = id");
        
        // Reset auto-increment and renumber
        $conn->query("ALTER TABLE buses DROP COLUMN id");
        $conn->query("ALTER TABLE buses ADD COLUMN id INT PRIMARY KEY AUTO_INCREMENT FIRST");
        
        // Set new sequential IDs
        $conn->query("SET @count = 0");
        $conn->query("UPDATE buses SET id = @count:= @count + 1 ORDER BY old_id");
        
        // Remove the old_id column
        $conn->query("ALTER TABLE buses DROP COLUMN old_id");
        
        echo "<div class='message'>✅ Bus IDs have been renumbered sequentially!</div>";
        
        // Refresh the page
        echo "<script>setTimeout(function(){ window.location.href = 'manage_buses.php'; }, 1500);</script>";
    }
    
    // Alternative method: Just display sequential numbers without changing database
    $result = $conn->query("SELECT * FROM buses ORDER BY id ASC");
    ?>
    
    <?php if ($result->num_rows > 0): ?>
    <table>
        <tr>
            <th>#</th>
            <th>Bus Name</th>
            <th>Driver Name</th>
            <th>Driver Phone</th>
            <th>Route</th>
            <th>Departure</th>
            <th>Total Seats</th>
            <th>Available</th>
            <th>Price (Rs)</th>
            <th style="width:190px;">Actions</th>
        </tr>

        <?php 
        $counter = 1;
        while ($bus = $result->fetch_assoc()): 
            // Get actual booked seats count for this bus
            $busId = $bus['id'];
            $bookedQuery = $conn->query("
                SELECT SUM(total_seats) as total_booked 
                FROM bookings 
                WHERE bus_id = $busId 
                AND payment_status = 'Paid' 
                AND ride_status != 'Cancelled'
            ");
            $bookedResult = $bookedQuery->fetch_assoc();
            $totalBooked = $bookedResult['total_booked'] ? $bookedResult['total_booked'] : 0;
            $availableSeats = $bus['total_seats'] - $totalBooked;
            
            // Update the bus record if needed
            if ($bus['available_seats'] != $availableSeats) {
                $updateSeatsQuery = $conn->query("UPDATE buses SET available_seats = $availableSeats WHERE id = $busId");
                $bus['available_seats'] = $availableSeats;
            }
        ?>
        <tr>
            <td><?php echo $counter; ?></td>
            <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
            <td><?php echo htmlspecialchars($bus['driver_name']); ?></td>
            <td class="driver-phone">
                📞 <a href="tel:<?php echo htmlspecialchars($bus['driver_phone']); ?>">
                    <?php echo htmlspecialchars($bus['driver_phone']); ?>
                </a>
            </td>
            <td><?php echo htmlspecialchars($bus['route_from']) . " → " . htmlspecialchars($bus['route_to']); ?></td>
            <td><?php echo date("d M Y, h:i A", strtotime($bus['departure_time'])); ?></td>
            <td class="seats-info"><?php echo $bus['total_seats']; ?></td>
            <td class="<?php echo ($bus['available_seats'] <= 5) ? 'seats-low' : 'seats-ok'; ?>">
                <?php echo $bus['available_seats']; ?>
                <?php if ($totalBooked > 0): ?>
                    <br><small style="color:#666;">(Booked: <?php echo $totalBooked; ?>)</small>
                <?php endif; ?>
            </td>
            <td>Rs. <?php echo number_format($bus['ticket_price']); ?></td>
            <td class="actions">
                <a href="edit_bus.php?id=<?php echo $bus['id']; ?>" class="edit">✏️ Edit</a>
                <a href="manage_rides.php?bus_id=<?php echo $bus['id']; ?>" class="view-bookings">📋 Bookings</a>
                <a href="manage_buses.php?delete=<?php echo $bus['id']; ?>" class="delete" onclick="return confirm('Are you sure you want to delete this bus?\n\nThis will delete ALL bus data including schedules and seat information.\n\n⚠️ This action cannot be undone!');">🗑️ Delete</a>
            </td>
        </tr>
        <?php 
        $counter++;
        endwhile; ?>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#555; padding: 20px;">No buses found. Add one to get started.</p>
    <?php endif; ?>
</div>

<footer class="footer">
    <p>© <?php echo date('Y'); ?> Bus Booking System | Admin Panel</p>
    <p>Total Buses: <?php echo $result->num_rows; ?></p>
</footer>

</body>
</html>