<?php
namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

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

        if (!$user) {
            // User baru, generate user_id dan buat record
            $user                 = new User();
            $user->user_id        = 'user_' . Str::random(10);
            $user->wallet_address = $walletAddress;
            $user->save();
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
            'nonce' => 'required|string',
        ]);

        $walletAddress = strtolower($request->wallet_address);
        $requestNonce = $request->nonce;

        // Cari user berdasarkan wallet address
        $user = User::where('wallet_address', $walletAddress)->first();

        if (!$user) {
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

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'user' => [
                    'id' => $user->id,
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'wallet_address' => $user->wallet_address,
                    'last_login' => $user->last_login->format('d/m/Y H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Authentication error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifikasi tanda tangan dan login (tidak digunakan dalam pendekatan langsung)
     */
    public function verifySignature(Request $request)
    {
        // Kode verifikasi lama - tidak digunakan dalam pendekatan langsung
        // Tetap disimpan untuk referensi
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
