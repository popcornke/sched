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
 * PASSWORD RESET OTP EMAIL TEMPLATE
 * ============================================================
 */

function bcpBuildPasswordResetOtpEmail(
    string $username,
    string $otp,
    int $expiryMinutes = 10
): string {

    $safeUsername = bcpMailEscape(
        $username
    );

    $safeOtp = bcpMailEscape(
        $otp
    );

    $safeExpiryMinutes = max(
        1,
        $expiryMinutes
    );


    return '
<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        BCP Password Reset Verification
    </title>

</head>


<body
    style="
        margin: 0;
        padding: 0;
        background: #f4f6fa;
        font-family: Arial, Helvetica, sans-serif;
        color: #172033;
    "
>


<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width: 100%;
        background: #f4f6fa;
    "
>

    <tr>

        <td
            align="center"
            style="
                padding: 40px 16px;
            "
        >


            <table
                role="presentation"
                width="560"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 560px;
                    background: #ffffff;
                    border: 1px solid #e2e7ef;
                    border-radius: 16px;
                    overflow: hidden;
                "
            >


                <!-- HEADER -->
                <tr>

                    <td
                        style="
                            background: #173f8f;
                            padding: 26px 30px;
                        "
                    >

                        <div
                            style="
                                color: #cbd8f4;
                                font-size: 12px;
                                font-weight: 700;
                                letter-spacing: 1.2px;
                            "
                        >
                            BESTLINK COLLEGE OF THE PHILIPPINES
                        </div>


                        <div
                            style="
                                margin-top: 7px;
                                color: #ffffff;
                                font-size: 20px;
                                font-weight: 800;
                            "
                        >
                            BCP Scheduling System
                        </div>

                    </td>

                </tr>


                <!-- CONTENT -->
                <tr>

                    <td
                        style="
                            padding: 34px 30px;
                        "
                    >


                        <div
                            style="
                                margin-bottom: 10px;
                                color: #315aa8;
                                font-size: 11px;
                                font-weight: 800;
                                letter-spacing: 1px;
                            "
                        >
                            ACCOUNT SECURITY
                        </div>


                        <h1
                            style="
                                margin: 0 0 18px;
                                color: #172033;
                                font-size: 24px;
                                line-height: 1.3;
                            "
                        >
                            Password Reset Verification
                        </h1>


                        <p
                            style="
                                margin: 0 0 14px;
                                color: #46546a;
                                font-size: 15px;
                                line-height: 1.7;
                            "
                        >
                            Hello <strong>'
                            . $safeUsername .
                            '</strong>,
                        </p>


                        <p
                            style="
                                margin: 0;
                                color: #46546a;
                                font-size: 15px;
                                line-height: 1.7;
                            "
                        >
                            We received a request to reset the password
                            for your BCP Scheduling System account.
                            Use the one-time verification code below
                            to continue.
                        </p>


                        <!-- OTP BOX -->
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="
                                margin: 28px 0;
                            "
                        >

                            <tr>

                                <td
                                    align="center"
                                    style="
                                        padding: 24px 16px;
                                        background: #f0f5ff;
                                        border: 1px solid #d8e3fb;
                                        border-radius: 12px;
                                    "
                                >


                                    <div
                                        style="
                                            margin-bottom: 11px;
                                            color: #67768e;
                                            font-size: 11px;
                                            font-weight: 700;
                                            letter-spacing: 1.1px;
                                        "
                                    >
                                        YOUR ONE-TIME PASSWORD
                                    </div>


                                    <div
                                        style="
                                            color: #173f8f;
                                            font-size: 36px;
                                            font-weight: 800;
                                            line-height: 1;
                                            letter-spacing: 9px;
                                        "
                                    >
                                        '
                                        . $safeOtp .
                                        '
                                    </div>


                                </td>

                            </tr>

                        </table>


                        <p
                            style="
                                margin: 0;
                                color: #637187;
                                font-size: 14px;
                                line-height: 1.7;
                            "
                        >
                            This verification code expires in
                            <strong>'
                            . $safeExpiryMinutes .
                            ' minutes</strong>.
                        </p>


                        <p
                            style="
                                margin: 10px 0 0;
                                color: #637187;
                                font-size: 14px;
                                line-height: 1.7;
                            "
                        >
                            Never share this OTP with anyone.
                            BCP personnel should never ask you
                            for this verification code.
                        </p>


                        <div
                            style="
                                height: 1px;
                                margin: 27px 0;
                                background: #e6eaf0;
                            "
                        ></div>


                        <p
                            style="
                                margin: 0;
                                color: #7b8798;
                                font-size: 13px;
                                line-height: 1.7;
                            "
                        >
                            If you did not request this password reset,
                            you may safely ignore this email.
                            Your password will remain unchanged.
                        </p>


                    </td>

                </tr>


                <!-- FOOTER -->
                <tr>

                    <td
                        align="center"
                        style="
                            padding: 19px 30px;
                            background: #f8fafc;
                            border-top: 1px solid #e6eaf0;
                            color: #8994a5;
                            font-size: 12px;
                            line-height: 1.6;
                        "
                    >

                        Bestlink College of the Philippines

                        <br>

                        Academic Scheduling Platform

                    </td>

                </tr>


            </table>


        </td>

    </tr>

</table>


</body>

</html>';
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
    ?string $idempotencyKey = null
): bool {

    /*
     * --------------------------------------------------------
     * LOAD RAILWAY / ENVIRONMENT VARIABLES
     * --------------------------------------------------------
     */

    $apiKey = trim(
        (string) getenv(
            'RESEND_API_KEY'
        )
    );


    $from = trim(
        (string) getenv(
            'RESEND_FROM_EMAIL'
        )
    );


    /*
     * --------------------------------------------------------
     * CONFIG VALIDATION
     * --------------------------------------------------------
     */

    if ($apiKey === '') {

        error_log(
            'BCP Mailer: RESEND_API_KEY is missing.'
        );

        return false;
    }


    if ($from === '') {

        error_log(
            'BCP Mailer: RESEND_FROM_EMAIL is missing.'
        );

        return false;
    }


    if (
        !filter_var(
            $recipientEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        error_log(
            'BCP Mailer: recipient email is invalid.'
        );

        return false;
    }


    /*
     * Railway PHP Docker image already
     * includes the cURL extension.
     */
    if (!function_exists('curl_init')) {

        error_log(
            'BCP Mailer: PHP cURL extension is unavailable.'
        );

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

                'to' => [
                    $recipientEmail
                ],

                'subject' => $subject,

                'html' => $html,

                'tags' => [
                    [
                        'name' =>
                            'category',

                        'value' =>
                            'password_reset'
                    ]
                ]
            ],

            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );


    } catch (Throwable $e) {

        error_log(
            'BCP Mailer JSON error: '
            . $e->getMessage()
        );

        return false;
    }


    /*
     * --------------------------------------------------------
     * HTTP HEADERS
     * --------------------------------------------------------
     */

    $headers = [
        'Authorization: Bearer '
            . $apiKey,

        'Content-Type: application/json',

        'Accept: application/json'
    ];


    /*
     * Helps prevent duplicate sends
     * if the same request is retried.
     */
    if (
        is_string(
            $idempotencyKey
        )
        && $idempotencyKey !== ''
    ) {

        $headers[] =
            'Idempotency-Key: '
            . $idempotencyKey;
    }


    /*
     * --------------------------------------------------------
     * INITIALIZE HTTPS REQUEST
     * --------------------------------------------------------
     */

    $curl = curl_init(
        'https://api.resend.com/emails'
    );


    if ($curl === false) {

        error_log(
            'BCP Mailer: curl_init() failed.'
        );

        return false;
    }


    /*
     * --------------------------------------------------------
     * CURL CONFIGURATION
     * --------------------------------------------------------
     */

    curl_setopt_array(
        $curl,
        [
            CURLOPT_POST =>
                true,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_TIMEOUT =>
                20,

            CURLOPT_HTTPHEADER =>
                $headers,

            CURLOPT_POSTFIELDS =>
                $payload,

            /*
             * Never disable SSL verification.
             */
            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_SSL_VERIFYHOST =>
                2,
        ]
    );


    /*
     * --------------------------------------------------------
     * SEND REQUEST
     * --------------------------------------------------------
     */

    $response = curl_exec(
        $curl
    );


    $curlError = curl_error(
        $curl
    );


    $httpCode = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


    curl_close(
        $curl
    );


    /*
     * --------------------------------------------------------
     * NETWORK ERROR
     * --------------------------------------------------------
     */

    if (
        $response === false
        || $curlError !== ''
    ) {

        error_log(
            'BCP Mailer transport error: '
            . $curlError
        );

        return false;
    }


    /*
     * --------------------------------------------------------
     * RESEND API ERROR
     * --------------------------------------------------------
     */

    if (
        $httpCode < 200
        || $httpCode >= 300
    ) {

        /*
         * Do not log:
         *
         * - RESEND_API_KEY
         * - OTP
         * - complete email body
         */
        error_log(
            'BCP Mailer: Resend returned HTTP '
            . $httpCode
            . '. Response: '
            . substr(
                (string) $response,
                0,
                500
            )
        );


        return false;
    }


    return true;
}


/*
 * ============================================================
 * PASSWORD RESET OTP SENDER
 * ============================================================
 *
 * This is the function forgot-password.php will call.
 *
 * Example:
 *
 * bcpSendPasswordResetOtp(
 *     "admin@example.com",
 *     "admin1",
 *     "123456",
 *     10,
 *     "bcp-reset-1-25"
 * );
 * ============================================================
 */

function bcpSendPasswordResetOtp(
    string $recipientEmail,
    string $username,
    string $otp,
    int $expiryMinutes = 10,
    ?string $idempotencyKey = null
): bool {

    /*
     * OTP must always be exactly six digits.
     */
    if (
        preg_match(
            '/^\d{6}$/D',
            $otp
        ) !== 1
    ) {

        error_log(
            'BCP Mailer: invalid OTP format.'
        );

        return false;
    }


    /*
     * Build official BCP HTML email.
     */
    $html = bcpBuildPasswordResetOtpEmail(
        $username,
        $otp,
        $expiryMinutes
    );


    /*
     * Send through Resend HTTPS API.
     */
    return bcpMailerSend(
        $recipientEmail,

        'BCP Scheduling System - Password Reset OTP',

        $html,

        $idempotencyKey
    );
}