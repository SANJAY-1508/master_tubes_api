<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// <<<<<<<<<<===================== LIST ALL ORDERS OPERATION =====================>>>>>>>>>>
if (isset($obj->customer_id) && isset($obj->fetch_all) && $obj->fetch_all == true) {
    
    $customer_id = $obj->customer_id;

    // Direct query to get all orders for the specific customer
    $sql = "SELECT * FROM `order_enquiry` WHERE `customer_id` = ? AND `deleted_at` = 0 ORDER BY `id` DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $orders = array();
        
        while ($row = $result->fetch_assoc()) {
            $row['product_details'] = json_decode($row['product_details']);
            $orders[] = $row;
        }
        $output["body"]["orders"] = $orders;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No orders found for this customer";
    }
    $stmt->close();
}

// <<<<<<<<<<===================== CREATE ORDER OPERATION =====================>>>>>>>>>>
else if (isset($obj->customer_id) && isset($obj->product_details)) {    
    $customer_id = $obj->customer_id;
    $product_details_json = json_encode($obj->product_details);
    
    // Generate Order Number
    $sql_count = "SELECT COUNT(*) as total FROM `order_enquiry` WHERE `deleted_at` = 0";
    $count_res = $conn->query($sql_count);
    $count_row = $count_res->fetch_assoc();
    $next_number = $count_row['total'] + 1;
    $order_no = "ORD_" . sprintf("%03d", $next_number);
    $order_id = uniqueID("order", $next_number);

    $sql = "INSERT INTO `order_enquiry` (`order_id`, `order_no`, `order_date`, `customer_id`, `shipping_address`, `total_items`, `product_details`, `sub_total`, `discount`, `shipping_charges`, `grand_total`, `status`, `created_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssisdddds", $order_id, $order_no, date('Y-m-d'), $customer_id, $obj->shipping_address, $obj->total_items, $product_details_json, $obj->sub_total, $obj->discount, $obj->shipping_charges, $obj->grand_total, $timestamp);

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Order Placed Successfully";
        $output["body"]["order_no"] = $order_no;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Error";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid Parameters";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>