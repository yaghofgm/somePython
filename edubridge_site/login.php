<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = hash('sha256', $_POST['senha']);

    $sql = "SELECT * FROM usuarios WHERE email = ? AND senha = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Erro ao preparar: " . $conn->error);
    }

    $stmt->bind_param("ss", $email, $senha);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $_SESSION['usuario'] = $email;
        header("Location: painel.php");
        exit;
    } else {
        echo "❌ Email ou senha incorretos.";
    }

    $stmt->close();
    $conn->close();
}
?>
