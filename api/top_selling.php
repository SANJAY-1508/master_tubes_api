<?php
include 'db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$output = array();
$baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/products/";

// --- STEP 1: CALCULATE TOP PRODUCT FROM ORDER_ENQUIRY ---
$sql_orders = "SELECT product_details FROM `order_enquiry`";
$res_orders = $conn->query($sql_orders);

$product_counts = [];

if ($res_orders && $res_orders->num_rows > 0) {
    while ($row = $res_orders->fetch_assoc()) {
        // Decode the JSON array from the product_details column
        $details = json_decode($row['product_details'], true);
        
        if (is_array($details)) {
            foreach ($details as $item) {
                $p_id = $item['product_id'];
                $qty = (int)$item['quantity'];
                
                // Tally quantities for each ID
                if (isset($product_counts[$p_id])) {
                    $product_counts[$p_id] += $qty;
                } else {
                    $product_counts[$p_id] = $qty;
                }
            }
        }
    }
}

// --- STEP 2: FETCH DETAILS FOR THE TOP PRODUCT ---
if (!empty($product_counts)) {
    // Sort by quantity descending and get the top ID
    arsort($product_counts);
    $top_product_id = array_key_first($product_counts);

    // SQL with Collation fix to prevent 'Illegal mix of collations' error
    $sql = "SELECT * FROM `product` 
            WHERE `product_id` = '$top_product_id' COLLATE utf8mb4_unicode_ci 
            AND `deleted_at` = 0 LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Format image URL
        if (!empty($row['product_img'])) {
            $row['product_img_url'] = $baseUrl . $row['product_img'];
        }

        $output["head"] = ["code" => 200, "msg" => "Success"];
        $output["body"]["top_product"] = $row;
        // Optional: include the order count
        $output["body"]["total_orders"] = $product_counts[$top_product_id];
    } else {
        $output["head"] = ["code" => 404, "msg" => "Product details not found"];
    }
} else {
    $output["head"] = ["code" => 404, "msg" => "No orders found in enquiry table"];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>