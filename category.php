<?php

include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Invalid JSON"]
    ]);
    exit();
}
$output = ["head" => ["code" => 400, "msg" => "Invalid request"]];
date_default_timezone_set('Asia/Calcutta');
$method = $_SERVER['REQUEST_METHOD'];

// inputs
$category_name = isset($input->category_name) ? trim($input->category_name) : null;
$category_id = isset($input->category_id) ? $input->category_id : null;
$company_id = isset($input->company_id) ? trim($input->company_id) : null;
$user_id = isset($input->user_id) ? $input->user_id : null;
$created_name = isset($input->created_name) ? $input->created_name : null;

// Action flags
$is_read_action = isset($input->fetch_all) || (isset($input->category_id) && !isset($input->update_action) && !isset($input->delete_action));
$is_create_action = $category_name && $company_id && !isset($input->category_id) && !isset($input->update_action) && !isset($input->delete_action);
$is_update_action = $category_id && $company_id && isset($input->update_action);
$is_delete_action = $category_id && $company_id && isset($input->delete_action);

// =========================================================================
// R - READ (LIST/FETCH)
// =========================================================================
if ($method === 'POST' && $is_read_action) {
    if (empty($company_id)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Company ID is required to fetch categories.";
        goto end_script;
    }

    if (!empty($category_id)) {  // Changed: Use !empty() to treat empty string/null/whitespace as "fetch all"
        // FETCH SINGLE CATEGORY
        $sql = "SELECT `id`, `category_id`, `category_name`, `company_id`, `created_at` 
                FROM `category` 
                WHERE `category_id` = ? AND `company_id` = ? AND `deleted_at` = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $category_id, $company_id);
    } else {
        // FETCH ALL CATEGORIES for a company
        $sql = "SELECT `id`, `category_id`, `category_name`, `company_id`, `created_at` 
                FROM `category` 
                WHERE `company_id` = ? AND `deleted_at` = 0 ORDER BY `category_name` ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $company_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["categories"] = $categories;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "No categories found.";
    }
    $stmt->close();
    goto end_script;
}
// =========================================================================
// C - CREATE (Category)
// =========================================================================
else if ($method === 'POST' && $is_create_action) {

    if (!empty($category_name) && !empty($company_id)) {

        // 1. Check if Category name already exists (Duplicate prevention)
        $check_sql = "SELECT `id` FROM `category` WHERE `category_name` = ? AND `company_id` = ? AND `deleted_at` = 0";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $category_name, $company_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Category name '$category_name' already exists for this company.";
            $check_stmt->close();
            goto end_script;
        }
        $check_stmt->close();

        // Generate unique category_id using the function
        $next_id_result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM `category`");
        $next_auto_id = $next_id_result->fetch_assoc()['next_id'];
        $category_id = uniqueID('CATEGORY', $next_auto_id);

        // Double-check if generated category_id already exists (unlikely, but safe)
        $id_check = $conn->prepare("SELECT 1 FROM `category` WHERE `category_id` = ?");
        $id_check->bind_param("s", $category_id);
        $id_check->execute();
        if ($id_check->get_result()->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Generated category_id conflict (retry)";
            $id_check->close();
            goto end_script;
        }
        $id_check->close();

        // 2. Insert the new Category record
        $insert_sql = "INSERT INTO `category` (`category_id`, `category_name`, `company_id`, `created_by`, `created_name`, `created_at`) 
                       VALUES (?, ?, ?, ?, ?, NOW())";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "sssss",
            $category_id,
            $category_name,
            $company_id,
            $user_id,
            $created_name
        );

        if ($insert_stmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Category created successfully.";
            $output["body"]["category_id"] = $category_id;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create category. Error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Category Name and Company ID are required.";
    }
    goto end_script;
}
// =========================================================================
// U - UPDATE (Category)
// =========================================================================
else if (($method === 'POST' || $method === 'PUT') && $is_update_action) {

    if (!empty($category_name)) {

        // 1. Check for duplicate category name (excluding current)
        $check_sql = "SELECT `id` FROM `category` WHERE `category_name` = ? AND `company_id` = ? AND `category_id` != ? AND `deleted_at` = 0";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $category_name, $company_id, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Category name '$category_name' is already used by another category.";
            $check_stmt->close();
            goto end_script;
        }
        $check_stmt->close();

        // 2. Update the Category record
        $update_sql = "UPDATE `category` SET `category_name` = ? 
                       WHERE `category_id` = ? AND `company_id` = ?";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            "sss",
            $category_name,
            $category_id,
            $company_id
        );

        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Category updated successfully.";
            } else {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Category updated successfully (No changes made).";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update category. Error: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Category Name is required for update.";
    }
    goto end_script;
}
// =========================================================================
// D - DELETE (Category - Soft Delete)
// =========================================================================
else if (($method === 'POST' || $method === 'DELETE') && $is_delete_action) {

    // Perform Soft Delete
    $delete_sql = "UPDATE `category` SET `deleted_at` = 1 
                   WHERE `category_id` = ? AND `company_id` = ? AND `deleted_at` = 0";

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ss", $category_id, $company_id);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Category deleted successfully.";
        } else {
            $output["head"]["code"] = 404;
            $output["head"]["msg"] = "Category not found or already deleted.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to delete category. Error: " . $delete_stmt->error;
    }
    $delete_stmt->close();
    goto end_script;
}
// =========================================================================
// Mismatch / Fallback
// =========================================================================
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter or request method is Mismatch for the operation requested.";
}

end_script:
// Close the database connection at the end
$conn->close();

echo json_encode($output, JSON_NUMERIC_CHECK);
