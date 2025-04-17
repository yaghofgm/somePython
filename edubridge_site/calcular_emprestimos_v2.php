<?php
/**
 * Loan Calculator v2
 * This version uses the new simplified Python script
 */

session_start();
include 'conexao.php';

// Check if the user is logged in and is a student
$usuario_logado = isset($_SESSION['usuario_id']);
$estudante_id = $usuario_logado && $_SESSION['usuario_categoria'] == 'estudante' ? $_SESSION['usuario_id'] : null;

// Get student data if logged in
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

// Get all universities
$sql_universidades = "SELECT id, nome FROM perfil_universidade ORDER BY nome";
$result_universidades = $conn->query($sql_universidades);
$universidades = [];

if ($result_universidades && $result_universidades->num_rows > 0) {
    while ($row = $result_universidades->fetch_assoc()) {
        $universidades[] = $row;
    }
}

// Get courses from selected university
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

// Configuration
$python_script = __DIR__ . '/site_calculateLoan.py';
$python_executable = 'python3';

/**
 * Function to calculate loan options using the Python script
 * @param array $student_data Student data
 * @return array Loan options
 */
function calcular_opciones_prestamo($student_data) {
    global $python_script, $python_executable;
    
    // Validate required minimum data
    if (!isset($student_data['gpa']) || !isset($student_data['university']) || !isset($student_data['course'])) {
        return ['error' => 'Incomplete student data'];
    }
    
    // Prepare JSON input for the script
    $input_json = json_encode($student_data);
    
    // Configure process execution
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];
    
    // Execute the Python script
    $process = proc_open("$python_executable $python_script", $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return ['error' => 'Error starting Python process'];
    }
    
    // Send data to the script
    fwrite($pipes[0], $input_json);
    fclose($pipes[0]);
    
    // Read script output
    $output = stream_get_contents($pipes[1]);
    $error_output = stream_get_contents($pipes[2]);
    
    // Close pipes and process
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    
    // Check for errors
    if ($exit_code !== 0 || !empty($error_output)) {
        return ['error' => "Execution error ($exit_code): $error_output"];
    }
    
    // Decode JSON output
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Error decoding response: ' . json_last_error_msg()];
    }
    
    return $result;
}

// Process form if submitted
$opciones_prestamo = [];
$errores = [];
$processed = false;

// DO NOT DELETE THIS UNDER ANY CIRCUMSTANCES
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $gpa = isset($_POST['gpa']) ? (float) $_POST['gpa'] : 0;
//     $university = isset($_POST['university']) ? trim($_POST['university']) : '';
//     $course = isset($_POST['course']) ? trim($_POST['course']) : '';
//     $semester_cost = isset($_POST['semester_cost']) ? (float) $_POST['semester_cost'] : 0;
//     $expected_salary = isset($_POST['expected_salary']) ? (float) $_POST['expected_salary'] : 0;
//     $remaining_semesters = isset($_POST['remaining_semesters']) ? (int) $_POST['remaining_semesters'] : 0;
//     $country = isset($_POST['country']) ? trim($_POST['country']) : 'BR'; // Default value: Brazil
    
//     // Basic validation
//     if ($gpa < 0 || $gpa > 4.00) {
//         $errores[] = 'GPA must be between 0 and 4.00';
//     }
    
//     if (empty($university)) {
//         $errores[] = 'You must select a university';
//     }
    
//     if (empty($course)) {
//         $errores[] = 'You must select a course';
//     }
    
//     if ($semester_cost <= 0) {
//         $errores[] = 'Semester cost must be greater than zero';
//     }
    
//     if ($expected_salary <= 0) {
//         $errores[] = 'Expected salary must be greater than zero';
//     }
    
//     if ($remaining_semesters <= 0) {
//         $errores[] = 'Remaining semesters must be greater than zero';
//     }
    
//     if (!in_array($country, ['BR', 'US', 'MX'])) {
//         $errores[] = 'Country must be Brazil (BR), United States (US), or Mexico (MX)';
//     }
    
//     // Fetch admin configurations to get the profit threshold
//     $profit_threshold = 0.05; // Default value
    
//     // If no errors, calculate loan options
//     if (empty($errores)) {
//         $student_data = [
//             'gpa' => $gpa,
//             'university' => $university,
//             'course' => $course,
//             'semester_cost' => $semester_cost,
//             'expected_salary' => $expected_salary,
//             'remaining_semesters' => $remaining_semesters,
//             'country' => $country, // Add country to calculation
//             'profit_threshold' => $profit_threshold // Using default value
//         ];
        
//         $opciones_prestamo = calcular_opciones_prestamo($student_data);
//         $processed = true;
//     }
// }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION["usuario_id"])) {
        echo "<p style='color: red;'>You need to be logged in to see your loan options.</p>";
        exit;
    }

    $student_id = $_SESSION["usuario_id"];
    $input = json_encode(["student_id" => $student_id]);

    // Use the same approach that worked with the test script
    $loanScript = __DIR__ . "/site_calculateLoan_v3_FINAL.py";
    
    // Configure pipes for process communication
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];
    
    // Execute the loan script
    $process = proc_open("/usr/bin/python3 " . $loanScript, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        echo "<p style='color:red;'>❌ Error starting the process.</p>";
        return;
    }
    
    // Send JSON data to the script
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    
    // Read output and errors
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    // Close the process
    $exitCode = proc_close($process);
    
    // Show debug details (remove in production)
    // echo "<pre><strong>DEBUG: Python Output:</strong>\n" . ($output ? htmlspecialchars($output) : 'No output') . "</pre>";
    
    if (!empty($error)) {
        echo "<pre><strong>DEBUG: Python Errors:</strong>\n" . htmlspecialchars($error) . "</pre>";
        echo "<p style='color:red;'>❌ An error occurred while processing the loan.</p>";
        return;
    }
    
    if ($exitCode !== 0) {
        echo "<p style='color:red;'>❌ Script failed with exit code: $exitCode</p>";
        return;
    }

    // Try to decode the returned JSON
    $result = json_decode($output, true);
    if ($result === null) {
        echo "<p style='color:red;'>❌ Error interpreting the returned JSON.</p>";
        return;
    }

    // If the JSON has an error
    if (isset($result["error"])) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($result["error"]) . "</p>";
        return;
    } 
    
    // Store options for display in the results section
    if (isset($result["options"]) && is_array($result["options"])) {
        // Desired order of options
        $ordem_desejada = ["Opção leve", "Opção equilibrada", "Opção intensa", "Opção econômica"];
        $english_labels = [
            "Opção leve" => "Light Option",
            "Opção equilibrada" => "Balanced Option",
            "Opção intensa" => "Intense Option",
            "Opção econômica" => "Economic Option"
        ];
        
        // Sort options according to desired order
        $opcoes_ordenadas = [];
        
        // First create associative array to facilitate sorting
        $opcoes_por_label = [];
        foreach ($result["options"] as $option) {
            // Translate the label to English
            if (isset($english_labels[$option['label']])) {
                $option['label'] = $english_labels[$option['label']];
            }
            $opcoes_por_label[$option['label']] = $option;
        }
        
        // Sort according to the desired order
        foreach ($ordem_desejada as $label) {
            if (isset($opcoes_por_label[$english_labels[$label]])) {
                $opcoes_ordenadas[] = $opcoes_por_label[$english_labels[$label]];
            }
        }
        
        // Add any options that weren't included in the sorting
        foreach ($result["options"] as $option) {
            if (isset($english_labels[$option['label']])) {
                $option['label'] = $english_labels[$option['label']];
            }
            
            $found = false;
            foreach ($opcoes_ordenadas as $sorted_option) {
                if ($sorted_option['label'] === $option['label']) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $opcoes_ordenadas[] = $option;
            }
        }
        
        // Replace original options with sorted ones
        $result["options"] = $opcoes_ordenadas;
        $opciones_prestamo = $result;
        $processed = true;
    } else {
        echo "<p style='color:red;'>❌ No options received. Check if the script returned valid data.</p>";
        return;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Loan Calculator - EduBridge</title>
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
                            Student Loan Calculator
                        </h2>
                        <a href="index.html" class="btn btn-light btn-sm">
                            <i class="bi bi-house-fill"></i> Home
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errores)): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Error!</h5>
                                <ul class="mb-0">
                                    <?php foreach($errores as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($processed && isset($opciones_prestamo['error'])): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Calculation Error!</h5>
                                <p><?php echo $opciones_prestamo['error']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$processed || !empty($errores) || isset($opciones_prestamo['error'])): ?>
                            <form method="post" action="" class="row g-3">
                                <!-- University (dropdown) -->
                                <div class="col-md-6">
                                    <label for="university" class="form-label">University</label>
                                    <select class="form-select" id="university" name="university" required>
                                        <option value="" disabled <?php echo empty($universidade_id) ? 'selected' : ''; ?>>Select a university</option>
                                        <?php foreach ($universidades as $uni): ?>
                                            <option value="<?php echo $uni['nome']; ?>" 
                                                    data-id="<?php echo $uni['id']; ?>"
                                                    <?php echo $universidade_id == $uni['id'] ? 'selected' : ''; ?>>
                                                <?php echo $uni['nome']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Course (dropdown) -->
                                <div class="col-md-6">
                                    <label for="course" class="form-label">Course</label>
                                    <select class="form-select" id="course" name="course" required>
                                        <option value="" disabled selected>Select a university first</option>
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
                                    <label for="gpa" class="form-label">Current GPA (0-4.00)</label>
                                    <input type="number" step="0.01" min="0" max="4.00" class="form-control" id="gpa" name="gpa" value="<?php echo $gpa_atual ?? ''; ?>" required>
                                </div>
                                
                                <!-- Country -->
                                <div class="col-md-4">
                                    <label for="country" class="form-label">Country</label>
                                    <select class="form-select" id="country" name="country" required>
                                        <option value="BR" <?php echo ($nacionalidade ?? '') == 'BR' ? 'selected' : ''; ?>>Brazil (BR)</option>
                                        <option value="MX" <?php echo ($nacionalidade ?? '') == 'MX' ? 'selected' : ''; ?>>Mexico (MX)</option>
                                        <option value="US" <?php echo ($nacionalidade ?? '') == 'US' ? 'selected' : ''; ?>>United States (US)</option>
                                    </select>
                                </div>
                                
                                <!-- Semester cost - auto-filled -->
                                <div class="col-md-4">
                                    <label for="semester_cost" class="form-label">Semester Cost (USD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="semester_cost" name="semester_cost" readonly>
                                </div>
                                
                                <!-- Expected salary - auto-filled -->
                                <div class="col-md-4">
                                    <label for="expected_salary" class="form-label">Expected Salary (USD$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="expected_salary" name="expected_salary" readonly>
                                </div>
                                
                                <!-- Remaining semesters -->
                                <div class="col-md-12">
                                    <label for="remaining_semesters" class="form-label">Remaining Semesters</label>
                                    <input type="number" min="1" max="12" class="form-control" id="remaining_semesters" name="remaining_semesters" required>
                                </div>
                                
                                <!-- Calculate button -->
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="bi bi-calculator-fill me-2"></i>
                                        Calculate Loan Options
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($processed && !isset($opciones_prestamo['error'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-success">
                                        <h4 class="alert-heading">Simulation Completed!</h4>
                                        <p>We analyzed your profile and found the following financing options for you:</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row row-cols-1 row-cols-md-3 g-4">
                                <?php foreach ($opciones_prestamo['options'] as $index => $option): ?>
                                    <div class="col">
                                        <div class="card h-100 loan-option">
                                            <div class="option-header text-center">
                                                <h4 class="mb-0"><?php echo $option['label']; ?></h4>
                                            </div>
                                            <div class="option-body">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Loan Amount:</span>
                                                        <strong>USD$ <?php echo number_format($option['loan_amount'], 2, '.', ','); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Interest Rate:</span>
                                                        <strong><?php echo number_format($option['interest_rate'] * 100, 2, '.', ','); ?>%</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Payment Term:</span>
                                                        <strong><?php echo $option['term_years']; ?> years</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Monthly Payment:</span>
                                                        <strong>USD$ <?php echo number_format($option['monthly_payment'], 2, '.', ','); ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Total Payment:</span>
                                                        <strong>USD$ <?php echo number_format($option['total_payment'], 2, '.', ','); ?></strong>
                                                    </li>
                                                </ul>
                                                <div class="d-grid gap-2 mt-3">
                                                    <button class="btn btn-primary">Apply for This Loan</button>
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
                                            <i class="bi bi-arrow-repeat me-2"></i> New Simulation
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted text-center">
                        <small>EduBridge &copy; <?php echo date('Y'); ?> - Student Loan Calculator</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to load courses when university is selected
        document.getElementById('university').addEventListener('change', function() {
            const universityId = this.options[this.selectedIndex].getAttribute('data-id');
            
            // Clear the course dropdown
            const courseSelect = document.getElementById('course');
            courseSelect.innerHTML = '<option value="" disabled selected>Loading courses...</option>';
            
            // Make an AJAX request to get the courses
            fetch('api_universidades_cursos.php?universidade_id=' + universityId)
                .then(response => response.json())
                .then(data => {
                    // Clear and add new courses
                    courseSelect.innerHTML = '<option value="" disabled selected>Select a course</option>';
                    
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
                    console.error('Error loading courses:', error);
                    courseSelect.innerHTML = '<option value="" disabled selected>Error loading courses</option>';
                });
        });
        
        // Function to update cost and salary fields when a course is selected
        document.getElementById('course').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const semesterCost = selectedOption.getAttribute('data-cost');
            const expectedSalary = selectedOption.getAttribute('data-salary');
            
            document.getElementById('semester_cost').value = semesterCost;
            document.getElementById('expected_salary').value = expectedSalary;
        });
        
        // Initialize fields if a course is already selected
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