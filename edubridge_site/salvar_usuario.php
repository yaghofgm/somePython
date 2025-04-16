<?php
// Configurações de erro para produção
error_reporting(0);
ini_set('display_errors', 0);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Inicializa a resposta
$resposta = [
    'sucesso' => false,
    'mensagem' => '',
    'erros' => []
];

// Função para sanitizar inputs
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Verifica se o formulário foi enviado via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitiza e valida os dados do formulário
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $sobrenome = sanitizeInput($_POST['sobrenome'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $telefone = sanitizeInput($_POST['telefone'] ?? '');
    $categoria = sanitizeInput($_POST['categoria'] ?? '');
    $senha = $_POST['senha'] ?? ''; // Não sanitizamos a senha antes do hash
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    // Validação do nome
    if (empty($nome)) {
        $resposta['erros']['nome'] = "Nome é obrigatório.";
    } elseif (strlen($nome) < 2 || strlen($nome) > 100) {
        $resposta['erros']['nome'] = "Nome deve ter entre 2 e 100 caracteres.";
    }
    
    // Validação do sobrenome
    if (empty($sobrenome)) {
        $resposta['erros']['sobrenome'] = "Sobrenome é obrigatório.";
    } elseif (strlen($sobrenome) < 2 || strlen($sobrenome) > 100) {
        $resposta['erros']['sobrenome'] = "Sobrenome deve ter entre 2 e 100 caracteres.";
    }
    
    // Validação do email
    if (empty($email)) {
        $resposta['erros']['email'] = "Email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resposta['erros']['email'] = "Formato de email inválido.";
    }
    
    // Validação da categoria
    $categorias_validas = ['estudante', 'investidor', 'universidade', 'empresa'];
    if (empty($categoria)) {
        $resposta['erros']['categoria'] = "Categoria é obrigatória.";
    } elseif (!in_array($categoria, $categorias_validas)) {
        $resposta['erros']['categoria'] = "Categoria inválida.";
    }
    
    // Validação da senha
    if (empty($senha)) {
        $resposta['erros']['senha'] = "Senha é obrigatória.";
    } elseif (strlen($senha) < 8) {
        $resposta['erros']['senha'] = "A senha deve ter pelo menos 8 caracteres.";
    } elseif (!preg_match("/[A-Z]/", $senha)) {
        $resposta['erros']['senha'] = "A senha deve conter pelo menos uma letra maiúscula.";
    } elseif (!preg_match("/[a-z]/", $senha)) {
        $resposta['erros']['senha'] = "A senha deve conter pelo menos uma letra minúscula.";
    } elseif (!preg_match("/[0-9]/", $senha)) {
        $resposta['erros']['senha'] = "A senha deve conter pelo menos um número.";
    }
    
    // Verifica se as senhas conferem
    if ($senha !== $confirmar_senha) {
        $resposta['erros']['confirmar_senha'] = "As senhas não conferem.";
    }
    
    // Se não houver erros de validação, prossegue com o cadastro
    if (empty($resposta['erros'])) {
        try {
            // Verifica se o email já está cadastrado
            $check_sql = "SELECT id FROM usuarios WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $resposta['erros']['email'] = "Este email já está cadastrado.";
                $resposta['mensagem'] = "Este email já está cadastrado.";
            } else {
                // Hash seguro da senha usando Bcrypt
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                $status = 'ativo'; // Pode ser alterado para 'pendente' se quiser confirmação por email
                
                // Prepara a consulta SQL para inserir o novo usuário
                $sql = "INSERT INTO usuarios (nome, sobrenome, email, senha, telefone, categoria, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    throw new Exception("Erro ao preparar a consulta: " . $conn->error);
                }
                
                // Vincula os parâmetros e executa a consulta
                $stmt->bind_param("sssssss", $nome, $sobrenome, $email, $senha_hash, $telefone, $categoria, $status);
                
                if ($stmt->execute()) {
                    $usuario_id = $stmt->insert_id;
                    
                    // Procesos específicos por categoria de usuario
                    if ($categoria === 'estudante') {
                        // Crear entrada en perfil_estudante con valores iniciales
                        $perfil_sql = "INSERT INTO perfil_estudante (usuario_id) VALUES (?)";
                        $perfil_stmt = $conn->prepare($perfil_sql);
                        
                        if ($perfil_stmt) {
                            $perfil_stmt->bind_param("i", $usuario_id);
                            $perfil_stmt->execute();
                            $perfil_stmt->close();
                        } else {
                            error_log("Error al crear perfil de estudiante: " . $conn->error);
                        }
                    } else if ($categoria === 'universidade') {
                        // Crear entrada en perfil_universidade
                        $perfil_sql = "INSERT INTO perfil_universidade (usuario_id) VALUES (?)";
                        $perfil_stmt = $conn->prepare($perfil_sql);
                        
                        if ($perfil_stmt) {
                            $perfil_stmt->bind_param("i", $usuario_id);
                            $perfil_stmt->execute();
                            $perfil_stmt->close();
                        } else {
                            error_log("Error al crear perfil de universidad: " . $conn->error);
                        }
                    }
                    
                    // Registra o cadastro no log
                    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'cadastro', ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    
                    if ($log_stmt) {
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $detalhes = json_encode([
                            'categoria' => $categoria,
                            'timestamp' => date('Y-m-d H:i:s'),
                        ]);
                        
                        $log_stmt->bind_param("isss", $usuario_id, $ip, $user_agent, $detalhes);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $resposta['sucesso'] = true;
                    $resposta['mensagem'] = "Cadastro realizado com sucesso! Você já pode fazer login.";
                    
                    // Fecha a conexão com o banco de dados
                    $conn->close();
                    
                    // SOLUÇÃO: Redirecionamento direto do servidor em vez de resposta JSON
                    header("Location: login.php?cadastro=sucesso&email=" . urlencode($email));
                    exit(); // Termina a execução do script após o redirecionamento
                    
                } else {
                    throw new Exception("Erro ao cadastrar: " . $stmt->error);
                }
                
                $stmt->close();
            }
            
            $check_stmt->close();
            
        } catch (Exception $e) {
            $resposta['mensagem'] = "Erro ao processar o cadastro: " . $e->getMessage();
            
            // Log do erro no servidor
            error_log("Erro de cadastro: " . $e->getMessage());
        }
    } else {
        // Se houver erros de validação, retorna o primeiro erro como mensagem principal
        $resposta['mensagem'] = reset($resposta['erros']);
    }
    
    $conn->close();
} else {
    $resposta['mensagem'] = "Método de requisição inválido.";
}

// Se chegou até aqui (não houve redirecionamento), define cabeçalho JSON e retorna a resposta
header('Content-Type: application/json');
echo json_encode($resposta);
?>
