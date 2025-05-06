@extends('layouts.app')

@section('content')
<div class="relative flex flex-col justify-center items-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md z-10" x-data="web3Login()">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-primary mb-2">Web3 Auth</h1>
            <div class="clay-badge clay-badge-success py-1 px-3 inline-block text-sm font-bold">
                HUBUNGKAN WALLET ANDA
            </div>
        </div>

        <!-- Login Card -->
        <div class="clay-card p-8">
            <div class="mb-6 flex justify-center">
                <div class="clay-card bg-primary/10 p-4 w-24 h-24 flex items-center justify-center">
                    <img src="{{ asset('images/wallet.svg') }}" alt="Wallet" class="h-16">
                </div>
            </div>

            <h2 class="text-2xl font-bold text-center mb-6">Login dengan Web3 Wallet</h2>

            <!-- Not Connected State -->
            <div x-show="!connected" class="space-y-6">
                <div class="clay-alert clay-alert-warning">
                    <p class="text-center">
                        Gunakan MetaMask untuk login ke sistem rekomendasi
                    </p>
                </div>

                <button @click="connectWallet()" class="clay-button clay-button-warning w-full py-3 px-6 text-black font-bold text-lg">
                    Hubungkan Wallet
                </button>
            </div>

            <!-- Connected State -->
            <div x-show="connected && !authenticated" class="space-y-6">
                <div class="clay-card bg-gray-50 p-4">
                    <div class="flex items-center text-gray-700 mb-2">
                        <i class="fas fa-wallet mr-2"></i>
                        <span class="font-medium">Wallet Terhubung:</span>
                    </div>
                    <div class="clay-input font-mono text-sm break-all py-2">
                        <span x-text="walletAddress"></span>
                    </div>
                </div>

                <button @click="login()" class="clay-button clay-button-primary w-full py-3 px-6 text-white font-bold text-lg">
                    <span x-show="!loading">Verifikasi Signature</span>
                    <span x-show="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                    </span>
                </button>
            </div>

            <!-- Error Alert -->
            <div x-show="error" class="mt-6 clay-alert clay-alert-danger">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <p x-text="error"></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <a href="/" class="clay-button clay-button-info inline-block py-2 px-4 font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

@push('scripts')
@vite(['resources/js/app.js'])

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
                this.error   = '';
                this.loading = true;

                try {
                    const message   = `Please sign this message to verify your identity. Nonce: ${this.nonce}`;
                    const signature = await window.ethereum.request({
                        method: 'personal_sign',
                        params: [message, this.walletAddress]
                    });

                    const res = await fetch('{{ route("web3.verify") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            wallet_address: this.walletAddress,
                            signature: signature
                        })
                    });

                    const data = await res.json();
                    if (!res.ok) {
                        throw new Error(data.error || 'Verifikasi gagal');
                    }

                    window.location.href = '{{ route("panel.dashboard") }}';
                } catch (err) {
                    console.error(err);
                    this.error = err.message;
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
@endpush
@endsection
