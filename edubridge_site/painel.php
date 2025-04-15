<?php
session_start();
// Configurações de erro para produção
error_reporting(0);
ini_set('display_errors', 0);

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php?expirado=1");
    exit();
}

// Atualiza o timestamp de último acesso
$_SESSION['ultimo_acesso'] = time();

// Redireciona para o painel específico com base na categoria do usuário
$categoria = $_SESSION['usuario_categoria'];

switch ($categoria) {
    case 'estudante':
        header("Location: painel_estudante.php");
        break;
    case 'investidor':
        header("Location: painel_investidor.php");
        break;
    case 'universidade':
        header("Location: painel_universidade.php");
        break;
    case 'empresa':
        header("Location: painel_empresa.php");
        break;
    case 'admin':
        header("Location: painel_admin.php");
        break;
    default:
        // Se por algum motivo a categoria não for reconhecida
        header("Location: login.php?erro=categoria_invalida");
        exit();
}
exit();
?>

