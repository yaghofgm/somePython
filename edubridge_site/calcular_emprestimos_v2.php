<?php
/**
 * Calcular Empréstimos v2
 * Esta versión utiliza el nuevo script de Python simplificado
 */

session_start();
include 'conexao.php';

// Verificar se o usuário está logado e é um estudante
$usuario_logado = isset($_SESSION['usuario_id']);
$estudante_id = $usuario_logado && $_SESSION['usuario_categoria'] == 'estudante' ? $_SESSION['usuario_id'] : null;

// Buscar dados do estudante se estiver logado
$universidade_id = null;
$curso_id = null;
$gpa_atual = null;
$nacionalidade = null;

if ($estudante_id) {
    $sql = "SELECT pe.*, cu.nome_curso, pu.nome as nome_universidade
            FROM perfil_estudante pe
            LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
            LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
            WHERE pe.usuario_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $estudante_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $estudante_data = $result->fetch_assoc();
        $universidade_id = $estudante_data['universidade_id'];
        $curso_id = $estudante_data['curso_id'];
        $gpa_atual = $estudante_data['gpa'];
        $nacionalidade = $estudante_data['nacionalidade'];
    }
}

// Buscar todas as universidades
$sql_universidades = "SELECT id, nome FROM perfil_universidade ORDER BY nome";
$result_universidades = $conn->query($sql_universidades);
$universidades = [];

if ($result_universidades && $result_universidades->num_rows > 0) {
    while ($row = $result_universidades->fetch_assoc()) {
        $universidades[] = $row;
    }
}

// Buscar cursos da universidade selecionada
$cursos = [];
if ($universidade_id) {
    $sql_cursos = "SELECT id, nome_curso, custo_semestre, salario_esperado FROM curso_universidade 
                   WHERE universidade_id = ? ORDER BY nome_curso";
    $stmt = $conn->prepare($sql_cursos);
    $stmt->bind_param("i", $universidade_id);
    $stmt->execute();
    $result_cursos = $stmt->get_result();
    
    if ($result_cursos && $result_cursos->num_rows > 0) {
        while ($row = $result_cursos->fetch_assoc()) {
            $cursos[] = $row;
        }
    }
}

// Configuración
$python_script = __DIR__ . '/site_calculateLoan.py';
$python_executable = 'python3';

/**
 * Função para calcular opciones de préstamo usando el script de Python
 * @param array $student_data Datos del estudiante
 * @return array Opciones de préstamo
 */
function calcular_opciones_prestamo($student_data) {
    global $python_script, $python_executable;
    
    // Validar datos mínimos requeridos
    if (!isset($student_data['gpa']) || !isset($student_data['university']) || !isset($student_data['course'])) {
        return ['error' => 'Datos incompletos del estudiante'];
    }
    
    // Preparar la entrada JSON para el script
    $input_json = json_encode($student_data);
    
    // Configurar la ejecución del proceso
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];
    
    // Ejecutar el script de Python
    $process = proc_open("$python_executable $python_script", $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return ['error' => 'Error al iniciar el proceso de Python'];
    }
    
    // Enviar datos al script
    fwrite($pipes[0], $input_json);
    fclose($pipes[0]);
    
    // Leer la salida del script
    $output = stream_get_contents($pipes[1]);
    $error_output = stream_get_contents($pipes[2]);
    
    // Cerrar pipes y proceso
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    
    // Verificar errores
    if ($exit_code !== 0 || !empty($error_output)) {
        return ['error' => "Error en la ejecución ($exit_code): $error_output"];
    }
    
    // Decodificar la salida JSON
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Error al decodificar la respuesta: ' . json_last_error_msg()];
    }
    
    return $result;
}

// Procesar el formulario si se ha enviado
$opciones_prestamo = [];
$errores = [];
$processed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gpa = isset($_POST['gpa']) ? (float) $_POST['gpa'] : 0;
    $university = isset($_POST['university']) ? trim($_POST['university']) : '';
    $course = isset($_POST['course']) ? trim($_POST['course']) : '';
    $semester_cost = isset($_POST['semester_cost']) ? (float) $_POST['semester_cost'] : 0;
    $expected_salary = isset($_POST['expected_salary']) ? (float) $_POST['expected_salary'] : 0;
    $remaining_semesters = isset($_POST['remaining_semesters']) ? (int) $_POST['remaining_semesters'] : 0;
    $country = isset($_POST['country']) ? trim($_POST['country']) : 'BR'; // Valor padrão: Brasil
    
    // Validación básica
    if ($gpa < 0 || $gpa > 4.00) {
        $errores[] = 'El GPA debe estar entre 0 y 4.00';
    }
    
    if (empty($university)) {
        $errores[] = 'Debe seleccionar una universidad';
    }
    
    if (empty($course)) {
        $errores[] = 'Debe seleccionar un curso';
    }
    
    if ($semester_cost <= 0) {
        $errores[] = 'El costo del semestre debe ser mayor que cero';
    }
    
    if ($expected_salary <= 0) {
        $errores[] = 'El salario esperado debe ser mayor que cero';
    }
    
    if ($remaining_semesters <= 0) {
        $errores[] = 'Los semestres restantes deben ser mayor que cero';
    }
    
    if (!in_array($country, ['BR', 'US', 'MX'])) {
        $errores[] = 'El país debe ser Brasil (BR), Estados Unidos (US) o México (MX)';
    }
    
    // Buscar admin configurations para obtener el umbral de ganancia
    $profit_threshold = 0.05; // Valor predeterminado
    
    // Si no hay errores, calcular opciones de préstamo
    if (empty($errores)) {
        $student_data = [
            'gpa' => $gpa,
            'university' => $university,
            'course' => $course,
            'semester_cost' => $semester_cost,
            'expected_salary' => $expected_salary,
            'remaining_semesters' => $remaining_semesters,
            'country' => $country, // Agregar el país al cálculo
            'profit_threshold' => $profit_threshold // Usando el valor predeterminado
        ];
        
        $opciones_prestamo = calcular_opciones_prestamo($student_data);
        $processed = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Empréstimos - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2755a8;
            border-color: #2755a8;
        }
        
        .loan-option {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .loan-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .option-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 10px;
            border-radius: 8px 8px 0 0;
        }
        
        .option-body {
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">
                            <i class="bi bi-calculator me-2"></i> 
                            Calculadora de Empréstimo Estudantil
                        </h2>
                        <a href="index.html" class="btn btn-light btn-sm">
                            <i class="bi bi-house-fill"></i> Início
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errores)): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Erro!</h5>
                                <ul class="mb-0">
                                    <?php foreach($errores as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($processed && isset($opciones_prestamo['error'])): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Erro no cálculo!</h5>
                                <p><?php echo $opciones_prestamo['error']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$processed || !empty($errores) || isset($opciones_prestamo['error'])): ?>
                            <form method="post" action="" class="row g-3">
                                <!-- Universidad (dropdown) -->
                                <div class="col-md-6">
                                    <label for="university" class="form-label">Universidade</label>
                                    <select class="form-select" id="university" name="university" required>
                                        <option value="" disabled <?php echo empty($universidade_id) ? 'selected' : ''; ?>>Selecione uma universidade</option>
                                        <?php foreach ($universidades as $uni): ?>
                                            <option value="<?php echo $uni['nome']; ?>" 
                                                    data-id="<?php echo $uni['id']; ?>"
                                                    <?php echo $universidade_id == $uni['id'] ? 'selected' : ''; ?>>
                                                <?php echo $uni['nome']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Curso (dropdown) -->
                                <div class="col-md-6">
                                    <label for="course" class="form-label">Curso</label>
                                    <select class="form-select" id="course" name="course" required>
                                        <option value="" disabled selected>Selecione primeiro uma universidade</option>
                                        <?php if (!empty($cursos)): ?>
                                            <?php foreach ($cursos as $curso): ?>
                                                <option value="<?php echo $curso['nome_curso']; ?>"
                                                        data-id="<?php echo $curso['id']; ?>"
                                                        data-cost="<?php echo $curso['custo_semestre']; ?>"
                                                        data-salary="<?php echo $curso['salario_esperado']; ?>"
                                                        <?php echo $curso_id == $curso['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $curso['nome_curso']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <!-- GPA -->
                                <div class="col-md-4">
                                    <label for="gpa" class="form-label">GPA Atual (0-4.00)</label>
                                    <input type="number" step="0.01" min="0" max="4.00" class="form-control" id="gpa" name="gpa" value="<?php echo $gpa_atual ?? ''; ?>" required>
                                </div>
                                
                                <!-- País do estudante -->
                                <div class="col-md-4">
                                    <label for="country" class="form-label">País</label>
                                    <select class="form-select" id="country" name="country" required>
                                        <option value="BR" <?php echo ($nacionalidade ?? '') == 'BR' ? 'selected' : ''; ?>>Brasil (BR)</option>
                                        <option value="MX" <?php echo ($nacionalidade ?? '') == 'MX' ? 'selected' : ''; ?>>México (MX)</option>
                                        <option value="US" <?php echo ($nacionalidade ?? '') == 'US' ? 'selected' : ''; ?>>Estados Unidos (US)</option>
                                    </select>
                                </div>
                                
                                <!-- Custo por semestre - autopreenchido -->
                                <div class="col-md-4">
                                    <label for="semester_cost" class="form-label">Custo do Semestre (USD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="semester_cost" name="semester_cost" readonly>
                                </div>
                                
                                <!-- Salario esperado - autopreenchido -->
                                <div class="col-md-4">
                                    <label for="expected_salary" class="form-label">Salário Esperado (USD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="expected_salary" name="expected_salary" readonly>
                                </div>
                                
                                <!-- Semestres restantes -->
                                <div class="col-md-12">
                                    <label for="remaining_semesters" class="form-label">Semestres Restantes</label>
                                    <input type="number" min="1" max="12" class="form-control" id="remaining_semesters" name="remaining_semesters" required>
                                </div>
                                
                                <!-- Botão para calcular -->
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="bi bi-calculator-fill me-2"></i>
                                        Calcular Opções de Empréstimo
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($processed && !isset($opciones_prestamo['error'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-success">
                                        <h4 class="alert-heading">Simulação Realizada!</h4>
                                        <p>Analisamos seu perfil e encontramos as seguintes opções de financiamento para você:</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row row-cols-1 row-cols-md-3 g-4">
                                <?php foreach ($opciones_prestamo['options'] as $index => $option): ?>
                                    <div class="col">
                                        <div class="card h-100 loan-option">
                                            <div class="option-header text-center">
                                                <h4 class="mb-0">Opção <?php echo $index + 1; ?></h4>
                                            </div>
                                            <div class="option-body">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Valor do Empréstimo:</span>
                                                        <strong>USD$ <?php echo number_format($option['loan_amount'], 2, ',', '.'); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Taxa de Juros:</span>
                                                        <strong><?php echo number_format($option['interest_rate'] * 100, 2); ?>%</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Prazo de Pagamento:</span>
                                                        <strong><?php echo $option['term_years']; ?> anos</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Pagamento Mensal:</span>
                                                        <strong>USD$ <?php echo number_format($option['monthly_payment'], 2, ',', '.'); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Total a Pagar:</span>
                                                        <strong>USD$ <?php echo number_format($option['total_payment'], 2, ',', '.'); ?></strong>
                                                    </li>
                                                </ul>
                                                <div class="d-grid gap-2 mt-3">
                                                    <button class="btn btn-primary">Solicitar Este Empréstimo</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-grid gap-2">
                                        <button onclick="window.location.reload()" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-repeat me-2"></i> Nova Simulação
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted text-center">
                        <small>EduBridge &copy; <?php echo date('Y'); ?> - Calculadora de Empréstimo Estudantil</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para carregar cursos quando a universidade for selecionada
        document.getElementById('university').addEventListener('change', function() {
            const universityId = this.options[this.selectedIndex].getAttribute('data-id');
            
            // Limpar o dropdown de cursos
            const courseSelect = document.getElementById('course');
            courseSelect.innerHTML = '<option value="" disabled selected>Carregando cursos...</option>';
            
            // Fazer uma requisição AJAX para buscar os cursos
            fetch('api_universidades_cursos.php?universidade_id=' + universityId)
                .then(response => response.json())
                .then(data => {
                    // Limpar e adicionar os novos cursos
                    courseSelect.innerHTML = '<option value="" disabled selected>Selecione um curso</option>';
                    
                    data.forEach(curso => {
                        const option = document.createElement('option');
                        option.value = curso.nome_curso;
                        option.textContent = curso.nome_curso;
                        option.setAttribute('data-id', curso.id);
                        option.setAttribute('data-cost', curso.custo_semestre);
                        option.setAttribute('data-salary', curso.salario_esperado);
                        courseSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar cursos:', error);
                    courseSelect.innerHTML = '<option value="" disabled selected>Erro ao carregar cursos</option>';
                });
        });
        
        // Função para atualizar os campos de custo e salário quando um curso for selecionado
        document.getElementById('course').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const semesterCost = selectedOption.getAttribute('data-cost');
            const expectedSalary = selectedOption.getAttribute('data-salary');
            
            document.getElementById('semester_cost').value = semesterCost;
            document.getElementById('expected_salary').value = expectedSalary;
        });
        
        // Inicializar os campos se já houver um curso selecionado
        window.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course');
            if (courseSelect.selectedIndex > 0) {
                const selectedOption = courseSelect.options[courseSelect.selectedIndex];
                const semesterCost = selectedOption.getAttribute('data-cost');
                const expectedSalary = selectedOption.getAttribute('data-salary');
                
                document.getElementById('semester_cost').value = semesterCost;
                document.getElementById('expected_salary').value = expectedSalary;
            }
        });
    </script>
</body>
</html>