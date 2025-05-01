@extends('layouts.app')

@section('content')
<div class="container max-w-4xl mx-auto">
    <div class="clay-card p-8">
        <h1 class="text-3xl font-bold mb-8 inline-block">
            <span class="clay-badge clay-badge-warning p-2 inline-block">
                <i class="fas fa-bell mr-2"></i> Pengaturan Notifikasi
            </span>
        </h1>

        @if (session('success'))
        <div class="clay-alert clay-alert-success mb-8">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        </div>
        @endif

        @if (session('error'))
        <div class="clay-alert clay-alert-danger mb-8">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
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
            <div class="clay-card bg-primary/10 p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-bell mr-2 text-primary"></i>
                    Jenis Notifikasi
                </h2>

                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="price_alerts" class="flex items-center">
                                <span class="font-medium">Price Alerts</span>
                                <span class="clay-badge clay-badge-danger ml-2 text-xs">Penting</span>
                            </label>
                            <p class="text-sm text-gray-600 mt-1">Dapatkan notifikasi ketika harga aset mencapai target Anda</p>
                        </div>
                        <div x-data="{ enabled: {{ isset($profile->notification_settings['price_alerts']) && $profile->notification_settings['price_alerts'] ? 'true' : 'false' }} }">
                            <label for="price_alerts" class="flex cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" id="price_alerts" name="price_alerts" class="sr-only" x-model="enabled">
                                    <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition" :class="enabled ? 'transform translate-x-full bg-primary' : ''"></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="recommendation_alerts" class="flex items-center">
                                <span class="font-medium">Rekomendasi Baru</span>
                            </label>
                            <p class="text-sm text-gray-600 mt-1">Notifikasi saat ada rekomendasi personal baru</p>
                        </div>
                        <div x-data="{ enabled: {{ isset($profile->notification_settings['recommendation_alerts']) && $profile->notification_settings['recommendation_alerts'] ? 'true' : 'false' }} }">
                            <label for="recommendation_alerts" class="flex cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" id="recommendation_alerts" name="recommendation_alerts" class="sr-only" x-model="enabled">
                                    <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition" :class="enabled ? 'transform translate-x-full bg-primary' : ''"></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="market_events" class="flex items-center">
                                <span class="font-medium">Market Events</span>
                            </label>
                            <p class="text-sm text-gray-600 mt-1">Notifikasi tentang pergerakan pasar signifikan</p>
                        </div>
                        <div x-data="{ enabled: {{ isset($profile->notification_settings['market_events']) && $profile->notification_settings['market_events'] ? 'true' : 'false' }} }">
                            <label for="market_events" class="flex cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" id="market_events" name="market_events" class="sr-only" x-model="enabled">
                                    <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition" :class="enabled ? 'transform translate-x-full bg-primary' : ''"></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label for="portfolio_updates" class="flex items-center">
                                <span class="font-medium">Portfolio Updates</span>
                            </label>
                            <p class="text-sm text-gray-600 mt-1">Update rutin tentang performa portofolio Anda</p>
                        </div>
                        <div x-data="{ enabled: {{ isset($profile->notification_settings['portfolio_updates']) && $profile->notification_settings['portfolio_updates'] ? 'true' : 'false' }} }">
                            <label for="portfolio_updates" class="flex cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" id="portfolio_updates" name="portfolio_updates" class="sr-only" x-model="enabled">
                                    <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition" :class="enabled ? 'transform translate-x-full bg-primary' : ''"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Methods Section -->
            <div class="clay-card bg-secondary/10 p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-envelope mr-2 text-secondary"></i>
                    Metode Pengiriman
                </h2>

                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="email_notifications" class="flex items-center">
                                <span class="font-medium">Email Notifications</span>
                            </label>
                            <p class="text-sm text-gray-600 mt-1">Dapatkan notifikasi melalui email (Coming Soon)</p>
                        </div>
                        <div x-data="{ enabled: {{ isset($profile->notification_settings['email_notifications']) && $profile->notification_settings['email_notifications'] ? 'true' : 'false' }} }">
                            <label for="email_notifications" class="flex cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" id="email_notifications" name="email_notifications" class="sr-only" x-model="enabled">
                                    <div class="block bg-gray-200 w-14 h-8 rounded-full"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition" :class="enabled ? 'transform translate-x-full bg-primary' : ''"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="clay-alert clay-alert-info mt-4">
                    <p class="flex items-center text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        Notifikasi dalam aplikasi selalu aktif dan tidak dapat dinonaktifkan.
                    </p>
                </div>
            </div>

            <!-- Frequency Section -->
            <div class="clay-card bg-success/10 p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <i class="fas fa-clock mr-2 text-success"></i>
                    Frekuensi Notifikasi
                </h2>

                <div class="clay-alert clay-alert-warning">
                    <p class="flex items-center text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        Pengaturan frekuensi notifikasi akan tersedia dalam versi berikutnya.
                    </p>
                </div>
            </div>

            <div class="flex justify-center pt-6">
                <button type="submit" class="clay-button clay-button-warning px-8 py-3 text-lg font-bold">
                    <i class="fas fa-save mr-2"></i> Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 flex justify-between">
        <a href="{{ route('panel.profile.edit') }}" class="clay-button clay-button-info">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Profil
        </a>

        <a href="{{ route('panel.dashboard') }}" class="clay-button clay-button-success">
            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
        </a>
    </div>
</div>

@push('styles')
<style>
    /* Custom toggle switch styles */
    .dot {
        transition: all 0.3s ease-in-out;
    }
</style>
@endpush
@endsection
