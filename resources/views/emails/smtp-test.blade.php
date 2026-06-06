@php
    $brand = '#e11d48';
    $brandDark = '#9f1239';
    $brandTint = '#fff1f2';
    $appName = $appName ?? config('app.name', 'a-tenda');
    $appUrl = (string) (config('app.url') ?: url('/'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $appName }} SMTP test</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;width:100%;">
                <tr>
                    <td style="padding:0 4px 16px;text-align:center;">
                        <span style="display:inline-block;padding:6px 14px;border-radius:999px;background:{{ $brandTint }};color:{{ $brandDark }};font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;">
                            {{ $appName }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border-radius:18px;border:1px solid #e4e4e7;overflow:hidden;">
                        <div style="height:6px;background:linear-gradient(90deg,{{ $brand }} 0%,#fb7185 50%,{{ $brand }} 100%);"></div>
                        <div style="padding:32px;">
                            <div style="display:inline-flex;align-items:center;gap:8px;background:#ecfdf5;color:#065f46;font-size:12px;font-weight:600;padding:6px 12px;border-radius:999px;border:1px solid #d1fae5;letter-spacing:.04em;">
                                ✓ SMTP delivery succeeded
                            </div>
                            <h1 style="margin:14px 0 10px;font-size:22px;color:#0f172a;font-weight:700;">
                                Your mailer is working.
                            </h1>
                            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#475569;">
                                This is a test message from <strong>{{ $appName }}</strong>. If you're reading it,
                                the SMTP credentials saved in <em>Admin → Settings → Email</em> are valid and
                                students will receive password-reset codes from this mailbox.
                            </p>
                            <div style="margin-top:16px;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;font-size:12px;color:#475569;line-height:1.6;">
                                <div><strong style="color:#0f172a;">Sent at:</strong> {{ now()->format('M d, Y · H:i T') }}</div>
                                <div><strong style="color:#0f172a;">From host:</strong> {{ parse_url($appUrl, PHP_URL_HOST) ?: 'localhost' }}</div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 8px 4px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.6;">
                        © {{ now()->year }} {{ $appName }} · Digital attendance system
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
