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