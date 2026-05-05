<?php
session_start();
include('../includes/db.php');

// Restrict non-admin users
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Fetch dashboard stats
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='passenger'")->fetch_assoc()['total'];
$totalBuses = $conn->query("SELECT COUNT(*) AS total FROM buses")->fetch_assoc()['total'];
$totalBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings")->fetch_assoc()['total'];
$totalRevenue = $conn->query("SELECT IFNULL(SUM(b.ticket_price), 0) AS revenue 
                              FROM bookings bk 
                              JOIN buses b ON bk.bus_id = b.id 
                              WHERE bk.payment_status='Paid'")->fetch_assoc()['revenue'];

// Fetch passenger details for modal
$passengers_query = $conn->query("
    SELECT id, name, email, phone, created_at 
    FROM users 
    WHERE role='passenger' 
    ORDER BY created_at DESC
");
$passengers = [];
while ($row = $passengers_query->fetch_assoc()) {
    $passengers[] = $row;
}

// Fetch bus details for modal
$buses_query = $conn->query("
    SELECT id, bus_name, bus_number, route_from, route_to, total_seats, available_seats
    FROM buses 
    ORDER BY id DESC
");
$buses = [];
while ($row = $buses_query->fetch_assoc()) {
    $buses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | Bus Booking System</title>
<style>
  * { box-sizing: border-box; scroll-behavior: smooth; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 0;
    background: #f4f7fa;
    height: 100vh;
    overflow: hidden;
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

  header h1 { margin: 0; font-size: 22px; }
  nav a {
    color: #fff;
    text-decoration: none;
    margin-left: 20px;
    font-weight: bold;
  }
  nav a:hover { text-decoration: underline; }

  .container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
    height: calc(100vh - 100px);
    overflow-y: auto;
  }

  h2 {
    color: #009432;
    border-bottom: 2px solid #009432;
    padding-bottom: 8px;
    margin-bottom: 25px;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }

  .card {
    background: #fff;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    /* Removed hover effect */
  }

  .card.clickable {
    cursor: pointer;
  }

  .card h3 {
    font-size: 1.1rem;
    color: #555;
    margin-bottom: 8px;
  }

  .card p {
    font-size: 1.8rem;
    color: #009432;
    font-weight: bold;
  }

  .actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
  }

  .action-btn {
    display: block;
    background: #009432;
    color: #fff;
    text-align: center;
    padding: 15px 10px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
  }

  .action-btn:hover {
    background: #007b2a;
    transform: translateY(-3px);
  }

  /* Modal Styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
  }

  .modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 10px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  }

  .modal-header {
    background: #009432;
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .modal-header h2 {
    color: white;
    border: none;
    margin: 0;
    padding: 0;
  }

  .close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    background: none;
    border: none;
  }

  .close:hover {
    color: #ffeb3b;
  }

  .modal-body {
    padding: 20px;
  }

  .passenger-table,
  .bus-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }

  .passenger-table th,
  .passenger-table td,
  .bus-table th,
  .bus-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
  }

  .passenger-table th,
  .bus-table th {
    background-color: #f8f9fa;
    color: #009432;
    font-weight: bold;
  }

  .passenger-table tr:hover,
  .bus-table tr:hover {
    background-color: #f5f5f5;
  }

  .no-data {
    text-align: center;
    color: #666;
    padding: 40px;
    font-style: italic;
  }

  footer {
    background: #009432;
    color: #fff;
    text-align: center;
    padding: 15px;
    margin-top: 40px;
  }

  @media (max-width: 768px) {
    header {
      flex-direction: column;
      text-align: center;
      gap: 10px;
    }
    nav a { margin: 10px; }
    
    .modal-content {
      width: 95%;
      margin: 10% auto;
    }
    
    .passenger-table,
    .bus-table {
      font-size: 14px;
    }
    
    .passenger-table th,
    .passenger-table td,
    .bus-table th,
    .bus-table td {
      padding: 8px 10px;
    }
  }
</style>
</head>
<body>

<header>
  <h1>🚌 Admin Dashboard</h1>
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
  <h2>System Overview</h2>

  <div class="stats-grid">
    <div class="card clickable" onclick="showPassengerModal()">
      <h3>Total Passengers</h3>
      <p><?php echo $totalUsers; ?></p>
    </div>
    <div class="card clickable" onclick="showBusModal()">
      <h3>Total Buses</h3>
      <p><?php echo $totalBuses; ?></p>
    </div>
    <div class="card">
      <h3>Total Bookings</h3>
      <p><?php echo $totalBookings; ?></p>
    </div>
    <div class="card">
      <h3>Total Revenue (Rs)</h3>
      <p><?php echo number_format($totalRevenue); ?></p>
    </div>
  </div>

  <h2>Quick Actions</h2>
  <div class="actions">
    <a href="add_bus.php" class="action-btn">➕ Add New Bus</a>
    <a href="manage_buses.php" class="action-btn">🚌 Manage Buses</a>
    <a href="manage_user.php" class="action-btn">👥 Manage Users</a>
    <a href="manage_rides.php" class="action-btn">📅 Manage Rides</a>
    <a href="../index.php" class="action-btn">🌐 View Website</a>
  </div>
</div>

<!-- Passenger Details Modal -->
<div id="passengerModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>👥 Passenger Details (<?php echo $totalUsers; ?> Total)</h2>
      <button class="close">&times;</button>
    </div>
    <div class="modal-body">
      <?php if (count($passengers) > 0): ?>
        <table class="passenger-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Joined Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($passengers as $passenger): ?>
              <tr>
                <td><?php echo htmlspecialchars($passenger['id']); ?></td>
                <td><?php echo htmlspecialchars($passenger['name']); ?></td>
                <td><?php echo htmlspecialchars($passenger['email']); ?></td>
                <td><?php echo htmlspecialchars($passenger['phone']); ?></td>
                <td><?php echo date('M j, Y', strtotime($passenger['created_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-data">
          <p>No passengers found in the system.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Bus Details Modal -->
<div id="busModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🚌 Bus Details (<?php echo $totalBuses; ?> Total)</h2>
      <button class="close">&times;</button>
    </div>
    <div class="modal-body">
      <?php if (count($buses) > 0): ?>
        <table class="bus-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Bus Name</th>
              <th>Bus Number</th>
              <th>Route</th>
              <th>Seats</th>
              <th>Available</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($buses as $bus): ?>
              <tr>
                <td><?php echo htmlspecialchars($bus['id']); ?></td>
                <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                <td><?php echo htmlspecialchars($bus['bus_number']); ?></td>
                <td><?php echo htmlspecialchars($bus['route_from']); ?> → <?php echo htmlspecialchars($bus['route_to']); ?></td>
                <td><?php echo htmlspecialchars($bus['total_seats']); ?></td>
                <td><?php echo htmlspecialchars($bus['available_seats']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-data">
          <p>No buses found in the system.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer>
  <p>© <?php echo date('Y'); ?> Bus Booking System | Admin Panel</p>
</footer>

<script>
  // Modal functionality
  const passengerModal = document.getElementById('passengerModal');
  const busModal = document.getElementById('busModal');
  const closeBtns = document.querySelectorAll('.close');
  
  function showPassengerModal() {
    passengerModal.style.display = 'block';
  }
  
  function showBusModal() {
    busModal.style.display = 'block';
  }
  
  function closeModal() {
    passengerModal.style.display = 'none';
    busModal.style.display = 'none';
  }
  
  // Close modal when clicking X
  closeBtns.forEach(btn => {
    btn.addEventListener('click', closeModal);
  });
  
  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    if (event.target === passengerModal || event.target === busModal) {
      closeModal();
    }
  });
  
  // Close modal with Escape key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
      closeModal();
    }
  });
</script>

</body>
</html>