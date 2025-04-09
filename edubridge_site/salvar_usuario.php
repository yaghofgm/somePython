<?php
include 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $sobrenome = $_POST['sobrenome'];
    $email = $_POST['email'];
    $senha = hash('sha256', $_POST['senha']);
    $categoria = $_POST['categoria'];
    $telefone = $_POST['telefone'];

    $sql = "INSERT INTO usuarios (nome, sobrenome, email, senha, telefone, categoria)
    VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);


    if (!$stmt) {
        die("Erro ao preparar: " . $conn->error);
    }

    $stmt->bind_param("ssssss", $nome, $sobrenome, $email, $senha, $telefone, $categoria);

    if ($stmt->execute()) {
        echo "✅ Cadastro realizado com sucesso! <a href='index.html'>Fazer login</a>";
    } else {
        echo "❌ Erro ao cadastrar: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
