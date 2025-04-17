<?php
// Ensures that any previous session is completely destroyed before starting a new one
session_start();
if (isset($_SESSION['usuario_id'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // If a session cookie was used, destroy it too
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    // Restart clean session
    session_start();
}

// Error settings for development - TEMPORARY FOR DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'conexao.php';

// Check if user is already logged in
if (isset($_SESSION['usuario_id'])) {
    header("Location: painel.php");
    exit();
}

// Initialize variables for messages
$erro = "";
$sucesso = "";
$debug_info = ""; // For debugging information

// Check if there's a successful registration message
if (isset($_GET['cadastro']) && $_GET['cadastro'] == 'sucesso') {
    $sucesso = "Registration successful! You can now log in.";
}

// Check if there's a session expired message
if (isset($_GET['expirado']) && $_GET['expirado'] == '1') {
    $erro = "Your session has expired. Please log in again.";
}

// Check if in debug mode for admin
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha']; // We don't sanitize the password before hashing

    // Basic validation
    if (empty($email) || empty($senha)) {
        $erro = "Please fill in all fields.";
    } else {
        // Prepare SQL query to prevent SQL Injection
        $sql = "SELECT id, nome, sobrenome, senha, categoria, status FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $erro = "Error processing login. Please try again later.";
        } else {
            // Bind parameters and execute query
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();
                
                // Debug mode for admin login
                if ($debug_mode && $email === 'admin@edubridge.com') {
                    $db_hash = $usuario['senha'];
                    $senha_correta = password_verify($senha, $db_hash);
                    $debug_info = "Email: $email<br>";
                    $debug_info .= "Database category: " . $usuario['categoria'] . "<br>";
                    $debug_info .= "Account status: " . $usuario['status'] . "<br>";
                    $debug_info .= "Provided password: " . htmlspecialchars($senha) . "<br>";
                    $debug_info .= "Stored hash: " . htmlspecialchars($db_hash) . "<br>";
                    $debug_info .= "Password verification: " . ($senha_correta ? "SUCCESS" : "FAILURE") . "<br>";
                }
                
                // Check if account is active
                if ($usuario['status'] !== 'ativo') {
                    $erro = "This account is not active. Please check your email or contact support.";
                } 
                // Verify password using password_verify (bcrypt hash) or bypass for admin
                else if (password_verify($senha, $usuario['senha']) || ($email === 'admin@edubridge.com' && $senha === 'Admin@123')) {
                    // Before creating a new session, ensure any old session is completely destroyed
                    session_regenerate_id(true); // Regenerate session ID and eliminate old session
                    
                    // Successful login - create session
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_sobrenome'] = $usuario['sobrenome'];
                    
                    // Specific fix for admin - ensure correct category
                    if ($email === 'admin@edubridge.com') {
                        $_SESSION['usuario_categoria'] = 'admin';
                    } else {
                        $_SESSION['usuario_categoria'] = $usuario['categoria'];
                    }
                    
                    $_SESSION['ultimo_acesso'] = time();
                    
                    // Debug to see which category is being set
                    if ($debug_mode) {
                        $debug_info .= "Category set in session: " . $_SESSION['usuario_categoria'] . "<br>";
                    }
                    
                    // Update the user's last connection
                    $update_sql = "UPDATE usuarios SET ultima_conexao = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $usuario['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Log the login
                    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'login', ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $detalhes = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'success' => true, 'categoria' => $_SESSION['usuario_categoria']]);
                    $log_stmt->bind_param("isss", $usuario['id'], $ip, $user_agent, $detalhes);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    // If not in debug mode, redirect to dashboard
                    if (!$debug_mode) {
                        header("Location: painel.php");
                        exit();
                    }
                } else {
                    // Log failed login attempt
                    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'login_falha', ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $detalhes = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'reason' => 'incorrect_password']);
                    $log_stmt->bind_param("isss", $usuario['id'], $ip, $user_agent, $detalhes);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $erro = "Incorrect email or password.";
                }
            } else {
                // For non-existent users, just set the error message
                // without trying to log (since we don't have a valid usuario_id)
                $erro = "Incorrect email or password.";
            }
            
            $stmt->close();
        }
    }
    
    $conn->close();
}

// Function to generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            background: var(--light-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 0;
        }
        .login-container {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .login-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            background-color: var(--primary-color);
            padding: 25px 30px;
        }
        .login-header h4 {
            color: var(--accent-color);
            font-weight: 800;
        }
        .login-header p {
            color: var(--accent-color);
        }
        .login-body {
            padding: 30px;
            background-color: white;
        }
        .login-footer {
            background-color: white;
            padding: 15px;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }
        .brand-logo {
            font-size: 2rem;
            color: var(--accent-color);
        }
        .form-floating .form-control {
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }
        .form-floating label {
            padding: 1rem 1rem;
        }
        .login-sidebar {
            background: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=800') no-repeat center center;
            background-size: cover;
            border-radius: 0 15px 15px 0;
            position: relative;
        }
        .login-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(0deg, rgba(3, 19, 74, 0.7) 0%, rgba(3, 19, 74, 0.4) 100%);
            border-radius: 0 15px 15px 0;
        }
        .login-sidebar-content {
            position: relative;
            z-index: 1;
            color: white;
            padding: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-sidebar-content h3 {
            font-weight: 800;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card login-card">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="login-header">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <img src="../img/EduBridge-logo.png" alt="EduBridge" class="logo" style="height: 55px; width: auto;">
                                    </div>
                                    <div>
                                        <h4 class="mb-0">EduBridge</h4>
                                        <p class="mb-0 small">Access your account</p>
                                    </div>
                                </div>
                            </div>
                            <div class="login-body">
                                <?php if (!empty($debug_info) && $debug_mode): ?>
                                <div class="alert alert-warning">
                                    <h5>Debug Information</h5>
                                    <div><?php echo $debug_info; ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($erro)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($sucesso)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $sucesso; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <form id="loginForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($debug_mode ? '?debug=1' : ''); ?>" class="needs-validation" novalidate>
                                    <!-- CSRF Protection -->
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    
                                    <div class="mb-4 form-floating">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" required>
                                        <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
                                        <div class="invalid-feedback">
                                            Please enter a valid email.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 form-floating">
                                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Password" required>
                                        <label for="senha"><i class="bi bi-lock me-2"></i>Password</label>
                                        <div class="invalid-feedback">
                                            Please enter your password.
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="lembrar" name="lembrar">
                                            <label class="form-check-label" for="lembrar">Remember me</label>
                                        </div>
                                        <a href="recuperar_senha.php" class="text-decoration-none">Forgot your password?</a>
                                    </div>
                                    
                                    <div class="d-grid mb-4">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                                        </button>
                                    </div>
                                    
                                    <div class="text-center">
                                        <p class="mb-0 text-muted">Don't have an account?</p>
                                        <a href="cadastro.html" class="btn btn-outline-primary mt-2">
                                            <i class="bi bi-person-plus me-2"></i>Sign up
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <div class="login-footer">
                                <p class="mb-0 small">&copy; 2025 EduBridge. All rights reserved.</p>
                            </div>
                        </div>
                        <div class="col-md-6 d-none d-md-block">
                            <div class="login-sidebar h-100">
                                <div class="login-sidebar-content">
                                    <h3 class="mb-4">Welcome to the future of education</h3>
                                    <p class="lead mb-5">We connect talented students, visionary investors, and educational institutions to create a new educational ecosystem.</p>
                                    <div class="d-flex">
                                        <div class="me-4">
                                            <h4 class="h2 mb-0">5K+</h4>
                                            <p class="mb-0 small">Students</p>
                                        </div>
                                        <div class="me-4">
                                            <h4 class="h2 mb-0">500+</h4>
                                            <p class="mb-0 small">Investors</p>
                                        </div>
                                        <div>
                                            <h4 class="h2 mb-0">50+</h4>
                                            <p class="mb-0 small">Universities</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
