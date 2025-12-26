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

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Handle Contact Form Submission
if (isset($obj->name) && isset($obj->email) && isset($obj->comment)) {
    $name = $conn->real_escape_string($obj->name);
    $email = $conn->real_escape_string($obj->email);
    $phone = isset($obj->phone) ? $conn->real_escape_string($obj->phone) : '';
    $comment = $conn->real_escape_string($obj->comment);

    if (!empty($name) && !empty($email)) {
        $insertQuery = "INSERT INTO `contact_form` (`name`, `email`, `phone`, `comment`, `created_at`) 
                        VALUES ('$name', '$email', '$phone', '$comment', '$timestamp')";

        if ($conn->query($insertQuery)) {
            $id = $conn->insert_id;
            // Using your uniqueID function if it exists in your config
            $contact_id = function_exists('uniqueID') ? uniqueID('contact', $id) : "CON".$id;
            
            $conn->query("UPDATE `contact_form` SET `contact_id`='$contact_id' WHERE `id` = $id");

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Submitted. We will contact you soon.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to save data: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Name and Email are required.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>