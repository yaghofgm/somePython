<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.html");
    exit;
}

echo "🎉 Bem-vindo, " . $_SESSION['usuario'] . "!<br>";
echo "<a href='logout.php'>Sair</a>";
?>

