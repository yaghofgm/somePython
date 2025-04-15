<?php
// Configurações de erro para desenvolvimento - TEMPORÁRIO PARA DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações do banco de dados
$host = "localhost";
$usuario_bd = "root"; // alterar em produção
$senha_bd = ""; // alterar em produção
$banco = "portalusuarios";

// Cria uma conexão
$conn = new mysqli($host, $usuario_bd, $senha_bd, $banco);

// Configura o charset para UTF-8
$conn->set_charset("utf8mb4");

// Verifica a conexão
if ($conn->connect_error) {
    // Log do erro
    error_log("Falha na conexão com o banco de dados: " . $conn->connect_error);
    
    // Exibir erro detalhado para debug
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// Função para executar queries SELECT com preparação segura
function select($sql, $types = null, $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Erro na preparação da query: " . $conn->error);
        return false;
    }
    
    // Bind parameters se existirem
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Executa a query
    if (!$stmt->execute()) {
        error_log("Erro na execução da query: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

// Função para executar queries de INSERT, UPDATE, DELETE
function execute($sql, $types = null, $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Erro na preparação da query: " . $conn->error);
        return false;
    }
    
    // Bind parameters se existirem
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Executa a query
    if (!$stmt->execute()) {
        error_log("Erro na execução da query: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $affected_rows = $stmt->affected_rows;
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return [
        'affected_rows' => $affected_rows,
        'insert_id' => $insert_id
    ];
}
?>
