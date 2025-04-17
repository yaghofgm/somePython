<?php
session_start();
// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Verifica se o usuário está logado e é um estudante
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'estudante') {
    header("Location: login.php?expirado=1");
    exit();
}

// Obter dados completos do perfil do estudante
$usuario_id = $_SESSION['usuario_id'];
$sql_perfil = "SELECT u.*, pe.*, 
               pu.nome as universidade_nome,
               pu.id as universidade_id, 
               cu.id as curso_id,
               cu.nome_curso as curso_nome,
               cu.salario_esperado,
               cu.custo_semestre,
               cu.gpa_medio
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

// Verificar si tenemos todos los datos necesarios para calcular
$datos_completos = (!empty($perfil['gpa']) && !empty($perfil['universidade_nome']) && 
                   !empty($perfil['curso_nome']) && !empty($perfil['salario_esperado']));

// Variables para mostrar resultados
$resultados = null;
$mensaje_error = null;
$country = isset($_POST['country']) ? $_POST['country'] : "US"; // País predeterminado (EEUU tiene menor riesgo)
$profit_threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 1.1; // Umbral de ganancia predeterminado (10%)

// Si el usuario ha enviado el formulario para calcular los préstamos
if (isset($_POST['calcular']) && $datos_completos) {
    $country = $_POST['country'];
    $profit_threshold = floatval($_POST['threshold']);
}

// Si el estudiante tiene sus datos completos, calculamos los préstamos
if ($datos_completos) {
    // Preparar los datos para el cálculo
    $gpa = floatval($perfil['gpa']);
    $university = $perfil['universidade_nome'];
    $course = $perfil['curso_nome'];
    $salario_esperado = floatval($perfil['salario_esperado']);
    $custo_semestre = isset($perfil['custo_semestre']) ? floatval($perfil['custo_semestre']) : 0;
    $total_program_cost = $custo_semestre * 8; // Multiplicamos por 8 semestres (4 años)
    
    // Crear un script Python temporal con los datos requeridos
    $temp_script = <<<PYTHON
import sys
import json
from pathlib import Path

# Añadir el directorio del algoritmo al path
sys.path.append('/home/yagho/python/edubridge_site')

# Intentar importar los módulos necesarios
try:
    from site_calculateLoan import getRange2Offer, detectar_subintervalos
    import numpy as np
    import pandas as pd
except Exception as e:
    print(json.dumps({{"error": f"Error de importación: {{str(e)}}"}}))
    sys.exit(1)

# Parámetros del usuario
params = {{
    "GPA": {$gpa},
    "sex": "male",  # Valor predeterminado, ajustar si es necesario
    "university": "{$university}",
    "course": "{$course}",
    "profit_threshold": {$profit_threshold},
    "country": "{$country}"
}}

try:
    # Ejecutar el cálculo
    interest_rates, loan_range = getRange2Offer(**params)
    
    # Procesar resultados
    result = []
    for i, rates in enumerate(interest_rates):
        intervalos = detectar_subintervalos(loan_range[i])
        if not intervalos:
            intervalos_texto = "Nenhum"
        else:
            intervalos_texto = ", ".join(intervalos)
        
        result.append({{
            "rate": float(rates * 100),
            "ranges": intervalos_texto
        }})
    
    # Imprimir el resultado como JSON
    print(json.dumps(result))
except Exception as e:
    print(json.dumps({{"error": f"Error de cálculo: {{str(e)}}"}}))
    sys.exit(1)
PYTHON;

    // Guardar el script temporal
    $temp_file = tempnam(sys_get_temp_dir(), 'py_');
    file_put_contents($temp_file, $temp_script);
    
    // Ejecutar el script Python
    $cmd = "/usr/bin/python3 {$temp_file} 2>&1";
    
    // Ejecutar el comando y capturar la salida
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    // Limpiar el archivo temporal
    @unlink($temp_file);
    
    if ($return_var === 0 && !empty($output)) {
        // Unir todas las líneas de salida en caso de que haya múltiples
        $json_output = implode("", $output);
        $resultados = json_decode($json_output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $mensaje_error = "Error al procesar los resultados del cálculo: " . json_last_error_msg() . 
                            "<br><strong>Salida del comando:</strong> <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        }
    } else {
        $mensaje_error = "Error al ejecutar el cálculo de préstamos (código: {$return_var})." . 
                        "<br><strong>Comando ejecutado:</strong> <code>" . htmlspecialchars($cmd) . "</code>" .
                        "<br><strong>Salida del comando:</strong> <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" width="device-width, initial-scale=1.0">
    <title>Calculadora de Empréstimos - EduBridge</title>
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
        
        .loan-range-box {
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .loan-rate {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .loan-ranges {
            color: #666;
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
                        <small class="text-white-50">Estudante</small>
                    </div>
                </div>
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link" href="painel_estudante.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="editar_perfil_estudante.php"><i class="bi bi-person"></i> Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-mortarboard"></i> Meu Curso</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calcular_emprestimos.php"><i class="bi bi-calculator"></i> Calcular Empréstimos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-chat-dots"></i> Mensagens</a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i> Sair</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <div class="container py-4">
                    <h2 class="mb-4"><i class="bi bi-calculator me-2"></i>Calculadora de Empréstimos Educacionais</h2>
                    
                    <?php if (!$datos_completos): ?>
                        <!-- Datos incompletos - Mostrar alerta -->
                        <div class="alert alert-warning">
                            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Dados incompletos</h4>
                            <p>Para calcular suas opções de empréstimo, precisamos de informações completas sobre seu perfil acadêmico.</p>
                            <hr>
                            <p class="mb-0">Por favor, complete seu perfil com as seguintes informações:</p>
                            <ul>
                                <?php if (empty($perfil['gpa'])): ?><li>GPA (média acadêmica)</li><?php endif; ?>
                                <?php if (empty($perfil['universidade_nome'])): ?><li>Universidade</li><?php endif; ?>
                                <?php if (empty($perfil['curso_nome'])): ?><li>Curso</li><?php endif; ?>
                                <?php if (empty($perfil['salario_esperado'])): ?><li>Salário esperado</li><?php endif; ?>
                            </ul>
                            <a href="editar_perfil_estudante.php" class="btn btn-primary mt-3">Completar Perfil</a>
                        </div>
                    <?php elseif ($mensaje_error): ?>
                        <!-- Error en el cálculo -->
                        <div class="alert alert-danger">
                            <h4 class="alert-heading"><i class="bi bi-x-circle-fill me-2"></i>Erro no cálculo</h4>
                            <p><?php echo $mensaje_error; ?></p>
                            <hr>
                            <p class="mb-0">Por favor, tente novamente mais tarde ou entre em contato com o suporte.</p>
                        </div>
                    <?php else: ?>
                        <!-- Datos completos - Mostrar formulario y resultados -->
                        <div class="row mb-4">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-person-badge me-2"></i>Seu Perfil Acadêmico
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">Nome:</div>
                                            <div class="col-sm-8"><?php echo $_SESSION['usuario_nome'] . ' ' . $_SESSION['usuario_sobrenome']; ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">Universidade:</div>
                                            <div class="col-sm-8"><?php echo $perfil['universidade_nome']; ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">Curso:</div>
                                            <div class="col-sm-8"><?php echo $perfil['curso_nome']; ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">GPA:</div>
                                            <div class="col-sm-8"><strong><?php echo number_format($perfil['gpa'], 2, ',', '.'); ?></strong> / 4.00</div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">Salário Esperado:</div>
                                            <div class="col-sm-8">$<?php echo number_format($perfil['salario_esperado'], 2, ',', '.'); ?></div>
                                        </div>
                                        <?php if (!empty($perfil['custo_semestre'])): ?>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-muted">Custo Semestral:</div>
                                            <div class="col-sm-8">$<?php echo number_format($perfil['custo_semestre'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-4 text-muted">Custo Total (8 semestres):</div>
                                            <div class="col-sm-8"><strong>$<?php echo number_format($perfil['custo_semestre'] * 8, 2, ',', '.'); ?></strong></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-sliders me-2"></i>Parâmetros de Cálculo
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="country" class="form-label">País para análise de risco:</label>
                                                <select id="country" name="country" class="form-select">
                                                    <option value="US" <?php echo ($country == 'US') ? 'selected' : ''; ?>>Estados Unidos (12% risco)</option>
                                                    <option value="MX" <?php echo ($country == 'MX') ? 'selected' : ''; ?>>México (20% risco)</option>
                                                    <option value="BR" <?php echo ($country == 'BR') ? 'selected' : ''; ?>>Brasil (35% risco)</option>
                                                </select>
                                                <div class="form-text">Países com menor risco permitem maiores valores de empréstimo.</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="threshold" class="form-label">Limiar de rentabilidade:</label>
                                                <select id="threshold" name="threshold" class="form-select">
                                                    <option value="1.05" <?php echo ($profit_threshold == 1.05) ? 'selected' : ''; ?>>5% (mais opções de empréstimo)</option>
                                                    <option value="1.1" <?php echo ($profit_threshold == 1.1) ? 'selected' : ''; ?>>10% (padrão)</option>
                                                    <option value="1.15" <?php echo ($profit_threshold == 1.15) ? 'selected' : ''; ?>>15% (mais conservador)</option>
                                                    <option value="1.2" <?php echo ($profit_threshold == 1.2) ? 'selected' : ''; ?>>20% (muito conservador)</option>
                                                </select>
                                                <div class="form-text">Valores menores oferecem mais opções de empréstimo.</div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="calcular" class="btn btn-primary">
                                                    <i class="bi bi-calculator me-2"></i>Calcular Opções de Empréstimo
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-cash-coin me-2"></i>Opções de Empréstimo Disponíveis
                            </div>
                            <div class="card-body">
                                <?php if (empty($resultados)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle-fill me-2"></i>Não encontramos opções de empréstimo viáveis com os parâmetros atuais.
                                    </div>
                                    <p>Isso pode ocorrer devido a:</p>
                                    <ul>
                                        <li>GPA abaixo do necessário para o curso e universidade</li>
                                        <li>Salário esperado insuficiente para garantir o pagamento</li>
                                        <li>Alta taxa de risco no país selecionado</li>
                                    </ul>
                                    <p>Sugestões:</p>
                                    <ul>
                                        <li>Tente selecionar um país com menor risco</li>
                                        <li>Diminua o limiar de rentabilidade para 5%</li>
                                        <li>Atualize seu salário esperado se tiver informações mais precisas</li>
                                    </ul>
                                <?php else: ?>
                                    <div class="row">
                                        <?php 
                                        $has_valid_ranges = false;
                                        foreach ($resultados as $resultado): 
                                            // Verificar si hay rangos válidos (no "Ninguno")
                                            if ($resultado['ranges'] !== "Ninguno" && $resultado['ranges'] !== "Nenhum"):
                                                $has_valid_ranges = true;
                                        ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="loan-range-box">
                                                    <div class="loan-rate"><?php echo number_format($resultado['rate'], 2, ',', '.'); ?>% de juros</div>
                                                    <div class="loan-ranges">
                                                        <strong>Valores aprovados:</strong> 
                                                        <?php echo $resultado['ranges']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                            endif;
                                        endforeach; 
                                        
                                        // Si no hay rangos válidos
                                        if (!$has_valid_ranges):
                                        ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle-fill me-2"></i>Não encontramos opções de empréstimo viáveis com os parâmetros atuais.
                                                </div>
                                                <p>Isso pode ocorrer devido a:</p>
                                                <ul>
                                                    <li>GPA abaixo do necessário para o curso e universidade</li>
                                                    <li>Salário esperado insuficiente para garantir o pagamento</li>
                                                    <li>Alta taxa de risco no país selecionado</li>
                                                </ul>
                                                <p>Sugestões:</p>
                                                <ul>
                                                    <li>Tente selecionar um país com menor risco</li>
                                                    <li>Diminua o limiar de rentabilidade para 5%</li>
                                                    <li>Atualize seu salário esperado se tiver informações mais precisas</li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-primary mt-3">
                                                    <i class="bi bi-lightbulb-fill me-2"></i>
                                                    <strong>Dica:</strong> Os valores apresentados representam intervalos de empréstimo viáveis considerando seu perfil acadêmico, salário esperado e os custos totais do programa (8 semestres).
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-4">
                                    <a href="editar_perfil_estudante.php" class="btn btn-outline-primary me-2">
                                        <i class="bi bi-pencil me-1"></i>Atualizar Perfil
                                    </a>
                                    <?php if (!empty($resultados) && $has_valid_ranges): ?>
                                    <a href="#" class="btn btn-primary">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Solicitar Empréstimo
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>