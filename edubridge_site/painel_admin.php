<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in and is an administrator
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'admin') {
    header("Location: login.php?expirado=1");
    exit();
}

// Include database connection
include 'conexao.php';

// Update last access timestamp
$_SESSION['ultimo_acesso'] = time();

// Get current user data
$id = $_SESSION['usuario_id'];
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

// Get dashboard totals with error handling
try {
    $sql_total_estudantes = "SELECT COUNT(*) as total FROM usuarios WHERE categoria = 'estudante'";
    $result_estudantes = $conn->query($sql_total_estudantes);
    if (!$result_estudantes) {
        throw new Exception("Database error: " . $conn->error);
    }
    $total_estudantes = $result_estudantes->fetch_assoc()['total'];
    
    $sql_total_investidores = "SELECT COUNT(*) as total FROM usuarios WHERE categoria = 'investidor'";
    $result_investidores = $conn->query($sql_total_investidores);
    if (!$result_investidores) {
        throw new Exception("Database error: " . $conn->error);
    }
    $total_investidores = $result_investidores->fetch_assoc()['total'];
    
    $sql_total_universidades = "SELECT COUNT(*) as total FROM usuarios WHERE categoria = 'universidade'";
    $result_universidades = $conn->query($sql_total_universidades);
    if (!$result_universidades) {
        throw new Exception("Database error: " . $conn->error);
    }
    $total_universidades = $result_universidades->fetch_assoc()['total'];
    
    $sql_total_empresas = "SELECT COUNT(*) as total FROM usuarios WHERE categoria = 'empresa'";
    $result_empresas = $conn->query($sql_total_empresas);
    if (!$result_empresas) {
        throw new Exception("Database error: " . $conn->error);
    }
    $total_empresas = $result_empresas->fetch_assoc()['total'];

    // Get recent users with error handling
    $sql_usuarios_recentes = "SELECT id, nome, sobrenome, email, categoria, status, data_criacao 
                           FROM usuarios 
                           ORDER BY data_criacao DESC 
                           LIMIT 10";
    $result_usuarios_recentes = $conn->query($sql_usuarios_recentes);
    if (!$result_usuarios_recentes) {
        throw new Exception("Database error: " . $conn->error);
    }

    // Get recent activities with error handling
    $sql_atividades = "SELECT ul.id, ul.usuario_id, ul.acao, ul.data_hora, u.nome, u.sobrenome, u.email, u.categoria
                    FROM usuarios_logs ul
                    JOIN usuarios u ON ul.usuario_id = u.id
                    ORDER BY ul.data_hora DESC
                    LIMIT 20";
    $result_atividades = $conn->query($sql_atividades);
    if (!$result_atividades) {
        throw new Exception("Database error: " . $conn->error);
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    // Set default values in case of error
    $total_estudantes = 0;
    $total_investidores = 0;
    $total_universidades = 0;
    $total_empresas = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #3066BE;
            --secondary-color: #119DA4;
            --accent-color: #6D9DC5;
            --light-color: #F2F5FF;
            --dark-color: #253237;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark-color);
        }
        .sidebar {
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 260px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            margin: 0.25rem 0;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        .dashboard-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        .dashboard-card .card-body {
            padding: 1.5rem;
        }
        .dashboard-card .icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        .dashboard-card .title {
            font-size: 1rem;
            color: #6c757d;
        }
        .dashboard-card .number {
            font-size: 2rem;
            font-weight: 700;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        .user-dropdown img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            padding: 0.35em 0.65em;
            border-radius: 50rem;
            font-size: 0.75em;
        }
        .status-ativo {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-pendente {
            background-color: #fff3cd;
            color: #664d03;
        }
        .status-suspenso {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-inativo {
            background-color: #e2e3e5;
            color: #41464b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column p-3">
        <div class="d-flex align-items-center mb-4 mt-2 px-2">
            <i class="bi bi-mortarboard-fill me-2 fs-4"></i>
            <h4 class="mb-0">EduBridge</h4>
        </div>
        <p class="text-white-50 small px-2 mb-2">MAIN MENU</p>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="listar_usuarios.php" class="nav-link">
                    <i class="bi bi-people"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-mortarboard"></i> Universities
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-bank"></i> Investors
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-briefcase"></i> Companies
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>
        <hr class="text-white-50">
        <div class="px-2">
            <a href="logout.php" class="nav-link text-white-50">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 rounded shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="#">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Reports</a>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo $usuario['foto_perfil'] ? 'uploads/profile/' . $usuario['foto_perfil'] : 'uploads/profile/default.jpg'; ?>" alt="User" class="me-2">
                                <span><?php echo $usuario['nome'] . ' ' . $usuario['sobrenome']; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Admin Dashboard</h2>
            <div>
                <button class="btn btn-primary">
                    <i class="bi bi-download me-2"></i> Export Report
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="title mb-0">Total Students</p>
                                <h3 class="number"><?php echo $total_estudantes; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                        </div>
                        <p class="text-success mb-0"><i class="bi bi-graph-up me-1"></i> +5% since last month</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="title mb-0">Total Investors</p>
                                <h3 class="number"><?php echo $total_investidores; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="bi bi-bank"></i>
                            </div>
                        </div>
                        <p class="text-success mb-0"><i class="bi bi-graph-up me-1"></i> +3% since last month</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="title mb-0">Total Universities</p>
                                <h3 class="number"><?php echo $total_universidades; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="bi bi-building"></i>
                            </div>
                        </div>
                        <p class="text-success mb-0"><i class="bi bi-graph-up me-1"></i> +2% since last month</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="title mb-0">Total Companies</p>
                                <h3 class="number"><?php echo $total_empresas; ?></h3>
                            </div>
                            <div class="icon">
                                <i class="bi bi-briefcase"></i>
                            </div>
                        </div>
                        <p class="text-success mb-0"><i class="bi bi-graph-up me-1"></i> +4% since last month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Recent Users</h5>
                    <a href="listar_usuarios.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Creation Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($usuario = $result_usuarios_recentes->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $usuario['id']; ?></td>
                                <td><?php echo $usuario['nome'] . ' ' . $usuario['sobrenome']; ?></td>
                                <td><?php echo $usuario['email']; ?></td>
                                <td>
                                    <?php 
                                    switch ($usuario['categoria']) {
                                        case 'estudante':
                                            echo '<span class="badge bg-primary">Student</span>';
                                            break;
                                        case 'investidor':
                                            echo '<span class="badge bg-success">Investor</span>';
                                            break;
                                        case 'universidade':
                                            echo '<span class="badge bg-info">University</span>';
                                            break;
                                        case 'empresa':
                                            echo '<span class="badge bg-secondary">Company</span>';
                                            break;
                                        case 'admin':
                                            echo '<span class="badge bg-dark">Admin</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    switch ($usuario['status']) {
                                        case 'ativo':
                                            echo '<span class="status-badge status-ativo">Active</span>';
                                            break;
                                        case 'pendente':
                                            echo '<span class="status-badge status-pendente">Pending</span>';
                                            break;
                                        case 'suspenso':
                                            echo '<span class="status-badge status-suspenso">Suspended</span>';
                                            break;
                                        case 'inativo':
                                            echo '<span class="status-badge status-inativo">Inactive</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?php echo $usuario['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownActions<?php echo $usuario['id']; ?>">
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-eye me-2"></i> View Details</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                            <?php if ($usuario['status'] == 'ativo'): ?>
                                            <li><a class="dropdown-item text-warning" href="#"><i class="bi bi-pause-circle me-2"></i> Suspend</a></li>
                                            <?php else: ?>
                                            <li><a class="dropdown-item text-success" href="#"><i class="bi bi-check-circle me-2"></i> Activate</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Recent Activities</h5>
                    <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Category</th>
                                <th>Action</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($atividade = $result_atividades->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $atividade['nome'] . ' ' . $atividade['sobrenome']; ?></td>
                                <td>
                                    <?php 
                                    switch ($atividade['categoria']) {
                                        case 'estudante':
                                            echo '<span class="badge bg-primary">Student</span>';
                                            break;
                                        case 'investidor':
                                            echo '<span class="badge bg-success">Investor</span>';
                                            break;
                                        case 'universidade':
                                            echo '<span class="badge bg-info">University</span>';
                                            break;
                                        case 'empresa':
                                            echo '<span class="badge bg-secondary">Company</span>';
                                            break;
                                        case 'admin':
                                            echo '<span class="badge bg-dark">Admin</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    switch ($atividade['acao']) {
                                        case 'login':
                                            echo '<span class="text-success"><i class="bi bi-box-arrow-in-right me-1"></i> Login</span>';
                                            break;
                                        case 'login_falha':
                                            echo '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Failed Login Attempt</span>';
                                            break;
                                        case 'logout':
                                            echo '<span class="text-secondary"><i class="bi bi-box-arrow-right me-1"></i> Logout</span>';
                                            break;
                                        case 'perfil_atualizado':
                                            echo '<span class="text-primary"><i class="bi bi-person-check me-1"></i> Profile Updated</span>';
                                            break;
                                        case 'cadastro':
                                            echo '<span class="text-info"><i class="bi bi-person-plus me-1"></i> Registration</span>';
                                            break;
                                        default:
                                            echo $atividade['acao'];
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($atividade['data_hora'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>