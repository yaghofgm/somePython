<?php
// Configurações de erro para facilitar debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Query para obter todos os emails dos usuários
$query = "SELECT email, nome, sobrenome, categoria, status FROM usuarios ORDER BY email";
$resultado = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emails Cadastrados - EduBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f2f5ff;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #3066BE;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .table {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mt-4">
            <div class="card-header">
                <h3>Lista de Emails Cadastrados</h3>
            </div>
            <div class="card-body">
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Nome</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($usuario = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nome'] . ' ' . $usuario['sobrenome']); ?></td>
                                    <td><?php echo ucfirst($usuario['categoria']); ?></td>
                                    <td><?php echo ucfirst($usuario['status']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Nenhum usuário cadastrado no sistema.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <p class="text-muted mb-0">Total de emails cadastrados: <?php echo $resultado ? $resultado->num_rows : 0; ?></p>
            </div>
        </div>
    </div>
</body>
</html>