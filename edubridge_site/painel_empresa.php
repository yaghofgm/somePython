<?php
session_start();
// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Verifica se o usuário está logado e é uma empresa
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'empresa') {
    header("Location: login.php?expirado=1");
    exit();
}

// Obter informações da empresa atual
$empresa_id = $_SESSION['usuario_id'];
$sql_empresa = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql_empresa);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$result_empresa = $stmt->get_result();
$empresa_data = $result_empresa->fetch_assoc();

// Buscar estudantes com filtros de interesse que correspondam ao perfil da empresa
$sql_estudantes_compativeis = "SELECT 
                           u.id, 
                           u.nome, 
                           u.sobrenome, 
                           u.email, 
                           p.universidade_id, 
                           p.curso_id, 
                           p.gpa, 
                           p.linkedin,
                           pu.nome AS nome_universidade,
                           cu.nome_curso
                           FROM usuarios u 
                           LEFT JOIN perfil_estudante p ON u.id = p.usuario_id
                           LEFT JOIN perfil_universidade pu ON p.universidade_id = pu.id
                           LEFT JOIN curso_universidade cu ON p.curso_id = cu.id
                           WHERE u.categoria = 'estudante' AND u.status = 'ativo'
                           ORDER BY u.data_criacao DESC 
                           LIMIT 10";
                           
$result_estudantes = $conn->query($sql_estudantes_compativeis);

// Inicializa um array vazio para oportunidades
$oportunidades = [];

// Mensagem vazia por padrão
$mensagem = '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" width="device-width, initial-scale=1.0">
    <title>Painel da Empresa - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        
        .table-scrollable {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .opportunity-card {
            transition: all 0.3s ease;
        }
        
        .opportunity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
                        <small class="text-white-50">Empresa</small>
                    </div>
                </div>
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#estudantes"><i class="bi bi-people"></i> Estudantes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#perfil"><i class="bi bi-person"></i> Perfil da Empresa</a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> Sair</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Painel da Empresa</h2>
                    <div>
                        <span class="text-muted me-2"><?php echo date('d/m/Y H:i'); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($mensagem)) echo $mensagem; ?>
                
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="stat-icon text-info">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-value">
                                <?php echo $result_estudantes ? $result_estudantes->num_rows : 0; ?>
                            </div>
                            <div class="stat-label">Estudantes Disponíveis</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="stat-icon text-primary">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="stat-value">
                                1
                            </div>
                            <div class="stat-label">Perfil Empresarial</div>
                        </div>
                    </div>
                </div>
                
                <!-- Estudantes Section -->
                <div class="card" id="estudantes">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-people me-2"></i>
                            Estudantes Compatíveis
                        </div>
                        <div>
                            <input type="text" class="form-control form-control-sm" placeholder="Buscar estudantes..." id="searchEstudantes">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-scrollable">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Universidade</th>
                                        <th>Curso</th>
                                        <th>GPA</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result_estudantes && $result_estudantes->num_rows > 0): ?>
                                        <?php while ($estudante = $result_estudantes->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($estudante['nome'] . ' ' . $estudante['sobrenome']); ?></td>
                                                <td><?php echo ($estudante['nome_universidade'] ? $estudante['nome_universidade'] : 'Não informado'); ?></td>
                                                <td><?php echo ($estudante['nome_curso'] ? $estudante['nome_curso'] : 'Não informado'); ?></td>
                                                <td><?php echo ($estudante['gpa'] ? $estudante['gpa'] : 'Não informado'); ?></td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-outline-primary">Ver Perfil</a>
                                                    <a href="#" class="btn btn-sm btn-outline-success">Convidar</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Nenhum estudante compatível encontrado</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtro para tabela de estudantes
        document.getElementById('searchEstudantes').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.querySelector('#estudantes table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html>