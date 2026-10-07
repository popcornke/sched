<?php

declare(strict_types=1);


/*
 * ============================================================
 * BCP EMAIL TEMPLATES
 * ============================================================
 */


function emailTemplatePasswordResetOtp(
    string $username,
    string $otp,
    int $expiryMinutes = 10
): string {

    $safeUsername = htmlspecialchars(
        $username,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );


    $safeOtp = htmlspecialchars(
        $otp,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );


    $safeExpiry =
        max(
            1,
            $expiryMinutes
        );


    return '
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Password Reset Verification
</title>

</head>


<body style="
    margin:0;
    padding:0;
    background:#f5f7fb;
    font-family:Arial,Helvetica,sans-serif;
    color:#172033;
">


<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width:100%;
        background:#f5f7fb;
    "
>

<tr>

<td
    align="center"
    style="
        padding:36px 16px;
    "
>


<table
    role="presentation"
    width="560"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width:100%;
        max-width:560px;
        background:#ffffff;
        border:1px solid #e3e8f1;
        border-radius:16px;
        overflow:hidden;
    "
>


<tr>

<td
    style="
        background:#163b86;
        padding:24px 30px;
        color:#ffffff;
    "
>

<div
    style="
        font-size:12px;
        letter-spacing:1.4px;
        font-weight:700;
        opacity:.82;
    "
>
    BESTLINK COLLEGE OF THE PHILIPPINES
</div>


<div
    style="
        font-size:20px;
        font-weight:800;
        margin-top:7px;
    "
>
    Academic Scheduling Platform
</div>

</td>

</tr>


<tr>

<td
    style="
        padding:32px 30px;
    "
>


<div
    style="
        display:inline-block;
        font-size:11px;
        font-weight:700;
        color:#3158ad;
        letter-spacing:1px;
        margin-bottom:10px;
    "
>
    ACCOUNT SECURITY
</div>


<h1
    style="
        margin:0 0 18px;
        font-size:23px;
        line-height:1.3;
        color:#172033;
    "
>
    Password reset verification
</h1>


<p
    style="
        margin:0 0 14px;
        font-size:15px;
        line-height:1.7;
        color:#46546a;
    "
>
    Hello <strong>'
        . $safeUsername .
        '</strong>,
</p>


<p
    style="
        margin:0;
        font-size:15px;
        line-height:1.7;
        color:#46546a;
    "
>
    A password reset was requested for your
    BCP Scheduling System account.
    Enter the verification code below
    to continue.
</p>


<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        margin:26px 0;
    "
>

<tr>

<td
    align="center"
    style="
        background:#f0f5ff;
        border:1px solid #d7e2fc;
        border-radius:12px;
        padding:22px 16px;
    "
>


<div
    style="
        font-size:11px;
        letter-spacing:1.2px;
        font-weight:700;
        color:#687994;
        margin-bottom:10px;
    "
>
    YOUR ONE-TIME PASSWORD
</div>


<div
    style="
        font-size:34px;
        line-height:1;
        font-weight:800;
        letter-spacing:9px;
        color:#173d91;
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
        margin:0;
        font-size:14px;
        line-height:1.7;
        color:#647186;
    "
>
    This verification code expires in
    <strong>'
        . $safeExpiry .
        ' minutes</strong>.
</p>


<p
    style="
        margin:10px 0 0;
        font-size:14px;
        line-height:1.7;
        color:#647186;
    "
>
    For your security, never share this
    OTP with another person.
</p>


<div
    style="
        height:1px;
        background:#e7ebf2;
        margin:26px 0;
    "
></div>


<p
    style="
        margin:0;
        font-size:13px;
        line-height:1.7;
        color:#7a8798;
    "
>
    If you did not request a password reset,
    you can safely ignore this email.
    Your password will remain unchanged.
</p>


</td>

</tr>


<tr>

<td
    style="
        padding:18px 30px;
        background:#f9fafc;
        border-top:1px solid #e8ecf3;
        color:#8994a5;
        font-size:12px;
        line-height:1.6;
        text-align:center;
    "
>

Bestlink College of the Philippines<br>

BCP Class Scheduling System

</td>

</tr>


</table>


</td>

</tr>

</table>


</body>

</html>';
}
