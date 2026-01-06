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
    // <<<<<<<<<<===================== This is to list products =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $select_cols = "`p`.`id`, `p`.`product_id`, `p`.`product_name`, `p`.`product_img`, `p`.`product_code`, `p`.`unit_id`, `p`.`category_id`, `p`.`product_details`, `p`.`product_price`, `p`.`product_with_discount_price`, `p`.`product_stock`, `p`.`product_disc_amt`, `p`.`discount_lock`, `p`.`new_arrival`, `p`.`status`, `p`.`created_at`, `u`.`unit_name`, `c`.`category_name`";
    $sql = "SELECT $select_cols FROM `product` p 
            LEFT JOIN `unit` u ON p.unit_id = u.unit_id AND u.deleted_at = 0
            LEFT JOIN `category` c ON p.category_id = c.category_id AND c.deleted_at = 0
            WHERE p.deleted_at = 0 AND p.status = 0 AND p.product_name LIKE '%$search_text%' ORDER BY p.product_name ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/uploads/products/";
        while ($row = $result->fetch_assoc()) {
            $output["body"]["products"][$count] = $row;
            if (!empty($row['product_img'])) {
                $output["body"]["products"][$count]['product_img_url'] = $baseUrl . $row['product_img'];
            } else {
                $output["body"]["products"][$count]['product_img_url'] = null;
            }
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Product Details Not Found";
        $output["body"]["products"] = [];
    }
} else if (isset($obj->product_name) && isset($obj->product_code) && isset($obj->unit_id) && isset($obj->category_id) && isset($obj->user_id) && isset($obj->created_name)) {
    // <<<<<<<<<<===================== This is to Create and Edit products =====================>>>>>>>>>>
    $product_name = $obj->product_name;
    $product_code = $obj->product_code;
    $unit_id = $obj->unit_id;
    $category_id = $obj->category_id;
    $user_id = $obj->user_id;
    $created_name = $obj->created_name;
    $product_img = isset($obj->product_img) ? $obj->product_img : null;
    $product_price = isset($obj->product_price) ? floatval($obj->product_price) : 0;
    $product_stock = isset($obj->product_stock) ? floatval($obj->product_stock) : 0;
    $product_disc_amt = isset($obj->product_disc_amt) ? floatval($obj->product_disc_amt) : 0;
    $product_details = isset($obj->product_details) ? $obj->product_details : null;
    $discount_lock = isset($obj->discount_lock) ? intval($obj->discount_lock) : 0;
    $status = isset($obj->status) ? intval($obj->status) : 0;
    $new_arrival = isset($obj->new_arrival) ? intval($obj->new_arrival) : 0;

    if (!empty($product_name) && !empty($product_code) && !empty($unit_id) && !empty($category_id) && !empty($user_id) && !empty($created_name)) {

        if (isset($obj->product_id)) {
            $edit_id = $obj->product_id;
            if ($edit_id) {
                // Check if product exists
                $check = $conn->query("SELECT `id` FROM `product` WHERE `product_id`='$edit_id' AND `deleted_at`=0 AND `status`=0");
                if ($check->num_rows > 0) {
                    // Check duplicate product_code if changed
                    $curr_code_check = $conn->query("SELECT `product_code` FROM `product` WHERE `product_id`='$edit_id'");
                    $curr_code = $curr_code_check->fetch_assoc()['product_code'];
                    if ($product_code !== $curr_code) {
                        $dup_check = $conn->query("SELECT `id` FROM `product` WHERE `product_code`='$product_code' AND `product_id` != '$edit_id' AND `deleted_at`=0");
                        if ($dup_check->num_rows > 0) {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Product code already used by another product.";
                            echo json_encode($output, JSON_NUMERIC_CHECK);
                            exit();
                        }
                    }

                    $product_with_discount_price = $product_price - $product_disc_amt;
                    $updateProduct = "";
                    $savedImageName = null;
                    if (!empty($product_img)) {
                        $savedImageName = saveBase64Image($product_img);
                        $updateProduct = "UPDATE `product` SET `product_name`='$product_name', `product_code`='$product_code', `unit_id`='$unit_id', `category_id`='$category_id', `product_img`='$savedImageName', `product_details`='$product_details', `product_price`=$product_price, `product_with_discount_price`=$product_with_discount_price, `product_stock`=$product_stock, `product_disc_amt`=$product_disc_amt, `discount_lock`=$discount_lock, `new_arrival`=$new_arrival, `status`=$status, `created_by`='$user_id', `created_name`='$created_name' WHERE `product_id`='$edit_id'";
                    } else {
                        $updateProduct = "UPDATE `product` SET `product_name`='$product_name', `product_code`='$product_code', `unit_id`='$unit_id', `category_id`='$category_id', `product_details`='$product_details', `product_price`=$product_price, `product_with_discount_price`=$product_with_discount_price, `product_stock`=$product_stock, `product_disc_amt`=$product_disc_amt, `discount_lock`=$discount_lock, `new_arrival`=$new_arrival, `status`=$status, `created_by`='$user_id', `created_name`='$created_name' WHERE `product_id`='$edit_id'";
                    }

                    if ($conn->query($updateProduct)) {
                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully Product Details Updated";
                        if ($savedImageName) {
                            $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/uploads/products/" . $savedImageName;
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Product not found.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid data.";
            }
        } else {
            // Check duplicate product_code
            $check = $conn->query("SELECT `id` FROM `product` WHERE `product_code`='$product_code' AND `deleted_at`=0");
            if ($check->num_rows == 0) {
                $product_with_discount_price = $product_price - $product_disc_amt;
                $createProduct = "";
                $savedImageName = null;
                if (!empty($product_img)) {
                    $savedImageName = saveBase64Image($product_img);
                    $createProduct = "INSERT INTO `product` (`product_name`, `product_code`, `unit_id`, `category_id`, `product_img`, `product_details`, `product_price`, `product_with_discount_price`, `product_stock`, `product_disc_amt`, `discount_lock`, `new_arrival`, `status`, `created_by`, `created_name`, `created_at`) VALUES ('$product_name', '$product_code', '$unit_id', '$category_id', '$savedImageName', '$product_details', $product_price, $product_with_discount_price, $product_stock, $product_disc_amt, $discount_lock, $new_arrival, $status, '$user_id', '$created_name', '$timestamp')";
                } else {
                    $createProduct = "INSERT INTO `product` (`product_name`, `product_code`, `unit_id`, `category_id`, `product_details`, `product_price`, `product_with_discount_price`, `product_stock`, `product_disc_amt`, `discount_lock`, `new_arrival`, `status`, `created_by`, `created_name`, `created_at`) VALUES ('$product_name', '$product_code', '$unit_id', '$category_id', '$product_details', $product_price, $product_with_discount_price, $product_stock, $product_disc_amt, $discount_lock, $new_arrival, $status, '$user_id', '$created_name', '$timestamp')";
                }

                if ($conn->query($createProduct)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('PROD', $id);
                    $update = "UPDATE `product` SET `product_id`='$enid' WHERE `id` = $id";
                    $conn->query($update);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Product Created";
                    $output["body"]["product_id"] = $enid;
                    if ($savedImageName) {
                        $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/uploads/products/" . $savedImageName;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Product Code Already Exists.";
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->product_id)) {
    // <<<<<<<<<<===================== This is to Delete the products =====================>>>>>>>>>>
    $delete_product_id = $obj->product_id;
    if (!empty($delete_product_id)) {
        if ($delete_product_id) {
            $deleteproduct = "UPDATE `product` SET `deleted_at`=1 WHERE `product_id`='$delete_product_id'";
            if ($conn->query($deleteproduct) === true && $conn->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Product Deleted.";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to delete. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid data.";
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

echo json_encode($output, JSON_NUMERIC_CHECK);