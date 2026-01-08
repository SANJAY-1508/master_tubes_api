<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

// 1. Setup Query to get data ONLY from customer_enquiry
$sql = "SELECT * FROM `customer_enquiry` WHERE 1=1";

// 2. Search logic restricted to customer fields
if (isset($obj->search_text) && !empty($obj->search_text)) {
    $search = $conn->real_escape_string($obj->search_text);
    $sql .= " AND (first_name LIKE '%$search%' 
                OR last_name LIKE '%$search%' 
                OR phone LIKE '%$search%' 
                OR city LIKE '%$search%')";
}

$sql .= " ORDER BY id DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $customers = array();

    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $output["body"]["customers"] = $customers;
} else {
    $output["head"]["code"] = 404;
    $output["head"]["msg"] = "No customer enquiries found";
    $output["body"]["customers"] = [];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>