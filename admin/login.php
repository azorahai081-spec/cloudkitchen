<?php
// admin/login.php
// Version 1.1 - Added CSRF Protection

// 1. CONFIGURATION
// We must include the config file to start the session and connect to the DB.
require_once('../config.php');

// (NEW) Generate a token for the login form
generate_csrf_token(); 

// 2. INITIALIZATION
$error_message = '';

// 3. SECURITY: If user is ALREADY logged in, redirect them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: live_orders.php');
    exit;
}

// 4. HANDLE FORM SUBMISSION (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) Validate CSRF Token
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            // --- SECURITY: Use PREPARED STATEMENTS ---
            $sql = "SELECT id, username, password, role FROM admin_users WHERE username = ? LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // User found. Now we verify the password.
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // SUCCESS! Passwords match.
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role']; // This is 'admin' or 'manager'
                    
                    // (NEW) Regenerate CSRF token after login
                    generate_csrf_token(); 
                    
                    // Redirect to the main dashboard page
                    header('Location: live_orders.php');
                    exit;
                } else {
                    // Invalid password
                    $error_message = 'Invalid username or password.';
                }
            } else {
                // User not found
                $error_message = 'Invalid username or password.';
            }
            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KitchCo</title>
    <!-- 1. Load Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- 2. Load Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- 3. Configure Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-slate-100 font-sans antialiased">

    <div class="flex min-h-screen items-center justify-center">
        
        <div class="w-full max-w-md p-8 bg-white shadow-xl rounded-2xl">
            <!-- Logo -->
            <div class="text-center mb-6">
                <a href="#" class="text-3xl font-bold text-orange-600">
                    KitchCo Admin
                </a>
                <p class="text-gray-500 mt-2">Please sign in to your account</p>
            </div>

            <!-- Error Message Display -->
            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg">
                    <?php echo e($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php" method="POST" class="space-y-6">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">
                        Username
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>

                <!-- Submit Button -->
                <div>
                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors"
                    >
                        Sign In
                    </button>
                </div>
            </form>
            
        </div>
    </div>

</body>
</html>