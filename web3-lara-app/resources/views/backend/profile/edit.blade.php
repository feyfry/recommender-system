@extends('layouts.app')

@section('content')
<div class="container max-w-4xl mx-auto">
    <div class="neo-brutalism bg-white p-8 rotate-[0.5deg]">
        <h1 class="text-3xl font-bold mb-8 inline-block">
            <span class="bg-brutal-pink py-1 px-4 neo-brutalism-sm rotate-[-1deg] inline-block">
                Profil Pengguna
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

        <!-- Wallet Info Card -->
        <div class="neo-brutalism-sm bg-brutal-yellow/20 p-5 mb-8 rotate-[-0.5deg]">
            <div class="flex items-center text-gray-700 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium text-lg">Wallet Address:</span>
            </div>
            <div class="neo-brutalism-sm bg-white p-3 font-mono text-sm break-all">
                {{ $user->wallet_address }}
            </div>
            <div class="mt-2 text-sm">
                <span class="text-gray-600">Login terakhir: {{ $user->last_login ? $user->last_login->diffForHumans() : 'Tidak pernah' }}</span>
            </div>
        </div>

        <!-- Profile Progress -->
        <div class="mb-8">
            <h2 class="text-lg font-bold mb-2">
                <span class="bg-brutal-blue py-1 px-2 neo-brutalism-sm rotate-[1deg] inline-block">
                    Kelengkapan Profil: {{ $profile->completeness_percentage }}%
                </span>
            </h2>
            <div class="neo-brutalism-sm bg-gray-200 h-4 w-full">
                <div class="bg-brutal-green h-full" style="width: {{ $profile->completeness_percentage }}%"></div>
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
                <label for="avatar_url" class="inline-block py-2 mb-2 font-medium">Avatar</label>

                <!-- Tampilkan avatar yang sudah ada jika tersedia -->
                @if($profile->avatar_url)
                <div class="mb-2">
                    <img src="{{ asset($profile->avatar_url) }}" alt="Avatar" class="w-24 h-24 object-cover rounded neo-brutalism-sm">
                </div>
                @endif

                <input type="file" name="avatar_url" id="avatar_url"
                    class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none neo-brutalism-sm">

                <p class="text-sm text-gray-600 mt-1">
                    Upload gambar PNG, JPG, atau SVG. Maksimum 2MB.
                </p>
            </div>

            <div>
                <label for="username" class="inline-block py-2 mb-2 font-medium">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $profile->username) }}"
                       class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none neo-brutalism-sm">
                @error('username')
                    <p class="text-brutal-pink font-medium mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-600 mt-1">
                    Username ini akan ditampilkan di sistem sebagai identitas Anda.
                </p>
            </div>

            <div>
                <label for="risk_tolerance" class="inline-block py-2 mb-2 font-medium">Toleransi Risiko</label>
                <select name="risk_tolerance" id="risk_tolerance"
                        class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none appearance-none neo-brutalism-sm">
                    <option value="" {{ old('risk_tolerance', $profile->risk_tolerance) == '' ? 'selected' : '' }}>-- Pilih Toleransi Risiko --</option>
                    <option value="low" {{ old('risk_tolerance', $profile->risk_tolerance) == 'low' ? 'selected' : '' }}>Rendah</option>
                    <option value="medium" {{ old('risk_tolerance', $profile->risk_tolerance) == 'medium' ? 'selected' : '' }}>Sedang</option>
                    <option value="high" {{ old('risk_tolerance', $profile->risk_tolerance) == 'high' ? 'selected' : '' }}>Tinggi</option>
                </select>

                <div class="grid grid-cols-3 gap-2 mt-2">
                    <div class="neo-brutalism-sm bg-brutal-green/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'low' ? 'border-2 border-black' : 'opacity-70' }}">
                        Rendah
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-green h-full" style="width: 33%"></div>
                        </div>
                        <div class="text-xs mt-1">Preferensi keamanan investasi</div>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-yellow/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'medium' ? 'border-2 border-black' : 'opacity-70' }}">
                        Sedang
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-yellow h-full" style="width: 66%"></div>
                        </div>
                        <div class="text-xs mt-1">Keseimbangan risk-reward</div>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-pink/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'high' ? 'border-2 border-black' : 'opacity-70' }}">
                        Tinggi
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-pink h-full" style="width: 100%"></div>
                        </div>
                        <div class="text-xs mt-1">Berani ambil risiko lebih tinggi</div>
                    </div>
                </div>
            </div>

            <div>
                <label for="investment_style" class="inline-block py-2 mb-2 font-medium">Gaya Investasi</label>
                <select name="investment_style" id="investment_style"
                        class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none appearance-none neo-brutalism-sm">
                    <option value="" {{ old('investment_style', $profile->investment_style) == '' ? 'selected' : '' }}>-- Pilih Gaya Investasi --</option>
                    <option value="conservative" {{ old('investment_style', $profile->investment_style) == 'conservative' ? 'selected' : '' }}>Konservatif</option>
                    <option value="balanced" {{ old('investment_style', $profile->investment_style) == 'balanced' ? 'selected' : '' }}>Seimbang</option>
                    <option value="aggressive" {{ old('investment_style', $profile->investment_style) == 'aggressive' ? 'selected' : '' }}>Agresif</option>
                </select>

                <div class="mt-4 neo-brutalism-sm bg-brutal-orange/10 p-4 text-sm">
                    <p class="font-bold mb-2">Tentang Gaya Investasi:</p>
                    <ul class="space-y-2">
                        <li><span class="font-medium">Konservatif:</span> Fokus pada proyek dengan kapitalisasi pasar besar dan volatilitas rendah</li>
                        <li><span class="font-medium">Seimbang:</span> Campuran antara proyek established dan proyek yang sedang berkembang</li>
                        <li><span class="font-medium">Agresif:</span> Mencakup proyek baru dengan potensi pertumbuhan tinggi namun risiko lebih besar</li>
                    </ul>
                </div>
            </div>

            <div class="pt-4">
                <label class="inline-block py-2 mb-2 font-medium">Preferensi Kategori</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach(['DeFi', 'NFT', 'Gaming', 'Layer-1', 'Layer-2', 'Meme', 'DEX', 'Lending', 'Oracle', 'Privacy'] as $category)
                    <div class="flex items-center">
                        <input type="checkbox" name="categories[]" id="category-{{ Str::slug($category) }}" value="{{ $category }}"
                               class="mr-2 h-4 w-4" {{ in_array($category, $profile->preferred_categories) ? 'checked' : '' }}>
                        <label for="category-{{ Str::slug($category) }}" class="text-sm">{{ $category }}</label>
                    </div>
                    @endforeach
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    Pilih kategori proyek yang Anda minati untuk mendapatkan rekomendasi yang lebih relevan.
                </p>
            </div>

            <div class="pt-4">
                <label class="inline-block py-2 mb-2 font-medium">Preferensi Chain/Blockchain</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach(['Ethereum', 'Solana', 'Avalanche', 'Binance Smart Chain', 'Polygon', 'Polkadot', 'Cosmos', 'Arbitrum'] as $chain)
                    <div class="flex items-center">
                        <input type="checkbox" name="chains[]" id="chain-{{ Str::slug($chain) }}" value="{{ $chain }}"
                               class="mr-2 h-4 w-4" {{ in_array($chain, $profile->preferred_chains) ? 'checked' : '' }}>
                        <label for="chain-{{ Str::slug($chain) }}" class="text-sm">{{ $chain }}</label>
                    </div>
                    @endforeach
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    Pilih blockchain yang Anda minati untuk mendapatkan rekomendasi yang lebih relevan.
                </p>
            </div>

            <div class="flex justify-center pt-6">
                <button type="submit" class="neo-brutalism bg-brutal-green px-8 py-3 text-lg font-bold text-black transform transition hover:-translate-y-1 hover:bg-brutal-green/90 rotate-[-1deg] hover:rotate-0">
                    Simpan Profil
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 flex justify-between">
        <a href="{{ route('panel.dashboard') }}" class="neo-brutalism-sm bg-white inline-block py-2 px-6 font-medium hover:bg-brutal-blue/20 transition rotate-[1deg] hover:rotate-[-1deg]">
            ← Kembali ke Dashboard
        </a>

        <a href="{{ route('panel.profile.notification-settings') }}" class="neo-brutalism-sm bg-brutal-yellow inline-block py-2 px-6 font-medium hover:bg-brutal-yellow/70 transition rotate-[-1deg] hover:rotate-[1deg]">
            Pengaturan Notifikasi →
        </a>
    </div>
</div>
@endsection
