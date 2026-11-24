<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Is he down? Now score him.</title>
    <style>
        @media screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .headline-cream { font-size: 34px !important; }
            .headline-orange { font-size: 32px !important; }
            .body-pad { padding: 28px 20px !important; }
            .hero-pad { padding: 28px 20px 36px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Georgia,'Times New Roman',Times,serif;color:#1f2a37;">
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        You've got the buck. Now comes the question everyone at camp is going to ask. What'd he score?
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f4;">
        <tr>
            <td align="center" style="padding:0;">
                <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;">

                    {{-- Hero --}}
                    <tr>
                        <td style="padding:0;line-height:0;font-size:0;">
                            <img src="{{ $welcomeHeroUrl }}" alt="Is he down? Now score him. Rackscore" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="body-pad" style="padding:36px 40px 18px;font-family:Georgia,'Times New Roman',Times,serif;font-size:18px;line-height:1.55;color:#243447;">
                            <p style="margin:0 0 18px;">
                                You've got the buck. Now comes the question everyone at camp is going to ask.
                                <strong style="color:#1b2430;">What'd he score?</strong>
                            </p>
                            <p style="margin:0;">
                                Upload a clear harvest photo to Rackscore for an estimated antler score
                                and a Boone &amp; Crockett–style breakdown. See how spread, tine length,
                                and mass contribute to the total.
                            </p>
                        </td>
                    </tr>

                    {{-- CTA + store badges --}}
                    <tr>
                        <td align="center" style="padding:22px 40px 8px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 16px;font-size:16px;font-weight:bold;color:#1b2430;">
                                Score your buck with Rackscore
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                <tr>
                                    <td style="padding:0 8px;">
                                        <a href="{{ $appleStoreUrl }}" target="_blank" style="text-decoration:none;">
                                            <img src="https://tools.applemediaservices.com/api/badges/download-on-the-app-store/black/en-us?size=250x83" alt="Download on the App Store" width="140" style="display:block;width:140px;height:auto;border:0;">
                                        </a>
                                    </td>
                                    <td style="padding:0 8px;">
                                        <a href="{{ $googlePlayUrl }}" target="_blank" style="text-decoration:none;">
                                            <img src="https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png" alt="Get it on Google Play" width="160" style="display:block;width:160px;height:auto;border:0;">
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="body-pad" style="padding:18px 40px 40px;font-family:Georgia,'Times New Roman',Times,serif;font-size:16px;line-height:1.55;color:#243447;">
                            <p style="margin:0;">
                                Still chasing him? You can score him from a trail-cam photo, too.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
