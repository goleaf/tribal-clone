<?php

class UserManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Change a user's email address.
     *
     * @param int $user_id User ID.
     * @param string $new_email New email address.
     * @return array Operation result (success: bool, message: string).
     */
    public function changeEmail(int $user_id, string $new_email): array
    {
        $new_email = trim($new_email);

        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        // Check if email is already taken by another user
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if ($stmt === false) {
             error_log("UserManager::changeEmail prepare select failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'A system error occurred (select).'];
        }
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'The provided email is already in use.'];
        }
        $stmt->close();

        // Update the email
        $stmt_update = $this->conn->prepare("UPDATE users SET email = ? WHERE id = ?");
         if ($stmt_update === false) {
             error_log("UserManager::changeEmail prepare update failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'A system error occurred (update).'];
        }
        $stmt_update->bind_param("si", $new_email, $user_id);

        if ($stmt_update->execute()) {
            $stmt_update->close();
            return ['success' => true, 'message' => 'Email address has been updated.'];
        } else {
            error_log("UserManager::changeEmail execute update failed: " . $this->conn->error);
            $stmt_update->close();
            return ['success' => false, 'message' => 'An error occurred while updating the email.'];
        }
    }

    /**
     * Change a user's password.
     *
     * @param int $user_id User ID.
     * @param string $current_password Current password (plain).
     * @param string $new_password New password (plain).
     * @param string $confirm_password Confirmation of the new password (plain).
     * @return array Operation result (success: bool, message: string).
     */
    public function changePassword(int $user_id, string $current_password, string $new_password, string $confirm_password): array
    {
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All password fields are required.'];
        }

        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'The new password and confirmation do not match.'];
        }

        // Verify current password
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = ?");
         if ($stmt === false) {
             error_log("UserManager::changePassword prepare select failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'A system error occurred (select).'];
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);

        if (!$stmt->fetch()) {
            $stmt->close();
            // User not found, although protected by session check, good practice to handle
             return ['success' => false, 'message' => 'User not found.'];
        }
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            return ['success' => false, 'message' => 'The current password is incorrect.'];
        }

        // Hash the new password and update
        $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
         if ($stmt_update === false) {
             error_log("UserManager::changePassword prepare update failed: " . $this->conn->error);
             return ['success' => false, 'message' => 'A system error occurred (update).'];
        }
        $stmt_update->bind_param("si", $new_hashed, $user_id);

        if ($stmt_update->execute()) {
            $stmt_update->close();
            return ['success' => true, 'message' => 'Password changed successfully.'];
        } else {
            error_log("UserManager::changePassword execute update failed: " . $this->conn->error);
            $stmt_update->close();
            return ['success' => false, 'message' => 'An error occurred while changing the password.'];
        }
    }

    // Methods for user management will be added here

}

?> 
