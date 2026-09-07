{{--
    The one scaffold for partner-branded mail to a user: identity colour on the
    header rule and button, partner logo when there is one, app-name fallback.
    Expects $user; yields title, header, content.
--}}
@php
    $partner = $user->partner;
    $partnerName = $partner?->name ?? config('app.name');
    $primaryColor = $partner?->identity?->primary_color ?? '#f86f33'; // orange-500, the brand accent
    $logoUrl = $partner?->identity?->logo_url;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
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
            background-color: #f9fafb;
            border-left: 4px solid {{ $primaryColor }};
            padding: 40px 20px;
            text-align: center;
            margin: 20px;
            border-radius: 4px;
        }
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .header-text {
            color: #111827;
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
            background-color: {{ $primaryColor }};
            color: #ffffff !important;
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
            border-left: 4px solid {{ $primaryColor }};
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
    <div class="email-container">
        <div class="header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $partnerName }}" class="logo">
            @endif
            <h1 class="header-text">@yield('header')</h1>
        </div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>{{ $partnerName }}</strong>
            </p>
            <p style="margin: 0;">
                Powered by {{ config('app.name') }}
            </p>
        </div>
    </div>
</body>
</html>
