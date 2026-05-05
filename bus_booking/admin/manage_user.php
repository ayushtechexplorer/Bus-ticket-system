<?php
session_start();
include('../includes/db.php');
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Delete user if requested
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $conn->query("DELETE FROM users WHERE id=$id");
  header("Location: manage_users.php");
  exit;
}

$users = $conn->query("SELECT * FROM users WHERE role='passenger'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users</title>
<style>
  body { font-family: Arial; background: #f5f7fa; }
  table { width: 90%; margin: 40px auto; border-collapse: collapse; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
  th { background: #009432; color: white; }
  a.btn { background: red; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
  a.btn:hover { background: darkred; }
  a.edit-btn { background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
  a.edit-btn:hover { background: #0056b3; }
  header { background: #009432; padding: 15px; color: white; display: flex; justify-content: space-between; }
  a.back { color: white; text-decoration: none; font-weight: bold; }
</style>
</head>
<body>

<header>
  <h2>Manage Users</h2>
  <a href="dashboard.php" class="back">← Back to Dashboard</a>
</header>

<table>
<tr><th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Username</th><th>Gender</th><th>Action</th></tr>
<?php while($u = $users->fetch_assoc()) { ?>
<tr>
  <td><?php echo $u['id']; ?></td>
  <td><?php echo $u['name']; ?></td>
  <td><?php echo $u['email']; ?></td>
  <td><?php echo $u['phone']; ?></td>
  <td><?php echo $u['address']; ?></td>
  <td><?php echo $u['username']; ?></td>
  <td><?php echo $u['gender']; ?></td>
  <td>
    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="edit-btn">Edit</a>
    <a href="?delete=<?php echo $u['id']; ?>" class="btn" onclick="return confirm('Delete this user?')">Delete</a>
  </td>
</tr>
<?php } ?>
</table>

</body>
</html>
