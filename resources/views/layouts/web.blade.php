<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ARTDEVATA Web - Chat Real-time')</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        wa: {
                            green: '#00a884',
                            dark: '#111b21',
                            panel: '#202c33',
                            hover: '#f0f2f5',
                            border: '#e9edef',
                            input: '#f0f2f5',
                            sent: '#d9fdd3',
                            received: '#ffffff',
                            muted: '#667781',
                            teal: '#128c7e',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Pusher JS for Laravel Reverb WebSocket -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <script>
        window.CSRF_TOKEN = "{{ csrf_token() }}";
        @if(session('sanctum_token'))
            window.SANCTUM_TOKEN = "{{ session('sanctum_token') }}";
        @endif
    </script>

    <style>
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            background-color: #d1d7db;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.35);
        }
        .chat-wallpaper {
            background-color: #efeae2;
            background-image: radial-gradient(#d3c8b4 0.75px, transparent 0.75px), radial-gradient(#d3c8b4 0.75px, #efeae2 0.75px);
            background-size: 30px 30px;
            background-position: 0 0, 15px 15px;
        }
    </style>
    @stack('styles')
</head>
<body class="h-screen w-screen overflow-hidden flex flex-col justify-center items-center">
    @yield('content')
    @stack('scripts')
</body>
</html>
