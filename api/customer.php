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

if (isset($obj->action) && $obj->action === 'send_otp' && isset($obj->email_id)) {
    $email_id = $obj->email_id;

    if (filter_var($email_id, FILTER_VALIDATE_EMAIL)) {
        // Check if recent OTP exists for this email (prevent spam, e.g., last 1 min)
        $stmtCheck = $conn->prepare("SELECT id FROM email_verification WHERE email_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND deleted_at = 0");
        $stmtCheck->bind_param('s', $email_id);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        if ($checkResult->num_rows > 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "OTP already sent recently. Please wait.";
        } else {
            // Generate 4-digit OTP
            $otp = sprintf("%04d", mt_rand(1, 9999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Get next sequential for email_verification_id
            $sql_count = "SELECT COUNT(*) as cnt FROM email_verification WHERE deleted_at = 0";
            $result_count = $conn->query($sql_count);
            $row_count = $result_count->fetch_assoc();
            $next_seq = (int)$row_count['cnt'] + 1;
            $email_verification_id = "mas_tub_email_" . sprintf("%03d", $next_seq);

            // Insert into email_verification
            $stmtInsert = $conn->prepare("INSERT INTO email_verification (`email_verification_id`, `email_id`, `otp`, `otp_expiry`, `created_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, 0)");
            $stmtInsert->bind_param('sssss', $email_verification_id, $email_id, $otp, $otp_expiry, $timestamp); // Fixed bind_param types
            if ($stmtInsert->execute()) {
                $stmtInsert->close();
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "OTP sent successfully";
                $output["body"]["otp"] = $otp; // Returned to frontend for EmailJS sending
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to generate OTP: " . $conn->error;
            }
        }
        $stmtCheck->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid Email.";
    }
} elseif (isset($obj->action) && $obj->action === 'verify_otp' && isset($obj->email_id) && isset($obj->otp)) {
    $email_id = $obj->email_id;
    $input_otp = $obj->otp;

    if (filter_var($email_id, FILTER_VALIDATE_EMAIL) && ctype_digit($input_otp) && strlen($input_otp) === 4) {
        // Verify OTP
        $stmtVerify = $conn->prepare("SELECT id FROM email_verification WHERE email_id = ? AND otp = ? AND otp_expiry > NOW() AND deleted_at = 0");
        $stmtVerify->bind_param('ss', $email_id, $input_otp);
        $stmtVerify->execute();
        $verifyResult = $stmtVerify->get_result();
        if ($verifyResult->num_rows > 0) {
            // Mark as used (soft delete)
            // $stmtUsed = $conn->prepare("UPDATE email_verification SET deleted_at = 1 WHERE email_id = ? AND otp = ?");
            // $stmtUsed->bind_param('ss', $email_id, $input_otp);
            // $stmtUsed->execute();
            // $stmtUsed->close();

            // Create partial customer (email only)
            // Check if email already exists
            $stmtEmailCheck = $conn->prepare("SELECT id FROM customers WHERE email_id = ? AND deleted_at = 0");
            $stmtEmailCheck->bind_param('s', $email_id);
            $stmtEmailCheck->execute();
            $email_check_result = $stmtEmailCheck->get_result();
            $email_exists = $email_check_result->num_rows > 0;
            $stmtEmailCheck->close();

            if ($email_exists) {
                $stmtNew = $conn->prepare("SELECT * FROM customers WHERE email_id = ? AND deleted_at = 0");
                $stmtNew->bind_param('s', $email_id);
                $stmtNew->execute();
                $resultNew = $stmtNew->get_result();
                $newCustomer = $resultNew->fetch_assoc();
                $stmtNew->close();

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "OTP verified. Login successful.";
                $output["body"]["customer"] = $newCustomer;
            } else {
                // Get next sequential ID for customer_no
                $sql_count = "SELECT COUNT(*) as cnt FROM customers WHERE deleted_at = 0";
                $result_count = $conn->query($sql_count);
                $row_count = $result_count->fetch_assoc();
                $next_seq = (int)$row_count['cnt'] + 1;
                $customer_no = "mas_tub_cus_" . sprintf("%03d", $next_seq);

                // Generate unique customer_id (assuming uniqueID function exists; fallback to uniqid if not)
                if (function_exists('uniqueID')) {
                    $customer_id = uniqueID("mas_tub_cus", $next_seq);
                } else {
                    $customer_id = "mas_tub_cus_" . uniqid();
                }

                // Partial insert: set empty values for required fields
                $first_name = '';
                $last_name = '';
                $phone_number = '';
                $insert_sql = "INSERT INTO customers (`customer_id`, `customer_no`, `first_name`, `last_name`, `phone_number`, `email_id`, `created_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
                $stmtInsert = $conn->prepare($insert_sql);
                $stmtInsert->bind_param('sssssss', $customer_id, $customer_no, $first_name, $last_name, $phone_number, $email_id, $timestamp);
                if ($stmtInsert->execute()) {
                    $stmtInsert->close();

                    // Fetch new customer
                    $internal_id = $conn->insert_id;
                    $stmtNew = $conn->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmtNew->bind_param('i', $internal_id);
                    $stmtNew->execute();
                    $resultNew = $stmtNew->get_result();
                    $newCustomer = $resultNew->fetch_assoc();
                    $stmtNew->close();

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "OTP verified. Customer created.";
                    $output["body"]["customer"] = $newCustomer;
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to create customer: " . $conn->error;
                }
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid or expired OTP.";
        }
        $stmtVerify->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid input.";
    }
} elseif (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list customers =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `customers` 
        WHERE `deleted_at` = 0 AND CONCAT(`first_name`, ' ', `last_name`) LIKE '%$search_text%'";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["customer"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Customer Details Not Found";
        $output["body"]["customer"] = [];
    }
} elseif (isset($obj->first_name) && isset($obj->last_name) && isset($obj->phone_number) && isset($obj->email_id)) {
    $first_name = $obj->first_name;
    $last_name = $obj->last_name;
    $phone_number = $obj->phone_number;
    $email_id = $obj->email_id;
    // Optional fields
    $date_of_birth = isset($obj->date_of_birth) ? $obj->date_of_birth : null;
    $gender = isset($obj->gender) ? $obj->gender : null;
    $delivery_address = isset($obj->delivery_address) ? $obj->delivery_address : null;
    $wishlist_products = isset($obj->wishlist_products) ? $obj->wishlist_products : null;
    if (!empty($first_name) && !empty($last_name) && !empty($phone_number) && !empty($email_id)) {
        $full_name = $first_name . ' ' . $last_name;
        if (!preg_match('/[^a-zA-Z0-9., ]+/', $full_name)) {
            if (ctype_digit($phone_number) && strlen($phone_number) == 10) {
                if (filter_var($email_id, FILTER_VALIDATE_EMAIL)) {
                    // Validate optional fields if provided
                    $dob_valid = true;
                    if ($date_of_birth) {
                        $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
                        $dob_valid = $dob && $dob->format('Y-m-d') === $date_of_birth;
                    }
                    $gender_valid = !$gender || in_array($gender, ['Male', 'Female', 'Other']);
                    $address_valid = !$delivery_address || !preg_match('/[^a-zA-Z0-9., ]+/', $delivery_address); // Basic alphanumeric check
                    $wishlist_valid = !$wishlist_products || is_string($wishlist_products); // Assume JSON string
                    if ($dob_valid && $gender_valid && $address_valid && $wishlist_valid) {
                        if (isset($obj->edit_customer_id)) {
                            $edit_id = $obj->edit_customer_id;
                            if ($edit_id) {
                                // Fetch Old Data
                                $stmtOld = $conn->prepare("SELECT * FROM customers WHERE customer_id = ? AND deleted_at = 0");
                                $stmtOld->bind_param('s', $edit_id);
                                $stmtOld->execute();
                                $resultOld = $stmtOld->get_result();
                                $oldCustomer = $resultOld->fetch_assoc();
                                $stmtOld->close();
                                if (!$oldCustomer) {
                                    $output["head"]["code"] = 400;
                                    $output["head"]["msg"] = "Customer not found.";
                                } else {
                                    // Check if new email is unique (if changed)
                                    $email_check = false;
                                    $phone_check = false; // Initialize phone_check to false
                                    if ($oldCustomer['email_id'] !== $email_id) {
                                        $stmtEmailCheck = $conn->prepare("SELECT id FROM customers WHERE email_id = ? AND deleted_at = 0 AND customer_id != ?");
                                        $stmtEmailCheck->bind_param('ss', $email_id, $edit_id);
                                        $stmtEmailCheck->execute();
                                        $email_check_result = $stmtEmailCheck->get_result();
                                        $email_check = $email_check_result->num_rows > 0;
                                        $stmtEmailCheck->close();
                                    }
                                    if ($oldCustomer['phone_number'] !== $phone_number) {
                                        $stmtPhoneCheck = $conn->prepare("SELECT id FROM customers WHERE phone_number = ? AND deleted_at = 0 AND customer_id != ?");
                                        $stmtPhoneCheck->bind_param('ss', $phone_number, $edit_id);
                                        $stmtPhoneCheck->execute();
                                        $phone_check = $stmtPhoneCheck->get_result()->num_rows > 0;
                                        $stmtPhoneCheck->close();
                                    }
                                    if ($email_check) {
                                        $output["head"]["code"] = 400;
                                        $output["head"]["msg"] = "Email already in use.";
                                    } elseif ($phone_check) {
                                        $output["head"]["code"] = 400;
                                        $output["head"]["msg"] = "Phone number already in use.";
                                    } else {
                                        // Build dynamic update query for optional fields
                                        $update_fields = [
                                            "first_name = ?",
                                            "last_name = ?",
                                            "phone_number = ?",
                                            "email_id = ?"
                                        ];
                                        $update_params = [$first_name, $last_name, $phone_number, $email_id];
                                        if ($date_of_birth) {
                                            $update_fields[] = "date_of_birth = ?";
                                            $update_params[] = $date_of_birth;
                                        }
                                        if ($gender) {
                                            $update_fields[] = "gender = ?";
                                            $update_params[] = $gender;
                                        }
                                        if ($delivery_address) {
                                            $update_fields[] = "delivery_address = ?";
                                            $update_params[] = $delivery_address;
                                        }
                                        if ($wishlist_products) {
                                            $update_fields[] = "wishlist_products = ?";
                                            $update_params[] = $wishlist_products;
                                        }
                                        $updateCustomer = "UPDATE customers SET " . implode(', ', $update_fields) . " WHERE customer_id = ?";
                                        $update_params[] = $edit_id;
                                        $stmtUpdate = $conn->prepare($updateCustomer);
                                        if ($stmtUpdate) {
                                            $stmtUpdate->bind_param(str_repeat('s', count($update_params)), ...$update_params);
                                            if ($stmtUpdate->execute()) {
                                                $stmtUpdate->close();
                                                // Fetch new data using internal id
                                                $internal_id = $oldCustomer['id'];
                                                $stmtNew = $conn->prepare("SELECT * FROM customers WHERE id = ?");
                                                $stmtNew->bind_param('i', $internal_id);
                                                $stmtNew->execute();
                                                $resultNew = $stmtNew->get_result();
                                                $newCustomer = $resultNew->fetch_assoc();
                                                $stmtNew->close();
                                                $output["head"]["code"] = 200;
                                                $output["head"]["msg"] = "Successfully Customer Details Updated";
                                                $output["body"]["customer"] = $newCustomer; // Added for consistency
                                            } else {
                                                $output["head"]["code"] = 400;
                                                $output["head"]["msg"] = "Failed to update: " . $conn->error;
                                            }
                                        } else {
                                            $output["head"]["code"] = 400;
                                            $output["head"]["msg"] = "Prepare failed: " . $conn->error;
                                        }
                                    }
                                }
                            } else {
                                $output["head"]["code"] = 400;
                                $output["head"]["msg"] = "Customer not found.";
                            }
                        } else {
                            // Creation logic (full create)
                            // Check if email already exists
                            $stmtEmailCheck = $conn->prepare("SELECT id FROM customers WHERE email_id = ? AND deleted_at = 0");
                            $stmtEmailCheck->bind_param('s', $email_id);
                            $stmtEmailCheck->execute();
                            $email_check_result = $stmtEmailCheck->get_result();
                            $email_exists = $email_check_result->num_rows > 0;
                            $stmtEmailCheck->close();
                            if ($email_exists) {
                                $output["head"]["code"] = 400;
                                $output["head"]["msg"] = "Email already in use.";
                            } else {
                                // Get next sequential ID for customer_no
                                $sql_count = "SELECT COUNT(*) as cnt FROM customers WHERE deleted_at = 0";
                                $result_count = $conn->query($sql_count);
                                $row_count = $result_count->fetch_assoc();
                                $next_seq = (int)$row_count['cnt'] + 1;
                                $customer_no = "mas_tub_cus_" . sprintf("%03d", $next_seq);
                                // Generate unique customer_id
                                if (function_exists('uniqueID')) {
                                    $customer_id = uniqueID("mas_tub_cus", $next_seq);
                                } else {
                                    $customer_id = "mas_tub_cus_" . uniqid();
                                }
                                // Build dynamic insert for optional fields
                                $insert_fields = [
                                    "`customer_id`",
                                    "`customer_no`",
                                    "`first_name`",
                                    "`last_name`",
                                    "`phone_number`",
                                    "`email_id`",
                                    "`created_at`",
                                    "`deleted_at`"
                                ];
                                $insert_placeholders = ["?", "?", "?", "?", "?", "?", "?", "0"];
                                $insert_params = [$customer_id, $customer_no, $first_name, $last_name, $phone_number, $email_id, $timestamp];
                                if ($date_of_birth) {
                                    $insert_fields[] = "`date_of_birth`";
                                    $insert_placeholders[] = "?";
                                    $insert_params[] = $date_of_birth;
                                }
                                if ($gender) {
                                    $insert_fields[] = "`gender`";
                                    $insert_placeholders[] = "?";
                                    $insert_params[] = $gender;
                                }
                                if ($delivery_address) {
                                    $insert_fields[] = "`delivery_address`";
                                    $insert_placeholders[] = "?";
                                    $insert_params[] = $delivery_address;
                                }
                                if ($wishlist_products) {
                                    $insert_fields[] = "`wishlist_products`";
                                    $insert_placeholders[] = "?";
                                    $insert_params[] = $wishlist_products;
                                }
                                $insert_sql = "INSERT INTO customers (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                                $stmtInsert = $conn->prepare($insert_sql);
                                if ($stmtInsert) {
                                    $stmtInsert->bind_param(str_repeat('s', count($insert_params)), ...$insert_params);
                                    if ($stmtInsert->execute()) {
                                        $stmtInsert->close();
                                        // Fetch new data using last insert id
                                        $internal_id = $conn->insert_id;
                                        $stmtNew = $conn->prepare("SELECT * FROM customers WHERE id = ?");
                                        $stmtNew->bind_param('i', $internal_id);
                                        $stmtNew->execute();
                                        $resultNew = $stmtNew->get_result();
                                        $newCustomer = $resultNew->fetch_assoc();
                                        $stmtNew->close();
                                        $output["head"]["code"] = 200;
                                        $output["head"]["msg"] = "Successfully Customer Created";
                                        $output["body"]["customer"] = $newCustomer;
                                    } else {
                                        $output["head"]["code"] = 400;
                                        $output["head"]["msg"] = "Failed to create: " . $conn->error;
                                    }
                                } else {
                                    $output["head"]["code"] = 400;
                                    $output["head"]["msg"] = "Prepare failed: " . $conn->error;
                                }
                            }
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Invalid optional fields (date_of_birth format: YYYY-MM-DD, gender: Male/Female/Other, address/wishlist: alphanumeric).";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Invalid Email.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Phone Number.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Customer Name Should be Alphanumeric.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} 


elseif (isset($obj->action) && $obj->action === 'get_profile' && isset($obj->customer_id)) {
    $customer_id = $obj->customer_id;
    
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ? AND deleted_at = 0");
    $stmt->bind_param('s', $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $customerData = $result->fetch_assoc();
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["customer"] = $customerData;
    } else {
        $output["head"]["code"] = 404;
        $output["head"]["msg"] = "Customer Not Found";
    }
    $stmt->close();
}

elseif (isset($obj->delete_customer_id)) {
    // <<<<<<<<<<===================== This is to Delete the customers =====================>>>>>>>>>>
    $delete_customer_id = $obj->delete_customer_id;
    if (!empty($delete_customer_id)) {
        if ($delete_customer_id) {
            // First, get the internal customer ID and old data
            $stmt = $conn->prepare("SELECT * FROM `customers` WHERE `customer_id` = ? AND `deleted_at` = 0");
            $stmt->bind_param('s', $delete_customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $output["head"]["code"] = 404;
                $output["head"]["msg"] = "Customer not found.";
            } else {
                $oldCustomer = $result->fetch_assoc();
                $internal_customer_id = $oldCustomer['id'];

                // Soft delete related chits (with error handling if table doesn't exist)
                $chits_deleted = true; // Default to true if no table or success
                try {
                    $deleteChits = $conn->prepare("UPDATE `chits` SET `deleted_at` = 1, `deleted_at_datetime` = NOW() WHERE `customer_id` = ?");
                    $deleteChits->bind_param('i', $internal_customer_id);
                    $chits_deleted = $deleteChits->execute();
                    $deleteChits->close();
                } catch (mysqli_sql_exception $e) {
                    // Table doesn't exist or other SQL error; assume no chits to delete
                    $chits_deleted = true;
                }

                // Soft delete the customer
                $deleteCustomer = $conn->prepare("UPDATE `customers` SET `deleted_at` = 1 WHERE `customer_id` = ?");
                $deleteCustomer->bind_param('s', $delete_customer_id);
                $customer_deleted = $deleteCustomer->execute();
                $deleteCustomer->close();

                if ($customer_deleted && $chits_deleted) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Customer and related Chits Deleted.";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to delete. Please try again.";
                }
            }
            $stmt->close();
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
