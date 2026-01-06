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



if (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list orders =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $customer_id = isset($obj->customer_id) ? $obj->customer_id : null;
    $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/uploads/products/";
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

            // Prepend baseUrl to each product_img if it exists
            if (is_array($row['product_details']) || is_object($row['product_details'])) {
                foreach ($row['product_details'] as &$product) {
                    if (isset($product->product_img) && !empty($product->product_img)) {
                        $product->product_img = $baseUrl . $product->product_img;
                    }
                }
            }

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

    if (!empty($customer_id) && !empty($shipping_address) && !empty($total_items) && !empty($product_details_json) && !empty($sub_total)  && !empty($shipping_charges) && !empty($grand_total)) {

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
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
