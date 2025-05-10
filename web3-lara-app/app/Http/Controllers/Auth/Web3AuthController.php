<?php
namespace App\Http\Controllers\Auth;

use Elliptic\EC;
use App\Models\User;
use kornrunner\Keccak;
use App\Models\Profile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Web3AuthController extends Controller
{
    /**
     * Menampilkan halaman login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('panel.dashboard');
        }
        return view('auth.web3login');
    }

    /**
     * Mendapatkan nonce untuk wallet address
     */
    public function getNonce(Request $request)
    {
        $request->validate(['wallet_address' => 'required|string']);
        $walletAddress = strtolower($request->wallet_address);

        $user = User::firstOrCreate(
            ['wallet_address' => $walletAddress],
            [
                'user_id' => 'user_' . Str::random(10),
                'role'    => 'community',
            ]
        );

        // Pastikan ada profile
        Profile::firstOrCreate(['user_id' => $user->id]);

        // Generate nonce baru
        $user->nonce = bin2hex(random_bytes(16));
        $user->save();

        return response()->json([
            'nonce'   => $user->nonce,
            'message' => 'Please sign this message to verify your identity. Nonce: ' . $user->nonce,
        ]);
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

        $wallet = strtolower($request->wallet_address);
        $sigHex = substr($request->signature, 2); // strip '0x'

        // Ambil user dan pesan nonce
        $user = User::where('wallet_address', $wallet)->first();
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $message = "Please sign this message to verify your identity. Nonce: {$user->nonce}";

        try {
            // 1. Pisahkan r, s, v
            $r = '0x' . substr($sigHex, 0, 64);
            $s = '0x' . substr($sigHex, 64, 64);
            $v = hexdec(substr($sigHex, 128, 2));

            // 2. Buat pesan ter-prefixed sesuai EIP-191
            $prefix    = "\x19Ethereum Signed Message:\n" . strlen($message);
            $prefixed  = $prefix . $message;
            $msgHash   = Keccak::hash($prefixed, 256);

            // 3. Recover public key
            $ec    = new EC('secp256k1');
            $pub   = $ec->recoverPubKey($msgHash, ['r' => $r, 's' => $s], $v - 27);
            $uncompressed = $pub->encode('hex', false);

            // 4. Hitung address dari public key
            $pubKeyHex = substr($uncompressed, 2); // strip '04'
            $addrHash  = Keccak::hash(hex2bin($pubKeyHex), 256);
            $recovered = '0x' . substr($addrHash, 24);
            $recovered = strtolower($recovered);

            Log::info("Recovered: {$recovered}, Expected: {$wallet}");

            if ($recovered !== $wallet) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // — lanjutkan login —
            $user->nonce      = bin2hex(random_bytes(16));
            $user->last_login = now();
            $user->save();
            Auth::login($user);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'user'    => [
                    'id'             => $user->id,
                    'user_id'        => $user->user_id,
                    'username'       => $user->profile?->username,
                    'wallet_address' => $wallet,
                    'last_login'     => $user->last_login->format('d/m/Y H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Signature recovery error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        // DIOPTIMALKAN: Hapus cache user-specific sebelum logout
        if (Auth::check()) {
            $userId       = Auth::user()->id;
            $userIdString = Auth::user()->user_id;

            // Hapus cache profile view
            Cache::forget("last_profile_view_{$userId}");

            // Hapus cache rekomendasi personal
            Cache::forget("personal_recommendations_{$userIdString}_hybrid_10");
            Cache::forget("personal_recommendations_{$userIdString}_fecf_10");
            Cache::forget("personal_recommendations_{$userIdString}_ncf_10");
            Cache::forget("rec_personal_{$userIdString}_10");
            Cache::forget("dashboard_personal_recs_{$userIdString}");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
