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
    // <<<<<<<<<<===================== List Videos (Active only) =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `video_id`, `video_link`, `created_at` FROM `video` WHERE `deleted_at` = 0 AND `video_link` LIKE '%$search_text%' ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["videos"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Video Details Not Found";
        $output["body"]["videos"] = [];
    }

} else if (isset($obj->video_link) && isset($obj->user_id)) {
    // <<<<<<<<<<===================== Create/Edit Video =====================>>>>>>>>>>
    $video_link = $obj->video_link;
    $user_id = $obj->user_id;

    if (!empty($video_link) && !empty($user_id)) {
        if (isset($obj->video_id)) {
            // EDIT MODE
            $edit_id = $obj->video_id;
            $updateVideo = "UPDATE `video` SET `video_link`='$video_link' WHERE `video_id`='$edit_id' AND `deleted_at` = 0";
            if ($conn->query($updateVideo)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Video Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Update Failed";
            }
        } else {
            // CREATE MODE
            $createVideo = "INSERT INTO `video` (`video_link`, `created_at`, `deleted_at`) VALUES ('$video_link', '$timestamp', 0)";
            if ($conn->query($createVideo)) {
                $id = $conn->insert_id;
                $enid = uniqueID('VIDEO', $id); // Using your custom function
                $conn->query("UPDATE `video` SET `video_id`='$enid' WHERE `id` = $id");

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Video Created";
                $output["body"] = ["video_id" => $enid, "video_link" => $video_link];
            }
        }
    }

// <<<<<<<<<<===================== Delete Videos =====================>>>>>>>>>>
} else if (isset($obj->video_id) && isset($obj->is_delete)) {

    $del_id = $obj->video_id;
    if ($obj->is_delete == 1) {
        $deleteSql = "UPDATE `video` SET `deleted_at` = 1 WHERE `video_id` = '$del_id'";
        if ($conn->query($deleteSql) && $conn->affected_rows > 0) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Video Deleted";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Delete Failed";
        }
    }

}


// <<<<<<<<<<===================== Bulk Soft Delete Videos =====================>>>>>>>>>>
else if (isset($obj->video_ids) && is_array($obj->video_ids) && isset($obj->is_delete)) {
    
    $video_ids = $obj->video_ids;
    $is_delete = $obj->is_delete;

    if (!empty($video_ids) && $is_delete == 1) {
        // Sanitize and prepare the list for the SQL IN clause
        $ids_list = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $video_ids)) . "'";
        
        $bulkDeleteSql = "UPDATE `video` SET `deleted_at` = 1 WHERE `video_id` IN ($ids_list)";
        
        if ($conn->query($bulkDeleteSql)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Selected Videos Deleted";
            $output["body"]["affected_rows"] = $conn->affected_rows;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Bulk Delete Failed: " . $conn->error;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid Data Provided";
    }
}else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>