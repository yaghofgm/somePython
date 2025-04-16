<?php
// Iniciar sessão
session_start();

// Incluir arquivo de conexão com o banco de dados
include_once('conexao.php');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduBridge - Calculadora de Empréstimos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .calculator-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .calculator-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .calculator-header h2 {
            color: #0d6efd;
        }
        .results-table {
            margin-top: 30px;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .loading-indicator {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.html">EduBridge</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calculadora_emprestimos.php">Calculadora</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cadastro.html">Cadastro</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Calculadora de Empréstimos -->
    <div class="container calculator-container">
        <div class="calculator-header">
            <h2>Calculadora de Empréstimos Educacionais</h2>
            <p class="text-muted">Descubra as opções de empréstimo disponíveis com base no seu perfil acadêmico</p>
        </div>

        <form id="loan-calculator-form">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="gpa" class="form-label">GPA (Média Escolar)</label>
                    <input type="number" class="form-control" id="gpa" name="gpa" min="2.0" max="4.0" step="0.1" value="3.5" required>
                    <div class="form-text">Entre 2.0 e 4.0</div>
                </div>

                <div class="col-md-6">
                    <label for="sex" class="form-label">Sexo</label>
                    <select class="form-select" id="sex" name="sex" required>
                        <option value="male">Masculino</option>
                        <option value="female">Feminino</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="university" class="form-label">Universidade</label>
                    <select class="form-select" id="university" name="university" required>
                        <option value="Tecnologico de Monterrey Estado de Mexico">Tecnologico de Monterrey</option>
                        <option value="Anahuac">Anahuac</option>
                        <option value="University of Michigan Ann Arbor">University of Michigan Ann Arbor</option>
                        <option value="University A">University A</option>
                        <option value="University B">University B</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="course" class="form-label">Curso</label>
                    <select class="form-select" id="course" name="course" required>
                        <option value="Ingenieria Mecanica">Ingenieria Mecanica</option>
                        <option value="Ingeniería Quimica">Ingeniería Quimica</option>
                        <option value="Ingeniería Computacion y Tecnologias de la informacion">Ing. Computação/TI</option>
                        <option value="Ingenieria Industrial">Ingenieria Industrial</option>
                        <option value="Electrical & Computer Engineering">Electrical & Computer Engineering</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Arts">Arts</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="country" class="form-label">País</label>
                    <select class="form-select" id="country" name="country" required>
                        <option value="BR">Brasil</option>
                        <option value="MX" selected>México</option>
                        <option value="US">Estados Unidos</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="profit_threshold" class="form-label">Limiar de Lucro</label>
                    <input type="number" class="form-control" id="profit_threshold" name="profit_threshold" min="1.0" max="2.0" step="0.01" value="1.1" required>
                    <div class="form-text">Entre 1.0 e 2.0</div>
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary w-100">Calcular Opções de Empréstimo</button>
                </div>
            </div>
        </form>
        
        <div class="loading-indicator" id="loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p>Calculando opções de empréstimo...</p>
        </div>
        
        <div class="results-table" id="results-container" style="display: none;">
            <h3 class="mb-4">Opções de Empréstimo Disponíveis</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Taxa de Juros (%)</th>
                            <th>Intervalos de Empréstimos Válidos (US$)</th>
                        </tr>
                    </thead>
                    <tbody id="results-body">
                        <!-- Resultados serão inseridos aqui via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">© 2025 EduBridge - Empréstimos Educacionais</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            $('#loan-calculator-form').on('submit', function(e) {
                e.preventDefault();
                
                // Mostrar indicador de carregamento
                $('#loading').show();
                $('#results-container').hide();
                
                // Coletar dados do formulário
                const formData = {
                    gpa: parseFloat($('#gpa').val()),
                    sex: $('#sex').val(),
                    university: $('#university').val(),
                    course: $('#course').val(),
                    country: $('#country').val(),
                    profit_threshold: parseFloat($('#profit_threshold').val())
                };
                
                // Enviar requisição AJAX para o backend
                $.ajax({
                    url: 'calculadora_api.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        // Esconder indicador de carregamento
                        $('#loading').hide();
                        
                        if (response.error) {
                            alert('Erro: ' + response.error);
                            return;
                        }
                        
                        // Mostrar resultados
                        $('#results-container').show();
                        
                        // Limpar tabela de resultados anterior
                        $('#results-body').empty();
                        
                        // Preencher tabela com novos resultados
                        response.results.forEach(function(row) {
                            $('#results-body').append(`
                                <tr>
                                    <td>${row.taxa_juros}</td>
                                    <td>${row.intervalos_emprestimos}</td>
                                </tr>
                            `);
                        });
                    },
                    error: function(xhr, status, error) {
                        $('#loading').hide();
                        alert('Erro ao processar a requisição: ' + error);
                    }
                });
            });
        });
    </script>
</body>
</html>