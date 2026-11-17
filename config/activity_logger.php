
<?php

function logActivity($conn, $userId, $action) {

    try {
        $stmt = $conn->prepare(
            "INSERT INTO activity_logs
             (user_id, action, created_at)
             VALUES (?, ?, NOW())"
        );

        $stmt->bind_param(
            "is",
            $userId,
            $action
        );

        $stmt->execute();
        $stmt->close();

        return true;

    } catch (Throwable $e) {

        error_log(
            "Activity logging error: " . $e->getMessage()
        );

        return false;
    }
}
