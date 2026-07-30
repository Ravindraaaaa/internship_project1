<?php
// includes/email_helper.php

if (!function_exists('send_system_email')) {
    /**
     * Sends an HTML email to the specified user id.
     * 
     * @param int $user_id The recipient's user ID
     * @param string $subject The email subject
     * @param string $message The email body content (HTML allowed)
     * @return bool True if mail sent successfully, false otherwise.
     */
    function send_system_email($user_id, $subject, $message) {
        global $pdo;
        
        if (!$pdo || empty($user_id)) return false;

        // Try to fetch email from users table first
        $stmt = $pdo->prepare("SELECT email, name as full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Check admins table
            $stmt = $pdo->prepare("SELECT email, name as full_name FROM admins WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user || empty($user['email'])) return false;

        $to = $user['email'];
        $name = $user['full_name'] ?? 'User';

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: AlumniNet System <noreply@alumninet.local>" . "\r\n";
        
        $html_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 600px; margin: 0 auto; }
                .header { background-color: #f8fafc; padding: 15px; border-bottom: 2px solid #38bdf8; text-align: center; font-size: 1.2rem; font-weight: bold; }
                .content { padding: 20px; }
                .footer { font-size: 0.8rem; color: #64748b; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    AlumniNet Notification
                </div>
                <div class='content'>
                    <p>Hi $name,</p>
                    <p>$message</p>
                </div>
                <div class='footer'>
                    This is an automated message from the AlumniNet Platform. Please do not reply to this email.
                </div>
            </div>
        </body>
        </html>
        ";

        // Dispatch mail
        try {
            return mail($to, $subject, $html_body, $headers);
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }
}
?>
