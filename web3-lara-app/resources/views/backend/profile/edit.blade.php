@extends('layouts.app')

@section('content')
<div class="container max-w-4xl mx-auto">
    <div class="clay-card p-8">
        <h1 class="text-3xl font-bold mb-8 inline-block">
            <span class="clay-badge clay-badge-secondary p-2 inline-block">
                <i class="fas fa-user-edit mr-2"></i> Profil Pengguna
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

        <!-- Wallet Info Card -->
        <div class="clay-card bg-warning/10 p-5 mb-8">
            <div class="flex items-center text-gray-700 mb-3">
                <i class="fas fa-wallet text-warning mr-2"></i>
                <span class="font-medium text-lg">Wallet Address</span>
            </div>
            <div class="clay-input font-mono text-sm break-all py-3">
                {{ $user->wallet_address }}
            </div>
            <div class="mt-2 text-sm">
                <span class="text-gray-600">Login terakhir: {{ $user->last_login ? $user->last_login->diffForHumans() : 'Tidak pernah' }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data" class="space-y-8">
            @method('PUT')
            @csrf

            <div class="mb-4">
                <label for="avatar_url" class="block mb-2 font-medium">Avatar</label>

                <!-- Tampilkan avatar yang sudah ada jika tersedia -->
                @if($profile->avatar_url)
                <div class="mb-4 flex justify-center">
                    <div class="clay-avatar clay-avatar-lg">
                        <img src="{{ asset($profile->avatar_url) }}" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                </div>
                @endif

                <div class="clay-card bg-primary/5 p-3">
                    <input type="file" name="avatar_url" id="avatar_url" class="w-full">
                    <p class="text-sm text-gray-600 mt-1">
                        Upload gambar PNG, JPG, atau SVG. Maksimum 2MB.
                    </p>
                </div>
            </div>

            <div>
                <label for="username" class="block mb-2 font-medium">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $profile->username) }}"
                       class="clay-input w-full">
                @error('username')
                    <p class="text-danger font-medium mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-600 mt-1">
                    Username ini akan ditampilkan di sistem sebagai identitas Anda.
                </p>
            </div>

            <div class="flex justify-center pt-6">
                <button type="submit" class="clay-button clay-button-success px-8 py-3 text-lg font-bold">
                    <i class="fas fa-save mr-2"></i> Simpan Profil
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 flex justify-center">
        <a href="{{ route('panel.dashboard') }}" class="clay-button clay-button-info">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
        </a>
    </div>
</div>
@endsection
