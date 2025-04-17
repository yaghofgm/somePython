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

// Obtém o ID do usuário logado
$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$tipo_mensagem = '';

// Processar o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação e sanitização dos dados
    $universidade_id = filter_input(INPUT_POST, 'universidade_id', FILTER_SANITIZE_NUMBER_INT);
    $curso_id = filter_input(INPUT_POST, 'curso_id', FILTER_SANITIZE_NUMBER_INT);
    $ano_ingresso = filter_input(INPUT_POST, 'ano_ingresso', FILTER_SANITIZE_NUMBER_INT);
    $semestre_atual = filter_input(INPUT_POST, 'semestre_atual', FILTER_SANITIZE_NUMBER_INT);
    $gpa = filter_input(INPUT_POST, 'gpa', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $linkedin = filter_input(INPUT_POST, 'linkedin', FILTER_SANITIZE_URL);
    
    // Formatando URL do LinkedIn se fornecido
    if (!empty($linkedin) && !preg_match('/^https?:\/\//', $linkedin)) {
        $linkedin = 'https://www.linkedin.com/in/' . $linkedin;
    }
    
    // Log inicial para depuração
    error_log("POST recebido: " . print_r($_POST, true));
    error_log("FILES recebido: " . print_r($_FILES, true));
    
    // Validar GPA (entre 0 e 4)
    if ($gpa < 0 || $gpa > 4) {
        $mensagem = "O GPA deve estar entre 0 e 4.";
        $tipo_mensagem = "danger";
    } else {
        // Verificar se o perfil já existe
        $sql_check = "SELECT usuario_id FROM perfil_estudante WHERE usuario_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $usuario_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $stmt_check->close();

        if ($result_check->num_rows > 0) {
            // Atualizar perfil existente
            $sql = "UPDATE perfil_estudante SET 
                    universidade_id = ?,
                    curso_id = ?,
                    ano_ingresso = ?,
                    semestre_atual = ?,
                    gpa = ?,
                    linkedin = ?
                    WHERE usuario_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiidssi", $universidade_id, $curso_id, $ano_ingresso, $semestre_atual, $gpa, $linkedin, $usuario_id);
        } else {
            // Inserir novo perfil
            $sql = "INSERT INTO perfil_estudante 
                    (usuario_id, universidade_id, curso_id, ano_ingresso, semestre_atual, gpa, linkedin) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiidssi", $usuario_id, $universidade_id, $curso_id, $ano_ingresso, $semestre_atual, $gpa, $linkedin);
        }

        // Upload do CV se fornecido
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK && $_FILES['cv']['size'] > 0) {
            // Debug - registrar informações do arquivo
            error_log("Tentativa de upload: " . print_r($_FILES['cv'], true));
            
            // Define o diretório de upload com caminho absoluto seguro
            $upload_dir = 'uploads/cv/';
            
            // Garantir que os diretórios de upload existam
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
                error_log("Criando diretório uploads");
            }
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                error_log("Criando diretório $upload_dir");
            }
            
            // Verificar permissões de escrita nos diretórios
            if (!is_writable('uploads') || !is_writable($upload_dir)) {
                error_log("ERRO: Diretórios não têm permissão de escrita");
                $mensagem .= " Erro: diretório de upload sem permissão de escrita.";
                $tipo_mensagem = "danger";
                
                // Tentar corrigir as permissões
                chmod('uploads', 0777);
                chmod($upload_dir, 0777);
                error_log("Tentativa de correção de permissões aplicada");
            }
            
            if (empty($tipo_mensagem)) {
                // Gerar nome de arquivo único
                $file_extension = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
                $file_name = 'cv_' . $usuario_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                error_log("Tentando salvar arquivo em: " . $target_file);
                
                // Verificar tipo de arquivo (permitir apenas PDF e DOCX)
                $allowed_extensions = ['pdf', 'docx'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    // Verificação adicional de MIME type
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($_FILES['cv']['tmp_name']);
                    error_log("MIME type do arquivo: " . $mime_type);
                    
                    $allowed_mimes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    
                    if (in_array($mime_type, $allowed_mimes) || $mime_type == 'application/octet-stream') {
                        if (move_uploaded_file($_FILES['cv']['tmp_name'], $target_file)) {
                            error_log("Arquivo salvo com sucesso em: " . $target_file);
                            
                            // Armazenar o caminho correto para o banco de dados (caminho relativo)
                            $cv_path = $target_file;
                            
                            // Atualizar o caminho do CV no banco de dados
                            $sql_cv = "UPDATE perfil_estudante SET cv_path = ? WHERE usuario_id = ?";
                            $stmt_cv = $conn->prepare($sql_cv);
                            $stmt_cv->bind_param("si", $cv_path, $usuario_id);
                            
                            if ($stmt_cv->execute()) {
                                error_log("CV path atualizado no banco de dados: " . $cv_path);
                                $mensagem .= " Currículo enviado com sucesso!";
                            } else {
                                error_log("Erro ao atualizar CV path: " . $conn->error);
                                $mensagem .= " Erro ao salvar currículo no banco de dados.";
                                $tipo_mensagem = "danger";
                            }
                            
                            $stmt_cv->close();
                        } else {
                            error_log("Falha ao mover arquivo: " . $_FILES['cv']['tmp_name'] . " para " . $target_file);
                            error_log("Detalhes: tmp_name existe: " . (file_exists($_FILES['cv']['tmp_name']) ? "Sim" : "Não"));
                            error_log("Detalhes: upload_dir é gravável: " . (is_writable($upload_dir) ? "Sim" : "Não"));
                            error_log("Permissões do diretório: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
                            
                            $mensagem .= " Não foi possível fazer o upload do arquivo. Erro de permissão ou caminho inválido.";
                            $tipo_mensagem = "danger";
                        }
                    } else {
                        error_log("Tipo MIME não permitido: " . $mime_type);
                        $mensagem .= " Tipo de arquivo não permitido. Por favor, envie apenas PDF ou DOCX.";
                        $tipo_mensagem = "danger";
                    }
                } else {
                    error_log("Extensão de arquivo não permitida: " . $file_extension);
                    $mensagem .= " Tipo de arquivo não permitido. Por favor, envie apenas PDF ou DOCX.";
                    $tipo_mensagem = "danger";
                }
            }
        }

        if ($stmt->execute()) {
            if (empty($mensagem)) {
                $mensagem = "Perfil atualizado com sucesso!";
                $tipo_mensagem = "success";
            } else if (empty($tipo_mensagem)) {
                $tipo_mensagem = "success";
            }
        } else {
            $mensagem = "Erro ao atualizar o perfil: " . $conn->error;
            $tipo_mensagem = "danger";
        }
        $stmt->close();
    }
}

// Obter dados do perfil do estudante junto com nomes da universidade e curso
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

// Verificar se o estudante já tem um CV (movido para cima antes de fechar a conexão)
$sql_cv = "SELECT cv_path FROM perfil_estudante WHERE usuario_id = ?";
$stmt_cv = $conn->prepare($sql_cv);
$stmt_cv->bind_param("i", $usuario_id);
$stmt_cv->execute();
$result_cv = $stmt_cv->get_result();
$row_cv = $result_cv->fetch_assoc();
$cv_atual = !empty($row_cv['cv_path']) ? $row_cv['cv_path'] : '';
$stmt_cv->close();

// Fechar conexão
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Academic Profile - EduBridge</title>
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
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
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
                        <a class="nav-link" href="painel_estudante.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-person"></i> My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-cash-coin"></i> Financing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-mortarboard"></i> My Course</a>
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
                    <h2 class="mb-0">Edit Academic Profile</h2>
                    <a href="painel_estudante.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                
                <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="form-section">
                                <h4 class="form-section-title">Academic Information</h4>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="universidade" class="form-label">University</label>
                                        <select class="form-select" id="universidade" name="universidade_id" required>
                                            <option value="" selected disabled>Select your university</option>
                                            <!-- Options will be loaded via AJAX -->
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="curso" class="form-label">Course</label>
                                        <select class="form-select" id="curso" name="curso_id" required disabled>
                                            <option value="" selected disabled>First select a university</option>
                                            <!-- Options will be loaded via AJAX after selecting a university -->
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ano_ingresso" class="form-label">Enrollment Year</label>
                                        <select class="form-select" id="ano_ingresso" name="ano_ingresso" required>
                                            <option value="" disabled <?php echo empty($perfil['ano_ingresso']) ? 'selected' : ''; ?>>Select a year</option>
                                            <?php 
                                            $ano_atual = date('Y');
                                            for ($ano = $ano_atual; $ano >= $ano_atual - 10; $ano--) {
                                                $selected = (isset($perfil['ano_ingresso']) && $perfil['ano_ingresso'] == $ano) ? 'selected' : '';
                                                echo "<option value=\"$ano\" $selected>$ano</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="semestre_atual" class="form-label">Current Semester</label>
                                        <select class="form-select" id="semestre_atual" name="semestre_atual" required>
                                            <option value="" disabled <?php echo empty($perfil['semestre_atual']) ? 'selected' : ''; ?>>Select a semester</option>
                                            <?php 
                                            for ($sem = 1; $sem <= 12; $sem++) {
                                                $selected = (isset($perfil['semestre_atual']) && $perfil['semestre_atual'] == $sem) ? 'selected' : '';
                                                echo "<option value=\"$sem\" $selected>$sem";
                                                echo $sem == 1 ? "st" : ($sem == 2 ? "nd" : ($sem == 3 ? "rd" : "th"));
                                                echo " semester</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gpa" class="form-label">GPA (Grade Point Average)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gpa" name="gpa" step="0.01" min="0" max="4" 
                                               value="<?php echo htmlspecialchars($perfil['gpa'] ?? ''); ?>" required>
                                        <span class="input-group-text">/ 4.00</span>
                                    </div>
                                    <div class="form-text">Enter your GPA on a scale of 0 to 4.</div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h4 class="form-section-title">Curriculum & Professional Links</h4>
                                
                                <!-- CV Upload Section -->
                                <div class="form-group">
                                    <label for="cv">Curriculum/Resume (PDF or DOCX):</label>
                                    <input type="file" class="form-control" id="cv" name="cv">
                                    <?php
                                    if (!empty($cv_atual)) {
                                        echo '<div class="mt-2">';
                                        echo '<p>Current resume: <a href="' . htmlspecialchars($cv_atual) . '" target="_blank">View resume</a> ';
                                        echo '<a href="remover_cv.php?id=' . $usuario_id . '" class="btn btn-sm btn-danger">Remove</a></p>';
                                        echo '</div>';
                                    }
                                    ?>
                                    <small class="form-text text-muted">Upload your updated resume. File must be in PDF or DOCX format.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="linkedin" class="form-label">LinkedIn Profile</label>
                                    <div class="input-group">
                                        <span class="input-group-text">linkedin.com/in/</span>
                                        <input type="text" class="form-control" id="linkedin" name="linkedin" 
                                               placeholder="your-username" 
                                               value="<?php echo str_replace('https://www.linkedin.com/in/', '', $perfil['linkedin'] ?? ''); ?>">
                                    </div>
                                    <div class="form-text">Enter only your LinkedIn username.</div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="painel_estudante.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <i class="bi bi-info-circle me-2"></i>Why Complete Your Profile?
                    </div>
                    <div class="card-body">
                        <p>Keeping your academic profile complete and up-to-date significantly increases your chances of securing funding on the EduBridge platform. Investors are looking for students with:</p>
                        
                        <ul>
                            <li>Complete and verifiable academic information</li>
                            <li>Solid academic record (GPA)</li>
                            <li>Well-structured professional resume</li>
                            <li>Professional online presence (LinkedIn)</li>
                        </ul>
                        
                        <p class="mb-0">The more complete your profile is, the better your ranking will be in the matching algorithms with investors.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const universidadeSelect = document.getElementById('universidade');
        const cursoSelect = document.getElementById('curso');
        let universidadeSelecionada = '<?php echo $perfil['universidade_id'] ?? ''; ?>';
        let cursoSelecionado = '<?php echo $perfil['curso_id'] ?? ''; ?>';
        
        // Load universities on page load
        loadUniversities();
        
        // Set up event to update courses when university changes
        universidadeSelect.addEventListener('change', function() {
            const universidadeId = this.value;
            if (universidadeId) {
                cursoSelect.disabled = false;
                loadCourses(universidadeId);
            } else {
                cursoSelect.disabled = true;
                cursoSelect.innerHTML = '<option value="" selected disabled>First select a university</option>';
            }
        });
        
        // Function to load all universities
        function loadUniversities() {
            fetch('api_universidades_cursos.php?action=get_universidades')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        universidadeSelect.innerHTML = '<option value="" selected disabled>Select your university</option>';
                        data.data.forEach(univ => {
                            const option = document.createElement('option');
                            option.value = univ.id;
                            option.textContent = univ.nome;
                            
                            // If a university is already selected, mark it
                            if (univ.id == universidadeSelecionada) {
                                option.selected = true;
                            }
                            
                            universidadeSelect.appendChild(option);
                        });
                        
                        // If a university is selected, load its courses
                        if (universidadeSelecionada) {
                            cursoSelect.disabled = false;
                            loadCourses(universidadeSelecionada);
                        }
                    } else {
                        console.error('Error loading universities:', data.message);
                    }
                })
                .catch(error => console.error('Request error:', error));
        }
        
        // Function to load courses for a specific university
        function loadCourses(universidadeId) {
            fetch(`api_universidades_cursos.php?action=get_cursos&universidade_id=${universidadeId}`)
                .then(response => response.json())
                .then(data => {
                    cursoSelect.innerHTML = '<option value="" selected disabled>Select your course</option>';
                    
                    if (data.success) {
                        data.data.forEach(curso => {
                            const option = document.createElement('option');
                            option.value = curso.id;
                            option.textContent = curso.nome_curso;
                            
                            // If a course is already selected, mark it
                            if (curso.id == cursoSelecionado) {
                                option.selected = true;
                            }
                            
                            cursoSelect.appendChild(option);
                        });
                    } else {
                        console.warn('No courses found for this university:', data.message);
                    }
                })
                .catch(error => console.error('Request error:', error));
        }
    });
    </script>
</body>
</html>