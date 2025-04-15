<?php
session_start();
// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php?expirado=1");
    exit();
}

// Obtém o ID do usuário
$usuario_id = $_SESSION['usuario_id'];

// Verifica se é um GET ou POST request
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica permissão de acesso (usuário deve estar removendo seu próprio CV)
    $id_solicitado = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['usuario_id']) ? $_POST['usuario_id'] : 0);
    
    if ($usuario_id != $id_solicitado) {
        $_SESSION['mensagem'] = "Operação não autorizada.";
        $_SESSION['tipo_mensagem'] = "danger";
        header("Location: editar_perfil_estudante.php");
        exit();
    }
    
    // Busca informações do CV no banco de dados
    $sql = "SELECT cv_path FROM perfil_estudante WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $perfil = $result->fetch_assoc();
    $stmt->close();
    
    // Se encontrou um CV no banco de dados
    if ($perfil && isset($perfil['cv_path']) && !empty($perfil['cv_path'])) {
        $cv_path = $perfil['cv_path'];
        error_log("Tentando remover CV: " . $cv_path);
        
        // Verifica se o arquivo existe fisicamente e tenta removê-lo
        if (file_exists($cv_path)) {
            if (unlink($cv_path)) {
                error_log("Arquivo removido com sucesso: " . $cv_path);
            } else {
                error_log("Falha ao remover arquivo: " . $cv_path . " - Erro: " . error_get_last()['message']);
            }
        } else {
            error_log("Arquivo não encontrado para remoção: " . $cv_path);
            // Tentar caminho alternativo (compatibilidade com versões anteriores)
            $alt_path = 'uploads/cv/' . basename($cv_path);
            if (file_exists($alt_path)) {
                if (unlink($alt_path)) {
                    error_log("Arquivo removido do caminho alternativo com sucesso: " . $alt_path);
                } else {
                    error_log("Falha ao remover arquivo do caminho alternativo: " . $alt_path);
                }
            } else {
                error_log("Arquivo não encontrado em nenhum caminho alternativo");
            }
        }
        
        // Atualiza o banco de dados para remover a referência do CV
        $sql_update = "UPDATE perfil_estudante SET cv_path = NULL WHERE usuario_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $usuario_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['mensagem'] = "Currículo removido com sucesso!";
            $_SESSION['tipo_mensagem'] = "success";
        } else {
            $_SESSION['mensagem'] = "Erro ao remover currículo do banco de dados: " . $conn->error;
            $_SESSION['tipo_mensagem'] = "danger";
        }
        
        $stmt_update->close();
    } else {
        $_SESSION['mensagem'] = "Nenhum currículo encontrado para remover.";
        $_SESSION['tipo_mensagem'] = "warning";
    }
    
    // Fecha a conexão
    $conn->close();
    
    // Redireciona de volta para a página de editar perfil
    header("Location: editar_perfil_estudante.php");
    exit();
} else {
    // Se não for GET ou POST, redireciona para a página principal
    header("Location: painel_estudante.php");
    exit();
}
?>