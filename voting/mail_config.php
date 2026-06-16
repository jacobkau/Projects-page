<?php
// mail_config.php - Using environment variable from Render

function sendEmailWithResend($to, $username, $resetLink) {
    // Get API key from environment variable
    $apiKey = getenv('RESEND_API_KEY');
    
    // If API key is not set in environment, try to use a hardcoded fallback (for testing only)
    if (empty($apiKey)) {
        error_log("RESEND_API_KEY environment variable is not set!");
         $apiKey = 're_f5bm2AgE_2JqwgEypNCgaUDS96SZP51f5'; 
        return ['success' => false, 'message' => 'API key not configured'];
    }
    
    $subject = "Password Reset - Voting System";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Password Reset</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
            .warning { background: #fef3c7; padding: 10px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🗳️ Voting System</h2>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                <p>We received a request to reset your password for your Voting System account.</p>
                <div style='text-align: center;'>
                    <a href='" . $resetLink . "' class='button'>🔐 Reset Password</a>
                </div>
                <div class='warning'>
                    <strong>⚠️ Important:</strong> This link will expire in <strong>1 hour</strong>.
                </div>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p><small style='word-break: break-all; color: #667eea;'>" . $resetLink . "</small></p>
                <p>If you didn't request this password reset, please ignore this email.</p>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;'>
                <p style='font-size: 12px; color: #6b7280;'>This is an automated message, please do not reply.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Voting System. All rights reserved.</p>
                <p>Secure voting platform</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Prepare the email data
    $emailData = [
        'from' => 'Voting System <onboarding@resend.dev>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $html
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the full response for debugging
    error_log("Resend API Response: HTTP $httpCode - Response: " . $response);
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => 'Password reset email sent successfully!'];
    } else {
        $errorMessage = "Failed to send email (HTTP $httpCode)";
        if ($response) {
            $decoded = json_decode($response, true);
            if (isset($decoded['message'])) {
                $errorMessage .= " - " . $decoded['message'];
            }
        }
        if ($curlError) {
            $errorMessage .= " - cURL Error: " . $curlError;
        }
        return ['success' => false, 'message' => $errorMessage];
    }
}

// Alternative fallback using native mail() function
function sendEmailFallback($to, $username, $resetLink) {
    $subject = "Password Reset - Voting System";
    
    $body = "
    <html>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello $username,</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='$resetLink'>$resetLink</a></p>
        <p>This link expires in 1 hour.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Voting System <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    return mail($to, $subject, $body, $headers);
}
?>
