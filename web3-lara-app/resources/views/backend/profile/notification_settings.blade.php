@extends('layouts.app')

@section('content')
<div class="container max-w-4xl mx-auto">
    <div class="neo-brutalism bg-white p-8 rotate-[-0.5deg]">
        <h1 class="text-3xl font-bold mb-8 inline-block">
            <span class="bg-brutal-yellow py-1 px-4 neo-brutalism-sm rotate-[1deg] inline-block">
                Pengaturan Notifikasi
            </span>
        </h1>

        @if (session('success'))
        <div class="neo-brutalism-sm bg-brutal-green p-4 mb-8 rotate-[-0.5deg]">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ session('success') }}
            </div>
        </div>
        @endif

        @if (session('error'))
        <div class="neo-brutalism-sm bg-brutal-pink p-4 mb-8 rotate-[-0.5deg]">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ session('error') }}
            </div>
        </div>
        @endif

        <p class="mb-6">
            Atur preferensi notifikasi Anda untuk memastikan Anda mendapatkan update yang relevan tentang portofolio, rekomendasi, dan pergerakan harga.
        </p>

        <form method="POST" action="{{ route('panel.profile.update-notification-settings') }}" class="space-y-8">
            @method('PUT')
            @csrf

            <!-- Notification Types Section -->
            <div class="neo-brutalism-sm bg-brutal-blue/10 p-6 rotate-[0.5deg]">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                    </svg>
                    Jenis Notifikasi
                </h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="price_alerts" class="flex items-center">
                                <span class="font-medium">Price Alerts</span>
                                <span class="ml-2 text-xs bg-brutal-pink/20 px-2 py-0.5 rounded">Penting</span>
                            </label>
                            <p class="text-sm text-gray-600">Dapatkan notifikasi ketika harga aset mencapai target Anda</p>
                        </div>
                        <div class="relative">
                            <input type="checkbox" name="price_alerts" id="price_alerts" class="sr-only" {{ (isset($profile->notification_settings['price_alerts']) && $profile->notification_settings['price_alerts']) ? 'checked' : '' }}>
                            <label for="price_alerts" class="block w-12 h-6 neo-brutalism-sm bg-gray-200 cursor-pointer transition">
                                <span class="absolute left-1 top-1 w-4 h-4 transition-transform duration-200 bg-brutal-pink transform
                                    {{ (isset($profile->notification_settings['price_alerts']) && $profile->notification_settings['price_alerts']) ? 'translate-x-6' : '' }}">
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="recommendation_alerts" class="font-medium">Rekomendasi Baru</label>
                            <p class="text-sm text-gray-600">Notifikasi saat ada rekomendasi personal baru</p>
                        </div>
                        <div class="relative">
                            <input type="checkbox" name="recommendation_alerts" id="recommendation_alerts" class="sr-only" {{ (isset($profile->notification_settings['recommendation_alerts']) && $profile->notification_settings['recommendation_alerts']) ? 'checked' : '' }}>
                            <label for="recommendation_alerts" class="block w-12 h-6 neo-brutalism-sm bg-gray-200 cursor-pointer transition">
                                <span class="absolute left-1 top-1 w-4 h-4 transition-transform duration-200 bg-brutal-blue transform
                                    {{ (isset($profile->notification_settings['recommendation_alerts']) && $profile->notification_settings['recommendation_alerts']) ? 'translate-x-6' : '' }}">
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="market_events" class="font-medium">Market Events</label>
                            <p class="text-sm text-gray-600">Notifikasi tentang pergerakan pasar signifikan</p>
                        </div>
                        <div class="relative">
                            <input type="checkbox" name="market_events" id="market_events" class="sr-only" {{ (isset($profile->notification_settings['market_events']) && $profile->notification_settings['market_events']) ? 'checked' : '' }}>
                            <label for="market_events" class="block w-12 h-6 neo-brutalism-sm bg-gray-200 cursor-pointer transition">
                                <span class="absolute left-1 top-1 w-4 h-4 transition-transform duration-200 bg-brutal-green transform
                                    {{ (isset($profile->notification_settings['market_events']) && $profile->notification_settings['market_events']) ? 'translate-x-6' : '' }}">
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="portfolio_updates" class="font-medium">Portfolio Updates</label>
                            <p class="text-sm text-gray-600">Update rutin tentang performa portofolio Anda</p>
                        </div>
                        <div class="relative">
                            <input type="checkbox" name="portfolio_updates" id="portfolio_updates" class="sr-only" {{ (isset($profile->notification_settings['portfolio_updates']) && $profile->notification_settings['portfolio_updates']) ? 'checked' : '' }}>
                            <label for="portfolio_updates" class="block w-12 h-6 neo-brutalism-sm bg-gray-200 cursor-pointer transition">
                                <span class="absolute left-1 top-1 w-4 h-4 transition-transform duration-200 bg-brutal-yellow transform
                                    {{ (isset($profile->notification_settings['portfolio_updates']) && $profile->notification_settings['portfolio_updates']) ? 'translate-x-6' : '' }}">
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Methods Section -->
            <div class="neo-brutalism-sm bg-brutal-pink/10 p-6 rotate-[-0.5deg]">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                    Metode Pengiriman
                </h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="email_notifications" class="font-medium">Email Notifications</label>
                            <p class="text-sm text-gray-600">Dapatkan notifikasi melalui email (Coming Soon)</p>
                        </div>
                        <div class="relative">
                            <input type="checkbox" name="email_notifications" id="email_notifications" class="sr-only" {{ (isset($profile->notification_settings['email_notifications']) && $profile->notification_settings['email_notifications']) ? 'checked' : '' }}>
                            <label for="email_notifications" class="block w-12 h-6 neo-brutalism-sm bg-gray-200 cursor-pointer transition">
                                <span class="absolute left-1 top-1 w-4 h-4 transition-transform duration-200 bg-brutal-orange transform
                                    {{ (isset($profile->notification_settings['email_notifications']) && $profile->notification_settings['email_notifications']) ? 'translate-x-6' : '' }}">
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 bg-brutal-yellow/10 p-3 neo-brutalism-sm text-sm">
                    <p class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        Notifikasi dalam aplikasi selalu aktif dan tidak dapat dinonaktifkan.
                    </p>
                </div>
            </div>

            <!-- Frequency Section -->
            <div class="neo-brutalism-sm bg-brutal-green/10 p-6 rotate-[0.5deg]">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    Frekuensi Notifikasi
                </h2>

                <div class="mt-2 bg-brutal-blue/10 p-3 neo-brutalism-sm text-sm mb-4">
                    <p class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        Pengaturan frekuensi notifikasi akan tersedia dalam versi berikutnya.
                    </p>
                </div>
            </div>

            <div class="flex justify-center pt-6">
                <button type="submit" class="neo-brutalism bg-brutal-yellow px-8 py-3 text-lg font-bold text-black transform transition hover:-translate-y-1 hover:bg-brutal-yellow/90 rotate-[1deg] hover:rotate-0">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 flex justify-between">
        <a href="{{ route('panel.profile.edit') }}" class="neo-brutalism-sm bg-white inline-block py-2 px-6 font-medium hover:bg-brutal-blue/20 transition rotate-[1deg] hover:rotate-[-1deg]">
            ← Kembali ke Profil
        </a>

        <a href="{{ route('panel.dashboard') }}" class="neo-brutalism-sm bg-brutal-green inline-block py-2 px-6 font-medium hover:bg-brutal-green/70 transition rotate-[-1deg] hover:rotate-[1deg]">
            Dashboard →
        </a>
    </div>
</div>
@endsection
