<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>ShuleSoft Talent Network</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f3ef; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    {{-- Preheader (hidden preview text in inbox lists) --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $name ? "{$name}, " : '' }}600+ schools across Africa are hiring through ShuleSoft Talent Network — create your free profile in under 60 seconds.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f3ef; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#1b1f27; padding:24px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="middle">
                                        <img src="{{ $logoUrl }}" width="28" height="28" alt="ShuleSoft" style="display:inline-block; vertical-align:middle; border-radius:6px;">
                                        <span style="display:inline-block; vertical-align:middle; margin-left:10px; font-size:15px; font-weight:800; color:#ffffff;">ShuleSoft Talent Network</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Hero --}}
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <div style="display:inline-block; background-color:#d9f2e7; color:#006040; font-size:11px; font-weight:700; padding:5px 12px; border-radius:999px; margin-bottom:14px;">
                                Built for people who want to work in schools
                            </div>
                            <h1 style="margin:0 0 12px; font-size:24px; line-height:1.3; font-weight:800; color:#1c1b15;">
                                The hiring network for schools using ShuleSoft.
                            </h1>
                            <p style="margin:0 0 20px; font-size:14.5px; line-height:1.6; color:#65635d;">
                                {{ $name ? "Hi {$name}," : 'Hi,' }} if you're looking to work in one of the <strong style="color:#1c1b15;">600+ schools</strong> using ShuleSoft across Africa, this is for you. Create one profile, get AI-matched to real openings, apply with one click, and track your hiring journey from application to onboarding — all in one place.
                            </p>
                        </td>
                    </tr>

                    {{-- Stats --}}
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f3ef; border-radius:12px;">
                                <tr>
                                    <td align="center" style="padding:18px 8px;">
                                        <div style="font-size:24px; font-weight:800; color:#006040;">600+</div>
                                        <div style="font-size:11.5px; font-weight:700; color:#1c1b15; margin-top:2px;">Schools</div>
                                    </td>
                                    <td align="center" style="padding:18px 8px; border-left:1px solid #dfdeda; border-right:1px solid #dfdeda;">
                                        <div style="font-size:24px; font-weight:800; color:#1c1b15;">11,000+</div>
                                        <div style="font-size:11.5px; font-weight:700; color:#65635d; margin-top:2px;">Employed</div>
                                    </td>
                                    <td align="center" style="padding:18px 8px;">
                                        <div style="font-size:24px; font-weight:800; color:#1c1b15;">5+</div>
                                        <div style="font-size:11.5px; font-weight:700; color:#65635d; margin-top:2px;">Countries</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td align="center" style="padding:0 32px 32px;">
                            <a href="{{ $ctaUrl }}" style="display:inline-block; background-color:#00805a; color:#ffffff; font-size:14.5px; font-weight:700; text-decoration:none; padding:14px 32px; border-radius:10px;">
                                Create My Free Profile →
                            </a>
                            <div style="margin-top:10px; font-size:12px; color:#65635d;">Takes under 60 seconds — just upload your CV.</div>
                        </td>
                    </tr>

                    {{-- Steps --}}
                    <tr>
                        <td style="padding:0 32px 32px; border-top:1px solid #dfdeda;">
                            <div style="padding-top:24px; font-size:11px; font-weight:700; color:#65635d; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:14px;">
                                Steps to get your dream job
                            </div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    @foreach ([
                                        ['1', 'Upload CV'],
                                        ['2', 'AI Creates Your Profile'],
                                        ['3', 'Apply to Matched Jobs'],
                                        ['4', 'Get Hired'],
                                    ] as [$n, $label])
                                        <td align="center" width="25%" style="padding:0 4px;">
                                            <div style="width:26px; height:26px; line-height:26px; border-radius:999px; background-color:#d9f2e7; color:#006040; font-size:12px; font-weight:800; margin:0 auto 6px;">{{ $n }}</div>
                                            <div style="font-size:11px; font-weight:600; color:#1c1b15;">{{ $label }}</div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Disclaimer / footer --}}
                    <tr>
                        <td style="background-color:#f4f3ef; padding:20px 32px; border-top:1px solid #dfdeda;">
                            <p style="margin:0 0 8px; font-size:11.5px; line-height:1.6; color:#65635d;">
                                You're receiving this email because you previously applied for a position through ShuleSoft. We're inviting past applicants to join the new ShuleSoft Talent Network so schools can find and match with you directly for future opportunities.
                            </p>
                            <p style="margin:0; font-size:11.5px; line-height:1.6; color:#65635d;">
                                Not interested? No action is needed — you can simply ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
