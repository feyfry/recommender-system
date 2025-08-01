<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Crypto Recommender System') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="min-h-screen">
    {{-- Clay Gradient Background --}}
    <div class="clay-gradient-bg">
        <div class="clay-blob clay-blob-1"></div>
        <div class="clay-blob clay-blob-2"></div>
        <div class="clay-blob clay-blob-3"></div>
    </div>

    <div x-data="{ sidebarOpen: false, mobileMenuOpen: false }">
        {{-- Top Navbar --}}
        <nav class="sticky top-0 z-50 w-full p-4" aria-label="Global">
            <div class="clay-card container mx-auto py-2 px-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        @if (auth()->check())
                            <button @click="sidebarOpen = !sidebarOpen" class="mr-4 text-primary p-1.5 lg:hidden">
                                <i class="fas fa-bars fa-lg"></i>
                            </button>
                        @endif
                        <a href="{{ url('/') }}" class="flex items-center">
                            <div class="bg-primary p-2 rounded-lg shadow-lg mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hop"><path d="M10.82 16.12c1.69.6 3.91.79 5.18.85.55.03 1-.42.97-.97-.06-1.27-.26-3.5-.85-5.18"/><path d="M11.5 6.5c1.64 0 5-.38 6.71-1.07.52-.2.55-.82.12-1.17A10 10 0 0 0 4.26 18.33c.35.43.96.4 1.17-.12.69-1.71 1.07-5.07 1.07-6.71 1.34.45 3.1.9 4.88.62a.88.88 0 0 0 .73-.74c.3-2.14-.15-3.5-.61-4.88"/><path d="M15.62 16.95c.2.85.62 2.76.5 4.28a.77.77 0 0 1-.9.7 16.64 16.64 0 0 1-4.08-1.36"/><path d="M16.13 21.05c1.65.63 3.68.84 4.87.91a.9.9 0 0 0 .96-.96 17.68 17.68 0 0 0-.9-4.87"/><path d="M16.94 15.62c.86.2 2.77.62 4.29.5a.77.77 0 0 0 .7-.9 16.64 16.64 0 0 0-1.36-4.08"/><path d="M17.99 5.52a20.82 20.82 0 0 1 3.15 4.5.8.8 0 0 1-.68 1.13c-2.33.2-5.3-.32-8.27-1.57"/><path d="M4.93 4.93 3 3a.7.7 0 0 1 0-1"/><path d="M9.58 12.18c1.24 2.98 1.77 5.95 1.57 8.28a.8.8 0 0 1-1.13.68 20.82 20.82 0 0 1-4.5-3.15"/></svg>
                            </div>
                            <span class="text-xl font-bold hidden md:block lg:inline">Crypto Recommender</span>
                        </a>
                    </div>

                    <div class="flex items-center">
                        @auth
                            <div class="hidden sm:flex items-center mr-6">
                                <div class="clay-avatar clay-avatar-sm mr-2">
                                    @if (Auth::user()->profile && Auth::user()->profile->avatar_url)
                                        <img src="{{ asset(Auth::user()->profile->avatar_url) }}" alt="Avatar" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user"></i>
                                    @endif
                                </div>
                                <span class="text-sm font-medium">{{ Auth::user()->profile?->username ?? Str::limit(Auth::user()->wallet_address, 8) }}</span>
                            </div>

                            <div class="relative" x-data="{ dropdownOpen: false }">
                                <button @click="dropdownOpen = !dropdownOpen" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                                    <span class="flex items-center">
                                        <span>Akun</span>
                                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                    </span>
                                </button>
                                <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="clay-dropdown-menu mt-2 py-1 right-0" x-transition>
                                    <a href="{{ route('panel.profile.edit') }}" class="clay-dropdown-item text-sm">
                                        <i class="fas fa-user mr-2"></i> Profil
                                    </a>
                                    @if(Auth::user()->isAdmin())
                                    <div class="clay-dropdown-divider"></div>
                                    <a href="{{ route('admin.dashboard') }}" class="clay-dropdown-item text-sm">
                                        <i class="fas fa-cog mr-2"></i> Admin Panel
                                    </a>
                                    @endif
                                    <div class="clay-dropdown-divider"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="clay-dropdown-item text-sm w-full text-left">
                                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="clay-button clay-button-success">
                                Login
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <div class="flex-1 relative flex">
            {{-- Sidebar for Desktop --}}
            @auth
                {{-- Sidebar for Larger Screens --}}
                <aside class="fixed lg:sticky top-[88px] z-30 w-64 transform transition-transform duration-300 lg:translate-x-0 self-start"
                    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}"
                    style="height: calc(100vh - 88px);">
                    <div class="h-full clay-card mx-4 mr-1 overflow-y-auto">
                        {{-- User Profile Summary --}}
                        <div class="mt-4 mb-6 px-2">
                            <div class="flex items-center mb-3">
                                <div class="clay-avatar clay-avatar-md mr-3">
                                    @if (Auth::user()->profile && Auth::user()->profile->avatar_url)
                                        <img src="{{ asset(Auth::user()->profile->avatar_url) }}" alt="Avatar" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user text-2xl"></i>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold truncate">{{ Auth::user()->profile?->username ?? Str::limit(Auth::user()->wallet_address, 10) }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ Str::limit(Auth::user()->wallet_address, 16) }}</div>
                                </div>
                            </div>

                            {{-- REMOVED: Risk tolerance and investment style display --}}
                        </div>

                        {{-- Sidebar Menu --}}
                        <nav class="space-y-1 px-2 border-t border-gray-200 pt-1" aria-label="Sidebar">
                            {{-- Dashboard --}}
                            <a href="{{ route('panel.dashboard') }}" class="clay-nav-button {{ request()->routeIs('panel.dashboard') ? 'active' : '' }}">
                                <i class="fas fa-home w-5 mr-3"></i>
                                <span>Dashboard</span>
                            </a>

                            <!-- Projects -->
                            <a href="{{ route('panel.projects') }}" class="clay-nav-button {{ request()->routeIs('panel.projects*') ? 'active' : '' }}">
                                <i class="fas fa-project-diagram w-5 mr-3"></i>
                                <span>Proyek</span>
                            </a>

                            {{-- Recommendations Menu --}}
                            <div x-data="{ open: {{ request()->routeIs('panel.recommendations*') ? 'true' : 'false' }} }">
                                <button @click="open = !open" class="clay-nav-button w-full flex justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-star w-5 mr-3"></i>
                                        <span>Rekomendasi</span>
                                    </div>
                                    <i class="fas fa-chevron-right transition-transform" :class="{'rotate-90': open}"></i>
                                </button>
                                <div x-show="open" class="pl-9 space-y-1 mt-1">
                                    <a href="{{ route('panel.recommendations') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.recommendations') && !request()->routeIs('panel.recommendations.*') ? 'font-bold text-primary' : '' }}">
                                        Overview
                                    </a>
                                    <a href="{{ route('panel.recommendations.personal') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.recommendations.personal') ? 'font-bold text-primary' : '' }}">
                                        Personal
                                    </a>
                                    <a href="{{ route('panel.recommendations.trending') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.recommendations.trending') ? 'font-bold text-primary' : '' }}">
                                        Trending
                                    </a>
                                    <a href="{{ route('panel.recommendations.popular') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.recommendations.popular') ? 'font-bold text-primary' : '' }}">
                                        Popular
                                    </a>
                                </div>
                            </div>

                            {{-- Portfolio Menu --}}
                            <div x-data="{ open: {{ request()->routeIs('panel.portfolio*') ? 'true' : 'false' }} }">
                                <button @click="open = !open" class="clay-nav-button w-full flex justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-wallet w-5 mr-3"></i>
                                        <span>Portfolio</span>
                                    </div>
                                    <i class="fas fa-chevron-right transition-transform" :class="{'rotate-90': open}"></i>
                                </button>
                                <div x-show="open" class="pl-9 space-y-1 mt-1">
                                    <a href="{{ route('panel.portfolio') }}"
                                    class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.portfolio') && !request()->routeIs('panel.portfolio.*') ? 'font-bold text-primary' : '' }}">
                                        <i class="fas fa-chart-pie mr-2 text-xs"></i>
                                        Overview
                                        <span class="text-xs text-gray-500 block">Real + Manual</span>
                                    </a>
                                    <a href="{{ route('panel.portfolio.onchain-analytics') }}"
                                    class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.portfolio.onchain-analytics') ? 'font-bold text-primary' : '' }}">
                                        <i class="fas fa-link mr-2 text-xs"></i>
                                        Onchain Analytics
                                        <span class="clay-badge clay-badge-success text-xs ml-1">LIVE</span>
                                    </a>
                                    <a href="{{ route('panel.portfolio.transaction-management') }}"
                                    class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('panel.portfolio.transaction-management') ? 'font-bold text-primary' : '' }}">
                                        <i class="fas fa-cash-register mr-2 text-xs"></i>
                                        Transaction Management
                                        <span class="clay-badge clay-badge-warning text-xs ml-1">MANUAL</span>
                                    </a>
                                </div>
                            </div>

                            {{-- Technical Analysis Menu --}}
                            <a href="{{ route('panel.technical-analysis') }}" class="clay-nav-button {{ request()->routeIs('panel.technical-analysis*') ? 'active' : '' }}">
                                <i class="fas fa-chart-line w-5 mr-3"></i>
                                <span>Analisis Teknikal</span>
                            </a>

                            {{-- Profile --}}
                            <a href="{{ route('panel.profile.edit') }}" class="clay-nav-button {{ request()->routeIs('panel.profile*') ? 'active' : '' }}">
                                <i class="fas fa-user w-5 mr-3"></i>
                                <span>Profil</span>
                            </a>

                            {{-- Admin Section (Only for admins) --}}
                            @if(Auth::check() && Auth::user()->isAdmin())
                            <div class="pt-4 mt-4 border-t border-gray-200">
                                <div class="px-2 mb-2 text-xs font-semibold text-gray-500">ADMIN PANEL</div>
                                <div x-data="{ open: {{ request()->routeIs('admin*') ? 'true' : 'false' }} }">
                                    <button @click="open = !open" class="clay-nav-button w-full flex justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-shield-alt w-5 mr-3"></i>
                                            <span>Admin</span>
                                        </div>
                                        <i class="fas fa-chevron-right transition-transform" :class="{'rotate-90': open}"></i>
                                    </button>
                                    <div x-show="open" class="pl-9 space-y-1 mt-1">
                                        <a href="{{ route('admin.dashboard') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('admin.dashboard') ? 'font-bold text-primary' : '' }}">
                                            Dashboard
                                        </a>
                                        <a href="{{ route('admin.users') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('admin.users*') ? 'font-bold text-primary' : '' }}">
                                            Pengguna
                                        </a>
                                        <a href="{{ route('admin.projects') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('admin.projects*') ? 'font-bold text-primary' : '' }}">
                                            Proyek
                                        </a>
                                        <a href="{{ route('admin.data-sync') }}" class="block py-1 px-3 text-sm rounded-md hover:bg-primary/10 {{ request()->routeIs('admin.data-sync*') ? 'font-bold text-primary' : '' }}">
                                            Sinkronisasi Data
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </nav>
                    </div>
                </aside>
            @endauth

            <div class="flex-1 flex flex-col w-full">
                {{-- Main Content --}}
                <main class="flex-1 px-4 pb-8 lg:pl-4">
                    <div class="container mx-auto">
                        @yield('content')
                    </div>
                </main>

                {{-- Footer --}}
                <footer class="p-4 text-center">
                    <div class="inline-block clay-card py-2 px-4 text-sm">
                        <span>Crypto Recommender System &copy; {{ date('Y') }}</span>
                    </div>
                </footer>
            </div>
        </div>

        {{-- Overlay for sidebar on mobile --}}
        <div
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 z-20 bg-black bg-opacity-50 lg:hidden"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
        </div>
    </div>

    @stack('scripts')
</body>
</html>
