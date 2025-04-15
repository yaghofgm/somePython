<?php
session_start();

// Inclui conexão com o banco de dados para registro de log
include 'conexao.php';

// Se houver um usuário logado, registra o logout no log
if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Registra o logout no log
    $log_sql = "INSERT INTO usuarios_logs (usuario_id, acao, ip, user_agent, detalhes) VALUES (?, 'logout', ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    
    if ($log_stmt) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $detalhes = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id()
        ]);
        
        $log_stmt->bind_param("isss", $usuario_id, $ip, $user_agent, $detalhes);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    $conn->close();
}

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

// Redireciona para a página inicial
header("Location: index.html");
exit();
?>

