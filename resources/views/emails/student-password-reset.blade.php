<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $appName }} password reset</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#1f2937;">
<div style="max-width:520px;margin:0 auto;padding:32px 16px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px 28px 22px;">
        <div style="font-size:14px;letter-spacing:.04em;text-transform:uppercase;color:#9ca3af;font-weight:600;">{{ $appName }}</div>
        <h1 style="margin:8px 0 12px;font-size:22px;line-height:1.3;color:#0f172a;">Password reset code</h1>

        <p style="font-size:14px;line-height:1.55;color:#374151;margin:0 0 16px;">
            Hi {{ $student->first_name ?: $student->last_name ?: 'student' }},
        </p>
        <p style="font-size:14px;line-height:1.55;color:#374151;margin:0 0 16px;">
            We received a request to reset the password for the account with index number
            <strong>{{ $student->index_number }}</strong>. Use the code below to set a new password.
            It expires in {{ $expiresInMinutes }} minutes.
        </p>

        <div style="margin:20px 0 24px;text-align:center;">
            <div style="display:inline-block;padding:14px 28px;border-radius:14px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;font-weight:700;font-size:28px;letter-spacing:.4em;font-family:'SFMono-Regular',Menlo,Consolas,monospace;">
                {{ $code }}
            </div>
        </div>

        <p style="font-size:13px;line-height:1.55;color:#6b7280;margin:0 0 6px;">
            If you didn’t request this, you can ignore this email — your password won’t change.
        </p>
        <p style="font-size:13px;line-height:1.55;color:#6b7280;margin:0;">
            For your security, never share this code with anyone.
        </p>
    </div>

    <div style="text-align:center;font-size:12px;color:#9ca3af;margin-top:16px;">
        © {{ now()->year }} {{ $appName }}
    </div>
</div>
</body>
</html>
