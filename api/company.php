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

<<<<<<< HEAD


if (isset($obj->search_text)) {
    $sql = "SELECT `id`, `company_id`, `company_name`, `address`, `pincode`, `phone`, `mobile`, `gst_no`, `state`, `city`, `img`, `acc_number`, `acc_holder_name`, `bank_name`, `ifsc_code`, `bank_branch`, `deleted_at`, `created_by`, `created_name`, `created_date` FROM `company` WHERE 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["company"] = $row;
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/company/" . $row["img"];
                $output["body"]["company"]["img"] = $imgLink;
            } else {
                $output["body"]["company"]["img"] = $imgLink;
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Company Details Not Found";
    }
} 
// <<<<<<<<<<===================== Update/Insert Company Details (UPSERT LOGIC) =====================>>>>>>>>>>
else if (isset($obj->company_name) && isset($obj->acc_holder_name) && isset($obj->company_profile_img) && isset($obj->address) && isset($obj->pincode) && isset($obj->city) && isset($obj->state) && isset($obj->phone_number) && isset($obj->mobile_number) && isset($obj->gst_number) && isset($obj->acc_number) && isset($obj->bank_name) && isset($obj->ifsc_code) && isset($obj->bank_branch)) {

    $company_name = $obj->company_name;
    $company_profile_img = $obj->company_profile_img;
    $address = $obj->address;
    $pincode = $obj->pincode;
    $city = $obj->city;
    $state = $obj->state;
    $phone_number = $obj->phone_number;
    $mobile_number = $obj->mobile_number;
    $gst_number = $obj->gst_number;
=======
if (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list companies =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `company_id`, `company_name`, `address`, `description`, `pincode`, `phone`, `mobile`, `email_id`, `gst_no`, `state`, `city`, `img`, `acc_number`, `acc_holder_name`, `bank_name`, `ifsc_code`, `bank_branch`, `minimum_order_value`, `website_link`, `facebook_link`, `instagram_link`, `youtube_link`, `gpay_qr_code`, `phonepe_qr_code`, `paytm_qr_code`, `location_link`, `upi_id`, `deleted_at`, `created_at` FROM `company` WHERE `deleted_at` = 0 AND `company_name` LIKE '%$search_text%'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["company"][$count] = $row;
            $baseUrl = "http://" . $_SERVER['SERVER_NAME'] . "/master_tubes_website_api/uploads/companies/";

            // Set full URL for img
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = $baseUrl . $row["img"];
                $output["body"]["company"][$count]["img"] = $imgLink;
            } else {
                $output["body"]["company"][$count]["img"] = $imgLink;
            }

            // Set full URL for gpay_qr_code
            $gpayLink = null;
            if ($row["gpay_qr_code"] != null && $row["gpay_qr_code"] != 'null' && strlen($row["gpay_qr_code"]) > 0) {
                $gpayLink = $baseUrl . $row["gpay_qr_code"];
                $output["body"]["company"][$count]["gpay_qr_code"] = $gpayLink;
            } else {
                $output["body"]["company"][$count]["gpay_qr_code"] = $gpayLink;
            }

            // Set full URL for phonepe_qr_code
            $phonepeLink = null;
            if ($row["phonepe_qr_code"] != null && $row["phonepe_qr_code"] != 'null' && strlen($row["phonepe_qr_code"]) > 0) {
                $phonepeLink = $baseUrl . $row["phonepe_qr_code"];
                $output["body"]["company"][$count]["phonepe_qr_code"] = $phonepeLink;
            } else {
                $output["body"]["company"][$count]["phonepe_qr_code"] = $phonepeLink;
            }

            // Set full URL for paytm_qr_code
            $paytmLink = null;
            if ($row["paytm_qr_code"] != null && $row["paytm_qr_code"] != 'null' && strlen($row["paytm_qr_code"]) > 0) {
                $paytmLink = $baseUrl . $row["paytm_qr_code"];
                $output["body"]["company"][$count]["paytm_qr_code"] = $paytmLink;
            } else {
                $output["body"]["company"][$count]["paytm_qr_code"] = $paytmLink;
            }

            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Company Details Not Found";
        $output["body"]["company"] = [];
    }
} else if (isset($obj->company_name) && isset($obj->address) && isset($obj->description) && isset($obj->pincode) && isset($obj->phone) && isset($obj->mobile) && isset($obj->email_id) && isset($obj->gst_no) && isset($obj->state) && isset($obj->city) && isset($obj->acc_number) && isset($obj->acc_holder_name) && isset($obj->bank_name) && isset($obj->ifsc_code) && isset($obj->bank_branch) && isset($obj->minimum_order_value)) {
    // <<<<<<<<<<===================== This is to Create and Edit companies =====================>>>>>>>>>>
    $company_name = $obj->company_name;
    $address = $obj->address;
    $description = $obj->description;
    $pincode = $obj->pincode;
    $phone = $obj->phone;
    $mobile = $obj->mobile;
    $email_id = $obj->email_id;
    $gst_no = $obj->gst_no;
    $state = $obj->state;
    $city = $obj->city;
    $img_base64 = isset($obj->img) ? $obj->img : null;
    $gpay_qr_base64 = isset($obj->gpay_qr_code) ? $obj->gpay_qr_code : null;
    $phonepe_qr_base64 = isset($obj->phonepe_qr_code) ? $obj->phonepe_qr_code : null;
    $paytm_qr_base64 = isset($obj->paytm_qr_code) ? $obj->paytm_qr_code : null;
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
    $acc_number = $obj->acc_number;
    $acc_holder_name = $obj->acc_holder_name;
    $bank_name = $obj->bank_name;
    $ifsc_code = $obj->ifsc_code;
    $bank_branch = $obj->bank_branch;
<<<<<<< HEAD

    if (!empty($company_name) && !empty($address) && !empty($pincode) && !empty($phone_number) && !empty($city) && !empty($state)) {
        if (!preg_match('/[^a-zA-Z0-9., ]+/', $company_name)) {
            if (function_exists('numericCheck') && numericCheck($phone_number) && strlen($phone_number) == 10) {

                $edit_id = 1; 
                $sql = "";
                $profile_path = "";
                $is_insert = false;
                
                // --- A. Check if the company record (id=1) already exists ---
                $check_sql = "SELECT COUNT(`id`) AS count, `company_id` FROM `company` WHERE `id` = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $edit_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_row = $check_result->fetch_assoc();
                $count = $check_row['count'];
                $company_id = $check_row['company_id'];
                $check_stmt->close();
                
                // --- B. Auto-Generate company_id if not present or being inserted ---
                if ($count == 0 || empty($company_id) || $company_id === null) {
                    // Generates a simple ID like COMP-000001 (based on the fixed ID of 1)
                    $company_id = 'COMP-' . str_pad($edit_id, 6, '0', STR_PAD_LEFT);
                }

                // --- C. Handle image path and define SQL (INSERT or UPDATE) ---
                if (!empty($company_profile_img) && function_exists('pngImageToWebP')) {
                    $outputFilePath = "../uploads/company/";
                    $profile_path = pngImageToWebP($company_profile_img, $outputFilePath);
                }

                if ($count == 0) {
                    $is_insert = true;
                    $sql = "INSERT INTO `company` (`id`, `company_id`, `company_name`, `img`, `address`, `pincode`, `city`, `state`, `phone`, `mobile`, `gst_no`, `acc_number`, `acc_holder_name`, `bank_name`, `ifsc_code`, `bank_branch`) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                } else if (!empty($company_profile_img) && function_exists('pngImageToWebP')) {
                    $sql = "UPDATE `company` SET `company_id`=?, `company_name`=?, `img`=?, `address`=?, `pincode`=?, `city`=?, `state`=?, `phone`=?, `mobile`=?, `gst_no`=?, `acc_number`=?, `acc_holder_name`=?, `bank_name`=?, `ifsc_code`=?, `bank_branch`=? WHERE `id`=?";
                } else {
                    $sql = "UPDATE `company` SET `company_id`=?, `company_name`=?, `address`=?, `pincode`=?, `city`=?, `state`=?, `phone`=?, `mobile`=?, `gst_no`=?, `acc_number`=?, `acc_holder_name`=?, `bank_name`=?, `ifsc_code`=?, `bank_branch`=? WHERE `id`=?";
                }
                $stmt = $conn->prepare($sql);
                if ($is_insert) {
                    $stmt->bind_param("isssssisssssssss", 
                        $edit_id, $company_id, $company_name, $profile_path, $address, $pincode, $city, $state, 
                        $phone_number, $mobile_number, $gst_number, $acc_number, $acc_holder_name, $bank_name, $ifsc_code, $bank_branch
                    );
                    $success_msg = "Successfully Company Details Created";
                }
                else if (!empty($company_profile_img) && function_exists('pngImageToWebP')) {
                    $stmt->bind_param("ssssissssssssssi", 
                        $company_id, $company_name, $profile_path, $address, $pincode, $city, $state, $phone_number, 
                        $mobile_number, $gst_number, $acc_number, $acc_holder_name, $bank_name, $ifsc_code, $bank_branch, 
                        $edit_id
                    );
                    $success_msg = "Successfully Company Details Updated";
                } else {
                    $stmt->bind_param("ssssissssssssssi", 
                        $company_id, $company_name, $address, $pincode, $city, $state, $phone_number, 
                        $mobile_number, $gst_number, $acc_number, $acc_holder_name, $bank_name, $ifsc_code, $bank_branch, 
                        $edit_id
                    );
                    $success_msg = "Successfully Company Details Updated";
                }

                if ($stmt->execute()) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = $success_msg;
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to process the request. Error: " . $stmt->error;
                }
                $stmt->close(); 
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Phone Number.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Company name Should be Alphanumeric.";
=======
    $minimum_order_value = $obj->minimum_order_value;
    $website_link = isset($obj->website_link) ? $obj->website_link : '';
    $facebook_link = isset($obj->facebook_link) ? $obj->facebook_link : '';
    $instagram_link = isset($obj->instagram_link) ? $obj->instagram_link : '';
    $youtube_link = isset($obj->youtube_link) ? $obj->youtube_link : '';
    $location_link = isset($obj->location_link) ? $obj->location_link : '';
    $upi_id = isset($obj->upi_id) ? $obj->upi_id : '';

    if (!empty($company_name) && !empty($gst_no) && !empty($address) && !empty($phone) && !empty($email_id) && !empty($state) && !empty($city)) {

        $is_edit = isset($obj->edit_company_id);
        $edit_id = $is_edit ? $obj->edit_company_id : null;

        if ($is_edit && !empty($edit_id)) {
            // Edit mode
            $curr_gst_sql = "SELECT `gst_no` FROM `company` WHERE `company_id` = '$edit_id'";
            $curr_gst_result = $conn->query($curr_gst_sql);
            if ($curr_gst_result->num_rows > 0) {
                $curr_gst = $curr_gst_result->fetch_assoc()['gst_no'];
                if ($gst_no !== $curr_gst) {
                    // GST changed, check uniqueness
                    $gst_check = $conn->query("SELECT `id` FROM `company` WHERE `gst_no` = '$gst_no'");
                    if ($gst_check->num_rows > 0) {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "GST Number Already Exists.";
                        echo json_encode($output, JSON_NUMERIC_CHECK);
                        exit();
                    }
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Company not found.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            // Build fields for UPDATE
            $fields = [
                "`company_name`='$company_name'",
                "`address`='$address'",
                "`description`='$description'",
                "`pincode`='$pincode'",
                "`phone`='$phone'",
                "`mobile`='$mobile'",
                "`email_id`='$email_id'",
                "`gst_no`='$gst_no'",
                "`state`='$state'",
                "`city`='$city'",
                "`acc_number`='$acc_number'",
                "`acc_holder_name`='$acc_holder_name'",
                "`bank_name`='$bank_name'",
                "`ifsc_code`='$ifsc_code'",
                "`bank_branch`='$bank_branch'",
                "`minimum_order_value`='$minimum_order_value'",
                "`website_link`='$website_link'",
                "`facebook_link`='$facebook_link'",
                "`instagram_link`='$instagram_link'",
                "`youtube_link`='$youtube_link'",
                "`location_link`='$location_link'",
                "`upi_id`='$upi_id'"
            ];

            // Handle images if provided
            if (!empty($img_base64)) {
                $outputFilePath = "../uploads/companies/";
                $img_path = pngImageToWebP($img_base64, $outputFilePath);
                $fields[] = "`img`='$img_path'";
            }

            if (!empty($gpay_qr_base64)) {
                $outputFilePath = "../uploads/companies/";
                $gpay_path = pngImageToWebP($gpay_qr_base64, $outputFilePath);
                $fields[] = "`gpay_qr_code`='$gpay_path'";
            }

            if (!empty($phonepe_qr_base64)) {
                $outputFilePath = "../uploads/companies/";
                $phonepe_path = pngImageToWebP($phonepe_qr_base64, $outputFilePath);
                $fields[] = "`phonepe_qr_code`='$phonepe_path'";
            }

            if (!empty($paytm_qr_base64)) {
                $outputFilePath = "../uploads/companies/";
                $paytm_path = pngImageToWebP($paytm_qr_base64, $outputFilePath);
                $fields[] = "`paytm_qr_code`='$paytm_path'";
            }

            $updateCompany = "UPDATE `company` SET " . implode(', ', $fields) . " WHERE `company_id`='$edit_id'";

            if ($conn->query($updateCompany)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Company Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
            }
        } else {
            // Create mode
            $gstCheck = $conn->query("SELECT `id` FROM `company` WHERE `gst_no`='$gst_no'");
            if ($gstCheck->num_rows == 0) {
                // Build columns and values for INSERT
                $columns = [
                    "`company_name`",
                    "`address`",
                    "`description`",
                    "`pincode`",
                    "`phone`",
                    "`mobile`",
                    "`email_id`",
                    "`gst_no`",
                    "`state`",
                    "`city`",
                    "`acc_number`",
                    "`acc_holder_name`",
                    "`bank_name`",
                    "`ifsc_code`",
                    "`bank_branch`",
                    "`minimum_order_value`",
                    "`website_link`",
                    "`facebook_link`",
                    "`instagram_link`",
                    "`youtube_link`",
                    "`location_link`",
                    "`upi_id`",
                    "`created_at`"
                ];
                $values = [
                    "'$company_name'",
                    "'$address'",
                    "'$description'",
                    "'$pincode'",
                    "'$phone'",
                    "'$mobile'",
                    "'$email_id'",
                    "'$gst_no'",
                    "'$state'",
                    "'$city'",
                    "'$acc_number'",
                    "'$acc_holder_name'",
                    "'$bank_name'",
                    "'$ifsc_code'",
                    "'$bank_branch'",
                    "'$minimum_order_value'",
                    "'$website_link'",
                    "'$facebook_link'",
                    "'$instagram_link'",
                    "'$youtube_link'",
                    "'$location_link'",
                    "'$upi_id'",
                    "'$timestamp'"
                ];

                // Handle images if provided
                if (!empty($img_base64)) {
                    $outputFilePath = "../uploads/companies/";
                    $img_path = pngImageToWebP($img_base64, $outputFilePath);
                    $columns[] = "`img`";
                    $values[] = "'$img_path'";
                }

                if (!empty($gpay_qr_base64)) {
                    $outputFilePath = "../uploads/companies/";
                    $gpay_path = pngImageToWebP($gpay_qr_base64, $outputFilePath);
                    $columns[] = "`gpay_qr_code`";
                    $values[] = "'$gpay_path'";
                }

                if (!empty($phonepe_qr_base64)) {
                    $outputFilePath = "../uploads/companies/";
                    $phonepe_path = pngImageToWebP($phonepe_qr_base64, $outputFilePath);
                    $columns[] = "`phonepe_qr_code`";
                    $values[] = "'$phonepe_path'";
                }

                if (!empty($paytm_qr_base64)) {
                    $outputFilePath = "../uploads/companies/";
                    $paytm_path = pngImageToWebP($paytm_qr_base64, $outputFilePath);
                    $columns[] = "`paytm_qr_code`";
                    $values[] = "'$paytm_path'";
                }

                $createCompany = "INSERT INTO `company` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

                if ($conn->query($createCompany)) {
                    $id = $conn->insert_id;
                    $enid = uniqueID('company', $id);
                    $update = "UPDATE `company` SET `company_id`='$enid' WHERE `id` = $id";
                    $conn->query($update);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Company Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "GST Number Already Exists.";
            }
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
<<<<<<< HEAD
}

// <<<<<<<<<<===================== Image Delete =====================>>>>>>>>>>

else if (isset($obj->image_delete)) {

    $image_delete = $obj->image_delete;

    if ($image_delete === true && function_exists('ImageRemove')) {

        $status = ImageRemove('company', 1);
        if ($status == "company Image Removed Successfully") {
=======
} else if (isset($obj->delete_company_id) && isset($obj->image_delete)) {
    $delete_company_id = $obj->delete_company_id;
    $image_delete = $obj->image_delete;

    if (!empty($delete_company_id) && $image_delete === true) {
        $status = ImageRemove('company', $delete_company_id);
        if ($status == "Company Image Removed Successfully") {
>>>>>>> c954fe642283d8f29c40345ceef92793518e526a
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "successfully company Image deleted !.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "faild to deleted.please try againg.";
        }
    } else {
        $output["head"]["code"] = 400;
<<<<<<< HEAD
        $output["head"]["msg"] = "Parameter is Mismatch or ImageRemove function is missing.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

// Close the database connection at the end
$conn->close();

echo json_encode($output, JSON_NUMERIC_CHECK);
?>
=======
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_company_id)) {
    // <<<<<<<<<<===================== This is to Delete the companies =====================>>>>>>>>>>
    $delete_company_id = $obj->delete_company_id;
    if (!empty($delete_company_id)) {
        $deleteCompany = "UPDATE `company` SET `deleted_at`=1 WHERE `company_id`='$delete_company_id'";
        if ($conn->query($deleteCompany) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Company Deleted.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
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
