<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your RackScore is waiting</title>

    <style>
        @media screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .content { padding: 24px 18px !important; }
            .headline { font-size: 30px !important; }
            .section-title { font-size: 23px !important; }
        }
    </style>
</head>

<body style="margin:0;padding:0;background:#f5f4f0;font-family:Arial,Helvetica,sans-serif;color:#08281e;">

{{-- Inbox preview --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    Your buck has been scored 👀 Could it take the top spot?
</div>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       style="background:#f5f4f0;">
    <tr>
        <td align="center" style="padding:24px 12px;">

            <table role="presentation" class="container" width="600"
                   cellspacing="0" cellpadding="0"
                   style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;">
                <tr>
                    <td class="content" style="padding:30px;">

                        {{-- Branding --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td align="center">
                                    <img src="{{ $logoUrl }}"
                                         width="58"
                                         alt="RackScore"
                                         style="display:block;width:58px;height:auto;border:0;">

                                    <p style="margin:8px 0 20px;font-size:25px;font-weight:bold;letter-spacing:2px;">
                                        RACKSCORE
                                    </p>

                                    <p style="margin:0 0 14px;font-size:12px;font-weight:bold;letter-spacing:3px;">
                                        🦌 YOUR SCORE IS WAITING…
                                    </p>

                                    <h1 class="headline"
                                        style="margin:0 0 24px;font-size:38px;line-height:1.15;">
                                        Big buck. Bigger<br>
                                        bragging rights? 👀
                                    </h1>
                                </td>
                            </tr>
                        </table>

                        {{-- Greeting --}}
                        <p style="margin:0 0 10px;font-size:19px;font-weight:bold;">
                            Hey {{ $hunterName }},
                        </p>

                        <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#536971;">
                            The score is in. The suspense is real. 🔒
                            Reveal your buck’s estimated score with a
                            <strong>7-day free trial</strong> in the RackScore app.
                        </p>

                        {{-- Deer photo with locked badge baked into the image --}}
                        <img src="{{ $lockedResultImageUrl }}"
                             width="540"
                             alt="Your buck with its estimated score locked"
                             style="display:block;width:100%;height:auto;border:0;border-radius:12px;">

                        {{-- User details --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                               style="margin-top:12px;background:#eef1e8;border-radius:12px;">
                            <tr>
                                <td width="50%" valign="top" style="padding:18px 14px 12px;">
                                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:2px;">
                                        HUNTER
                                    </p>
                                    <p style="margin:0;font-size:15px;">
                                        {{ $hunterName }}
                                    </p>
                                </td>

                                <td width="50%" valign="top" style="padding:18px 14px 12px;">
                                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:2px;">
                                        EMAIL
                                    </p>
                                    <p style="margin:0;font-size:14px;word-break:break-all;">
                                        {{ $hunterEmail }}
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td valign="top" style="padding:0 14px 18px;">
                                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:2px;">
                                        SPECIES
                                    </p>
                                    <p style="margin:0;font-size:15px;">
                                        {{ $species ?? 'Whitetail deer' }}
                                    </p>
                                </td>

                                <td valign="top" style="padding:0 14px 18px;">
                                    <p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:2px;">
                                        STATUS
                                    </p>
                                    <p style="margin:0;font-size:15px;">
                                        Score ready · Locked
                                    </p>
                                </td>
                            </tr>
                        </table>

                        {{-- Leaderboard teaser --}}
                        <h2 class="section-title"
                            style="margin:24px 0 10px;text-align:center;font-size:26px;line-height:1.25;">
                            🏆 Could your buck take the top spot?
                        </h2>

                        <p style="margin:0 0 18px;text-align:center;font-size:15px;line-height:1.6;color:#536971;">
                            That rack has you wondering… now see how it stacks up.<br>
                            A high score could put you among the leaders. 🔥
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding:15px 18px;background:#102f46;border-radius:12px 12px 0 0;color:#ffffff;font-size:20px;font-weight:bold;">
                                    Leaderboard
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:12px;background:#faf7ef;border-radius:0 0 12px 12px;">

                                    @foreach ($leaders as $leader)
                                        <table role="presentation" width="100%"
                                               cellspacing="0" cellpadding="0"
                                               style="margin-bottom:9px;background:#ffffff;border:1px solid #e8e6df;border-radius:12px;">
                                            <tr>
                                                <td width="48" style="padding:12px 0 12px 12px;">
                                                    <img src="{{ $leader['avatar_url'] }}"
                                                         width="40" height="40"
                                                         alt=""
                                                         style="display:block;width:40px;height:40px;border-radius:50%;border:0;">
                                                </td>

                                                <td style="padding:12px 8px;">
                                                    <p style="margin:0 0 5px;font-size:14px;font-weight:bold;color:#102630;">
                                                        {{ $leader['name'] }}
                                                    </p>
                                                    <p style="margin:0;font-size:12px;color:#71818a;">
                                                        Rank #{{ $loop->iteration }}
                                                    </p>
                                                </td>

                                                <td align="right"
                                                    style="padding:12px;font-size:17px;font-weight:bold;color:#b4531d;white-space:nowrap;">
                                                    {{ $leader['score_display'] }}
                                                </td>
                                            </tr>
                                        </table>
                                    @endforeach

                                    {{-- User rank stays hidden --}}
                                    <table role="presentation" width="100%"
                                           cellspacing="0" cellpadding="0"
                                           style="background:#edf2e8;border:1px dashed #8ba18e;border-radius:12px;">
                                        <tr>
                                            <td width="48" style="padding:12px 0 12px 12px;">
                                                <img src="{{ $deerImageUrl }}"
                                                     width="40" height="40"
                                                     alt=""
                                                     style="display:block;width:40px;height:40px;border-radius:50%;border:0;">
                                            </td>

                                            <td style="padding:12px 8px;">
                                                <p style="margin:0 0 5px;font-size:14px;font-weight:bold;">
                                                    {{ $hunterName }}
                                                </p>
                                                <p style="margin:0;font-size:12px;color:#536971;">
                                                    Where will you land? 👀
                                                </p>
                                            </td>

                                            <td align="right"
                                                style="padding:12px;font-size:20px;font-weight:bold;color:#b4531d;white-space:nowrap;">
                                                ??? 🔒
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                        {{-- CTA --}}
                        <p style="margin:20px 0 12px;text-align:center;font-size:14px;line-height:1.5;color:#536971;">
                            Ready to stop guessing? Your score is one reveal away.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td align="center" bgcolor="#f4510b"
                                    style="border-radius:8px;mso-padding-alt:17px 20px;">
                                    <a href="{{ $appUrl }}"
                                       style="display:block;padding:17px 20px;color:#ffffff;font-size:19px;font-weight:bold;text-align:center;text-decoration:none;border-radius:8px;">
                                        Lets fu*king Reveal it
                                        <img src="{{ $rockEmojiUrl }}"
                                             width="26" height="28"
                                             alt="🤘"
                                             style="display:inline-block;width:26px;height:28px;vertical-align:middle;border:0;">
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:10px 0 0;text-align:center;font-size:12px;line-height:1.5;color:#536971;">
                            Start your 7-day free trial in the RackScore app.
                        </p>

                        {{-- Footer --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                               style="margin-top:22px;border-top:1px solid #e0e4df;">
                            <tr>
                                <td align="center" style="padding-top:20px;">
                                    <img src="{{ $logoUrl }}"
                                         width="38"
                                         alt="RackScore"
                                         style="display:block;width:38px;height:auto;border:0;">

                                    <p style="margin:10px 0 14px;font-size:12px;line-height:1.6;color:#536971;">
                                        Your buck. Your score. Your bragging rights. 🦌<br>
                                        The RackScore Team
                                    </p>

                                    <p style="margin:0;font-size:11px;line-height:1.8;color:#71818a;">
                                        <a href="{{ $websiteUrl }}" style="color:#71818a;">RackScore</a>
                                        &nbsp;·&nbsp;
                                        <a href="{{ $supportUrl }}" style="color:#71818a;">Support</a>
                                        &nbsp;·&nbsp;
                                        <a href="{{ $unsubscribeUrl }}" style="color:#71818a;">Email preferences</a>
                                    </p>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>