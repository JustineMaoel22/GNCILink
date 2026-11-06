<?php
/** Inline styles and presentation tables keep authentication emails usable in Outlook. */
function authEmailEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Plain text first, HTML second, for the host's local mail transport. */
function authEmailAlternativeBody(string $plain, string $html, string $boundary): string {
    return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        .chunk_split(base64_encode($plain), 76, "\r\n")
        .'--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        .chunk_split(base64_encode($html), 76, "\r\n").'--'.$boundary."--\r\n";
}

function authEmailLayout(string $title, string $preview, string $firstName, string $content, string $notice): string {
    $e = 'authEmailEscape';
    $name = trim($firstName) !== '' ? $firstName : 'Administrator';
    return '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($title).'</title></head>'
        .'<body style="margin:0;padding:0;background-color:#f0f3f1;color:#243b2e;font-family:Arial,Helvetica,sans-serif;">'
        .'<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">'.$e($preview).'</div>'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f3f1;"><tr><td align="center" style="padding:32px 12px;">'
        .'<!--[if mso]><table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border:1px solid #dfe6e1;border-radius:16px;overflow:hidden;">'
        .'<tr><td style="height:5px;background-color:#eaba3b;font-size:1px;line-height:5px;">&nbsp;</td></tr>'
        .'<tr><td style="padding:28px 28px 24px;background-color:#094024;">'
        .'<p style="margin:0 0 7px;font-size:12px;line-height:18px;letter-spacing:1px;font-weight:bold;color:#eaba3b;">GUAGUA NATIONAL COLLEGES</p>'
        .'<p style="margin:0;font-size:27px;line-height:34px;font-weight:bold;color:#ffffff;">GNCILink</p>'
        .'<p style="margin:4px 0 0;font-size:13px;line-height:20px;color:#dce9e1;">Admin account security</p></td></tr>'
        .'<tr><td style="padding:30px 28px;">'
        .'<h1 style="margin:0 0 20px;font-size:25px;line-height:32px;color:#094024;">'.$e($title).'</h1>'
        .'<p style="margin:0 0 14px;font-size:15px;line-height:24px;">Hello '.$e($name).',</p>'
        .$content
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;background-color:#f5f8f6;border-left:3px solid #eaba3b;"><tr><td style="padding:14px 16px;font-size:13px;line-height:21px;color:#526259;">'.$e($notice).'</td></tr></table>'
        .'</td></tr><tr><td style="padding:20px 28px;border-top:1px solid #e5ebe7;font-size:12px;line-height:19px;color:#617168;">'
        .'<p style="margin:0 0 4px;font-weight:bold;color:#094024;">Guagua National Colleges</p>'
        .'<p style="margin:0;">This is an automated account security email. Please do not reply.</p></td></tr></table>'
        .'<!--[if mso]></td></tr></table><![endif]-->'
        .'</td></tr></table></body></html>';
}

function authBuildOtpEmailHtml(string $firstName, string $otp, int $minutes): string {
    $duration = max(1, $minutes).' minutes';
    $content = '<p style="margin:0 0 22px;font-size:15px;line-height:24px;">Use this verification code to finish signing in to your admin account.</p>'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#edf5ef;border:1px solid #d4e5d9;border-radius:10px;"><tr><td align="center" style="padding:22px 12px;">'
        .'<p style="margin:0 0 8px;font-size:12px;line-height:18px;font-weight:bold;color:#526259;">YOUR VERIFICATION CODE</p>'
        .'<p style="margin:0;font-family:Consolas,monospace;font-size:36px;line-height:46px;font-weight:bold;letter-spacing:6px;color:#094024;">'.authEmailEscape($otp).'</p>'
        .'</td></tr></table><p style="margin:18px 0 0;font-size:14px;line-height:23px;">This code expires in <strong>'.authEmailEscape($duration).'</strong>. Do not share it with anyone.</p>';
    return authEmailLayout('Verify your sign-in', 'Your GNCILink sign-in code expires in '.$duration.'.', $firstName, $content, 'If you did not request this code, do not share it or approve the sign-in. Contact your administrator.');
}

function authBuildResetEmailHtml(string $firstName, string $url): string {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url)) throw new InvalidArgumentException('Invalid password reset URL.');
    $link = authEmailEscape($url);
    $content = '<p style="margin:0 0 22px;font-size:15px;line-height:24px;">We received a request to reset your admin account password. Choose a new password using the button below.</p>'
        .'<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#094024" style="border-radius:8px;mso-padding-alt:14px 24px;">'
        .'<a href="'.$link.'" style="display:inline-block;padding:14px 24px;border:1px solid #094024;border-radius:8px;font-size:15px;line-height:22px;font-weight:bold;color:#ffffff;text-decoration:none;">Reset my password</a>'
        .'</td></tr></table><p style="margin:20px 0 0;font-size:14px;line-height:23px;">This link expires in <strong>15 minutes</strong> and can only be used once.</p>'
        .'<p style="margin:20px 0 6px;font-size:12px;line-height:20px;color:#617168;">If the button does not work, copy this link into your browser:</p>'
        .'<p style="margin:0;font-size:12px;line-height:20px;word-break:break-all;overflow-wrap:anywhere;"><a href="'.$link.'" style="color:#094024;text-decoration:underline;word-break:break-all;">'.$link.'</a></p>';
    return authEmailLayout('Reset your password', 'Reset your GNCILink password within 15 minutes.', $firstName, $content, 'If you did not request a password reset, you can ignore this email. Your password has not been changed.');
}

function authBuildPasswordChangedEmailHtml(string $firstName): string {
    $content = '<p style="margin:0 0 16px;font-size:15px;line-height:24px;">Your admin account password was reset successfully.</p>'
        .'<p style="margin:0;font-size:15px;line-height:24px;">Existing sessions have been signed out. Use your new password the next time you sign in.</p>';
    return authEmailLayout('Your password was changed', 'Your GNCILink password was reset successfully.', $firstName, $content, 'If you did not make this change, contact your Super Admin immediately.');
}
