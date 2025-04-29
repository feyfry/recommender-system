<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Web3 Recommender System') }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brutal: {
                            yellow: '#FFDE59',
                            pink: '#FF5F7E',
                            blue: '#65CEFF',
                            green: '#7AE582',
                            orange: '#FF914D'
                        }
                    }
                }
            }
        }
    </script>

    <!-- AlpineJS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Web3 Neo-Brutalism Styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Space Grotesk', sans-serif;
            background-color: #f8f8f8;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23bdbdbd' fill-opacity='0.2'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .neo-brutalism {
            box-shadow: 6px 6px 0 0 #000;
            border: 3px solid #000;
        }
        .neo-brutalism-sm {
            box-shadow: 4px 4px 0 0 #000;
            border: 2px solid #000;
        }
        .neo-shadow {
            text-shadow: 2px 2px 0 #000;
        }
        .noisy-bg {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            background-blend-mode: multiply;
            background-size: 100px;
            opacity: 0.05;
        }
        .gradient-text {
            background: linear-gradient(90deg, #ff9962 0%, #FF5F7E 50%, #65CEFF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen">
    <!-- Animated Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-20 left-10 w-32 h-32 bg-brutal-yellow rounded-full opacity-60"></div>
        <div class="absolute bottom-40 right-10 w-48 h-48 bg-brutal-blue rounded-full opacity-60"></div>
        <div class="absolute top-1/2 -translate-y-1/2 left-1/3 w-40 h-40 bg-brutal-pink rounded-full opacity-50"></div>
        <div class="absolute inset-0 noisy-bg"></div>
    </div>

    <!-- Navbar -->
    <nav class="z-50 relative mb-8">
        <div class="neo-brutalism bg-white mx-4 mt-4 sm:mx-8 py-3 px-6 flex flex-wrap justify-between items-center">
            <a href="{{ url('/') }}" class="flex items-center">
                <div class="neo-brutalism-sm bg-brutal-orange p-2 rotate-[-3deg]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hop-icon lucide-hop"><path d="M10.82 16.12c1.69.6 3.91.79 5.18.85.55.03 1-.42.97-.97-.06-1.27-.26-3.5-.85-5.18"/><path d="M11.5 6.5c1.64 0 5-.38 6.71-1.07.52-.2.55-.82.12-1.17A10 10 0 0 0 4.26 18.33c.35.43.96.4 1.17-.12.69-1.71 1.07-5.07 1.07-6.71 1.34.45 3.1.9 4.88.62a.88.88 0 0 0 .73-.74c.3-2.14-.15-3.5-.61-4.88"/><path d="M15.62 16.95c.2.85.62 2.76.5 4.28a.77.77 0 0 1-.9.7 16.64 16.64 0 0 1-4.08-1.36"/><path d="M16.13 21.05c1.65.63 3.68.84 4.87.91a.9.9 0 0 0 .96-.96 17.68 17.68 0 0 0-.9-4.87"/><path d="M16.94 15.62c.86.2 2.77.62 4.29.5a.77.77 0 0 0 .7-.9 16.64 16.64 0 0 0-1.36-4.08"/><path d="M17.99 5.52a20.82 20.82 0 0 1 3.15 4.5.8.8 0 0 1-.68 1.13c-2.33.2-5.3-.32-8.27-1.57"/><path d="M4.93 4.93 3 3a.7.7 0 0 1 0-1"/><path d="M9.58 12.18c1.24 2.98 1.77 5.95 1.57 8.28a.8.8 0 0 1-1.13.68 20.82 20.82 0 0 1-4.5-3.15"/></svg>
                </div>
                <span class="text-xl font-bold ml-3">{{ config('/', 'Web3 Recommender') }}</span>
            </a>

            <div class="flex items-center space-x-4 mt-4 sm:mt-0">
                @auth
                    <div class="hidden sm:flex items-center mr-6">
                        <div class="neo-brutalism-sm bg-brutal-blue p-1.5 rotate-[-2deg]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="ml-2 font-medium">{{ Auth::user()->username ?? Str::limit(Auth::user()->wallet_address, 10) }}</span>
                    </div>

                    <a href="{{ route('panel.profile.edit') }}" class="neo-brutalism-sm bg-brutal-yellow py-1.5 px-3 font-medium hover:rotate-[1deg] transform transition">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="neo-brutalism-sm bg-brutal-pink py-1.5 px-3 font-medium hover:rotate-[-1deg] transform transition">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="neo-brutalism-sm bg-brutal-green py-1.5 px-3 font-medium hover:rotate-[1deg] transform transition">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 sm:px-6 py-6 z-10 relative">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="mt-16 mb-6 px-4 text-center">
        <div class="inline-block neo-brutalism-sm bg-white py-1.5 px-3 rotate-[-1deg]">
            <span class="text-sm font-bold">Web3 Recommender System &copy; {{ date('Y') }}</span>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
