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
        <div class="neo-brutalism-sm bg-brutal-red p-4 mb-8 rotate-[-0.5deg]">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ session('error') }}
            </div>
        </div>
        @endif

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
        </div>

        <form method="POST" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data" class="space-y-8">
            @method('PUT')
            @csrf

            <div>
                <label for="username" class="inline-block py-2 mb-2 font-medium">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $profile->username) }}"
                       class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none">
                @error('username')
                    <p class="text-brutal-pink font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="risk_tolerance" class="inline-block py-2 mb-2 font-medium">Toleransi Risiko</label>
                <select name="risk_tolerance" id="risk_tolerance"
                        class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none appearance-none">
                    <option value="" hidden>-- Pilih Toleransi Risiko --</option>
                    <option value="low" {{ old('risk_tolerance', $profile->risk_tolerance) == 'low' ? 'selected' : '' }}>Rendah</option>
                    <option value="medium" {{ old('risk_tolerance', $profile->risk_tolerance) == 'medium' ? 'selected' : '' }}>Sedang</option>
                    <option value="high" {{ old('risk_tolerance', $profile->risk_tolerance) == 'high' ? 'selected' : '' }}>Tinggi</option>
                </select>

                <div class="grid grid-cols-3 gap-2 mt-2">
                    <div class="neo-brutalism-sm bg-brutal-green/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'low' ? 'border-2 border-black' : 'opacity-50' }}">
                        Rendah
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-green h-full" style="width: 33%"></div>
                        </div>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-yellow/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'medium' ? 'border-2 border-black' : 'opacity-50' }}">
                        Sedang
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-yellow h-full" style="width: 66%"></div>
                        </div>
                    </div>
                    <div class="neo-brutalism-sm bg-brutal-pink/20 p-2 text-center text-sm {{ $profile->risk_tolerance == 'high' ? 'border-2 border-black' : 'opacity-50' }}">
                        Tinggi
                        <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="bg-brutal-pink h-full" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label for="investment_style" class="inline-block py-2 mb-2 font-medium">Gaya Investasi</label>
                <select name="investment_style" id="investment_style" class="w-full px-4 py-3 border-2 border-black focus:border-brutal-blue focus:outline-none appearance-none">
                    <option value="" hidden>-- Pilih Gaya Investasi --</option>
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

            <div class="flex justify-center pt-4">
                <button type="submit" class="neo-brutalism bg-brutal-green px-8 py-3 text-lg font-bold text-black transform transition hover:-translate-y-1 hover:bg-brutal-green/90 rotate-[-1deg] hover:rotate-0">
                    Simpan Profil
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 text-center">
        <a href="{{ route('panel.dashboard') }}" class="neo-brutalism-sm bg-white inline-block py-2 px-6 font-medium hover:bg-brutal-blue/20 transition rotate-[1deg] hover:rotate-[-1deg]">
            ← Kembali ke Dashboard
        </a>
    </div>
</div>
@endsection
