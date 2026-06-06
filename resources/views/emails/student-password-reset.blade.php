@php
    // System brand colours (kept inline because most email clients
    // strip <style> and external CSS — every rule has to live on
    // the element). Rose is the primary on the public/auth pages,
    // matching what students see when they request the reset.
    $brand = '#e11d48';
    $brandDark = '#9f1239';
    $brandTint = '#fff1f2';
    $appName = $appName ?? config('app.name', 'a-tenda');
    $appUrl = (string) (config('app.url') ?: url('/'));
    $loginUrl = rtrim($appUrl, '/').'/student/password-reset/verify';
    $supportEmail = (string) (config('mail.from.address') ?: 'support@'.parse_url($appUrl, PHP_URL_HOST));
    $studentName = trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: ($student->index_number ?? 'student');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $appName }} password reset</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">

<!-- preheader (shows in inbox preview, not in body) -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;color:#f4f4f5;">
    Your {{ $appName }} reset code is {{ $code }}. Expires in {{ $expiresInMinutes }} min.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

                <!-- header / brand bar -->
                <tr>
                    <td style="padding:0 4px 16px;text-align:center;">
                        <span style="display:inline-block;padding:6px 14px;border-radius:999px;background:{{ $brandTint }};color:{{ $brandDark }};font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;">
                            {{ $appName }}
                        </span>
                    </td>
                </tr>

                <!-- card -->
                <tr>
                    <td style="background:#ffffff;border-radius:18px;border:1px solid #e4e4e7;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden;">

                        <!-- gradient strip -->
                        <div style="height:6px;background:linear-gradient(90deg,{{ $brand }} 0%,#fb7185 50%,{{ $brand }} 100%);"></div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:32px 32px 8px;">
                                    <h1 style="margin:0 0 6px;font-size:22px;line-height:1.3;color:#0f172a;font-weight:700;">
                                        Reset your password
                                    </h1>
                                    <p style="margin:0;font-size:14px;color:#64748b;">
                                        Hi {{ $studentName }} — here's the one-time code to set a new password.
                                    </p>
                                </td>
                            </tr>

                            <!-- OTP block -->
                            <tr>
                                <td style="padding:20px 32px 8px;">
                                    <div style="border:1px solid #fecdd3;background:{{ $brandTint }};border-radius:14px;padding:22px 18px;text-align:center;">
                                        <div style="font-size:11px;color:{{ $brandDark }};font-weight:600;letter-spacing:.18em;text-transform:uppercase;margin-bottom:10px;">
                                            Your 6-digit code
                                        </div>
                                        <div style="font-size:34px;font-weight:700;letter-spacing:.5em;color:{{ $brandDark }};font-family:'SFMono-Regular',Menlo,Consolas,monospace;line-height:1;">
                                            {{ $code }}
                                        </div>
                                        <div style="margin-top:12px;font-size:12px;color:#9f1239;">
                                            Expires in <strong>{{ $expiresInMinutes }} minutes</strong>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- detail list -->
                            <tr>
                                <td style="padding:18px 32px 8px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;color:#334155;">
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;width:130px;color:#64748b;">Index number</td>
                                            <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;font-weight:600;color:#0f172a;">{{ $student->index_number ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;color:#64748b;">Requested at</td>
                                            <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;color:#0f172a;">{{ now()->format('M d, Y · H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;color:#64748b;">Code valid for</td>
                                            <td style="padding:8px 0;color:#0f172a;">{{ $expiresInMinutes }} minutes</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td style="padding:18px 32px 4px;text-align:center;">
                                    <a href="{{ $loginUrl }}"
                                        style="display:inline-block;background:{{ $brand }};color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 28px;border-radius:12px;letter-spacing:.01em;">
                                        Open {{ $appName }} to enter the code
                                    </a>
                                    <div style="margin-top:10px;font-size:12px;color:#94a3b8;">
                                        Or paste the code on the verification page you opened earlier.
                                    </div>
                                </td>
                            </tr>

                            <!-- safety note -->
                            <tr>
                                <td style="padding:22px 32px 28px;">
                                    <div style="border-top:1px dashed #e2e8f0;padding-top:18px;font-size:12px;line-height:1.6;color:#64748b;">
                                        <strong style="color:#0f172a;">Didn't request this?</strong>
                                        Ignore this email — your password won't change. Codes only work for the account that asked for one.
                                        <br><br>
                                        For your security, never share this code with anyone, not even staff. {{ $appName }} will never ask you for it.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- footer -->
                <tr>
                    <td style="padding:18px 8px 4px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.6;">
                        Need help? Email <a href="mailto:{{ $supportEmail }}" style="color:{{ $brandDark }};text-decoration:none;">{{ $supportEmail }}</a>
                        <br>
                        © {{ now()->year }} {{ $appName }} · Digital attendance system
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
