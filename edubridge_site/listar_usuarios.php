<?php
session_start();
// Configurações de erro para facilitar debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Verifica se o usuário está logado e é um administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_categoria'] !== 'empresa') {
    // Se não tiver permissão, redireciona para login
    header("Location: login.php");
    exit();
}

// Query para obter todos os usuários
$query = "SELECT id, nome, sobrenome, email, categoria, status, data_criacao, ultima_conexao FROM usuarios ORDER BY nome";
$resultado = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuários - EduBridge</title>
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
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(109, 157, 197, 0.1);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .badge.bg-success {
            background-color: #28a745 !important;
        }
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529;
        }
        .badge.bg-danger {
            background-color: #dc3545 !important;
        }
        .badge.bg-secondary {
            background-color: #6c757d !important;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-people me-2"></i>Lista de Usuários Cadastrados</h3>
                <a href="painel.php" class="btn btn-light"><i class="bi bi-arrow-left me-2"></i>Voltar ao Painel</a>
            </div>
            <div class="card-body">
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Data de Cadastro</th>
                                    <th>Último Acesso</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($usuario = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nome'] . ' ' . $usuario['sobrenome']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $categoria_cor = [
                                                'estudante' => 'info',
                                                'investidor' => 'success',
                                                'universidade' => 'primary',
                                                'empresa' => 'dark'
                                            ];
                                            echo $categoria_cor[$usuario['categoria']] ?? 'secondary';
                                        ?>">
                                            <?php echo ucfirst($usuario['categoria']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $status_cor = [
                                                'ativo' => 'success',
                                                'pendente' => 'warning',
                                                'suspenso' => 'danger',
                                                'inativo' => 'secondary'
                                            ];
                                            echo $status_cor[$usuario['status']] ?? 'secondary';
                                        ?>">
                                            <?php echo ucfirst($usuario['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($usuario['ultima_conexao']) {
                                            echo date('d/m/Y H:i', strtotime($usuario['ultima_conexao']));
                                        } else {
                                            echo '<span class="text-muted">Nunca acessou</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="btn btn-outline-danger" title="Excluir" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $usuario['id']; ?>"><i class="bi bi-trash"></i></button>
                                        </div>
                                        
                                        <!-- Modal de confirmação para excluir -->
                                        <div class="modal fade" id="deleteModal<?php echo $usuario['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $usuario['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $usuario['id']; ?>">Confirmar Exclusão</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Tem certeza que deseja excluir o usuário <strong><?php echo htmlspecialchars($usuario['nome'] . ' ' . $usuario['sobrenome']); ?></strong>?
                                                        <p class="text-danger mt-2">Esta ação não pode ser desfeita!</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <a href="excluir_usuario.php?id=<?php echo $usuario['id']; ?>&confirm=1" class="btn btn-danger">Excluir</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> Nenhum usuário cadastrado ainda.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="cadastro.html" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i>Cadastrar Novo Usuário
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>