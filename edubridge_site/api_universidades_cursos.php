<?php
// api_universidades_cursos.php
// API endpoint for handling university and course data for the registration form

header('Content-Type: application/json');
require_once 'conexao.php';

// Get the requested action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'get_universidades':
        // Retrieve all universities
        getUniversidades();
        break;
        
    case 'get_cursos':
        // Get courses by university
        $universidade_id = isset($_GET['universidade_id']) ? intval($_GET['universidade_id']) : 0;
        
        if ($universidade_id > 0) {
            getCursosByUniversidade($universidade_id);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Missing universidade_id parameter',
                'data' => []
            ]);
        }
        break;
        
    case 'search_courses':
        // Search for courses by university and query
        $universityId = isset($_GET['university_id']) ? intval($_GET['university_id']) : 0;
        $query = isset($_GET['query']) ? $_GET['query'] : '';
        
        if ($universityId > 0 && !empty($query)) {
            searchCourses($universityId, $query);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required parameters',
                'data' => []
            ]);
        }
        break;
        
    default:
        // Invalid action
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action',
            'data' => []
        ]);
        break;
}

/**
 * Retrieve all universities from the database
 */
function getUniversidades() {
    global $conn;
    
    try {
        $sql = "SELECT id, nome FROM perfil_universidade ORDER BY nome";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $universidades = [];
            
            while ($row = $result->fetch_assoc()) {
                $universidades[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Universities retrieved successfully',
                'data' => $universidades
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to execute query',
                'data' => []
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

/**
 * Get courses by university ID
 */
function getCursosByUniversidade($universidade_id) {
    global $conn;
    
    try {
        $sql = "SELECT id, nome_curso FROM curso_universidade WHERE universidade_id = ? ORDER BY nome_curso";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $universidade_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $cursos = [];
            
            while ($row = $result->fetch_assoc()) {
                $cursos[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Courses retrieved successfully',
                'data' => $cursos
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to execute query',
                'data' => []
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

/**
 * Search for courses by university and query
 */
function searchCourses($universityId, $query) {
    global $conn;
    
    try {
        // Search for courses that belong to the specified university and match the query
        $sql = "SELECT id, nome_curso as nome FROM curso_universidade 
                WHERE universidade_id = ? 
                AND nome_curso LIKE ? 
                ORDER BY nome_curso 
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $searchPattern = '%' . $query . '%';
        $stmt->bind_param("is", $universityId, $searchPattern);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $courses = [];
            
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Courses retrieved successfully',
                'data' => $courses
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to execute query',
                'data' => []
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}
?>