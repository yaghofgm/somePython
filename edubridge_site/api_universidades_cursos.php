<?php
// Configuraciones de error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir conexión a la base de datos
include 'conexao.php';

// Definir encabezado para JSON
header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

// Obtener todas las universidades
if (isset($_GET['action']) && $_GET['action'] === 'get_universidades') {
    $sql = "SELECT id, nome FROM perfil_universidade ORDER BY nome";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = $row;
        }
        $response['success'] = true;
    } else {
        $response['message'] = "Nenhuma universidade encontrada.";
    }
}

// Obtener cursos de una universidad específica
if (isset($_GET['action']) && $_GET['action'] === 'get_cursos' && isset($_GET['universidade_id'])) {
    $universidade_id = (int)$_GET['universidade_id'];
    
    $sql = "SELECT id, nome_curso FROM curso_universidade WHERE universidade_id = ? ORDER BY nome_curso";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $universidade_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = $row;
        }
        $response['success'] = true;
    } else {
        $response['message'] = "Nenhum curso encontrado para esta universidade.";
    }
    $stmt->close();
}

// Cerrar la conexión a la base de datos
$conn->close();

// Devolver la respuesta como JSON
echo json_encode($response);
?>