<?php
include('includes/db.php');
session_start();

$fullname = $email = $phone = $address = $username = $gender = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $error = '';

    // --- SERVER-SIDE VALIDATION ---
    if (empty($fullname) || empty($email) || empty($phone) || empty($address) || empty($username) || empty($gender) || empty($password)) {
        $error = "All fields are required!";
    } elseif (!preg_match("/^(97|98)[0-9]{8}$/", $phone)) {
        $error = "Phone number must start with 97 or 98 and be 10 digits long.";
        $phone = '';
    } elseif (!preg_match("/^[a-zA-Z0-9]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $error = "Please enter a valid email address.";
        $email = '';
    } elseif (!preg_match("/^[a-zA-Z0-9]{7,}@/", $email)) {
        $error = "Email must have at least 7 letters/numbers before @ symbol.";
        $email = '';
    } elseif (!preg_match("/^[a-zA-Z0-9_]{4,20}$/", $username)) {
        $error = "Username must be 4-20 characters and can only contain letters, numbers, and underscore.";
        $username = '';
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
        $password = $confirm = '';
    } elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/", $password)) {
        $error = "Password must be at least 12 characters, include letters, numbers, and one special character.";
        $password = $confirm = '';
    } else {
        // Check if username or email already exists
        $check = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email or username already exists!";
            $email = $username = '';
        } else {
            // Added username back to the INSERT query
            $query = $conn->prepare("INSERT INTO users (name, email, phone, address, username, gender, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'passenger')");
            $query->bind_param("sssssss", $fullname, $email, $phone, $address, $username, $gender, $password);
            
            if ($query->execute()) {
                $success = "Registration successful! <a href='login.php'>Login here</a>.";
                // Clear all fields on success
                $fullname = $email = $phone = $address = $username = $gender = '';
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Lane - Premium Bus Booking</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #009432;
    --primary-dark: #007a2a;
    --secondary: #1a237e;
    --dark: #121212;
    --light: #f8f9fa;
    --gray: #6c757d;
    --card-bg: rgba(255, 255, 255, 0.95);
    --shadow: 0 10px 30px rgba(0,0,0,0.1);
}

* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:100%; height:100%; font-family:'Poppins', sans-serif; background-color: var(--light); color: var(--dark); overflow:hidden; }

.main-content {
    width:100%;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?ixlib=rb-4.0.3&auto=format&fit=crop&w=2069&q=80') no-repeat center center;
    background-size: cover;
    position: relative;
}

.main-content::before {
    content:'';
    position:absolute;
    top:0; left:0;
    width:100%; height:100%;
    background: linear-gradient(135deg, rgba(0,148,50,0.3) 0%, rgba(26,35,126,0.3) 100%);
    z-index:0;
}

.register-container {
    max-width: 480px;
    width: 100%;
    background: var(--card-bg);
    backdrop-filter: blur(10px);
    border-radius:16px;
    padding:35px 30px;
    box-shadow: var(--shadow);
    position: relative;
    z-index:1;
    border:1px solid rgba(255,255,255,0.2);
    max-height:95vh;
    overflow-y:auto;
}

.register-container::-webkit-scrollbar {
    width: 6px;
}

.register-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.register-container::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.register-header { text-align:center; margin-bottom:25px; }
.register-header i { font-size:2.5rem; color:var(--primary); margin-bottom:12px; display:block; }
.register-header h2 { font-size:1.8rem; color:var(--dark); font-weight:700; margin-bottom:8px; }
.register-header p { color: var(--gray); font-size:0.95rem; }

.form-group { margin-bottom:15px; position:relative; }
.form-group label { display:block; margin-bottom:6px; font-weight:500; color:var(--dark); font-size:0.9rem; }
.form-group label .required {
    color: #dc3545;
    margin-left: 3px;
}
.form-control { width:100%; padding:12px 12px 12px 40px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; background-color: rgba(255,255,255,0.9); transition: all 0.3s; }
.form-control:focus { outline:none; border-color: var(--primary); box-shadow:0 0 0 3px rgba(0,148,50,0.1); }
.input-icon { position:absolute; left:12px; top:35px; color:var(--gray); font-size:1rem; }

select.form-control { padding-left:12px; }

.helper-text {
    font-size: 0.7rem;
    color: #6c757d;
    margin-top: 4px;
    display: block;
}

.show-password { display:flex; align-items:center; margin-top:8px; font-size:0.85rem; }
.show-password input { width:auto; margin-right:6px; }

.btn-register {
    width:100%; padding:13px;
    background: var(--primary); color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px; margin-top:10px;
    transition: background 0.3s;
}

.btn-register:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-back {
    width:100%; padding:13px;
    background: #6c757d; color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px; margin-top:10px;
    transition: background 0.3s;
    text-decoration: none;
    margin-top: 8px;
}

.btn-back:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

/* Message Styling */
.message-box {
    border-radius:8px; padding:12px; margin-bottom:15px; text-align:center; font-weight:500; font-size:0.9rem;
}

.error-message {
    background: #f8d7da; 
    border:1px solid #f5c6cb;
    color: #721c24;
}

.success-message {
    background: #d4edda; 
    border:1px solid #c3e6cb;
    color: #155724;
}

.register-footer { text-align:center; margin-top:20px; padding-top:18px; border-top:1px solid rgba(0,0,0,0.1); }
.register-footer p { color: var(--gray); font-size:0.9rem; }
.register-footer a { color: var(--primary); text-decoration:none; font-weight:600; }
.register-footer a:hover { color: var(--primary-dark); text-decoration:underline; }

.brand-header { position:absolute; top:20px; left:20px; display:flex; align-items:center; gap:8px; color:white; font-weight:600; font-size:1.2rem; z-index:2; }
.brand-header i { font-size:1.5rem; }

/* Validation styling */
.form-control.error { border-color: #dc3545; background-color: rgba(220,53,69,0.05); }
.form-control.success { border-color: #28a745; background-color: rgba(40,167,69,0.05); }

@media (max-width:576px) {
    .register-container { padding:25px 20px; max-width:350px; }
    .register-header h2 { font-size:1.6rem; }
    .brand-header { font-size:1.1rem; top:15px; left:15px; }
}

@media (max-height:700px) {
    .register-container { transform: scale(0.95); }
}
</style>
</head>
<body>

<div class="brand-header">
    <i class="fas fa-bus"></i>
    <span>Lane</span>
</div>

<div class="main-content">
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-user-plus"></i>
            <h2>Create Account</h2>
            <p>Join thousands of travelers</p>
        </div>

        <?php if(isset($error) && !empty($error)): ?>
            <div class="message-box error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($success) && !empty($success)): ?>
            <div class="message-box success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="fullname">Full Name <span class="required">*</span></label>
                <i class="fas fa-user input-icon"></i>
                <input type="text" id="fullname" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Enter your full name" required>
            </div>

            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <i class="fas fa-at input-icon"></i>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" placeholder="Choose a username" required>
                <small class="helper-text">4-20 characters, letters, numbers, and underscore only</small>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                <small class="helper-text">Must have at least 7 characters before @ symbol</small>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number <span class="required">*</span></label>
                <i class="fas fa-phone input-icon"></i>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Enter your phone number" required>
                <small class="helper-text">Must start with 97 or 98 and be 10 digits (e.g., 98XXXXXXXX)</small>
            </div>

            <div class="form-group">
                <label for="address">Address <span class="required">*</span></label>
                <i class="fas fa-home input-icon"></i>
                <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($address); ?>" placeholder="Enter your address" required>
            </div>

            <div class="form-group">
                <label for="gender">Gender <span class="required">*</span></label>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="">Select your gender</option>
                    <option value="Male" <?php if($gender=='Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if($gender=='Female') echo 'selected'; ?>>Female</option>
                    <option value="Other" <?php if($gender=='Other') echo 'selected'; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                <small class="helper-text">Minimum 12 characters with letters, numbers, and special character (@$!%*?&)</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter your password" required>
                <div class="show-password">
                    <input type="checkbox" id="togglePassword" onclick="togglePass()">
                    <label for="togglePassword">Show Password</label>
                </div>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
            
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </form>

        <div class="register-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const pass = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    if (pass.type === 'password') {
        pass.type = 'text';
        confirm.type = 'text';
    } else {
        pass.type = 'password';
        confirm.type = 'password';
    }
}

function validateForm() {
    const fullname = document.getElementById('fullname').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    const gender = document.getElementById('gender').value;
    const pass = document.getElementById('password').value.trim();
    const confirm = document.getElementById('confirm_password').value.trim();
    
    let isValid = true;
    let errorMessage = '';

    // Check empty fields
    if (!fullname || !username || !email || !phone || !address || !gender || !pass || !confirm) {
        errorMessage = "All fields are required!";
        isValid = false;
    }
    // Username validation
    else if (!/^[a-zA-Z0-9_]{4,20}$/.test(username)) {
        errorMessage = "Username must be 4-20 characters and can only contain letters, numbers, and underscore.";
        document.getElementById('username').classList.add('error');
        isValid = false;
    } else {
        document.getElementById('username').classList.remove('error');
        document.getElementById('username').classList.add('success');
    }
    // Phone validation
    else if (!/^(97|98)[0-9]{8}$/.test(phone)) {
        errorMessage = "Phone number must start with 97 or 98 and be 10 digits long.";
        document.getElementById('phone').classList.add('error');
        isValid = false;
    } else {
        document.getElementById('phone').classList.remove('error');
        document.getElementById('phone').classList.add('success');
    }

    // Email validation
    const emailRegex = /^[a-zA-Z0-9]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(email)) {
        errorMessage = "Please enter a valid email address.";
        document.getElementById('email').classList.add('error');
        isValid = false;
    } else if (!/^[a-zA-Z0-9]{7,}@/.test(email)) {
        errorMessage = "Email must have at least 7 letters/numbers before @ symbol.";
        document.getElementById('email').classList.add('error');
        isValid = false;
    } else {
        document.getElementById('email').classList.remove('error');
        document.getElementById('email').classList.add('success');
    }

    // Password validation
    const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/;
    if (!passwordRegex.test(pass)) {
        errorMessage = "Password must be at least 12 characters with letters, numbers, and a special character.";
        document.getElementById('password').classList.add('error');
        isValid = false;
    } else {
        document.getElementById('password').classList.remove('error');
        document.getElementById('password').classList.add('success');
    }

    // Password match validation
    if (pass !== confirm) {
        errorMessage = "Passwords do not match!";
        document.getElementById('confirm_password').classList.add('error');
        isValid = false;
    } else if (pass && confirm) {
        document.getElementById('confirm_password').classList.remove('error');
        document.getElementById('confirm_password').classList.add('success');
    }

    if (!isValid) {
        alert(errorMessage);
    }
    
    return isValid;
}

// Real-time validation on input
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['username', 'phone', 'email', 'password', 'confirm_password'];
    
    inputs.forEach(function(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('blur', function() {
                validateField(inputId);
            });
            input.addEventListener('input', function() {
                validateField(inputId);
            });
        }
    });
});

function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const value = field.value.trim();
    
    switch(fieldId) {
        case 'username':
            if (value && !/^[a-zA-Z0-9_]{4,20}$/.test(value)) {
                field.classList.add('error');
                field.classList.remove('success');
            } else if (value) {
                field.classList.remove('error');
                field.classList.add('success');
            } else {
                field.classList.remove('error', 'success');
            }
            break;
            
        case 'phone':
            if (value && !/^(97|98)[0-9]{8}$/.test(value)) {
                field.classList.add('error');
                field.classList.remove('success');
            } else if (value) {
                field.classList.remove('error');
                field.classList.add('success');
            } else {
                field.classList.remove('error', 'success');
            }
            break;
            
        case 'email':
            const emailRegex = /^[a-zA-Z0-9]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            const minLengthRegex = /^[a-zA-Z0-9]{7,}@/;
            
            if (value && (!emailRegex.test(value) || !minLengthRegex.test(value))) {
                field.classList.add('error');
                field.classList.remove('success');
            } else if (value) {
                field.classList.remove('error');
                field.classList.add('success');
            } else {
                field.classList.remove('error', 'success');
            }
            break;
            
        case 'password':
            const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/;
            if (value && !passwordRegex.test(value)) {
                field.classList.add('error');
                field.classList.remove('success');
            } else if (value) {
                field.classList.remove('error');
                field.classList.add('success');
                // Also revalidate confirm password if it has a value
                const confirmField = document.getElementById('confirm_password');
                if (confirmField && confirmField.value) {
                    validateField('confirm_password');
                }
            } else {
                field.classList.remove('error', 'success');
            }
            break;
            
        case 'confirm_password':
            const password = document.getElementById('password').value;
            if (value && value !== password) {
                field.classList.add('error');
                field.classList.remove('success');
            } else if (value && value === password) {
                field.classList.remove('error');
                field.classList.add('success');
            } else {
                field.classList.remove('error', 'success');
            }
            break;
    }
}
</script>
</body>
</html>