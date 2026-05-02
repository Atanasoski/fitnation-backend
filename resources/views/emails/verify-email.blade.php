<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background-color: {{ $user->partner?->identity?->primary_color ?? '#fa812d' }};
            padding: 40px 20px;
            text-align: center;
        }
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .header-text {
            color: {{ $user->partner?->identity?->text_on_primary_color ?? '#ffffff' }};
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .content {
            padding: 40px 20px;
            color: #374151;
            line-height: 1.6;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #111827;
        }
        .message {
            margin-bottom: 30px;
            font-size: 16px;
        }
        .cta-button {
            display: inline-block;
            background-color: {{ $user->partner?->identity?->primary_color ?? '#fa812d' }};
            color: #ffffff;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .cta-button:hover {
            opacity: 0.9;
        }
        .secondary-info {
            background-color: #f9fafb;
            border-left: 4px solid {{ $user->partner?->identity?->primary_color ?? '#fa812d' }};
            padding: 16px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 30px 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    @php
        $partner = $user->partner;
        $partnerName = $partner?->name ?? config('app.name');
        $logoUrl = null;
        if ($partner?->identity?->logo_url) {
            $logoUrl = $partner->identity->logo_url;
        } elseif ($partner?->identity?->logo) {
            $logoUrl = asset($partner->identity->logo);
        }
    @endphp

    <div class="email-container">
        <div class="header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $partnerName }}" class="logo">
            @endif
            <h1 class="header-text">Welcome to {{ $partnerName }}</h1>
        </div>

        <div class="content">
            <p class="greeting">Hello, {{ $user->name }}!</p>

            <div class="message">
                <p>You recently created an account at <strong>{{ $partnerName }}</strong>.</p>
                <p>Please verify your email address to activate your account and continue your fitness journey.</p>
            </div>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="cta-button">
                    Verify Email Address
                </a>
            </div>

            <div class="secondary-info">
                <p style="margin: 0; font-size: 14px;">
                    This verification link expires automatically for security reasons.
                </p>
            </div>

            <p style="margin-top: 30px; color: #6b7280; font-size: 14px;">
                If you did not create this account, you can safely ignore this email.
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>{{ $partnerName }}</strong>
            </p>
            <p style="margin: 0;">
                Powered by Fit Nation
            </p>
        </div>
    </div>
</body>
</html>
