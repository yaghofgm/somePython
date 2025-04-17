<?php
session_start();
// Error settings for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'conexao.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'estudante') {
    header("Location: login.php?expirado=1");
    exit();
}

// Get student profile data
$usuario_id = $_SESSION['usuario_id'];
$sql_perfil = "SELECT u.*, pe.*, 
               pu.nome as universidade_nome,
               cu.nome_curso as curso_nome
               FROM usuarios u 
               LEFT JOIN perfil_estudante pe ON u.id = pe.usuario_id 
               LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
               LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
               WHERE u.id = ?";
$stmt_perfil = $conn->prepare($sql_perfil);
$stmt_perfil->bind_param("i", $usuario_id);
$stmt_perfil->execute();
$result_perfil = $stmt_perfil->get_result();
$perfil = $result_perfil->fetch_assoc();
$stmt_perfil->close();

// Initialize variables for statistics (since financiamentos doesn't exist yet)
$total_financiamentos = 0;
$valor_total = 0;
$financiamentos_aprovados = 0;
$financiamentos = [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduBridge</title>
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
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .sidebar {
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 0;
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .logo-container {
            padding: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo-container .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
        }
        
        .stat-card .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            color: #6c757d;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 px-0 sidebar">
                <div class="logo-container d-flex align-items-center">
                    <i class="bi bi-mortarboard-fill me-2"></i>
                    <div class="logo">EduBridge</div>
                </div>
                <div class="user-info px-3 pb-3 mb-3 border-bottom border-white border-opacity-10">
                    <div class="user-avatar">
                        <?php echo substr($_SESSION['usuario_nome'], 0, 1); ?>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo $_SESSION['usuario_nome'] . ' ' . $_SESSION['usuario_sobrenome']; ?></div>
                        <small class="text-white-50">Student</small>
                    </div>
                </div>
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="editar_perfil_estudante.php"><i class="bi bi-person"></i> My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-mortarboard"></i> My Course</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calcular_emprestimos_v2.php"><i class="bi bi-calculator"></i> Calculate Loans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-chat-dots"></i> Messages</a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <!-- Profile Header -->
                <div class="profile-header d-flex align-items-center mb-4">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
                    </div>
                    <div>
                        <h2 class="mb-1"><?php echo $_SESSION['usuario_nome'] . ' ' . $_SESSION['usuario_sobrenome']; ?></h2>
                        <p class="mb-0"><?php echo !empty($perfil['universidade_nome']) ? $perfil['universidade_nome'] : 'University not specified'; ?> | 
                           <?php echo !empty($perfil['curso_nome']) ? $perfil['curso_nome'] : 'Course not specified'; ?></p>
                        <a href="editar_perfil_estudante.php" class="btn btn-sm btn-light mt-2">Complete Profile</a>
                    </div>
                </div>
                
                <!-- Dashboard Content -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Financial Overview -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-cash me-2"></i>Financial Overview</span>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="bi bi-cash-coin fs-1 text-muted"></i>
                                    </div>
                                    <h5>Financing system under development</h5>
                                    <p class="text-muted">We're working to offer educational financing options.</p>
                                    <a href="calcular_emprestimos_v2.php" class="btn btn-primary">
                                        <i class="bi bi-calculator me-2"></i>Financing Calculator
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Academic Progress -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-journal-bookmark me-2"></i>Academic Progress
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Course Progress</span>
                                        <span><?php echo (!empty($perfil['semestre_atual']) && !empty($perfil['ano_ingresso'])) ? $perfil['semestre_atual'] . 'th semester' : 'Not specified'; ?></span>
                                    </div>
                                    <div class="progress">
                                        <?php
                                        $progresso = 0;
                                        if (!empty($perfil['semestre_atual'])) {
                                            // Estimating an 8-semester course
                                            $progresso = min(($perfil['semestre_atual'] / 8) * 100, 100);
                                        }
                                        ?>
                                        <div class="progress-bar bg-success" style="width: <?php echo $progresso; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>GPA</span>
                                        <span><?php echo !empty($perfil['gpa']) ? number_format($perfil['gpa'], 2, '.', ',') . ' / 4.00' : 'Not specified'; ?></span>
                                    </div>
                                    <div class="progress">
                                        <?php
                                        $gpa_percent = 0;
                                        if (!empty($perfil['gpa'])) {
                                            $gpa_percent = min(($perfil['gpa'] / 4) * 100, 100);
                                        }
                                        ?>
                                        <div class="progress-bar bg-primary" style="width: <?php echo $gpa_percent; ?>%"></div>
                                    </div>
                                </div>
                                
                                <?php if (empty($perfil['curso_nome']) || empty($perfil['universidade_nome']) || empty($perfil['gpa'])): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Your academic profile is incomplete. Complete it to increase your chances of getting financing.
                                </div>
                                <?php endif; ?>
                                
                                <a href="editar_perfil_estudante.php" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil me-2"></i>Update Academic Information
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Profile Completion -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-check me-2"></i>Profile Completion
                            </div>
                            <div class="card-body">
                                <?php
                                $campos_perfil = [
                                    'universidade_nome' => !empty($perfil['universidade_nome']),
                                    'curso_nome' => !empty($perfil['curso_nome']),
                                    'ano_ingresso' => !empty($perfil['ano_ingresso']),
                                    'semestre_atual' => !empty($perfil['semestre_atual']),
                                    'gpa' => !empty($perfil['gpa']),
                                    'cv_path' => !empty($perfil['cv_path']),
                                    'linkedin' => !empty($perfil['linkedin'])
                                ];
                                $campos_preenchidos = array_sum($campos_perfil);
                                $total_campos = count($campos_perfil);
                                $percentual_preenchido = ($campos_preenchidos / $total_campos) * 100;
                                ?>
                                
                                <div class="text-center mb-3">
                                    <div class="d-inline-block position-relative">
                                        <svg width="120" height="120" viewBox="0 0 120 120">
                                            <circle cx="60" cy="60" r="54" fill="none" stroke="#e6e6e6" stroke-width="12" />
                                            <circle cx="60" cy="60" r="54" fill="none" stroke="<?php echo $percentual_preenchido == 100 ? '#28a745' : '#3066BE'; ?>" stroke-width="12"
                                                stroke-dasharray="339.292" stroke-dashoffset="<?php echo 339.292 * (1 - $percentual_preenchido / 100); ?>" />
                                        </svg>
                                        <div class="position-absolute top-50 start-50 translate-middle">
                                            <h3 class="mb-0"><?php echo round($percentual_preenchido); ?>%</h3>
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($campos_perfil as $campo => $preenchido): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <?php 
                                        $nomes = [
                                            'universidade_nome' => 'University',
                                            'curso_nome' => 'Course',
                                            'ano_ingresso' => 'Enrollment Year',
                                            'semestre_atual' => 'Current Semester',
                                            'gpa' => 'GPA',
                                            'cv_path' => 'Resume/CV',
                                            'linkedin' => 'LinkedIn'
                                        ];
                                        echo $nomes[$campo] ?? ucfirst($campo); 
                                        ?>
                                        
                                        <?php if ($preenchido): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-exclamation-circle text-warning"></i>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="mt-3">
                                    <a href="editar_perfil_estudante.php" class="btn btn-primary w-100">Complete Profile</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Opportunities -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-lightning-charge me-2"></i>Opportunities
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush mb-3">
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <h6 class="mb-1">Scholarship - Study Abroad</h6>
                                            <p class="mb-0 small text-muted">University of Cambridge</p>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">New</span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <h6 class="mb-1">Technology Internship</h6>
                                            <p class="mb-0 small text-muted">Microsoft Brazil</p>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">New</span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action px-0">
                                        <div>
                                            <h6 class="mb-1">Financing Program</h6>
                                            <p class="mb-0 small text-muted">EduBridge and BNDES Partnership</p>
                                        </div>
                                    </a>
                                </div>
                                <a href="#" class="btn btn-outline-primary w-100">View All</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>