<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($reminderType ?? 'first') === 'second' ? 'Score another buck' : 'Score your first buck' }}</title>
    <style>
        @media screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-content { padding: 24px 18px !important; }
            .headline { font-size: 30px !important; }
            .cta { font-size:17px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f4f0;font-family:Arial,Helvetica,sans-serif;color:#102f46;">
    @php
        $isSecond = ($reminderType ?? 'first') === 'second';
    @endphp
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        @if ($isSecond)
            One score down. Got another buck photo? See how the next rack stacks up. 🦌
        @else
            Got a buck photo? Open RackScore and discover what that rack could score. 🦌
        @endif
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f5f4f0;">
        <tr><td align="center" style="padding:24px 12px;">
            <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:16px;">
                <tr><td class="email-content" style="padding:32px 30px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        <tr><td align="center">
                            <img src="{{ $logoUrl }}" width="58" alt="RackScore antler logo" style="display:block;width:58px;height:auto;border:0;">
                            <p style="margin:8px 0 26px;font-size:25px;font-weight:bold;letter-spacing:2px;color:#102f46;">RACKSCORE</p>
                            @if ($isSecond)
                                <p style="margin:0 0 14px;font-size:12px;font-weight:bold;letter-spacing:2px;color:#b4531d;">🦌 READY FOR ROUND TWO?</p>
                                <h1 class="headline" style="margin:0 0 24px;font-size:38px;line-height:1.15;color:#102f46;">One score in.<br>What’s the next rack? 👀</h1>
                            @else
                                <p style="margin:0 0 14px;font-size:12px;font-weight:bold;letter-spacing:2px;color:#b4531d;">🦌 YOUR FIRST SCORE STARTS HERE</p>
                                <h1 class="headline" style="margin:0 0 24px;font-size:38px;line-height:1.15;color:#102f46;">That rack has a story.<br>What’s its number? 👀</h1>
                            @endif
                        </td></tr>
                    </table>

                    <p style="margin:0 0 12px;font-size:19px;font-weight:bold;">Hey {{ $hunterName ?: 'there' }},</p>
                    @if ($isSecond)
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#536971;">You already scored one buck — nice. Got another photo sitting on your phone? Let’s see what that next rack could score. 🦌</p>
                    @else
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#536971;">You made it into RackScore… but we haven’t seen your first buck photo yet. Got one on your phone? Let’s put that rack to the test. 🦌</p>
                    @endif

                    @if (!empty($heroImageUrl))
                        <img src="{{ $heroImageUrl }}" width="540" alt="Whitetail buck — example photo" style="display:block;width:100%;height:auto;border:0;border-radius:12px;">
                        <p style="margin:7px 0 18px;font-size:11px;text-align:center;color:#71818a;">Example photo · Bring your own buck</p>
                    @endif

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8f5ee;border-radius:12px;">
                        <tr><td style="padding:22px;">
                            @if ($isSecond)
                                <h2 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#102f46;">Build your trophy list. 🔥</h2>
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">01.</strong> Open the RackScore app.</p>
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">02.</strong> Upload another clear buck photo with the antlers visible.</p>
                                <p style="margin:0;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">03.</strong> Get the estimated score and compare with your first.</p>
                            @else
                                <h2 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#102f46;">From “looks big” to an estimated score. 🔥</h2>
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">01.</strong> Open the RackScore app.</p>
                                <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">02.</strong> Upload a clear buck photo with the antlers visible.</p>
                                <p style="margin:0;font-size:15px;line-height:1.5;color:#536971;"><strong style="color:#b4531d;">03.</strong> Follow the in-app steps to get your estimated score.</p>
                            @endif
                        </td></tr>
                    </table>

                    @if ($isSecond)
                        <h2 style="margin:24px 0 10px;text-align:center;font-size:24px;line-height:1.3;color:#102f46;">🏆 Which rack wins?</h2>
                        <p style="margin:0 0 22px;text-align:center;font-size:15px;line-height:1.6;color:#536971;">Your first score is on the books.<br>Score another and see how they stack up.</p>
                    @else
                        <h2 style="margin:24px 0 10px;text-align:center;font-size:24px;line-height:1.3;color:#102f46;">🏆 A future leaderboard contender?</h2>
                        <p style="margin:0 0 22px;text-align:center;font-size:15px;line-height:1.6;color:#536971;">Maybe your buck belongs among the leaders.<br>Start with your first score and see how it stacks up.</p>
                    @endif

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        <tr><td align="center" bgcolor="#f4510b" style="background-color:#f4510b;border-radius:8px;mso-padding-alt:18px 20px;">
                            <a class="cta" href="{{ $appUrl }}" style="display:block;padding:18px 20px;font-size:19px;line-height:1.25;font-weight:bold;text-align:center;color:#ffffff;text-decoration:none;border-radius:8px;">
                                @if ($isSecond)
                                    Score My Next Buck 🤘
                                @else
                                    Let’s Score My First Buck 🤘
                                @endif
                            </a>
                        </td></tr>
                    </table>
                    <p style="margin:11px 0 0;text-align:center;font-size:12px;line-height:1.5;color:#536971;">
                        @if ($isSecond)
                            Open RackScore to upload another photo.
                        @else
                            Open RackScore to upload your first photo.
                        @endif
                    </p>
                    <p style="margin:22px 0 0;font-size:15px;line-height:1.6;color:#536971;">The photo’s on your phone. The curiosity’s in your head.<br><strong style="color:#102f46;">Time to find out. 👀</strong></p>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;border-top:1px solid #e0e4df;">
                        <tr><td align="center" style="padding-top:20px;">
                            <img src="{{ $logoUrl }}" width="38" alt="RackScore" style="display:block;width:38px;height:auto;border:0;">
                            <p style="margin:10px 0 14px;font-size:12px;line-height:1.6;color:#536971;">Your buck. Your score. Your bragging rights. 🦌<br>The RackScore Team</p>
                            <p style="margin:0 0 10px;font-size:11px;line-height:1.6;color:#71818a;">
                                @if ($isSecond)
                                    You’re receiving this reminder because you scored once in RackScore.
                                @else
                                    You’re receiving this reminder because you created a RackScore account.
                                @endif
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.8;color:#71818a;">
                                <a href="{{ $websiteUrl }}" style="color:#71818a;">RackScore</a> &nbsp;·&nbsp;
                                <a href="{{ $supportUrl }}" style="color:#71818a;">Support</a> &nbsp;·&nbsp;
                                <a href="{{ $unsubscribeUrl }}" style="color:#71818a;">Unsubscribe</a>
                            </p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
