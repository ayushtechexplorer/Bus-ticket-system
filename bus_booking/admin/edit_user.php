<?php
session_start();
include('../includes/db.php');

// Restrict non-admin users
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Fetch user data if editing
if (isset($_GET['id'])) {
  $id = $_GET['id'];
  $user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
} else {
  // If no ID is provided, redirect to user management page
  header("Location: manage_users.php");
  exit;
}

// Update user if form is submitted
if (isset($_POST['update'])) {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $address = $_POST['address'];
  $username = $_POST['username'];
  $gender = $_POST['gender'];

  $conn->query("UPDATE users SET name='$name', email='$email', phone='$phone', address='$address', username='$username', gender='$gender' WHERE id=$id");

  header("Location: manage_user.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User</title>
<style>
  body { font-family: Arial; background: #f5f7fa; padding: 20px; }
  .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  input[type="text"], input[type="email"], input[type="phone"], select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
  button { width: 100%; padding: 10px; background: #009432; color: white; border: none; border-radius: 4px; font-size: 16px; }
  button:hover { background: #007b2a; }
</style>
</head>
<body>

<div class="container">
  <h2>Edit User Information</h2>
  <form method="POST">
    <label for="name">Full Name:</label>
    <input type="text" name="name" id="name" value="<?php echo $user['name']; ?>" required>

    <label for="email">Email:</label>
    <input type="email" name="email" id="email" value="<?php echo $user['email']; ?>" required>

    <label for="phone">Phone:</label>
    <input type="text" name="phone" id="phone" value="<?php echo $user['phone']; ?>" required>

    <label for="address">Address:</label>
    <input type="text" name="address" id="address" value="<?php echo $user['address']; ?>" required>

    <label for="username">Username:</label>
    <input type="text" name="username" id="username" value="<?php echo $user['username']; ?>" required>

    <label for="gender">Gender:</label>
    <select name="gender" id="gender" required>
      <option value="male" <?php echo ($user['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
      <option value="female" <?php echo ($user['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
    </select>

    <button type="submit" name="update">Update User</button>
  </form>
</div>

</body>
</html>
