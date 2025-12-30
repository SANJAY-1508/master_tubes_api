<?php
<<<<<<< HEAD
// 1. Disable display_errors to prevent HTML notices from breaking JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'db/config.php';

// 2. Comprehensive CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 3. Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
=======

include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

<<<<<<< HEAD
// <<<<<<<<<<===================== LIST ALL ORDERS OPERATION =====================>>>>>>>>>>
if (isset($obj->fetch_all) && $obj->fetch_all == true) {

    // customer_id irukka illaya nu check
    if (!empty($obj->customer_id)) {

        // 👉 Customer specific orders
        $customer_id = $obj->customer_id;
        $sql = "SELECT * FROM `order_enquiry` 
                WHERE `customer_id` = ? 
                AND `deleted_at` = 0 
                ORDER BY `id` DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $customer_id);
    } else {

        // 👉 customer_id illa → ALL orders
        $sql = "SELECT * FROM `order_enquiry` 
                WHERE `deleted_at` = 0 
                ORDER BY `id` DESC";

        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $orders = [];

        while ($row = $result->fetch_assoc()) {
            $row['product_details'] = json_decode($row['product_details']);
            $orders[] = $row;
        }

        $output["body"]["orders"] = $orders;
    } else {

        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No orders found";
    }

    $stmt->close();
}


// <<<<<<<<<<===================== CREATE ORDER OPERATION =====================>>>>>>>>>>
else if (isset($obj->customer_id) && isset($obj->product_details)) {
    $customer_id = $obj->customer_id;
    $product_details_json = json_encode($obj->product_details);

    // FIX: Assign date to a variable to avoid "Only variables should be passed by reference"
    $order_date = date('Y-m-d');

    // Generate Order Number
    $sql_count = "SELECT COUNT(*) as total FROM `order_enquiry` WHERE `deleted_at` = 0";
    $count_res = $conn->query($sql_count);
    $count_row = $count_res->fetch_assoc();
    $next_number = $count_row['total'] + 1;
    $order_no = "ORD_" . sprintf("%03d", $next_number);

    // Ensure uniqueID is defined in your config or here
    $order_id = uniqueID("order", $next_number);

    $sql = "INSERT INTO `order_enquiry` (`order_id`, `order_no`, `order_date`, `customer_id`, `shipping_address`, `total_items`, `product_details`, `sub_total`, `discount`, `shipping_charges`, `grand_total`, `status`, `created_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0)";

    $stmt = $conn->prepare($sql);

    // FIX: All arguments are now variables
    $stmt->bind_param(
        "sssssisdddds",
        $order_id,
        $order_no,
        $order_date,
        $customer_id,
        $obj->shipping_address,
        $obj->total_items,
        $product_details_json,
        $obj->sub_total,
        $obj->discount,
        $obj->shipping_charges,
        $obj->grand_total,
        $timestamp
    );

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Order Placed Successfully";
        $output["body"]["order_no"] = $order_no;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Database Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid Parameters";
=======


if (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list orders =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $customer_id = isset($obj->customer_id) ? $obj->customer_id : null;
    $sql = "SELECT * FROM `order_enquiry` WHERE `deleted_at` = 0";
    if (!empty($customer_id)) {
        $sql .= " AND `customer_id` = '$customer_id'";
    }
    $sql .= " AND (`order_no` LIKE '%$search_text%' OR `customer_id` LIKE '%$search_text%') ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $row['product_details'] = json_decode($row['product_details']);
            $output["body"]["orders"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Order Details Not Found";
        $output["body"]["orders"] = [];
    }
} else if (isset($obj->customer_id) && isset($obj->product_details) && isset($obj->shipping_address) && isset($obj->total_items) && isset($obj->sub_total) && isset($obj->discount) && isset($obj->shipping_charges) && isset($obj->grand_total)) {
    // <<<<<<<<<<===================== This is to Create orders =====================>>>>>>>>>>
    $customer_id = $obj->customer_id;
    $shipping_address = $obj->shipping_address;
    $total_items = $obj->total_items;
    $product_details_json = json_encode($obj->product_details);
    $sub_total = $obj->sub_total;
    $discount = $obj->discount;
    $shipping_charges = $obj->shipping_charges;
    $grand_total = $obj->grand_total;

    if (!empty($customer_id) && !empty($shipping_address) && !empty($total_items) && !empty($product_details_json) && !empty($sub_total) && !empty($discount) && !empty($shipping_charges) && !empty($grand_total)) {

        // Generate Order Number
        $sql_count = "SELECT COUNT(*) as total FROM `order_enquiry` WHERE `deleted_at` = 0";
        $count_res = $conn->query($sql_count);
        $count_row = $count_res->fetch_assoc();
        $next_number = $count_row['total'] + 1;
        $order_no = "ORD_" . sprintf("%03d", $next_number);

        $order_date = date('Y-m-d');

        $createOrder = "INSERT INTO `order_enquiry` (`order_no`, `order_date`, `customer_id`, `shipping_address`, `total_items`, `product_details`, `sub_total`, `discount`, `shipping_charges`, `grand_total`, `status`, `created_at`, `deleted_at`) VALUES ('$order_no', '$order_date', '$customer_id', '$shipping_address', $total_items, '$product_details_json', $sub_total, $discount, $shipping_charges, $grand_total, 0, '$timestamp', 0)";

        if ($conn->query($createOrder)) {
            $id = $conn->insert_id;
            $enid = uniqueID('order', $id);
            $update = "UPDATE `order_enquiry` SET `order_id`='$enid' WHERE `id` = $id";
            $conn->query($update);

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Order Placed Successfully";
            $output["body"]["order_no"] = $order_no;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
}

echo json_encode($output, JSON_NUMERIC_CHECK);
