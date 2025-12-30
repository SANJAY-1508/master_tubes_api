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
    exit;
}

$output = ["head" => ["code" => 400, "msg" => "Invalid request"]];
date_default_timezone_set('Asia/Calcutta');
$method = $_SERVER['REQUEST_METHOD'];

// inputs 
$unit_name = isset($input->unit_name) ? trim($input->unit_name) : null;
$unit_id = isset($input->unit_id) ? trim($input->unit_id) : null;
$user_id = $input->user_id ?? null;
$created_name = $input->created_name ?? null;

// Action flags
$is_fetch = $method === 'POST' && (isset($input->fetch_all) || isset($input->unit_id));
$is_create = $method === 'POST' && $unit_name && !isset($input->update_action) && !isset($input->delete_action);
$is_update = in_array($method, ['POST', 'PUT']) && $unit_id && isset($input->update_action);
$is_delete = in_array($method, ['POST', 'DELETE']) && $unit_id && isset($input->delete_action);


// ==================================================================
// 1. FETCH UNITS (Single or All)
// ==================================================================
if ($is_fetch) {
   
    if (!empty($input->unit_id)) {
        // Fetch single unit
        $sql = "SELECT id, unit_id, unit_name, created_at 
                FROM unit
                WHERE unit_id = ? AND deleted_at = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $input->unit_id);
    } else {
        // Fetch all units
        $sql = "SELECT id, unit_id, unit_name, created_at 
                FROM unit
                WHERE deleted_at = 0
                ORDER BY unit_name ASC";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $units = $result->fetch_all(MYSQLI_ASSOC);
    
    if (count($units) > 0) {
        $output["head"] = ["code" => 200, "msg" => "Success"];
        $output["body"]["units"] = $units;
    } else {
        $output["head"] = ["code" => 404, "msg" => "No units found"];
    }
    $stmt->close();
    goto end;
}
// ==================================================================
// 2. CREATE UNIT
// ==================================================================
else if ($is_create) {
    if (empty($unit_name)) {
        $output["head"]["msg"] = "unit_name is required";
        goto end;
    }
    
    // Check duplicate unit name (Globally, since company_id is removed)
    $check = $conn->prepare("SELECT 1 FROM unit WHERE unit_name = ? AND deleted_at = 0");
    $check->bind_param("s", $unit_name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $output["head"]["msg"] = "Unit name already exists";
        $check->close();
        goto end;
    }
    $check->close();

    // Generate unique unit_id
    $next_id_result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM unit");
    $next_auto_id = $next_id_result->fetch_assoc()['next_id'];
    $unit_id = uniqueID('UNIT', $next_auto_id);

    // Double-check if generated unit_id already exists
    $id_check = $conn->prepare("SELECT 1 FROM unit WHERE unit_id = ?");
    $id_check->bind_param("s", $unit_id);
    $id_check->execute();
    if ($id_check->get_result()->num_rows > 0) {
        $output["head"]["msg"] = "Generated unit_id conflict (retry)";
        $id_check->close();
        goto end;
    }
    $id_check->close();

    $stmt = $conn->prepare("INSERT INTO unit
        (unit_id, unit_name, created_by, created_name, created_at)
        VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $unit_id, $unit_name, $user_id, $created_name);
    
    if ($stmt->execute()) {
        $output["head"] = ["code" => 200, "msg" => "Unit created successfully"];
        $output["body"] = [
            "unit_id" => $unit_id,
            "unit_name" => $unit_name
        ];
    } else {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database error: " . $stmt->error;
    }
    $stmt->close();
    goto end;
}
// ==================================================================
// 3. UPDATE UNIT
// ==================================================================
else if ($is_update) {
    if (empty($unit_name)) {
        $output["head"]["msg"] = "unit_name is required for update";
        goto end;
    }
    
    // Check if unit exists
    $check = $conn->prepare("SELECT 1 FROM unit WHERE unit_id = ? AND deleted_at = 0");
    $check->bind_param("s", $unit_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $output["head"]["msg"] = "Unit not found";
        $check->close();
        goto end;
    }
    $check->close();
    
    $stmt = $conn->prepare("UPDATE unit SET unit_name = ? WHERE unit_id = ?");
    $stmt->bind_param("ss", $unit_name, $unit_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $output["head"] = ["code" => 200, "msg" => "Unit updated successfully"];
    } else {
        $output["head"]["msg"] = "No changes made or unit not found";
    }
    $stmt->close();
    goto end;
}
// ==================================================================
// 4. DELETE UNIT (Soft Delete)
// ==================================================================
else if ($is_delete) {
    $stmt = $conn->prepare("UPDATE unit SET deleted_at = 1 WHERE unit_id = ? AND deleted_at = 0");
    $stmt->bind_param("s", $unit_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $output["head"] = ["code" => 200, "msg" => "Unit deleted successfully"];
    } else {
        $output["head"] = ["code" => 404, "msg" => "Unit not found or already deleted"];
    }
    $stmt->close();
    goto end;
}
// ==================================================================
// DEFAULT: Invalid Request
// ==================================================================
else {
    $output["head"]["msg"] = "Invalid action or parameters";
}

end:
$conn->close();
echo json_encode($output, JSON_UNESCAPED_UNICODE);
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
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
