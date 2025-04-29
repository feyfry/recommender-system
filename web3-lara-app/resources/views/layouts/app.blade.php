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
<body class="min-h-screen" x-data="{ sidebarOpen: false }">
    <!-- Animated Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-20 left-10 w-32 h-32 bg-brutal-yellow rounded-full opacity-60"></div>
        <div class="absolute bottom-40 right-10 w-48 h-48 bg-brutal-blue rounded-full opacity-60"></div>
        <div class="absolute top-1/2 -translate-y-1/2 left-1/3 w-40 h-40 bg-brutal-pink rounded-full opacity-50"></div>
        <div class="absolute inset-0 noisy-bg"></div>
    </div>

    <!-- Top Navbar -->
    <nav class="z-50 relative sticky top-0">
        <div class="neo-brutalism bg-white mx-4 mt-4 py-2 px-6 flex flex-wrap justify-between items-center">
            <div class="flex items-center">
                @if (auth()->check())
                    <button @click="sidebarOpen = !sidebarOpen" class="mr-4 neo-brutalism-sm bg-brutal-orange p-1.5 hover:rotate-[3deg] transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                @endif
                <a href="{{ url('/') }}" class="flex items-center">
                    <div class="neo-brutalism-sm bg-brutal-orange p-2 rotate-[-3deg]">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hop-icon lucide-hop"><path d="M10.82 16.12c1.69.6 3.91.79 5.18.85.55.03 1-.42.97-.97-.06-1.27-.26-3.5-.85-5.18"/><path d="M11.5 6.5c1.64 0 5-.38 6.71-1.07.52-.2.55-.82.12-1.17A10 10 0 0 0 4.26 18.33c.35.43.96.4 1.17-.12.69-1.71 1.07-5.07 1.07-6.71 1.34.45 3.1.9 4.88.62a.88.88 0 0 0 .73-.74c.3-2.14-.15-3.5-.61-4.88"/><path d="M15.62 16.95c.2.85.62 2.76.5 4.28a.77.77 0 0 1-.9.7 16.64 16.64 0 0 1-4.08-1.36"/><path d="M16.13 21.05c1.65.63 3.68.84 4.87.91a.9.9 0 0 0 .96-.96 17.68 17.68 0 0 0-.9-4.87"/><path d="M16.94 15.62c.86.2 2.77.62 4.29.5a.77.77 0 0 0 .7-.9 16.64 16.64 0 0 0-1.36-4.08"/><path d="M17.99 5.52a20.82 20.82 0 0 1 3.15 4.5.8.8 0 0 1-.68 1.13c-2.33.2-5.3-.32-8.27-1.57"/><path d="M4.93 4.93 3 3a.7.7 0 0 1 0-1"/><path d="M9.58 12.18c1.24 2.98 1.77 5.95 1.57 8.28a.8.8 0 0 1-1.13.68 20.82 20.82 0 0 1-4.5-3.15"/></svg>
                    </div>
                    <span class="text-xl font-bold ml-3">{{ config('/', 'Web3 Recommender') }}</span>
                </a>
            </div>

            <div class="flex items-center space-x-4 mt-4 sm:mt-0">
                @auth
                    <div class="hidden sm:flex items-center mr-6">
                        <div class="neo-brutalism-sm bg-brutal-blue p-1.5 rotate-[-2deg]">
                            @if (Auth::user()->profile && Auth::user()->profile->avatar_url)
                                <img src="{{ asset(Auth::user()->profile->avatar_url) }}" alt="Avatar" class="w-5 h-5 rounded-full">
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <span class="ml-2 font-medium">{{ Auth::user()->profile?->username ?? Str::limit(Auth::user()->wallet_address, 8) }}</span>
                    </div>

                    <div class="relative" x-data="{ dropdownOpen: false }">
                        <button @click="dropdownOpen = !dropdownOpen" class="neo-brutalism-sm bg-brutal-yellow py-1.5 px-3 font-medium hover:rotate-[1deg] transform transition">
                            <span class="flex items-center">
                                <span>Akun</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                        <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 mt-2 w-48 rounded-md neo-brutalism-sm bg-white z-50">
                            <div class="py-1">
                                <a href="{{ route('panel.profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-brutal-yellow/20">
                                    Profil
                                </a>
                                <a href="{{ route('panel.profile.notification-settings') }}" class="block px-4 py-2 text-sm hover:bg-brutal-yellow/20">
                                    Notifikasi
                                </a>
                                @if(Auth::user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-brutal-yellow/20">
                                    Admin Panel
                                </a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm hover:bg-brutal-yellow/20">
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="neo-brutalism-sm bg-brutal-green py-1.5 px-3 font-medium hover:rotate-[1deg] transform transition">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Sidebar Canvas -->
    @auth
        <div x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-x-full"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 -translate-x-full"
            @click.away="sidebarOpen = false"
            class="fixed top-0 left-4 h-full w-64 z-40 mt-24">

            <div class="neo-brutalism bg-white h-full py-4 px-2 overflow-y-auto">
                <!-- User Profile Summary (if authenticated) -->
                <div class="mb-6 px-4">
                    <div class="flex items-center mb-2">
                        <div class="neo-brutalism-sm bg-brutal-blue p-1.5 rotate-[2deg]">
                            @if (Auth::user()->profile && Auth::user()->profile->avatar_url)
                                <img src="{{ asset(Auth::user()->profile->avatar_url) }}" alt="Avatar" class="w-5 h-5 rounded-full">
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <span class="ml-2 font-bold truncate">{{ Auth::user()->profile?->username ?? Str::limit(Auth::user()->wallet_address, 10) }}</span>
                    </div>

                    <div class="text-xs text-gray-600 mt-1">
                        <div class="flex items-center mb-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Risk: {{ Auth::user()->profile?->risk_tolerance_text ?? 'Belum diatur' }}
                        </div>
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                            Style: {{ Auth::user()->profile?->investment_style_text ?? 'Belum diatur' }}
                        </div>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <div class="space-y-1 px-2">
                    <!-- Dashboard -->
                    <a href="{{ route('panel.dashboard') }}" class="flex items-center py-2 px-4 neo-brutalism-sm {{ request()->routeIs('panel.dashboard') ? 'bg-brutal-yellow/30' : 'hover:bg-brutal-yellow/20' }} transform transition hover:translate-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <!-- Recommendations Section -->
                    <div x-data="{ open: {{ request()->routeIs('panel.recommendations*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center justify-between w-full py-2 px-4 neo-brutalism-sm hover:bg-brutal-pink/20 transform transition hover:translate-x-1">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>Rekomendasi</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div x-show="open" class="pl-12 space-y-1 mt-1">
                            <a href="{{ route('panel.recommendations') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations') && !request()->routeIs('panel.recommendations.*') ? 'font-bold' : '' }}">
                                Overview
                            </a>
                            <a href="{{ route('panel.recommendations.personal') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations.personal') ? 'font-bold' : '' }}">
                                Personal
                            </a>
                            <a href="{{ route('panel.recommendations.trending') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations.trending') ? 'font-bold' : '' }}">
                                Trending
                            </a>
                            <a href="{{ route('panel.recommendations.popular') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations.popular') ? 'font-bold' : '' }}">
                                Popular
                            </a>
                            <a href="{{ route('panel.recommendations.categories') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations.categories') ? 'font-bold' : '' }}">
                                Categories
                            </a>
                            <a href="{{ route('panel.recommendations.chains') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.recommendations.chains') ? 'font-bold' : '' }}">
                                Chains
                            </a>
                        </div>
                    </div>

                    <!-- Portfolio Section -->
                    <div x-data="{ open: {{ request()->routeIs('panel.portfolio*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center justify-between w-full py-2 px-4 neo-brutalism-sm hover:bg-brutal-green/20 transform transition hover:translate-x-1">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <span>Portfolio</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div x-show="open" class="pl-12 space-y-1 mt-1">
                            <a href="{{ route('panel.portfolio') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.portfolio') && !request()->routeIs('panel.portfolio.*') ? 'font-bold' : '' }}">
                                Overview
                            </a>
                            <a href="{{ route('panel.portfolio.transactions') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.portfolio.transactions') ? 'font-bold' : '' }}">
                                Transactions
                            </a>
                            <a href="{{ route('panel.portfolio.price-alerts') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('panel.portfolio.price-alerts') ? 'font-bold' : '' }}">
                                Price Alerts
                            </a>
                        </div>
                    </div>

                    <!-- Profile -->
                    <a href="{{ route('panel.profile.edit') }}" class="flex items-center py-2 px-4 neo-brutalism-sm {{ request()->routeIs('panel.profile*') ? 'bg-brutal-blue/30' : 'hover:bg-brutal-blue/20' }} transform transition hover:translate-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Profile</span>
                    </a>

                    <!-- Admin Section (Only visible to admins) -->
                    @if(Auth::check() && Auth::user()->isAdmin())
                    <div class="pt-4 mt-4 border-t border-black/10">
                        <div class="px-4 mb-2 text-xs font-semibold">ADMIN PANEL</div>
                        <div x-data="{ open: {{ request()->routeIs('admin*') ? 'true' : 'false' }} }">
                            <button @click="open = !open" class="flex items-center justify-between w-full py-2 px-4 neo-brutalism-sm hover:bg-brutal-orange/20 transform transition hover:translate-x-1">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Admin</span>
                                </div>
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <div x-show="open" class="pl-12 space-y-1 mt-1">
                                <a href="{{ route('admin.dashboard') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('admin.dashboard') ? 'font-bold' : '' }}">
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.users') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('admin.users*') ? 'font-bold' : '' }}">
                                    Users
                                </a>
                                <a href="{{ route('admin.projects') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('admin.projects*') ? 'font-bold' : '' }}">
                                    Projects
                                </a>
                                <a href="{{ route('admin.data-sync') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('admin.data-sync*') ? 'font-bold' : '' }}">
                                    Data Sync
                                </a>
                                <a href="{{ route('admin.activity-logs') }}" class="block py-1 px-4 text-sm rounded {{ request()->routeIs('admin.activity-logs*') ? 'font-bold' : '' }}">
                                    Activity Logs
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @endauth

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
