@extends('layouts.app')

@section('styles')
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
@endsection

@section('content')
<div class="relative flex flex-col justify-center items-center py-4 px-4 sm:px-6 lg:px-8">
    <!-- Background Elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
        <div class="absolute top-10 left-20 w-32 h-32 bg-brutal-yellow rounded-full opacity-60"></div>
        <div class="absolute bottom-20 right-20 w-48 h-48 bg-brutal-blue rounded-full opacity-60"></div>
        <div class="absolute top-1/2 left-0 w-40 h-40 bg-brutal-pink rounded-full opacity-50"></div>
        <div class="absolute inset-0 noisy-bg"></div>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md z-10" x-data="web3Login()">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold gradient-text inline-block mb-2">Web3 Auth</h1>
            <div class="neo-brutalism-sm bg-brutal-green rotate-[-2deg] py-1 px-3 inline-block text-sm font-bold">
                CONNECT YOUR WALLET
            </div>
        </div>

        <!-- Login Card -->
        <div class="neo-brutalism bg-white p-8 rotate-[1deg] transition-transform hover:rotate-0">
            <div class="mb-6 flex justify-center">
                <img src="{{ asset('images/wallet.svg') }}" alt="Wallet" class="h-20 neo-brutalism-sm bg-white p-2 rotate-[-3deg]">
            </div>

            <h2 class="text-2xl font-bold text-center mb-6">Login dengan Web3 Wallet</h2>

            <!-- Not Connected State -->
            <div x-show="!connected" class="space-y-6">
                <p class="text-center bg-brutal-yellow neo-brutalism-sm p-3 rotate-[1deg]">
                    Gunakan MetaMask untuk login ke sistem rekomendasi
                </p>

                <button @click="connectWallet()"
                        class="w-full bg-brutal-orange neo-brutalism-sm py-3 px-6 text-black font-bold text-lg hover:bg-brutal-orange/90 transform transition hover:translate-y-1 rotate-[-1deg] hover:rotate-0">
                    Hubungkan Wallet
                </button>
            </div>

            <!-- Connected State -->
            <div x-show="connected && !authenticated" class="space-y-6">
                <div class="bg-gray-100 neo-brutalism-sm p-4 rotate-[-1deg]">
                    <div class="flex items-center text-gray-700 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm11 1H6v8l4-2 4 2V6z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">Wallet Terhubung:</span>
                    </div>
                    <div class="font-mono text-sm break-all bg-white px-3 py-2" x-text="walletAddress"></div>
                </div>

                <button @click="login()"
                        class="w-full bg-brutal-blue neo-brutalism-sm py-3 px-6 text-black font-bold text-lg hover:bg-brutal-blue/90 transform transition hover:translate-y-1 rotate-[1deg] hover:rotate-0">
                    <span x-show="!loading">Signature</span>
                    <span x-show="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Proses...
                    </span>
                </button>
            </div>

            <!-- Error Alert -->
            <div x-show="error" class="mt-6 bg-brutal-pink neo-brutalism-sm p-4 text-black rotate-[-1deg]">
                <div class="flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <p x-text="error"></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <a href="/" class="neo-brutalism-sm bg-white inline-block py-2 px-4 font-medium hover:bg-brutal-yellow transition rotate-[-1deg] hover:rotate-[1deg]">
                ← Kembali ke Beranda
            </a>

            <p class="mt-6 text-sm font-medium">
                <span class="bg-black text-white px-2 py-0.5 mr-2 inline-block neo-brutalism-sm">SKRIPSI</span>
                Sistem Rekomendasi Berbasis Web3
            </p>
        </div>
    </div>
</div>

@push('scripts')
<!-- Web3.js library -->
<script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/ethers@6.7.1/dist.min.js"></script>

<script>
    function web3Login() {
        return {
            connected: false,
            authenticated: false,
            loading: false,
            walletAddress: '',
            nonce: '',
            error: '',

            async connectWallet() {
                this.error = '';
                this.loading = true;

                try {
                    // Periksa apakah MetaMask tersedia
                    if (!window.ethereum) {
                        throw new Error('MetaMask tidak terdeteksi. Silakan instal MetaMask dan refresh halaman.');
                    }

                    // Minta akun dari MetaMask
                    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });

                    if (accounts.length === 0) {
                        throw new Error('Tidak ada akun MetaMask yang terdeteksi atau akses ditolak.');
                    }

                    this.walletAddress = accounts[0];
                    this.connected = true;

                    // Dapatkan nonce dari server
                    await this.getNonce();

                } catch (error) {
                    console.error('Error connecting wallet:', error);
                    this.error = error.message || 'Gagal menghubungkan wallet. Silakan coba lagi.';
                } finally {
                    this.loading = false;
                }
            },

            async getNonce() {
                try {
                    const response = await fetch('{{ route("web3.nonce") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            wallet_address: this.walletAddress
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Gagal mendapatkan nonce');
                    }

                    this.nonce = data.nonce;
                    this.message = data.message;

                } catch (error) {
                    console.error('Error getting nonce:', error);
                    this.error = error.message || 'Gagal mendapatkan nonce. Silakan coba lagi.';
                }
            },

            async login() {
                this.error = '';
                this.loading = true;

                try {
                    // Buat pesan yang akan ditandatangani
                    const message = `Please sign this message to verify your identity. Nonce: ${this.nonce}`;

                    // Dapatkan tanda tangan dari MetaMask
                    const signature = await window.ethereum.request({
                        method: 'personal_sign',
                        params: [message, this.walletAddress]
                    });

                    // Kirim signature langsung ke server tanpa verifikasi client-side
                    const response = await fetch('{{ route("web3.direct-auth") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            wallet_address: this.walletAddress,
                            nonce: this.nonce
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Verifikasi gagal');
                    }

                    // Autentikasi berhasil
                    this.authenticated = true;

                    // Redirect ke dashboard
                    window.location.href = '{{ route("panel.dashboard") }}';

                } catch (error) {
                    console.error('Error during login:', error);
                    this.error = error.message || 'Login gagal. Silakan coba lagi.';
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endpush
@endsection
