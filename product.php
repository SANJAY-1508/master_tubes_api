<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');

// Input variables 
$company_id         = isset($obj->company_id) ? trim($obj->company_id) : null;
$product_id         = isset($obj->product_id) ? trim($obj->product_id) : null;
$product_name       = isset($obj->product_name) ? trim($obj->product_name) : null;
$product_img        = isset($obj->product_img) ? $obj->product_img : null;
$product_code       = isset($obj->product_code) ? trim($obj->product_code) : null;;
$unit_id            = isset($obj->unit_id) ? trim($obj->unit_id) : null;;
$category_id        = isset($obj->category_id) ? trim($obj->category_id) : null;;

$product_price      = isset($obj->product_price) ? floatval($obj->product_price) : null;
$product_stock      = isset($obj->product_stock) ? floatval($obj->product_stock) : null;
$product_disc_amt   = isset($obj->product_disc_amt) ? floatval($obj->product_disc_amt) : null;

$product_details    = isset($obj->product_details) ? trim($obj->product_details) : null;
$discount_lock      = isset($obj->discount_lock) ? intval($obj->discount_lock) : null;
$status             = isset($obj->status) ? intval($obj->status) : null;

$id                 = isset($obj->id) ? intval($obj->id) : null;
$user_id            = isset($obj->user_id) ? trim($obj->user_id) : null;;
$created_name       = isset($obj->created_name) ? trim($obj->created_name) : null;

// Helper flags
$method = $_SERVER['REQUEST_METHOD'];
$is_read_action = isset($obj->fetch_all) || (isset($obj->id) && !isset($obj->update_action) && !isset($obj->delete_action));
$is_create_action = $product_name && $product_code && $company_id && $unit_id && $category_id && !isset($obj->id) && !isset($obj->product_id) && !isset($obj->update_action) && !isset($obj->delete_action);
$is_update_action = $product_id && $company_id && isset($obj->update_action);
$is_delete_action = $product_id && $company_id && isset($obj->delete_action);



// ===================================================================
// R - READ (Fetch Products + Full Image URL)
// ===================================================================
if ($method === 'POST' && $is_read_action) {

    if (empty($company_id)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Company ID is required.";
        goto end_script;
    }

    $select_cols = "p.*, u.unit_name, c.category_name";
    $from_tables = "`product` p 
                    LEFT JOIN `unit` u ON p.unit_id = u.id AND u.deleted_at = 0
                    LEFT JOIN `category` c ON p.category_id = c.id AND c.deleted_at = 0";
    $where_clause = "p.company_id = ? AND p.deleted_at = 0 AND p.status = 0";

    if (isset($obj->id) && $obj->id !== null) {
        $sql = "SELECT {$select_cols} FROM {$from_tables} WHERE {$where_clause} AND p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $company_id, $id);
    } else if (isset($obj->product_id) && $obj->product_id !== null) {
        $sql = "SELECT {$select_cols} FROM {$from_tables} WHERE {$where_clause} AND p.product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $company_id, $product_id);
    } else {
        $sql = "SELECT {$select_cols} FROM {$from_tables} WHERE {$where_clause} ORDER BY p.product_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $company_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];

    $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/zen_online_stores/uploads/products/";

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['product_img'])) {
            $row['product_img_url'] = $baseUrl . $row['product_img'];
        } else {
            $row['product_img_url'] = null;
        }
        $products[] = $row;
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["products"] = $products;

    $stmt->close();
}

// ===================================================================
// C - CREATE
// ===================================================================
else if ($method === 'POST' && $is_create_action) {

    if (empty($product_name) || empty($product_code) || empty($unit_id) || empty($category_id)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Required fields are missing.";
        goto end_script;
    }

    // Check duplicate product_code
    $check = $conn->prepare("SELECT id FROM product WHERE product_code = ? AND company_id = ? AND deleted_at = 0");
    $check->bind_param("si", $product_code, $company_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Product code already exists.";
        $check->close();
        goto end_script;
    }
    $check->close();

    // Get next auto_increment_id
    $sql = "SELECT MAX(`id`) as max_id FROM `product`";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $next_id = ($row['max_id'] ?? 0) + 1;

    // Generate unique product_id
    $new_product_id = uniqueID('PROD', $next_id);

    // Handle image upload
    $savedImageName = saveBase64Image($product_img);

    // Compute discount price
    $product_with_discount_price = ($product_price ?? 0) - ($product_disc_amt ?? 0);

    // Default values if null
    $product_details = $product_details ?? null;
    $discount_lock = $discount_lock ?? 0;
    $status = $status ?? 0;

    $stmt = $conn->prepare("INSERT INTO `product` 
        (`product_id`, `company_id`, `product_name`, `product_img`, `product_code`, `unit_id`, `category_id`, 
         `product_details`, `product_price`, `product_with_discount_price`, `product_stock`, `product_disc_amt`, 
         `discount_lock`, `status`, `created_by`, `created_name`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssssssddddssss",
        $new_product_id,
        $company_id,
        $product_name,
        $savedImageName,
        $product_code,
        $unit_id,
        $category_id,
        $product_details,
        $product_price,
        $product_with_discount_price,
        $product_stock,
        $product_disc_amt,
        $discount_lock,
        $status,
        $user_id,
        $created_name
    );

    if ($stmt->execute()) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Product created successfully.";
        $output["body"]["product_id"] = $new_product_id;
        $output["body"]["image_url"] = $savedImageName ? "http://{$_SERVER['SERVER_NAME']}/zen_online_stores/uploads/products/{$savedImageName}" : null;
    } else {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "DB Error: " . $stmt->error;
    }
    $stmt->close();
}

// ===================================================================
// U - UPDATE
// ===================================================================
else if (($method === 'POST' || $method === 'PUT') && $is_update_action) {

    if (empty($product_id) || empty($product_name) || empty($product_code)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Product ID, Name and Code are required for update.";
        goto end_script;
    }

    // Check duplicate code (exclude current product)
    $check = $conn->prepare("SELECT id FROM product WHERE product_code = ? AND company_id = ? AND product_id != ? AND deleted_at = 0");
    $check->bind_param("sis", $product_code, $company_id, $product_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Product code already used by another product.";
        $check->close();
        goto end_script;
    }
    $check->close();

    // Fetch current product
    $fetch_sql = "SELECT * FROM `product` WHERE `product_id` = ? AND `company_id` = ? AND `deleted_at` = 0 AND `status` = 0";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("is", $product_id, $company_id);
    $fetch_stmt->execute();
    $fetch_result = $fetch_stmt->get_result();
    if ($fetch_result->num_rows === 0) {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "Product not found.";
        $fetch_stmt->close();
        goto end_script;
    }
    $current = $fetch_result->fetch_assoc();
    $fetch_stmt->close();

    // Handle new image if provided
    $finalImageName = null;
    $include_image_update = !empty($product_img);
    if ($include_image_update) {
        $finalImageName = saveBase64Image($product_img);
        if ($finalImageName === null) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid image data.";
            goto end_script;
        }
    }

    // Build dynamic SET clause
    $sets = [];
    $types = "";
    $params = [];

    $fields = [
        'product_name' => $product_name,
        'product_code' => $product_code,
        'unit_id' => $unit_id,
        'category_id' => $category_id,
        'product_price' => $product_price,
        'product_stock' => $product_stock,
        'product_disc_amt' => $product_disc_amt,
        'product_details' => $product_details,
        'discount_lock' => $discount_lock,
        'status' => $status
    ];

    // Handle calculated discount price
    if (isset($obj->product_price) || isset($obj->product_disc_amt)) {
        $price = $product_price !== null ? $product_price : floatval($current['product_price']);
        $disc_amt = $product_disc_amt !== null ? $product_disc_amt : floatval($current['product_disc_amt']);
        $fields['product_with_discount_price'] = $price - $disc_amt;
    }

    foreach ($fields as $col => $val) {
        if ($val !== null) {
            $sets[] = "`$col` = ?";
            $params[] = $val;
            $types .= is_numeric($val) ? "d" : "s";
        }
    }

    // Handle image update separately
    if ($include_image_update) {
        $sets[] = "`product_img` = ?";
        $params[] = $finalImageName;
        $types .= "s";
    }

    if (empty($sets)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "No fields to update.";
        goto end_script;
    }

    $setClause = implode(", ", $sets);
    $sql = "UPDATE `product` SET $setClause WHERE `product_id` = ? AND `company_id` = ?";
    $params[] = $product_id;
    $params[] = $company_id;
    $types .= "si";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Product updated successfully.";
        if ($include_image_update && $finalImageName) {
            $output["body"]["image_url"] = "http://{$_SERVER['SERVER_NAME']}/zen_online_stores/uploads/products/{$finalImageName}";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "No changes made or product not found.";
    }
    $stmt->close();
}

// ===================================================================
// D - DELETE (Soft)
// ===================================================================
else if (($method === 'POST' || $method === 'DELETE') && $is_delete_action) {

    // Perform Soft Delete
    $delete_sql = "UPDATE `product` SET `deleted_at` = 1 
                   WHERE `product_id` = ? AND `company_id` = ?";

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("si", $product_id, $company_id);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Product deleted successfully.";
        } else {
            $output["head"]["code"] = 404;
            $output["head"]["msg"] = "Product not found or already deleted.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to delete Product. Error: " . $delete_stmt->error;
    }
    $delete_stmt->close();
}

// ===================================================================
// Fallback
// ===================================================================
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid request or parameters missing.";
}

end_script:
$conn->close();
echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
