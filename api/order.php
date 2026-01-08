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
    $search_text = $conn->real_escape_string($obj->search_text);
    $customer_id = isset($obj->customer_id) ? $conn->real_escape_string($obj->customer_id) : null;
    
    // NEW: Capture the type from the request (1 = Ordinary, 2 = Customized)
    $type = isset($obj->type) ? $conn->real_escape_string($obj->type) : null;

    $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/uploads/products/";
    
    // Start with the base query
    $sql = "SELECT * FROM `order_enquiry` WHERE `deleted_at` = 0";
    
    // Filter by customer if provided
    if (!empty($customer_id)) {
        $sql .= " AND `customer_id` = '$customer_id'";
    }

    // NEW: Filter by type if provided
    if (!empty($type)) {
        $sql .= " AND `type` = '$type'";
    }

    // Filter by search text
    $sql .= " AND (`order_no` LIKE '%$search_text%' OR `customer_id` LIKE '%$search_text%')";
    
    $sql .= " ORDER BY `id` DESC";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $row['product_details'] = json_decode($row['product_details']); //

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
} else if (isset($obj->product_details) && isset($obj->shipping_address) && isset($obj->total_items) && isset($obj->discount) && isset($obj->grand_total)) {

    // 1. Capture basic details
    $customer_id = isset($obj->customer_id) ? $obj->customer_id : 'null';
    $shipping_address_raw = $obj->shipping_address;

    // Convert to string for the main order_enquiry table (this is what you save in DB)
    $shipping_address_db = is_string($shipping_address_raw) ? $shipping_address_raw : json_encode($shipping_address_raw);

    $total_items = $obj->total_items;
    $product_details_json = json_encode($obj->product_details);
    $discount = $obj->discount;
    $grand_total = $obj->grand_total;

    // 2. Map the order type from your frontend payload
    $order_type = isset($obj->product_details[0]->type) ? $obj->product_details[0]->type : '1';

    // 3. Set temporary NULL values (Ensure your DB allows NULL for these columns)
    $sub_total = "NULL";
    $shipping_charges = "NULL";

    if (!empty($shipping_address_db) && !empty($total_items) && !empty($product_details_json)) {

        // Generate Order Number
        $sql_count = "SELECT COUNT(*) as total FROM `order_enquiry` WHERE `deleted_at` = 0";
        $count_res = $conn->query($sql_count);
        $count_row = $count_res->fetch_assoc();
        $next_number = $count_row['total'] + 1;
        $order_no = "ORD_" . sprintf("%03d", $next_number);
        $order_date = date('Y-m-d');

        // 4. Execute Insert - FIXED: Use $shipping_address_db instead of $shipping_address
        $createOrder = "INSERT INTO `order_enquiry` (
            `order_no`, `order_date`, `customer_id`, `shipping_address`, 
            `total_items`, `product_details`, `sub_total`, `discount`, 
            `shipping_charges`, `grand_total`, `status`, `type`, `created_at`, `deleted_at`
        ) VALUES (
            '$order_no', '$order_date', '$customer_id', '$shipping_address_db', 
            $total_items, '$product_details_json', $sub_total, $discount, 
            $shipping_charges, $grand_total, 0, '$order_type', '$timestamp', 0
        )";

        if ($conn->query($createOrder)) {
            $id = $conn->insert_id;
            $enid = uniqueID('order', $id);
            $update = "UPDATE `order_enquiry` SET `order_id`='$enid' WHERE `id` = $id";
            $conn->query($update);

            // 5. Populate customer_enquiry table
            $addr = $shipping_address_raw;

            // Use the keys from your React payload (firstName, lastName, etc.)
            $fname = isset($addr->firstName) ? $conn->real_escape_string($addr->firstName) : '';
            $lname = isset($addr->lastName) ? $conn->real_escape_string($addr->lastName) : '';
            $main_addr = isset($addr->address) ? $conn->real_escape_string($addr->address) : '';
            $apartment = isset($addr->apartment) ? $conn->real_escape_string($addr->apartment) : '';
            $city = isset($addr->city) ? $conn->real_escape_string($addr->city) : '';
            $state = isset($addr->state) ? $conn->real_escape_string($addr->state) : '';
            $pin = isset($addr->pinCode) ? $conn->real_escape_string($addr->pinCode) : '';
            $phone = isset($addr->phone) ? $conn->real_escape_string($addr->phone) : '';
            $generated_cust_id = uniqueID('cust', $id);


            $customerEnquirySql = "INSERT INTO `customer_enquiry` 
        (`customer_id`, `first_name`, `last_name`, `address`, `apartment`, `city`, `state`, `pin_code`, `phone`, `created_at`) 
        VALUES 
        ('$generated_cust_id', '$fname', '$lname', '$main_addr', '$apartment', '$city', '$state', '$pin', '$phone', '$timestamp')";

            $conn->query($customerEnquirySql);


            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Order Placed Successfully";
            $output["body"]["order_no"] = $order_no;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Database Error: " . $conn->error;
        }
    }
}
// } else if (isset($obj->product_details) && isset($obj->shipping_address) && isset($obj->total_items) && isset($obj->sub_total) && isset($obj->discount) && isset($obj->shipping_charges) && isset($obj->grand_total)) {
//     // <<<<<<<<<<===================== Create orders (Guest-Friendly) =====================>>>>>>>>>>

//     // Check if customer_id exists, otherwise set it to a default value like 'GUEST' or 0
//     $customer_id = isset($obj->customer_id) ? $obj->customer_id : 'null';

//     $shipping_address = $obj->shipping_address;
//     $total_items = $obj->total_items;
//     $product_details_json = json_encode($obj->product_details);

//     $discount = $obj->discount;

//     $grand_total = $obj->grand_total;
//     $order_type = isset($obj->product_details[0]->type) ? $obj->product_details[0]->type : '1';
//     $sub_total = "NULL";
//     $shipping_charges = "NULL";

//     // Remove the mandatory check for customer_id inside the empty() check
//     if (!empty($shipping_address) && !empty($total_items) && !empty($product_details_json)) {

//         // Generate Order Number
//         $sql_count = "SELECT COUNT(*) as total FROM `order_enquiry` WHERE `deleted_at` = 0";
//         $count_res = $conn->query($sql_count);
//         $count_row = $count_res->fetch_assoc();
//         $next_number = $count_row['total'] + 1;
//         $order_no = "ORD_" . sprintf("%03d", $next_number);

//         $order_date = date('Y-m-d');

//         // Note: Ensure your database 'customer_id' column can accept the string 'GUEST' 
//         // or change 'GUEST' to 0 if your column is an integer.
//         $createOrder = "INSERT INTO `order_enquiry` (`order_no`, `order_date`, `customer_id`, `shipping_address`, `total_items`, `product_details`, `sub_total`, `discount`, `shipping_charges`, `grand_total`, `status`,`type`, `created_at`, `deleted_at`) VALUES ('$order_no', '$order_date', '$customer_id', '$shipping_address', $total_items, '$product_details_json', $sub_total, $discount, $shipping_charges, $grand_total, 0, '$order_type', '$timestamp', 0)";

//         if ($conn->query($createOrder)) {
//             $id = $conn->insert_id;
//             $enid = uniqueID('order', $id);
//             $update = "UPDATE `order_enquiry` SET `order_id`='$enid' WHERE `id` = $id";
//             $conn->query($update);



//             $output["head"]["code"] = 200;
//             $output["head"]["msg"] = "Order Placed Successfully";
//             $output["body"]["order_no"] = $order_no;
//         } else {
//             $output["head"]["code"] = 400;
//             $output["head"]["msg"] = "Database Error: " . $conn->error;
//         }
//     } else {
//         $output["head"]["code"] = 400;
//         $output["head"]["msg"] = "Please provide all the required details.";
//     }
// }
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
