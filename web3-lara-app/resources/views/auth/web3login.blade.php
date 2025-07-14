@extends('layouts.app')

@section('content')
<div class="relative flex flex-col justify-center items-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md z-10" x-data="web3Login()" x-cloak>
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-primary mb-2">Wallet Auth</h1>
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

            <h2 class="text-2xl font-bold text-center mb-6">Login dengan Crypto Wallet</h2>

            <!-- Not Connected State -->
            <div x-show="!connected" class="space-y-6">
                <div class="clay-alert clay-alert-warning">
                    <p class="text-center">
                        Gunakan MetaMask untuk login ke sistem rekomendasi
                    </p>
                </div>

                <button @click="connectWallet()"
                        :disabled="loading"
                        class="clay-button clay-button-warning w-full py-3 px-6 text-black font-bold text-lg">
                    <span x-show="!loading">Hubungkan Wallet</span>
                    <span x-show="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menghubungkan...
                    </span>
                </button>
            </div>

            <!-- Connected State -->
            <div x-show="connected && !authenticated" class="space-y-6">
                <div class="clay-card bg-gray-50 p-4">
                    <div class="flex items-center text-gray-700 mb-2">
                        <i class="fas fa-wallet mr-2"></i>
                        <span class="font-medium">Wallet Terhubung:</span>
                    </div>
                    <div class="font-mono text-sm break-all bg-white p-3 rounded border border-gray-200">
                        <span x-text="walletAddress" class="text-gray-800"></span>
                    </div>
                    <div class="mt-2 text-xs text-green-600 flex items-center">
                        <i class="fas fa-check-circle mr-1"></i>
                        Wallet berhasil terhubung
                    </div>
                </div>

                <button @click="login()"
                        :disabled="loading"
                        class="clay-button clay-button-primary w-full py-3 px-6 text-white font-bold text-lg">
                    <span x-show="!loading">Verifikasi Signature</span>
                    <span x-show="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                    </span>
                </button>

                <!-- Reset Connection Button -->
                <button @click="resetConnection()"
                        class="clay-button clay-button-secondary w-full py-2 px-4 text-sm">
                    <i class="fas fa-sync-alt mr-2"></i> Ganti Wallet
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

        <!-- Welcome Overlay Alert -->
        <div x-show="showWelcomeAlert"
             class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50"
             style="display: none;">
            <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 text-center transform transition-all shadow-xl">
                <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-4xl font-logo-color"></i>
                </div>
                <h3 class="text-3xl font-extrabold mb-2 solana-gradient">Selamat Datang!</h3>
                <p class="text-gray-600 mb-4">Login berhasil. Anda akan dialihkan ke Dashboard.</p>
                <div class="flex justify-center">
                    <svg class="animate-spin h-8 w-8 solana-stroke" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@vite(['resources/js/app.js'])

<style>
    [x-cloak] { display: none !important; }

    .solana-gradient {
        background: linear-gradient(90deg, #9945FF, #14F195);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        color: transparent;
    }

    .solana-stroke {
        color: #9945FF;
    }

    .font-logo-color {
        color: #6366F1;
    }
</style>

<script>
    function web3Login() {
        return {
            connected: false,
            authenticated: false,
            loading: false,
            walletAddress: '',
            nonce: '',
            message: '',
            error: '',
            showWelcomeAlert: false,

            async init() {
                // Check if MetaMask is installed on component init
                if (typeof window.ethereum === 'undefined') {
                    this.error = 'MetaMask tidak terdeteksi. Silakan instal MetaMask terlebih dahulu.';
                }

                // Check if already connected
                await this.checkExistingConnection();

                // Setup event listeners
                this.setupEventListeners();
            },

            async checkExistingConnection() {
                try {
                    if (window.ethereum && window.ethereum.isConnected()) {
                        const accounts = await window.ethereum.request({ method: 'eth_accounts' });
                        if (accounts && accounts.length > 0) {
                            this.walletAddress = accounts[0].toLowerCase();
                            this.connected = true;
                            // Force Alpine to update the DOM
                            this.$nextTick(() => {
                                // Trigger reactivity update
                                console.log('Existing connection found:', this.walletAddress);
                            });
                        }
                    }
                } catch (error) {
                    console.error('Error checking existing connection:', error);
                }
            },

            async connectWallet() {
                this.error = '';
                this.loading = true;

                try {
                    // Check if MetaMask is available
                    if (typeof window.ethereum === 'undefined') {
                        throw new Error('MetaMask tidak terdeteksi. Silakan instal MetaMask dan refresh halaman.');
                    }

                    // Request accounts with proper error handling
                    let accounts;
                    try {
                        accounts = await window.ethereum.request({
                            method: 'eth_requestAccounts'
                        });
                    } catch (error) {
                        if (error.code === 4001) {
                            throw new Error('Anda menolak permintaan koneksi wallet.');
                        }
                        throw error;
                    }

                    if (!accounts || accounts.length === 0) {
                        throw new Error('Tidak ada akun MetaMask yang terdeteksi.');
                    }

                    // Normalize address and force reactivity update
                    this.walletAddress = accounts[0].toLowerCase();
                    this.connected = true;

                    // Force Alpine to update the DOM
                    await this.$nextTick();

                    console.log('Wallet connected:', this.walletAddress);

                    // Get nonce from server
                    await this.getNonce();

                } catch (error) {
                    console.error('Error connecting wallet:', error);
                    this.error = error.message || 'Gagal menghubungkan wallet. Silakan coba lagi.';
                    this.connected = false;
                    this.walletAddress = '';
                } finally {
                    this.loading = false;
                }
            },

            async resetConnection() {
                this.connected = false;
                this.walletAddress = '';
                this.nonce = '';
                this.message = '';
                this.error = '';
                this.loading = false;

                // Force update
                await this.$nextTick();

                console.log('Connection reset');
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
                    throw error;
                }
            },

            async login() {
                this.error = '';
                this.loading = true;

                try {
                    // Make sure wallet is still connected
                    if (!window.ethereum || !this.walletAddress) {
                        throw new Error('Wallet tidak terhubung. Silakan hubungkan wallet terlebih dahulu.');
                    }

                    // Sign message with proper error handling
                    let signature;
                    try {
                        signature = await window.ethereum.request({
                            method: 'personal_sign',
                            params: [this.message, this.walletAddress, '']
                        });
                    } catch (error) {
                        if (error.code === 4001) {
                            throw new Error('Anda menolak permintaan tanda tangan.');
                        }
                        if (error.code === -32603) {
                            try {
                                signature = await window.ethereum.request({
                                    method: 'eth_sign',
                                    params: [this.walletAddress, this.message]
                                });
                            } catch (altError) {
                                throw new Error('Gagal menandatangani pesan. Pastikan MetaMask terbuka dan Anda sudah login.');
                            }
                        }
                        throw error;
                    }

                    if (!signature) {
                        throw new Error('Tanda tangan kosong. Silakan coba lagi.');
                    }

                    // Verify signature on server
                    const verifyResponse = await fetch('{{ route("web3.verify") }}', {
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

                    const verifyData = await verifyResponse.json();

                    if (!verifyResponse.ok) {
                        throw new Error(verifyData.error || 'Verifikasi tanda tangan gagal');
                    }

                    if (verifyData.success) {
                        this.loading = false;
                        this.authenticated = true;
                        this.showWelcomeAlert = true;

                        setTimeout(() => {
                            window.location.href = '{{ route("panel.dashboard") }}';
                        }, 2000);
                    } else {
                        throw new Error('Autentikasi gagal. Silakan coba lagi.');
                    }

                } catch (error) {
                    console.error('Error during login:', error);

                    if (error.code === -32603) {
                        this.error = 'MetaMask mengalami error internal. Silakan coba lagi atau restart MetaMask.';
                    } else if (error.code === 4001) {
                        this.error = 'Anda membatalkan proses login.';
                    } else {
                        this.error = error.message || 'Terjadi kesalahan saat proses login.';
                    }

                    this.loading = false;
                }
            },

            setupEventListeners() {
                if (window.ethereum) {
                    // Remove existing listeners to prevent duplicates
                    if (window.ethereum.removeAllListeners) {
                        window.ethereum.removeAllListeners('accountsChanged');
                        window.ethereum.removeAllListeners('chainChanged');
                    }

                    window.ethereum.on('accountsChanged', async (accounts) => {
                        console.log('Accounts changed:', accounts);

                        if (accounts.length === 0) {
                            await this.resetConnection();
                            this.error = 'Wallet terputus. Silakan hubungkan kembali.';
                        } else if (accounts[0].toLowerCase() !== this.walletAddress) {
                            // Account changed, update address and get new nonce
                            this.walletAddress = accounts[0].toLowerCase();
                            this.connected = true;

                            // Force reactivity update
                            await this.$nextTick();

                            try {
                                await this.getNonce();
                            } catch (error) {
                                this.error = 'Gagal mendapatkan nonce untuk akun baru.';
                            }
                        }
                    });

                    window.ethereum.on('chainChanged', () => {
                        // Reload page when chain changes
                        window.location.reload();
                    });
                }
            }
        }
    }

    // Initialize Alpine component
    document.addEventListener('alpine:init', () => {
        Alpine.data('web3Login', web3Login);
    });
</script>
@endpush
@endsection
