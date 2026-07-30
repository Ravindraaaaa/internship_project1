<?php
/**
 * Real-Time Socket-Level SMTP Mailer Helper
 */

if (!function_exists('send_smtp_email')) {
    function send_smtp_email($to_email, $subject, $html_body, $to_name = '') {
        $config_file = __DIR__ . '/../config/smtp.php';
        $config = file_exists($config_file) ? require $config_file : [];

        $enabled    = $config['enabled'] ?? (defined('SMTP_ENABLED') ? SMTP_ENABLED : true);
        $host       = $config['host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
        $port       = intval($config['port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 465));
        $encryption = strtolower($config['encryption'] ?? (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'ssl'));
        $username   = trim($config['username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''));
        $password   = str_replace(' ', '', trim($config['password'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '')));
        $from_email = trim($config['from_email'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $username));
        $from_name  = trim($config['from_name'] ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'AlumniNet Security Team'));

        // Check if real SMTP credentials are standard dummy values
        if (!$enabled || empty($username) || empty($password) || strpos($password, 'xxxx') !== false) {
            // Attempt standard PHP mail() fallback
            $headers  = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: {$from_name} <{$from_email}>" . "\r\n";

            $sent = @mail($to_email, $subject, $html_body, $headers);
            return [
                'success' => $sent,
                'method' => 'mail',
                'message' => $sent ? 'Email dispatched via PHP mail().' : 'PHP mail() attempted. SMTP credentials recommended for guaranteed Gmail/Mobile inbox delivery.'
            ];
        }

        // Real Socket-based SMTP Connection
        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : (($encryption === 'tls') ? 'tls://' : '');
            $socket_url = $prefix . $host;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client($socket_url . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                throw new Exception("Socket connection failed: $errstr ($errno)");
            }

            stream_set_timeout($socket, 10);

            $read_response = function() use ($socket) {
                $response = '';
                while ($str = fgets($socket, 512)) {
                    $response .= $str;
                    if (substr($str, 3, 1) == ' ') break;
                }
                return $response;
            };

            $send_command = function($command) use ($socket, $read_response) {
                fputs($socket, $command . "\r\n");
                return $read_response();
            };

            // Read welcome greeting
            $greeting = $read_response();
            if (substr($greeting, 0, 3) != '220') {
                throw new Exception("Server response error: $greeting");
            }

            // EHLO
            $send_command("EHLO " . gethostname());

            // STARTTLS if port 587
            if ($encryption === 'tls' && $port == 587) {
                $tls_res = $send_command("STARTTLS");
                if (substr($tls_res, 0, 3) != '220') {
                    throw new Exception("STARTTLS failed: $tls_res");
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $send_command("EHLO " . gethostname());
            }

            // AUTH LOGIN
            $auth_res = $send_command("AUTH LOGIN");
            if (substr($auth_res, 0, 3) != '334') {
                throw new Exception("AUTH LOGIN rejected: $auth_res");
            }

            $user_res = $send_command(base64_encode($username));
            if (substr($user_res, 0, 3) != '334') {
                throw new Exception("Username rejected: $user_res");
            }

            $pass_res = $send_command(base64_encode($password));
            if (substr($pass_res, 0, 3) != '235') {
                throw new Exception("Authentication failed (Check password/App Password): $pass_res");
            }

            // MAIL FROM
            $from_res = $send_command("MAIL FROM: <$from_email>");
            if (substr($from_res, 0, 3) != '250') {
                throw new Exception("MAIL FROM rejected: $from_res");
            }

            // RCPT TO
            $rcpt_res = $send_command("RCPT TO: <$to_email>");
            if (substr($rcpt_res, 0, 3) != '250') {
                throw new Exception("RCPT TO rejected: $rcpt_res");
            }

            // DATA
            $data_res = $send_command("DATA");
            if (substr($data_res, 0, 3) != '354') {
                throw new Exception("DATA rejected: $data_res");
            }

            // Build MIME Message Header & Body
            $recipient_str = !empty($to_name) ? "$to_name <$to_email>" : "<$to_email>";
            $mime_message  = "From: $from_name <$from_email>\r\n";
            $mime_message .= "To: $recipient_str\r\n";
            $mime_message .= "Subject: $subject\r\n";
            $mime_message .= "MIME-Version: 1.0\r\n";
            $mime_message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mime_message .= "Date: " . date('r') . "\r\n";
            $mime_message .= "X-Mailer: AlumniNet Real-Time Mailer\r\n\r\n";
            $mime_message .= $html_body . "\r\n.";

            $send_res = $send_command($mime_message);
            if (substr($send_res, 0, 3) != '250') {
                throw new Exception("Failed sending message body: $send_res");
            }

            $send_command("QUIT");
            fclose($socket);

            return [
                'success' => true,
                'method' => 'smtp',
                'message' => 'Email sent successfully via real-time SMTP to ' . $to_email
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'method' => 'smtp',
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Enterprise Responsive HTML Email Template Generator
 */
if (!function_exists('build_enterprise_email_template')) {
    function build_enterprise_email_template($title, $body_content, $action_url = null, $action_text = null) {
        $year = date('Y');
        $btn_html = '';
        if ($action_url && $action_text) {
            $btn_html = "
            <div style='text-align: center; margin: 2rem 0;'>
                <a href='{$action_url}' style='background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; display: inline-block; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);'>
                    {$action_text}
                </a>
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #e2e8f0;'>
            <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background-color: #0f172a; padding: 40px 10px;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' width='100%' style='max-width: 600px; background-color: #1e293b; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px rgba(0,0,0,0.4);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #1e1b4b 0%, #311b92 100%); padding: 30px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);'>
                                    <div style='display: inline-block; width: 64px; height: 64px; background: rgba(255,255,255,0.1); border-radius: 50%; padding: 12px; margin-bottom: 12px;'>
                                        <img src='https://img.icons8.com/color/96/graduation-cap.png' alt='Logo' width='40' height='40' style='display: block; margin: 0 auto;'>
                                    </div>
                                    <h1 style='color: #ffffff; font-size: 1.5rem; margin: 0; font-weight: 700; letter-spacing: -0.5px;'>AlumniNet Enterprise</h1>
                                    <p style='color: #a5b4fc; font-size: 0.85rem; margin: 4px 0 0 0;'>Official Institution Communication Portal</p>
                                </td>
                            </tr>
                            <!-- Body Content -->
                            <tr>
                                <td style='padding: 32px 30px; color: #cbd5e1; font-size: 0.95rem; line-height: 1.6;'>
                                    <h2 style='color: #f8fafc; font-size: 1.25rem; margin-top: 0; margin-bottom: 16px; font-weight: 600;'>{$title}</h2>
                                    {$body_content}
                                    {$btn_html}
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #0f172a; padding: 24px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.8rem; color: #64748b;'>
                                    <p style='margin: 0 0 8px 0;'>&copy; {$year} AlumniNet Platform. All rights reserved.</p>
                                    <p style='margin: 0; color: #475569;'>Need assistance? Contact Support at <a href='mailto:support@alumninet.edu' style='color: #818cf8; text-decoration: none;'>support@alumninet.edu</a></p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }
}

/**
 * Logged Email Dispatcher
 */
if (!function_exists('send_logged_email')) {
    function send_logged_email($to_email, $subject, $html_body, $to_name = '', $category = 'general') {
        global $pdo;
        
        $res = send_smtp_email($to_email, $subject, $html_body, $to_name);
        $status = ($res['success'] ?? false) ? 'Sent' : 'Failed';
        $response_msg = $res['message'] ?? $res['error'] ?? 'No response details';

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO email_logs (recipient_email, subject, category, status, smtp_response) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$to_email, $subject, $category, $status, $response_msg]);
            } catch (Exception $e) {
                error_log("Failed writing to email_logs: " . $e->getMessage());
            }
        }

        return $res;
    }
}

/**
 * Get Dynamic Admin Recipient Email for Alerts & System Notifications
 */
if (!function_exists('get_admin_email')) {
    function get_admin_email() {
        global $pdo;
        
        $config_file = __DIR__ . '/../config/smtp.php';
        $config = file_exists($config_file) ? require $config_file : [];

        $from_email = trim($config['from_email'] ?? $config['username'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USERNAME') ? SMTP_USERNAME : '')));
        if (!empty($from_email) && filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            return $from_email;
        }

        if (isset($pdo)) {
            try {
                $stmt = $pdo->query("SELECT email FROM admins WHERE email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1");
                $admin_db_email = $stmt->fetchColumn();
                if (!empty($admin_db_email) && filter_var($admin_db_email, FILTER_VALIDATE_EMAIL)) {
                    return $admin_db_email;
                }
            } catch (Exception $e) {
                // Failover
            }
        }

        return 'admin@internship.com';
    }
}

