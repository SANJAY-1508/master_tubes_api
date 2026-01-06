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
} elseif(isset($obj->search_text)) {
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `contact_form` WHERE `name` LIKE '%$search_text%' ORDER BY `id` ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $output["body"]["contact_form"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "contact_form Details Not Found";
        $output["body"]["contact_form"] = [];
    }
    
}else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>