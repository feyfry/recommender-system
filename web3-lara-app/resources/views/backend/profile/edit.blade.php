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

        <!-- Profile Progress -->
        <div class="mb-8">
            <h2 class="text-lg font-bold mb-2 flex items-center">
                <i class="fas fa-tasks text-info mr-2"></i>
                <span>Kelengkapan Profil: {{ $profile->completeness_percentage }}%</span>
            </h2>
            <div class="clay-progress">
                <div class="clay-progress-bar clay-progress-primary" style="width: {{ $profile->completeness_percentage }}%"></div>
            </div>
            @if($profile->completeness_percentage < 100)
            <p class="text-sm mt-2">
                Lengkapi profil Anda untuk mendapatkan rekomendasi yang lebih personal!
            </p>
            @endif
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

            <div>
                <label for="risk_tolerance" class="block mb-2 font-medium">Toleransi Risiko</label>
                <select name="risk_tolerance" id="risk_tolerance" class="clay-select w-full">
                    <option value="" {{ old('risk_tolerance', $profile->risk_tolerance) == '' ? 'selected' : '' }}>-- Pilih Toleransi Risiko --</option>
                    <option value="low" {{ old('risk_tolerance', $profile->risk_tolerance) == 'low' ? 'selected' : '' }}>Rendah</option>
                    <option value="medium" {{ old('risk_tolerance', $profile->risk_tolerance) == 'medium' ? 'selected' : '' }}>Sedang</option>
                    <option value="high" {{ old('risk_tolerance', $profile->risk_tolerance) == 'high' ? 'selected' : '' }}>Tinggi</option>
                </select>

                <div class="grid grid-cols-3 gap-2 mt-3">
                    <div class="clay-card bg-success/10 p-2 text-center text-sm {{ $profile->risk_tolerance == 'low' ? 'border-2 border-success' : 'opacity-70' }}">
                        <div>Rendah</div>
                        <div class="clay-progress mt-1">
                            <div class="clay-progress-bar clay-progress-success" style="width: 33%"></div>
                        </div>
                        <div class="text-xs mt-1">Preferensi keamanan investasi</div>
                    </div>
                    <div class="clay-card bg-warning/10 p-2 text-center text-sm {{ $profile->risk_tolerance == 'medium' ? 'border-2 border-warning' : 'opacity-70' }}">
                        <div>Sedang</div>
                        <div class="clay-progress mt-1">
                            <div class="clay-progress-bar clay-progress-warning" style="width: 66%"></div>
                        </div>
                        <div class="text-xs mt-1">Keseimbangan risk-reward</div>
                    </div>
                    <div class="clay-card bg-danger/10 p-2 text-center text-sm {{ $profile->risk_tolerance == 'high' ? 'border-2 border-danger' : 'opacity-70' }}">
                        <div>Tinggi</div>
                        <div class="clay-progress mt-1">
                            <div class="clay-progress-bar clay-progress-danger" style="width: 100%"></div>
                        </div>
                        <div class="text-xs mt-1">Berani ambil risiko lebih tinggi</div>
                    </div>
                </div>
            </div>

            <div>
                <label for="investment_style" class="block mb-2 font-medium">Gaya Investasi</label>
                <select name="investment_style" id="investment_style" class="clay-select w-full">
                    <option value="" {{ old('investment_style', $profile->investment_style) == '' ? 'selected' : '' }}>-- Pilih Gaya Investasi --</option>
                    <option value="conservative" {{ old('investment_style', $profile->investment_style) == 'conservative' ? 'selected' : '' }}>Konservatif</option>
                    <option value="balanced" {{ old('investment_style', $profile->investment_style) == 'balanced' ? 'selected' : '' }}>Seimbang</option>
                    <option value="aggressive" {{ old('investment_style', $profile->investment_style) == 'aggressive' ? 'selected' : '' }}>Agresif</option>
                </select>

                <div class="clay-card bg-info/10 p-4 text-sm mt-4">
                    <p class="font-bold mb-2"><i class="fas fa-info-circle mr-2"></i>Tentang Gaya Investasi:</p>
                    <ul class="space-y-2">
                        <li><span class="font-medium">Konservatif:</span> Fokus pada proyek dengan kapitalisasi pasar besar dan volatilitas rendah</li>
                        <li><span class="font-medium">Seimbang:</span> Campuran antara proyek established dan proyek yang sedang berkembang</li>
                        <li><span class="font-medium">Agresif:</span> Mencakup proyek baru dengan potensi pertumbuhan tinggi namun risiko lebih besar</li>
                    </ul>
                </div>
            </div>

            <div class="pt-4">
                <label class="block mb-2 font-medium">Preferensi Kategori</label>
                <div class="clay-card bg-primary/5 p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach(['DeFi', 'NFT', 'Gaming', 'Layer-1', 'Layer-2', 'Meme', 'DEX', 'Lending', 'Oracle', 'Privacy'] as $category)
                        <div class="clay-checkbox-container">
                            <input type="checkbox" name="categories[]" id="category-{{ Str::slug($category) }}" value="{{ $category }}"
                                class="clay-checkbox" {{ in_array($category, $profile->preferred_categories) ? 'checked' : '' }}>
                            <label for="category-{{ Str::slug($category) }}">{{ $category }}</label>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-gray-600 mt-3">
                        Pilih kategori proyek yang Anda minati untuk mendapatkan rekomendasi yang lebih relevan.
                    </p>
                </div>
            </div>

            <div class="pt-4">
                <label class="block mb-2 font-medium">Preferensi Chain/Blockchain</label>
                <div class="clay-card bg-secondary/5 p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 gap-3">
                        @foreach(['Ethereum', 'Solana', 'Avalanche', 'Binance Smart Chain', 'Polygon', 'Polkadot', 'Cosmos', 'Arbitrum'] as $chain)
                        <div class="clay-checkbox-container">
                            <input type="checkbox" name="chains[]" id="chain-{{ Str::slug($chain) }}" value="{{ $chain }}"
                                class="clay-checkbox" {{ in_array($chain, $profile->preferred_chains) ? 'checked' : '' }}>
                            <label for="chain-{{ Str::slug($chain) }}">{{ $chain }}</label>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-sm text-gray-600 mt-3">
                        Pilih blockchain yang Anda minati untuk mendapatkan rekomendasi yang lebih relevan.
                    </p>
                </div>
            </div>

            <div class="flex justify-center pt-6">
                <button type="submit" class="clay-button clay-button-success px-8 py-3 text-lg font-bold">
                    <i class="fas fa-save mr-2"></i> Simpan Profil
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 flex justify-between">
        <a href="{{ route('panel.dashboard') }}" class="clay-button clay-button-info">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
        </a>

        <a href="{{ route('panel.profile.notification-settings') }}" class="clay-button clay-button-warning">
            Pengaturan Notifikasi <i class="fas fa-arrow-right ml-2"></i>
        </a>
    </div>
</div>
@endsection
