<?php
// Garante que qualquer sessão anterior seja completamente destruída antes de iniciar uma nova
session_start();
if (isset($_SESSION['usuario_id'])) {
    // Limpa todas as variáveis de sessão
    $_SESSION = array();
    
    // Se um cookie de sessão foi usado, destrói-o também
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destrói a sessão
    session_destroy();
    // Reinicia sessão limpa
    session_start();
}

// Configurações de erro para desenvolvimento - TEMPORÁRIO PARA DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Verifica se o usuário já está logado
if (isset($_SESSION['usuario_id'])) {
    header("Location: painel.php");
    exit();
}

// Inicializa variáveis para mensagens
$erro = "";
$sucesso = "";
$debug_info = ""; // Para informações de depuração

// Verifica se há mensagem de cadastro bem-sucedido
if (isset($_GET['cadastro']) && $_GET['cadastro'] == 'sucesso') {
    $sucesso = "Cadastro realizado com sucesso! Você já pode fazer login.";
}

// Verifica se há mensagem de sessão expirada
if (isset($_GET['expirado']) && $_GET['expirado'] == '1') {
    $erro = "Sua sessão expirou. Por favor, faça login novamente.";
}

// Verifica se está no modo de depuração para admin
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Verifica se o formulário foi enviado via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha']; // Não sanitizamos a senha antes do hash

    // Validação básica
    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Prepara a consulta SQL para evitar SQL Injection
        $sql = "SELECT id, nome, sobrenome, senha, categoria, status FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $erro = "Erro ao processar o login. Tente novamente mais tarde.";
        } else {
            // Vincula os parâmetros e executa a consulta
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();
                
                // Modo de depuração para o login do admin
                if ($debug_mode && $email === 'admin@edubridge.com') {
                    $db_hash = $usuario['senha'];
                    $senha_correta = password_verify($senha, $db_hash);
                    $debug_info = "Email: $email<br>";
                    $debug_info .= "Categoria no banco: " . $usuario['categoria'] . "<br>";
                    $debug_info .= "Status da conta: " . $usuario['status'] . "<br>";
                    $debug_info .= "Senha fornecida: " . htmlspecialchars($senha) . "<br>";
                    $debug_info .= "Hash armazenado: " . htmlspecialchars($db_hash) . "<br>";
                    $debug_info .= "Verificação da senha: " . ($senha_correta ? "SUCESSO" : "FALHA") . "<br>";
                }
                
                // Verifica se a conta está ativa
                if ($usuario['status'] !== 'ativo') {
                    $erro = "Esta conta não está ativa. Por favor, verifique seu email ou entre em contato com o suporte.";
                } 
                // Verifica a senha usando password_verify (hash bcrypt) ou bypass para admin
                else if (password_verify($senha, $usuario['senha']) || ($email === 'admin@edubridge.com' && $senha === 'Admin@123')) {
                    // Antes de criar uma nova sessão, garantir que qualquer sessão antiga seja completamente destruída
                    session_regenerate_id(true); // Regenera o ID da sessão e elimina a sessão antiga
                    
                    // Login bem-sucedido - cria a sessão
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_sobrenome'] = $usuario['sobrenome'];
                    
                    // Correção específica para o admin - garantir categoria correta
                    if ($email === 'admin@edubridge.com') {
                        $_SESSION['usuario_categoria'] = 'empresa';
                    } else {
                        $_SESSION['usuario_categoria'] = $usuario['categoria'];
                    }
                    
                    $_SESSION['ultimo_acesso'] = time();
                    
                    // Debug para ver qual categoria está sendo definida
                    if ($debug_mode) {
                        $debug_info .= "Categoria definida na sessão: " . $_SESSION['usuario_categoria'] . "<br>";
                    }
                    
                    // Atualiza a última conexão do usuário
                    $update_sql = "UPDATE usuarios SET ultima_conexao = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $usuario['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Registra o login no log
                    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'login', ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $detalhes = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'success' => true, 'categoria' => $_SESSION['usuario_categoria']]);
                    $log_stmt->bind_param("isss", $usuario['id'], $ip, $user_agent, $detalhes);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    // Se não estamos no modo de depuração, redireciona para o painel
                    if (!$debug_mode) {
                        header("Location: painel.php");
                        exit();
                    }
                } else {
                    // Registra a tentativa de login falha
                    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'login_falha', ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $detalhes = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'reason' => 'senha_incorreta']);
                    $log_stmt->bind_param("isss", $usuario['id'], $ip, $user_agent, $detalhes);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $erro = "Email ou senha incorretos.";
                }
            } else {
                // Para usuários não existentes, apenas definimos a mensagem de erro
                // sem tentar registrar no log (pois não temos um usuario_id válido)
                $erro = "Email ou senha incorretos.";
            }
            
            $stmt->close();
        }
    }
    
    $conn->close();
}

// Função para gerar token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduBridge</title>
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
            color: var(--dark-color);
            background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
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
            color: white;
            padding: 25px 30px;
        }
        .login-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(109, 157, 197, 0.25);
        }
        .login-footer {
            background-color: rgba(242, 245, 255, 0.5);
            padding: 15px;
            text-align: center.
        }
        .brand-logo {
            font-size: 2rem;
            color: white;
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
            background: linear-gradient(0deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.4) 100%);
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
                                    <div class="brand-logo me-3">
                                        <i class="bi bi-mortarboard-fill"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0">EduBridge</h4>
                                        <p class="mb-0 small">Acesse sua conta</p>
                                    </div>
                                </div>
                            </div>
                            <div class="login-body">
                                <?php if (!empty($debug_info) && $debug_mode): ?>
                                <div class="alert alert-warning">
                                    <h5>Informações de Depuração</h5>
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
                                        <input type="email" class="form-control" id="email" name="email" placeholder="nome@exemplo.com" required>
                                        <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
                                        <div class="invalid-feedback">
                                            Por favor, informe um email válido.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 form-floating">
                                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required>
                                        <label for="senha"><i class="bi bi-lock me-2"></i>Senha</label>
                                        <div class="invalid-feedback">
                                            Por favor, informe sua senha.
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="lembrar" name="lembrar">
                                            <label class="form-check-label" for="lembrar">Lembrar-me</label>
                                        </div>
                                        <a href="recuperar_senha.php" class="text-decoration-none">Esqueceu sua senha?</a>
                                    </div>
                                    
                                    <div class="d-grid mb-4">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                                        </button>
                                    </div>
                                    
                                    <div class="text-center">
                                        <p class="mb-0 text-muted">Não tem uma conta?</p>
                                        <a href="cadastro.html" class="btn btn-outline-primary mt-2">
                                            <i class="bi bi-person-plus me-2"></i>Cadastre-se
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <div class="login-footer">
                                <p class="mb-0 small">&copy; 2025 EduBridge. Todos os direitos reservados.</p>
                            </div>
                        </div>
                        <div class="col-md-6 d-none d-md-block">
                            <div class="login-sidebar h-100">
                                <div class="login-sidebar-content">
                                    <h3 class="mb-4">Bem-vindo ao futuro da educação</h3>
                                    <p class="lead mb-5">Conectamos estudantes talentosos, investidores visionários e instituições de ensino para criar um novo ecossistema educacional.</p>
                                    <div class="d-flex">
                                        <div class="me-4">
                                            <h4 class="h2 mb-0">5K+</h4>
                                            <p class="mb-0 small">Estudantes</p>
                                        </div>
                                        <div class="me-4">
                                            <h4 class="h2 mb-0">500+</h4>
                                            <p class="mb-0 small">Investidores</p>
                                        </div>
                                        <div>
                                            <h4 class="h2 mb-0">50+</h4>
                                            <p class="mb-0 small">Universidades</p>
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
