<?php
session_start();
// Error settings for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'conexao.php';

// Check if the user is logged in and is an investor
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'investidor') {
    header("Location: login.php?expirado=1");
    exit();
}

// Get investor data
$usuario_id = $_SESSION['usuario_id'];

// Initialize empty arrays since the financiamentos table doesn't exist
$financiamentos = [];
$total_financiamentos = 0;
$valor_total_investido = 0;
$valor_total_a_receber = 0;
$financiamentos_ativos = 0;

// Get featured students
$sql_estudantes = "SELECT u.id, u.nome, u.sobrenome, u.email, 
                          pu.nome as universidade, 
                          cu.nome_curso as curso,
                          pe.gpa
                  FROM usuarios u
                  JOIN perfil_estudante pe ON u.id = pe.usuario_id
                  LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
                  LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
                  WHERE u.categoria = 'estudante' 
                  AND u.status = 'ativo'
                  AND pe.gpa >= 3.0
                  LIMIT 5";
$stmt_estudantes = $conn->prepare($sql_estudantes);
$stmt_estudantes->execute();
$result_estudantes = $stmt_estudantes->get_result();
$estudantes_destaque = [];
while ($row = $result_estudantes->fetch_assoc()) {
    // Fallback values if data is null
    if ($row['universidade'] === null) $row['universidade'] = 'University not specified';
    if ($row['curso'] === null) $row['curso'] = 'Course not specified';
    $estudantes_destaque[] = $row;
}
$stmt_estudantes->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investor Dashboard - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #3066BE;
            --secondary-color: #119DA4;
            --accent-color: #6D9DC5;
            --light-color: #F2F5FF;
            --dark-color: #253237;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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
        
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        .table-scrollable {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .investment-card {
            transition: all 0.3s ease;
        }
        
        .investment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .student-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .student-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 20px;
        }
        
        .gpa-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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
                        <small class="text-white-50">Investor</small>
                    </div>
                </div>
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-person"></i> My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-search"></i> Find Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-graph-up"></i> Reports</a>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Investor Dashboard</h2>
                    <div>
                        <span class="text-muted me-2"><?php echo date('m/d/Y H:i'); ?></span>
                    </div>
                </div>
                
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo count($estudantes_destaque); ?>
                            </div>
                            <div class="stat-label">Featured Students</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-success">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <div class="stat-value">
                                <?php 
                                    $avg_gpa = 0;
                                    if(count($estudantes_destaque) > 0) {
                                        $total_gpa = 0;
                                        foreach($estudantes_destaque as $estudante) {
                                            $total_gpa += $estudante['gpa'];
                                        }
                                        $avg_gpa = $total_gpa / count($estudantes_destaque);
                                    }
                                    echo number_format($avg_gpa, 2, '.', ',');
                                ?>
                            </div>
                            <div class="stat-label">Average GPA</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-info">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="stat-value">
                                <?php 
                                    $universidades = [];
                                    foreach($estudantes_destaque as $estudante) {
                                        if(!empty($estudante['universidade']) && !in_array($estudante['universidade'], $universidades)) {
                                            $universidades[] = $estudante['universidade'];
                                        }
                                    }
                                    echo count($universidades);
                                ?>
                            </div>
                            <div class="stat-label">Universities</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-warning">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="stat-value">
                                <?php 
                                    $cursos = [];
                                    foreach($estudantes_destaque as $estudante) {
                                        if(!empty($estudante['curso']) && !in_array($estudante['curso'], $cursos)) {
                                            $cursos[] = $estudante['curso'];
                                        }
                                    }
                                    echo count($cursos);
                                ?>
                            </div>
                            <div class="stat-label">Courses</div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Rows -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Message to investor -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-info-circle me-2"></i>Important Information</span>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    <h5><i class="bi bi-lightbulb me-2"></i>Welcome to your Dashboard!</h5>
                                    <p>We are in the process of implementing the financing system. Soon you will be able to:</p>
                                    <ul>
                                        <li>Search and find talented students that match your investment profile</li>
                                        <li>Invest in the education of promising students</li>
                                        <li>Track the academic progress of your investments</li>
                                        <li>View detailed statistics and return forecasts</li>
                                    </ul>
                                    <p class="mb-0">Stay tuned for our updates!</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Chart (Placeholder) -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up me-2"></i>Students by Area
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="studentsAreaChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Featured Students -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-star me-2"></i>Featured Students</span>
                            </div>
                            <div class="card-body">
                                <?php if (count($estudantes_destaque) > 0): ?>
                                    <?php foreach ($estudantes_destaque as $estudante): ?>
                                    <div class="card mb-3 student-card">
                                        <div class="card-body d-flex align-items-center position-relative">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($estudante['nome'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($estudante['nome'] . ' ' . $estudante['sobrenome']); ?></h6>
                                                <p class="mb-0 small"><?php echo htmlspecialchars($estudante['universidade'] . ' - ' . $estudante['curso']); ?></p>
                                            </div>
                                            <div class="gpa-badge"><?php echo number_format($estudante['gpa'], 1); ?></div>
                                        </div>
                                        <div class="card-footer bg-transparent p-3">
                                            <a href="#" class="btn btn-sm btn-primary w-100">View Profile</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-search fs-1 text-muted mb-3"></i>
                                        <p>We couldn't find any featured students to show at this time.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="#" class="btn btn-outline-primary w-100 mt-2">
                                    <i class="bi bi-search me-2"></i>Find More Students
                                </a>
                            </div>
                        </div>
                        
                        <!-- Investment Tips -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-lightbulb me-2"></i>Investment Tips
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item px-0">
                                        <h6><i class="bi bi-graph-up text-success me-2"></i>Diversify your portfolio</h6>
                                        <p class="mb-0 small">Invest in students from different courses and universities to reduce risk.</p>
                                    </div>
                                    <div class="list-group-item px-0">
                                        <h6><i class="bi bi-stars text-warning me-2"></i>Look for high performance</h6>
                                        <p class="mb-0 small">Students with good academic records tend to have more professional success.</p>
                                    </div>
                                    <div class="list-group-item px-0">
                                        <h6><i class="bi bi-building text-primary me-2"></i>Consider the institution</h6>
                                        <p class="mb-0 small">The university's reputation can impact the student's future opportunities.</p>
                                    </div>
                                </div>
                                <a href="#" class="btn btn-outline-primary w-100 mt-3">
                                    <i class="bi bi-book me-2"></i>Investor Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data for student areas chart
            var areaCtx = document.getElementById('studentsAreaChart').getContext('2d');
            var areaChart = new Chart(areaCtx, {
                type: 'pie',
                data: {
                    labels: ['Engineering', 'Medicine', 'Law', 'Technology', 'Business', 'Arts'],
                    datasets: [{
                        data: [30, 20, 15, 25, 10, 5],
                        backgroundColor: [
                            '#3066BE',
                            '#119DA4',
                            '#6D9DC5',
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Student Distribution by Area'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>