<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;padding:32px 24px;">
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="{{ $appName ?? 'App' }}" style="max-height:48px;">
                            @else
                                <strong style="font-size:20px;">{{ $appName ?? 'App' }}</strong>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:16px;line-height:1.6;">
                            <p style="margin:0 0 12px;">Hi {{ $name }},</p>
                            <p style="margin:0 0 20px;">
                                Welcome to {{ $appName ?? 'RackScore' }}! Your account is ready —
                                start scoring racks and tracking your hunts.
                            </p>
                            @if(!empty($appUrl))
                                <p style="margin:0 0 24px;text-align:center;">
                                    <a href="{{ $appUrl }}" style="display:inline-block;padding:12px 24px;background:#1a1a1a;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">
                                        Open App
                                    </a>
                                </p>
                            @endif
                            <p style="margin:0;color:#666;font-size:14px;">
                                Glad to have you with us.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:28px;font-size:12px;color:#999;text-align:center;">
                            &copy; {{ $year }} {{ $appName ?? 'App' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
