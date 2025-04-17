<?php
session_start();
// Error settings for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'conexao.php';

// Check if the user is logged in and is a university
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'universidade') {
    header("Location: login.php?expirado=1");
    exit();
}

// Get university data
$usuario_id = $_SESSION['usuario_id'];

// Get university profile
$sql_universidade = "SELECT pu.nome FROM perfil_universidade pu WHERE pu.usuario_id = ?";
$stmt_universidade = $conn->prepare($sql_universidade);
$stmt_universidade->bind_param("i", $usuario_id);
$stmt_universidade->execute();
$result_universidade = $stmt_universidade->get_result();
$nome_universidade = "Not defined";

if ($result_universidade->num_rows > 0) {
    $universidade_data = $result_universidade->fetch_assoc();
    $nome_universidade = $universidade_data['nome'];
}
$stmt_universidade->close();

// Get university courses
$sql_cursos = "SELECT cu.* FROM curso_universidade cu 
              JOIN perfil_universidade pu ON cu.universidade_id = pu.id 
              WHERE pu.usuario_id = ?";
$stmt_cursos = $conn->prepare($sql_cursos);
$stmt_cursos->bind_param("i", $usuario_id);
$stmt_cursos->execute();
$result_cursos = $stmt_cursos->get_result();
$cursos = [];
while ($row = $result_cursos->fetch_assoc()) {
    $cursos[] = $row;
}
$stmt_cursos->close();

// Get university students
$sql_estudantes = "SELECT u.id, u.nome, u.sobrenome, u.email, 
                  cu.nome_curso as curso, 
                  pe.ano_ingresso, pe.semestre_atual, pe.gpa
                  FROM usuarios u
                  JOIN perfil_estudante pe ON u.id = pe.usuario_id
                  JOIN curso_universidade cu ON pe.curso_id = cu.id
                  JOIN perfil_universidade pu ON cu.universidade_id = pu.id
                  WHERE pu.usuario_id = ?
                  AND u.categoria = 'estudante'";
$stmt_estudantes = $conn->prepare($sql_estudantes);
$stmt_estudantes->bind_param("i", $usuario_id);
$stmt_estudantes->execute();
$result_estudantes = $stmt_estudantes->get_result();
$estudantes = [];
$total_estudantes = 0;
$gpa_medio = 0;
$total_gpa = 0;

while ($row = $result_estudantes->fetch_assoc()) {
    $estudantes[] = $row;
    $total_estudantes++;
    if ($row['gpa']) {
        $total_gpa += $row['gpa'];
    }
}

if ($total_estudantes > 0) {
    $gpa_medio = $total_gpa / $total_estudantes;
}

$stmt_estudantes->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Dashboard - EduBridge</title>
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
        
        .university-logo {
            width: 100px;
            height: 100px;
            border-radius: 10px;
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
        
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        .course-badge {
            font-size: 12px;
            border-radius: 15px;
            padding: 5px 10px;
            margin: 3px;
            display: inline-block;
            background-color: rgba(48, 102, 190, 0.1);
            color: var(--primary-color);
        }
        
        .table-scrollable {
            max-height: 400px;
            overflow-y: auto;
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
                        <div class="fw-bold"><?php echo $_SESSION['usuario_nome']; ?></div>
                        <small class="text-white-50">University</small>
                    </div>
                </div>
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-building"></i> University Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-mortarboard"></i> Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-people"></i> Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-graph-up"></i> Reports</a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Dashboard - <?php echo htmlspecialchars($nome_universidade); ?></h2>
                    <div>
                        <span class="text-muted me-2"><?php echo date('m/d/Y H:i'); ?></span>
                    </div>
                </div>
                
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-primary">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo count($cursos); ?>
                            </div>
                            <div class="stat-label">Courses Offered</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-success">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo $total_estudantes; ?>
                            </div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-info">
                                <i class="bi bi-award"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo number_format($gpa_medio, 2, '.', ','); ?>
                            </div>
                            <div class="stat-label">Average GPA</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-icon text-warning">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo count($estudantes) > 0 ? '80%' : '0%'; ?>
                            </div>
                            <div class="stat-label">Retention Rate</div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Rows -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Message about future features -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-info-circle me-2"></i>Important Information</span>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    <h5><i class="bi bi-lightbulb me-2"></i>Welcome to your Dashboard!</h5>
                                    <p>We are in the process of implementing the EduBridge system for universities. Soon you will be able to:</p>
                                    <ul>
                                        <li>See detailed statistics about your courses and students</li>
                                        <li>Track students' academic progress</li>
                                        <li>View financing opportunities for your students</li>
                                        <li>Receive performance analysis and suggestions for improvements</li>
                                    </ul>
                                    <p class="mb-0">Stay tuned for our updates!</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Courses -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-mortarboard me-2"></i>Courses Offered</span>
                                <a href="#" class="btn btn-sm btn-outline-primary">Manage Courses</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($cursos) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Average GPA</th>
                                                <th>Cost/Semester</th>
                                                <th>Expected Salary</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cursos as $curso): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($curso['nome_curso']); ?></td>
                                                <td><?php echo $curso['gpa_medio'] ? number_format($curso['gpa_medio'], 2, '.', ',') : 'N/A'; ?></td>
                                                <td>US$ <?php echo $curso['custo_semestre'] ? number_format($curso['custo_semestre'], 2, '.', ',') : 'N/A'; ?></td>
                                                <td>US$ <?php echo $curso['salario_esperado'] ? number_format($curso['salario_esperado'], 2, '.', ',') : 'N/A'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="bi bi-mortarboard fs-1 text-muted"></i>
                                    </div>
                                    <h5>No courses registered</h5>
                                    <p class="text-muted">Add courses to start managing your students.</p>
                                    <a href="#" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Add Course
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Students Distribution Chart -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up me-2"></i>Student Distribution by Course
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="studentDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Recent Students -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-people me-2"></i>Recent Students</span>
                                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($estudantes) > 0): ?>
                                    <?php 
                                    // Show only 5 students
                                    $display_estudantes = array_slice($estudantes, 0, 5); 
                                    ?>
                                    <?php foreach ($display_estudantes as $estudante): ?>
                                    <div class="card mb-3 student-card">
                                        <div class="card-body d-flex align-items-center position-relative">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($estudante['nome'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($estudante['nome'] . ' ' . $estudante['sobrenome']); ?></h6>
                                                <p class="mb-0 small"><?php echo htmlspecialchars($estudante['curso']); ?> - Year: <?php echo $estudante['ano_ingresso']; ?></p>
                                            </div>
                                            <?php if ($estudante['gpa']): ?>
                                            <div class="gpa-badge"><?php echo number_format($estudante['gpa'], 1); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                        <p>There are no students registered for this university.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- University Performance -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up me-2"></i>University Performance
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>Graduation Rate:</span>
                                    <div class="progress w-50" style="height: 10px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 85%"></div>
                                    </div>
                                    <span class="ms-2">85%</span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>Employability:</span>
                                    <div class="progress w-50" style="height: 10px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 78%"></div>
                                    </div>
                                    <span class="ms-2">78%</span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>Satisfaction:</span>
                                    <div class="progress w-50" style="height: 10px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: 90%"></div>
                                    </div>
                                    <span class="ms-2">90%</span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-grid">
                                    <a href="#" class="btn btn-outline-primary">
                                        <i class="bi bi-file-earmark-text me-2"></i>Complete Report
                                    </a>
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
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data for student distribution
            var distributionCtx = document.getElementById('studentDistributionChart').getContext('2d');
            
            // Prepare data from PHP
            <?php
            $courseNames = [];
            $studentCounts = [];
            
            // Count students by course
            $courseStudentCount = [];
            foreach ($estudantes as $estudante) {
                $curso = $estudante['curso'];
                if (!isset($courseStudentCount[$curso])) {
                    $courseStudentCount[$curso] = 0;
                }
                $courseStudentCount[$curso]++;
            }
            
            // Convert to arrays for Chart.js
            foreach ($courseStudentCount as $curso => $count) {
                $courseNames[] = $curso;
                $studentCounts[] = $count;
            }
            ?>
            
            var studentDistributionChart = new Chart(distributionCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($courseNames); ?>,
                    datasets: [{
                        label: 'Number of Students',
                        data: <?php echo json_encode($studentCounts); ?>,
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
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>