<?php

declare(strict_types=1);

/*
 * ============================================================
 * BCP SCHEDULING SYSTEM
 * CENTRAL TRANSACTIONAL MAILER
 * ============================================================
 *
 * Provider:
 * Resend
 *
 * Transport:
 * HTTPS REST API
 *
 * Compatible with:
 * - XAMPP / localhost
 * - Railway deployment
 *
 * Required environment variables:
 *
 * RESEND_API_KEY
 * RESEND_FROM_EMAIL
 *
 * Example Railway variables:
 *
 * RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxx
 * RESEND_FROM_EMAIL=BCP Scheduling <onboarding@resend.dev>
 *
 * IMPORTANT:
 * Never hardcode the API key in this file.
 * ============================================================
 */

// PANG-CONNECT SA TEMPLATES FILE
require_once __DIR__ . '/email-templates.php';

/*
 * ============================================================
 * ENVIRONMENT VALUE
 * ============================================================
 *
 * getenv() is the primary source on Railway and XAMPP SetEnv.
 * $_ENV / $_SERVER are fallbacks for PHP configurations that
 * expose environment values through those superglobals.
 */

function bcpEnv(string $key): string
{
    $value = getenv($key);

    if ($value !== false) {
        $value = trim((string) $value);

        if ($value !== '') {
            return $value;
        }
    }

    if (isset($_ENV[$key])) {
        $value = trim((string) $_ENV[$key]);

        if ($value !== '') {
            return $value;
        }
    }

    if (isset($_SERVER[$key])) {
        $value = trim((string) $_SERVER[$key]);

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/*
 * ============================================================
 * HTML ESCAPE
 * ============================================================
 */

function bcpMailEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
 * ============================================================
 * GENERIC RESEND EMAIL SENDER
 * ============================================================
 */

function bcpMailerSend(
    string $recipientEmail,
    string $subject,
    string $html,
    ?string $idempotencyKey = null,
    string $category = 'password_reset'
): bool {

    /*
     * --------------------------------------------------------
     * LOAD RAILWAY / ENVIRONMENT VARIABLES
     * --------------------------------------------------------
     */

    $apiKey = bcpEnv(
        'RESEND_API_KEY'
    );


    $from = bcpEnv(
        'RESEND_FROM_EMAIL'
    );


    /*
     * --------------------------------------------------------
     * CONFIG VALIDATION
     * --------------------------------------------------------
     */

    if ($apiKey === '') {
        error_log('BCP Mailer: RESEND_API_KEY is missing.');
        return false;
    }


    if ($from === '') {
        error_log('BCP Mailer: RESEND_FROM_EMAIL is missing.');
        return false;
    }


    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('BCP Mailer: recipient email is invalid.');
        return false;
    }


    /*
     * Railway PHP Docker image already
     * includes the cURL extension.
     */
    if (!function_exists('curl_init')) {
        error_log('BCP Mailer: PHP cURL extension is unavailable.');
        return false;
    }


    /*
     * --------------------------------------------------------
     * RESEND REQUEST BODY
     * --------------------------------------------------------
     */

    try {

        $payload = json_encode(
            [
                'from' => $from,
                'to' => [$recipientEmail],
                'subject' => $subject,
                'html' => $html,
                'tags' => [
                    [
                        'name' => 'category',
                        'value' => (preg_match('/^[a-z0-9_-]{1,50}$/D', $category) === 1 ? $category : 'transactional')
                    ]
                ]
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (Throwable $e) {
        error_log('BCP Mailer JSON error: ' . $e->getMessage());
        return false;
    }


    /*
     * --------------------------------------------------------
     * HTTP HEADERS
     * --------------------------------------------------------
     */

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if (is_string($idempotencyKey) && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }


    /*
     * --------------------------------------------------------
     * INITIALIZE HTTPS REQUEST
     * --------------------------------------------------------
     */

    $curl = curl_init('https://api.resend.com/emails');

    if ($curl === false) {
        error_log('BCP Mailer: curl_init() failed.');
        return false;
    }


    /*
     * --------------------------------------------------------
     * CURL CONFIGURATION
     * --------------------------------------------------------
     */

    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        /* Never disable SSL verification in production */
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    /* 
     * FIX FOR XAMPP/WINDOWS LOCALHOST ONLY:
     * If curl fails due to SSL cert issue on local XAMPP, this ignores it ONLY if running on localhost. 
     * Note: Remove this in production if you want strict SSL.
     */
    if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
        $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
    }

    curl_setopt_array($curl, $curlOptions);


    /*
     * --------------------------------------------------------
     * SEND REQUEST
     * --------------------------------------------------------
     */

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);


    /*
     * --------------------------------------------------------
     * NETWORK ERROR
     * --------------------------------------------------------
     */

    if ($response === false || $curlError !== '') {
        error_log('BCP Mailer transport error: ' . $curlError);
        return false;
    }


    /*
     * --------------------------------------------------------
     * RESEND API ERROR
     * --------------------------------------------------------
     */

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            'BCP Mailer: Resend returned HTTP ' . $httpCode . '. Response: ' . substr((string) $response, 0, 500)
        );
        return false;
    }

    return true;
}


/*
 * ============================================================
 * PASSWORD RESET OTP SENDER
 * ============================================================
 */

function bcpSendPasswordResetOtp(
    string $recipientEmail,
    string $username,
    string $otp,
    int $expiryMinutes = 10,
    ?string $idempotencyKey = null
): bool {

    if (preg_match('/^\d{6}$/D', $otp) !== 1) {
        error_log('BCP Mailer: invalid OTP format.');
        return false;
    }

    $html = emailTemplatePasswordResetOtp($username, $otp, $expiryMinutes);

    return bcpMailerSend(
        $recipientEmail,
        'BCP Scheduling System - Password Reset OTP',
        $html,
        $idempotencyKey
    );
}

/*
 * ============================================================
 * LOGIN 2FA OTP SENDER
 * ============================================================
 */
function bcpSendLoginOtp(
    string $recipientEmail,
    string $username,
    string $otp,
    int $expiryMinutes = 10,
    ?string $idempotencyKey = null
): bool {

    if (preg_match('/^\d{6}$/D', $otp) !== 1) {
        error_log('BCP Mailer: invalid login OTP format.');
        return false;
    }

    $html = bcpBuildLoginOtpEmail($username, $otp, $expiryMinutes);

    return bcpMailerSend(
        $recipientEmail,
        'BCP Scheduling System - Login Verification Code',
        $html,
        $idempotencyKey,
        'login_2fa'
    );
}
