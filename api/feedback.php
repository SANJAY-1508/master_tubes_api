<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();


//List Feedback
if (isset($obj->action) && $obj->action === 'list') {

    $sql = "SELECT id, name, city, rating, feedback, created_at 
            FROM `feedback` 
            WHERE `deleted_at` = 0 
            ORDER BY `id` DESC";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $feedbacks = array();

        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }
        $output["body"]["feedbacks"] = $feedbacks;
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No feedback found";
        $output["body"]["feedbacks"] = [];
    }

    // SAVE FEEDBACK ---
} else if (isset($obj->name) && isset($obj->rating)) {

    $name = mysqli_real_escape_string($conn, $obj->name);
    $rating = mysqli_real_escape_string($conn, $obj->rating);
    $city = isset($obj->city) ? mysqli_real_escape_string($conn, $obj->city) : '';
    $feedback = isset($obj->feedback) ? mysqli_real_escape_string($conn, $obj->feedback) : '';

    if (in_array($rating, ['1', '2', '3', '4', '5'])) {
        $sql = "INSERT INTO `feedback` (`name`, `city`, `rating`, `feedback`, `deleted_at`) 
                VALUES ('$name', '$city', '$rating', '$feedback', 0)";

        if ($conn->query($sql)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Thank you for your feedback!";
        } else {
            $output["head"]["code"] = 500;
            $output["head"]["msg"] = "Database Error";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid Rating";
    }

    // --- CASE 3: INVALID REQUEST ---
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
?>