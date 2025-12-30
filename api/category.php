<?php

include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
<<<<<<< HEAD
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
$category_img = isset($input->category_img) ? $input->category_img : null;
$user_id = isset($input->user_id) ? $input->user_id : null;
$created_name = isset($input->created_name) ? $input->created_name : null;

// Action flags
$is_read_action = isset($input->fetch_all) || (isset($input->category_id) && !isset($input->update_action) && !isset($input->delete_action));
$is_create_action = $category_name && !isset($input->category_id) && !isset($input->update_action) && !isset($input->delete_action);
$is_update_action = $category_id && isset($input->update_action);
$is_delete_action = $category_id && isset($input->delete_action);

// =========================================================================
// R - READ (LIST/FETCH)
// =========================================================================
if ($method === 'POST' && $is_read_action) {

    if (!empty($category_id)) {
        // FETCH SINGLE CATEGORY
        $sql = "SELECT `id`, `category_id`, `category_name`, `category_img`, `created_at` 
                FROM `category` 
                WHERE `category_id` = ? AND `deleted_at` = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category_id);
    } else {
        // FETCH ALL CATEGORIES
        $sql = "SELECT `id`, `category_id`, `category_name`, `category_img`, `created_at` 
                FROM `category` 
                WHERE `deleted_at` = 0 ORDER BY `category_name` ASC";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];

    $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/";

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['category_img'])) {
                $row['category_img_url'] = $baseUrl . $row['category_img'];
            } else {
                $row['category_img_url'] = null;
            }
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

    if (!empty($category_name)) {

        // 1. Check if Category name already exists
        $check_sql = "SELECT `id` FROM `category` WHERE `category_name` = ? AND `deleted_at` = 0";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Category name '$category_name' already exists.";
            $check_stmt->close();
            goto end_script;
        }
        $check_stmt->close();

        // Generate unique category_id
        $next_id_result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM `category`");
        $next_auto_id = $next_id_result->fetch_assoc()['next_id'];
        $category_id = uniqueID('CATEGORY', $next_auto_id);

        // Double-check if generated category_id exists
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

        // Handle image upload
        $uploadDir = "../uploads/categories/";
        $savedImageName = saveBase64Image($category_img, $uploadDir);

        $insert_sql = "INSERT INTO `category` (`category_id`, `category_name`, `category_img`, `created_by`, `created_name`, `created_at`) 
                       VALUES (?, ?, ?, ?, ?, NOW())";

        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "sssss",
            $category_id,
            $category_name,
            $savedImageName,
            $user_id,
            $created_name
        );

        if ($insert_stmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Category created successfully.";
            $output["body"]["category_id"] = $category_id;
            if ($savedImageName) {
                $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/" . $savedImageName;
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create category. Error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Category Name is required.";
    }
    goto end_script;
}
// =========================================================================
// U - UPDATE (Category)
// =========================================================================
else if (($method === 'POST' || $method === 'PUT') && $is_update_action) {

    if (!empty($category_id)) {

        // Fetch current category
        $fetch_sql = "SELECT * FROM `category` WHERE `category_id` = ? AND `deleted_at` = 0";
        $fetch_stmt = $conn->prepare($fetch_sql);
        $fetch_stmt->bind_param("s", $category_id);
        $fetch_stmt->execute();
        $fetch_result = $fetch_stmt->get_result();
        if ($fetch_result->num_rows === 0) {
            $output["head"]["code"] = 404;
            $output["head"]["msg"] = "Category not found.";
            $fetch_stmt->close();
            goto end_script;
        }
        $current = $fetch_result->fetch_assoc();
        $fetch_stmt->close();

        // Check for duplicate category name
        if (!empty($category_name) && $category_name !== $current['category_name']) {
            $check_sql = "SELECT `id` FROM `category` WHERE `category_name` = ? AND `category_id` != ? AND `deleted_at` = 0";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $category_name, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Category name '$category_name' is already used by another category.";
                $check_stmt->close();
                goto end_script;
            }
            $check_stmt->close();
        }

        // Handle new image if provided
        $finalImageName = null;
        $include_image_update = !empty($category_img);
        $uploadDir = "../uploads/categories/";
        if ($include_image_update) {
            $finalImageName = saveBase64Image($category_img, $uploadDir);
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
            'category_name' => $category_name,
        ];

        foreach ($fields as $col => $val) {
            if ($val !== null && $val !== '') {
                $sets[] = "`$col` = ?";
                $params[] = $val;
                $types .= "s";
            }
        }

        if ($include_image_update) {
            $sets[] = "`category_img` = ?";
            $params[] = $finalImageName;
            $types .= "s";
        }

        if (empty($sets)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "No fields to update.";
            goto end_script;
        }

        $setClause = implode(", ", $sets);
        $sql = "UPDATE `category` SET $setClause WHERE `category_id` = ?";
        $params[] = $category_id;
        $types .= "s";

        $update_stmt = $conn->prepare($sql);
        $update_stmt->bind_param($types, ...$params);

        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Category updated successfully.";
                if ($include_image_update && $finalImageName) {
                    $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/" . $finalImageName;
                }
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
        $output["head"]["msg"] = "Category ID is required for update.";
    }
    goto end_script;
}
// =========================================================================
// D - DELETE (Category - Soft Delete)
// =========================================================================
else if (($method === 'POST' || $method === 'DELETE') && $is_delete_action) {

    $delete_sql = "UPDATE `category` SET `deleted_at` = 1 
                   WHERE `category_id` = ? AND `deleted_at` = 0";

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("s", $category_id);

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
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter or request method is Mismatch for the operation requested.";
}

end_script:
$conn->close();
echo json_encode($output, JSON_NUMERIC_CHECK);
=======
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
    // <<<<<<<<<<===================== This is to list categories =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `category_id`, `category_name`, `category_img`, `created_at` FROM `category` WHERE `deleted_at` = 0 AND `category_name` LIKE '%$search_text%' ORDER BY `category_name` ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/";
        while ($row = $result->fetch_assoc()) {
            $output["body"]["categories"][$count] = $row;
            if (!empty($row['category_img'])) {
                $output["body"]["categories"][$count]['category_img_url'] = $baseUrl . $row['category_img'];
            } else {
                $output["body"]["categories"][$count]['category_img_url'] = null;
            }
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Category Details Not Found";
        $output["body"]["categories"] = [];
    }
} else if (isset($obj->category_name) && isset($obj->user_id) && isset($obj->created_name)) {
    // <<<<<<<<<<===================== This is to Create and Edit categories =====================>>>>>>>>>>
    $category_name = $obj->category_name;
    $user_id = $obj->user_id;
    $created_name = $obj->created_name;
    $category_img = isset($obj->category_img) ? $obj->category_img : null;

    if (!empty($category_name) && !empty($user_id) && !empty($created_name)) {

        if (isset($obj->category_id)) {
            $edit_id = $obj->category_id;
            if ($edit_id) {
                // Check if category exists
                $check = $conn->query("SELECT `id` FROM `category` WHERE `category_id`='$edit_id' AND `deleted_at`=0");
                if ($check->num_rows > 0) {
                    // Check for duplicate category name if changed
                    $curr_name_check = $conn->query("SELECT `category_name` FROM `category` WHERE `category_id`='$edit_id'");
                    $curr_name = $curr_name_check->fetch_assoc()['category_name'];
                    if ($category_name !== $curr_name) {
                        $dup_check = $conn->query("SELECT `id` FROM `category` WHERE `category_name`='$category_name' AND `category_id` != '$edit_id' AND `deleted_at`=0");
                        if ($dup_check->num_rows > 0) {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Category name '$category_name' is already used by another category.";
                            echo json_encode($output, JSON_NUMERIC_CHECK);
                            exit();
                        }
                    }

                    $updateCategory = "";
                    $savedImageName = null;
                    if (!empty($category_img)) {
                        $uploadDir = "../uploads/categories/";
                        $savedImageName = saveBase64Image($category_img, $uploadDir);
                        $updateCategory = "UPDATE `category` SET `category_name`='$category_name', `category_img`='$savedImageName', `created_by`='$user_id', `created_name`='$created_name' WHERE `category_id`='$edit_id'";
                    } else {
                        $updateCategory = "UPDATE `category` SET `category_name`='$category_name', `created_by`='$user_id', `created_name`='$created_name' WHERE `category_id`='$edit_id'";
                    }

                    if ($conn->query($updateCategory)) {
                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully Category Details Updated";
                        if ($savedImageName) {
                            $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/" . $savedImageName;
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Category not found.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid data.";
            }
        } else {
            // Check duplicate category name
            $check = $conn->query("SELECT `id` FROM `category` WHERE `category_name`='$category_name' AND `deleted_at`=0");
            if ($check->num_rows == 0) {
                $createCategory = "";
                $savedImageName = null;
                if (!empty($category_img)) {
                    $uploadDir = "../uploads/categories/";
                    $savedImageName = saveBase64Image($category_img, $uploadDir);
                    $createCategory = "INSERT INTO `category` (`category_name`, `category_img`, `created_by`, `created_name`, `created_at`) VALUES ('$category_name', '$savedImageName', '$user_id', '$created_name', '$timestamp')";
                } else {
                    $createCategory = "INSERT INTO `category` (`category_name`, `created_by`, `created_name`, `created_at`) VALUES ('$category_name', '$user_id', '$created_name', '$timestamp')";
                }

                if ($conn->query($createCategory)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('CATEGORY', $id);
                    $update = "UPDATE `category` SET `category_id`='$enid' WHERE `id` = $id";
                    $conn->query($update);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Category Created";
                    $output["body"]["category_id"] = $enid;
                    if ($savedImageName) {
                        $output["body"]["image_url"] = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/categories/" . $savedImageName;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Category Name Already Exists.";
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->category_id)) {
    // <<<<<<<<<<===================== This is to Delete the categories =====================>>>>>>>>>>
    $delete_category_id = $obj->category_id;
    if (!empty($delete_category_id)) {
        if ($delete_category_id) {
            $deletecategory = "UPDATE `category` SET `deleted_at`=1 WHERE `category_id`='$delete_category_id' AND `deleted_at`=0";
            if ($conn->query($deletecategory) === true && $conn->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Category Deleted.";
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
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
