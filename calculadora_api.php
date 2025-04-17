<?php
header("Content-Type: application/json");

// Check if request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Get parameters from POST request
$gpa = isset($_POST['gpa']) ? floatval($_POST['gpa']) : null;
$sex = isset($_POST['sex']) ? $_POST['sex'] : null;
$university = isset($_POST['university']) ? $_POST['university'] : null;
$course = isset($_POST['course']) ? $_POST['course'] : null;
$country = isset($_POST['country']) ? $_POST['country'] : null;
$profit_threshold = isset($_POST['profit_threshold']) ? floatval($_POST['profit_threshold']) : null;

// Validate required parameters
if ($gpa === null || $sex === null || $university === null || $course === null || $country === null || $profit_threshold === null) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

// Map sex values to match Python script requirements (0 for male, 1 for female)
$gender = ($sex === 'female') ? 1 : 0;

// Execute Python script with parameters
$pythonScript = '../edubridge_algoritmo/calculate_loanOptions.py';
$command = "python3 " . escapeshellarg($pythonScript) . " " . 
           escapeshellarg($gpa) . " " . 
           escapeshellarg($gender) . " " . 
           escapeshellarg($university) . " " . 
           escapeshellarg($course) . " " . 
           escapeshellarg($country) . " " . 
           escapeshellarg($profit_threshold);

$output = [];
$return_val = 0;
exec($command, $output, $return_val);

// Check if Python script executed successfully
if ($return_val !== 0) {
    echo json_encode(["error" => "Failed to execute calculation script"]);
    exit;
}

// Process Python script output
$results = [];
foreach ($output as $line) {
    // Expected format: "interest_rate,min_loan,max_loan"
    $parts = explode(',', $line);
    if (count($parts) === 3) {
        $taxa_juros = floatval($parts[0]);
        $min_loan = floatval($parts[1]);
        $max_loan = floatval($parts[2]);
        
        $results[] = [
            "taxa_juros" => $taxa_juros,
            "intervalos_emprestimos" => [$min_loan, $max_loan]
        ];
    }
}

// Return results
echo json_encode(["results" => $results]);
?>