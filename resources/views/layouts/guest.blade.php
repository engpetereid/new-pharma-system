<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#08111F">

        <title>RUQAX Pharmaceuticals</title>
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/admin/images/brand/favicon-32.png') }}">
        <link rel="shortcut icon" href="{{ asset('assets/admin/images/brand/favicon.ico') }}">
        <link rel="apple-touch-icon" href="{{ asset('assets/admin/images/brand/favicon-180.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body {
                font-family: 'Cairo', sans-serif !important;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-rendering: optimizeLegibility;
                background: radial-gradient(ellipse at 50% 30%, #0C1829 0%, #07101D 55%, #040A14 100%) fixed;
                min-height: 100vh;
            }
            .rq-logo {
                width: 190px; height: 190px; border-radius: 22px; object-fit: cover;
                box-shadow: 0 0 0 1px rgba(141, 180, 206, .18), 0 14px 36px rgba(0, 0, 0, .35);
            }
            .rq-tagline { color: #A1A2A6; font-size: .8rem; font-weight: 500; }
            .rq-card { border-top: 3px solid #3A6F98 !important; }
            .rq-card button[type="submit"] {
                background: #3A6F98 !important;
                box-shadow: 0 2px 8px rgba(58, 111, 152, .2) !important;
            }
            .rq-card button[type="submit"]:hover { background: #305F82 !important; }
            .rq-card input:focus { border-color: #3A6F98 !important; --tw-ring-color: rgba(58, 111, 152, .3) !important; }
            .rq-card a { color: #3A6F98 !important; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
            <div class="mb-6 text-center">
                <img src="{{ asset('assets/admin/images/brand/ruqax-logo.png') }}" alt="RUQAX Pharmaceuticals" class="rq-logo mx-auto mb-3">
                <p class="rq-tagline">نظام إدارة التوزيع والعمليات الدوائية</p>
            </div>

            <div class="rq-card w-full sm:max-w-md px-6 py-6 bg-white shadow-xl overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
