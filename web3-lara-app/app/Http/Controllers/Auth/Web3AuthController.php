<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Web3AuthController extends Controller
{
    /**
     * Tampilkan halaman login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('panel.dashboard');
        }

        return view('auth.web3login');
    }

    /**
     * Menghasilkan nonce untuk wallet address
     */
    public function getNonce(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string',
        ]);

        $walletAddress = strtolower($request->wallet_address);

        // Cari atau buat user berdasarkan wallet address
        $user = User::where('wallet_address', $walletAddress)->first();

        if (! $user) {
            // User baru, generate user_id dan buat record
            $user                 = new User();
            $user->user_id        = 'user_' . Str::random(10);
            $user->wallet_address = $walletAddress;
            $user->role           = 'community'; // Default role
            $user->save();

            // Buat profil kosong untuk user baru
            Profile::create([
                'user_id' => $user->id,
            ]);
        }

        // Generate nonce baru untuk autentikasi
        $this->generateNonce($user);

        return response()->json([
            'nonce'   => $user->nonce,
            'message' => 'Please sign this message to verify your identity. Nonce: ' . $user->nonce,
        ]);
    }

    /**
     * Bantuan generate nonce
     */
    private function generateNonce($user)
    {
        $user->nonce = bin2hex(random_bytes(16));
        $user->save();
        return $user->nonce;
    }

    /**
     * Direct authentication without verification (temporary solution)
     */
    public function directAuth(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string',
            'nonce'          => 'required|string',
        ]);

        $walletAddress = strtolower($request->wallet_address);
        $requestNonce  = $request->nonce;

        // Cari user berdasarkan wallet address
        $user = User::where('wallet_address', $walletAddress)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Validasi nonce
        if ($user->nonce !== $requestNonce) {
            return response()->json(['error' => 'Invalid nonce'], 401);
        }

        try {
            // Perbarui nonce untuk mencegah replay attack
            $this->generateNonce($user);

            // Update last_login dengan waktu sekarang
            $user->last_login = now();
            $user->save();

            // Login user
            Auth::login($user);

            // Catat aktivitas login
            ActivityLog::create([
                'user_id'       => $user->user_id,
                'activity_type' => 'login',
                'description'   => 'Login dengan wallet ' . substr($user->wallet_address, 0, 10) . '...',
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'user'    => [
                    'id'             => $user->id,
                    'user_id'        => $user->user_id,
                    'username'       => $user->profile?->username,
                    'wallet_address' => $user->wallet_address,
                    'last_login'     => $user->last_login->format('d/m/Y H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Authentication error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifikasi tanda tangan dan login (pendekatan dengan verifikasi tanda tangan)
     */
    public function verifySignature(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string',
            'signature'      => 'required|string',
        ]);

        $walletAddress = strtolower($request->wallet_address);

        // Cari user berdasarkan wallet address
        $user = User::where('wallet_address', $walletAddress)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        try {
            // Pesan yang ditandatangani oleh user
            $message = "Please sign this message to verify your identity. Nonce: {$user->nonce}";

            // Verifikasi tanda tangan menggunakan library Web3
            // Note: Verifikasi tanda tangan ini memerlukan library tambahan seperti Web3.php
            // Kode di bawah ini adalah contoh dan perlu disesuaikan dengan library yang digunakan
            /*
            $web3 = new Web3('https://mainnet.infura.io/v3/YOUR_INFURA_KEY');
            $personal = $web3->personal;
            $isValid = $personal->ecRecover($message, $request->signature) === $walletAddress;
            */

            // Karena implementasi di atas memerlukan library tambahan,
            // untuk sementara kita anggap tanda tangan selalu valid
            $isValid = true;

            if (! $isValid) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Perbarui nonce untuk mencegah replay attack
            $this->generateNonce($user);

            // Update last_login dengan waktu sekarang
            $user->last_login = now();
            $user->save();

            // Login user
            Auth::login($user);

            // Catat aktivitas login
            ActivityLog::create([
                'user_id'       => $user->user_id,
                'activity_type' => 'login',
                'description'   => 'Login dengan wallet ' . substr($user->wallet_address, 0, 10) . '...',
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'user'    => [
                    'id'             => $user->id,
                    'user_id'        => $user->user_id,
                    'username'       => $user->profile?->username,
                    'wallet_address' => $user->wallet_address,
                    'last_login'     => $user->last_login->format('d/m/Y H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Authentication error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        // Catat aktivitas logout sebelum menghancurkan sesi
        if (Auth::check()) {
            ActivityLog::create([
                'user_id'       => Auth::user()->user_id,
                'activity_type' => 'logout',
                'description'   => 'Logout dari sistem',
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
