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

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Invalid JSON"]
    ]);
    exit;
}


$input = json_decode(file_get_contents('php://input'));
$output = ["head" => ["code" => 400, "msg" => "Invalid request"]];
date_default_timezone_set('Asia/Calcutta');
$method = $_SERVER['REQUEST_METHOD'];



// inputs 
$unit_name = isset($input->unit_name) ? trim($input->unit_name) : null;
$unit_id = isset($input->unit_id) ? trim($input->unit_id) : null;
$company_id = isset($input->company_id) ? trim($input->company_id) : null;
$user_id = $input->user_id ?? null;
$created_name = $input->created_name ?? null;
// Action flags
$is_fetch = $method === 'POST' && (isset($input->fetch_all) || isset($input->unit_id));
$is_create = $method === 'POST' && $unit_name && $company_id && !isset($input->update_action) && !isset($input->delete_action);
$is_update = in_array($method, ['POST', 'PUT']) && $unit_id && $company_id && isset($input->update_action);
$is_delete = in_array($method, ['POST', 'DELETE']) && $unit_id && $company_id && isset($input->delete_action);


// ==================================================================
// 1. FETCH UNITS (by company_id or single unit_id)
// ==================================================================
if ($is_fetch) {
    if (empty($company_id)) {
        $output["head"]["msg"] = "company_id is required";
        goto end;
    }
    if (!empty($input->unit_id)) {
        // Fetch single unit
        $sql = "SELECT id, unit_id, unit_name, company_id, created_at 
                FROM unit
                WHERE unit_id = ? AND company_id = ? AND deleted_at = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $input->unit_id, $company_id);
    } else {
        // Fetch all units for company
        $sql = "SELECT id, unit_id, unit_name, company_id, created_at 
                FROM unit
                WHERE company_id = ? AND deleted_at = 0
                ORDER BY unit_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $company_id);
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
    if (empty($unit_name) || empty($company_id)) {
        $output["head"]["msg"] = "unit_name and company_id are required";
        goto end;
    }
    // Check duplicate unit name
    $check = $conn->prepare("SELECT 1 FROM unit WHERE unit_name = ? AND company_id = ? AND deleted_at = 0");
    $check->bind_param("ss", $unit_name, $company_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $output["head"]["msg"] = "Unit name already exists";
        $check->close();
        goto end;
    }
    $check->close();

    // Generate unique unit_id using the function (produces 32-char MD5 hash like your sample)
    $next_id_result = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM unit");
    $next_auto_id = $next_id_result->fetch_assoc()['next_id'];
    $unit_id = uniqueID('UNIT', $next_auto_id);

    // Double-check if generated unit_id already exists (unlikely with MD5)
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
        (unit_id, unit_name, company_id, created_by, created_name, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $unit_id, $unit_name, $company_id, $user_id, $created_name);
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
    // Check if unit exists and belongs to company
    $check = $conn->prepare("SELECT 1 FROM unit WHERE unit_id = ? AND company_id = ? AND deleted_at = 0");
    $check->bind_param("ss", $unit_id, $company_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $output["head"]["msg"] = "Unit not found or access denied";
        $check->close();
        goto end;
    }
    $check->close();
    $stmt = $conn->prepare("UPDATE unit SET unit_name = ? WHERE unit_id = ? AND company_id = ?");
    $stmt->bind_param("sss", $unit_name, $unit_id, $company_id);
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
    $stmt = $conn->prepare("UPDATE unit SET deleted_at = 1 WHERE unit_id = ? AND company_id = ? AND deleted_at = 0");
    $stmt->bind_param("ss", $unit_id, $company_id);
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
