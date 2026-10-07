<?php

declare(strict_types=1);

/*
 * ============================================================
 * BCP EMAIL TEMPLATES (ENTERPRISE GRADE)
 * ============================================================
 */

function emailTemplatePasswordResetOtp(
    string $username,
    string $otp,
    int $expiryMinutes = 10
): string {

    $safeUsername = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeExpiry = max(1, $expiryMinutes);

    return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Password Reset Verification</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fa; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; color: #1e293b;">

    <!-- 100% Background Wrapper -->
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f7fa" style="background-color: #f4f7fa; width: 100%;">
        <tr>
            <td align="center" valign="top" style="padding: 40px 15px;">
                
                <!-- Main Email Container -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                    
                    <!-- BCP Blue Header -->
                    <tr>
                        <td align="center" bgcolor="#1a3a8c" style="background-color: #1a3a8c; padding: 40px 20px; text-align: center;">
                            <!-- NOTE: Replace the src below with your LIVE URL when hosted -->
                            <img src="https://your-live-domain.com/assets/images/BCP_LOGO.png" alt="BCP Logo" width="70" style="display: block; margin: 0 auto 15px; border: 0; outline: none; text-decoration: none;">
                            
                            <p style="margin: 0; color: #93c5fd; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">
                                Bestlink College of the Philippines
                            </p>
                            <p style="margin: 8px 0 0 0; color: #ffffff; font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; letter-spacing: -0.5px;">
                                Scheduling System
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td align="left" style="padding: 40px 30px;">
                            
                            <p style="margin: 0 0 10px 0; color: #3b82f6; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">
                                Account Security
                            </p>
                            
                            <h1 style="margin: 0 0 20px 0; font-family: Arial, sans-serif; font-size: 22px; font-weight: bold; color: #0f172a;">
                                Password Reset Request
                            </h1>
                            
                            <p style="margin: 0 0 16px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                Hello <strong>' . $safeUsername . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 30px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                We received a request to reset the password for your academic scheduling account. Please use the verification code below to securely complete the process.
                            </p>

                            <!-- High-Impact OTP Box -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" bgcolor="#f0f5ff" style="background-color: #f0f5ff; border: 2px dashed #b6cbf0; border-radius: 12px; padding: 25px 20px;">
                                        <p style="margin: 0 0 10px 0; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; color: #64748b; letter-spacing: 2px; text-transform: uppercase;">
                                            Your Verification Code
                                        </p>
                                        <p style="margin: 0; font-family: Arial, sans-serif; font-size: 38px; font-weight: bold; color: #1a3a8c; letter-spacing: 10px;">
                                            ' . $safeOtp . '
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #64748b;">
                                This code will expire in <strong>' . $safeExpiry . ' minutes</strong>. For your security, do not share this code with anyone. BCP personnel will never ask for this code.
                            </p>

                            <!-- Divider -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 30px 0;">
                                <tr>
                                    <td height="1" bgcolor="#e2e8f0" style="background-color: #e2e8f0; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #94a3b8;">
                                If you did not request this password reset, please ignore this email or contact your IT administrator. Your password will remain unchanged.
                            </p>
                            
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" bgcolor="#f8fafc" style="background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #f1f5f9;">
                            <p style="margin: 0 0 8px 0; font-family: Arial, sans-serif; font-size: 12px; font-weight: bold; color: #64748b;">
                                Bestlink College of the Philippines
                            </p>
                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #94a3b8;">
                                1071 Brgy. 170, Novaliches, Caloocan City<br>
                                Automated security message — please do not reply.
                            </p>
                        </td>
                    </tr>

                </table>
                
                <!-- Extra space at bottom -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr><td height="40" style="font-size: 0; line-height: 0;">&nbsp;</td></tr>
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>';
}


function bcpBuildLoginOtpEmail(
    string $username,
    string $otp,
    int $expiryMinutes = 10
): string {

    $safeUsername = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeExpiryMinutes = max(1, $expiryMinutes);

    return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Login Verification</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fa; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; color: #1e293b;">

    <!-- 100% Background Wrapper -->
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f7fa" style="background-color: #f4f7fa; width: 100%;">
        <tr>
            <td align="center" valign="top" style="padding: 40px 15px;">
                
                <!-- Main Email Container -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                    
                    <!-- BCP Blue Header -->
                    <tr>
                        <td align="center" bgcolor="#1a3a8c" style="background-color: #1a3a8c; padding: 40px 20px; text-align: center;">
                            <!-- NOTE: Replace the src below with your LIVE URL when hosted -->
                            <img src="assets/images/BCP_LOGO.png" alt="BCP Logo" width="70" style="display: block; margin: 0 auto 15px; border: 0; outline: none; text-decoration: none;">
                            
                            <p style="margin: 0; color: #93c5fd; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">
                                Bestlink College of the Philippines
                            </p>
                            <p style="margin: 8px 0 0 0; color: #ffffff; font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; letter-spacing: -0.5px;">
                                Scheduling System
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td align="left" style="padding: 40px 30px;">
                            
                            <p style="margin: 0 0 10px 0; color: #3b82f6; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">
                                Login Security
                            </p>
                            
                            <h1 style="margin: 0 0 20px 0; font-family: Arial, sans-serif; font-size: 22px; font-weight: bold; color: #0f172a;">
                                Sign-in Verification
                            </h1>
                            
                            <p style="margin: 0 0 16px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                Hello <strong>' . $safeUsername . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 30px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                A sign-in attempt was made for your BCP Scheduling System account. Use the one-time verification code below to securely continue logging in.
                            </p>

                            <!-- High-Impact OTP Box -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" bgcolor="#f0f5ff" style="background-color: #f0f5ff; border: 2px dashed #b6cbf0; border-radius: 12px; padding: 25px 20px;">
                                        <p style="margin: 0 0 10px 0; font-family: Arial, sans-serif; font-size: 11px; font-weight: bold; color: #64748b; letter-spacing: 2px; text-transform: uppercase;">
                                            Your Login Verification Code
                                        </p>
                                        <p style="margin: 0; font-family: Arial, sans-serif; font-size: 38px; font-weight: bold; color: #1a3a8c; letter-spacing: 10px;">
                                            ' . $safeOtp . '
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #64748b;">
                                This code will expire in <strong>' . $safeExpiryMinutes . ' minutes</strong>. For your security, do not share this code with anyone.
                            </p>

                            <!-- Divider -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 30px 0;">
                                <tr>
                                    <td height="1" bgcolor="#e2e8f0" style="background-color: #e2e8f0; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #94a3b8;">
                                If you did not attempt to sign in, you may safely ignore this email and consider changing your password immediately.
                            </p>
                            
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" bgcolor="#f8fafc" style="background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #f1f5f9;">
                            <p style="margin: 0 0 8px 0; font-family: Arial, sans-serif; font-size: 12px; font-weight: bold; color: #64748b;">
                                Bestlink College of the Philippines
                            </p>
                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #94a3b8;">
                                1071 Brgy. 170, Novaliches, Caloocan City<br>
                                Automated security message — please do not reply.
                            </p>
                        </td>
                    </tr>

                </table>
                
                <!-- Extra space at bottom -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr><td height="40" style="font-size: 0; line-height: 0;">&nbsp;</td></tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>';
}