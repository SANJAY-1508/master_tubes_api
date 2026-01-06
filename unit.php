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
    // <<<<<<<<<<===================== This is to list units =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `unit_id`, `unit_name`, `created_at` FROM `unit` WHERE `deleted_at` = 0 AND `unit_name` LIKE '%$search_text%' ORDER BY unit_name ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["units"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Unit Details Not Found";
        $output["body"]["units"] = [];
    }
} else if (isset($obj->unit_name) && isset($obj->user_id) && isset($obj->created_name)) {
    // <<<<<<<<<<===================== This is to Create and Edit units =====================>>>>>>>>>>
    $unit_name = $obj->unit_name;
    $user_id = $obj->user_id;
    $created_name = $obj->created_name;

    if (!empty($unit_name) && !empty($user_id) && !empty($created_name)) {

        if (isset($obj->unit_id)) {
            $edit_id = $obj->unit_id;
            if ($edit_id) {
                // Check if unit exists
                $check = $conn->query("SELECT `id` FROM `unit` WHERE `unit_id`='$edit_id' AND `deleted_at`=0");
                if ($check->num_rows > 0) {
                    $updateUnit = "UPDATE `unit` SET `unit_name`='$unit_name', `created_by`='$user_id', `created_name`='$created_name' WHERE `unit_id`='$edit_id'";
                    if ($conn->query($updateUnit)) {
                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully Unit Details Updated";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Unit not found.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid data.";
            }
        } else {
            // Check duplicate unit name
            $check = $conn->query("SELECT `id` FROM `unit` WHERE `unit_name`='$unit_name' AND `deleted_at`=0");
            if ($check->num_rows == 0) {
                $createUnit = "INSERT INTO `unit` (`unit_name`, `created_by`, `created_name`, `created_at`) VALUES ('$unit_name', '$user_id', '$created_name', '$timestamp')";
                if ($conn->query($createUnit)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('UNIT', $id);
                    $update = "UPDATE `unit` SET `unit_id`='$enid' WHERE `id` = $id";
                    $conn->query($update);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Unit Created";
                    $output["body"] = [
                        "unit_id" => $enid,
                        "unit_name" => $unit_name
                    ];
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unit Name Already Exists.";
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->unit_id)) {
    // <<<<<<<<<<===================== This is to Delete the units =====================>>>>>>>>>>
    $delete_unit_id = $obj->unit_id;
    if (!empty($delete_unit_id)) {
        if ($delete_unit_id) {
            $deleteunit = "UPDATE `unit` SET `deleted_at`=1 WHERE `unit_id`='$delete_unit_id' AND `deleted_at`=0";
            if ($conn->query($deleteunit) === true && $conn->affected_rows > 0) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Unit Deleted.";
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