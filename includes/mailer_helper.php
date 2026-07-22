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
