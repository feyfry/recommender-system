<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Elliptic\EC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use kornrunner\Keccak;

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
        try {
            $request->validate(['wallet_address' => 'required|string']);

            // Pastikan wallet address dalam format yang benar
            $walletAddress = $this->normalizeAddress($request->wallet_address);

            if (! $this->isValidEthereumAddress($walletAddress)) {
                return response()->json([
                    'error' => 'Invalid Ethereum address format',
                ], 400);
            }

            $user = User::firstOrCreate(
                ['wallet_address' => $walletAddress],
                [
                    'user_id' => 'user_' . Str::random(10),
                    'role'    => 'community',
                ]
            );

            // Pastikan ada profile
            Profile::firstOrCreate(['user_id' => $user->id]);

            // Generate nonce baru - gunakan format yang lebih simple
            $nonce       = Str::random(32);
            $user->nonce = $nonce;
            $user->save();

            return response()->json([
                'nonce'   => $nonce,
                'message' => "Sign this message to verify you own this wallet:\n\nNonce: {$nonce}",
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getNonce: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate nonce: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifikasi tanda tangan dan login
     */
    public function verifySignature(Request $request)
    {
        try {
            $request->validate([
                'wallet_address' => 'required|string',
                'signature'      => 'required|string',
            ]);

            $wallet    = $this->normalizeAddress($request->wallet_address);
            $signature = $request->signature;

            // Ambil user dan pesan nonce
            $user = User::where('wallet_address', $wallet)->first();
            if (! $user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Format message yang sama dengan frontend
            $message = "Sign this message to verify you own this wallet:\n\nNonce: {$user->nonce}";

            // Verifikasi signature menggunakan pendekatan yang lebih sederhana
            if ($this->verifyMessageSignature($message, $signature, $wallet)) {
                                                     // Signature valid - lanjutkan login
                $user->nonce      = Str::random(32); // Generate nonce baru
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
            } else {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

        } catch (\Exception $e) {
            Log::error('Signature verification error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        // Clear cache sebelum logout
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

    /**
     * Normalize Ethereum address
     */
    private function normalizeAddress($address)
    {
        // Remove 0x prefix if exists and convert to lowercase
        $address = strtolower(preg_replace('/^0x/', '', $address));

        // Add 0x prefix back
        return '0x' . $address;
    }

    /**
     * Validate Ethereum address format
     */
    private function isValidEthereumAddress($address)
    {
        // Check if it's a valid Ethereum address format
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }

    /**
     * Simplified message signature verification
     */
    private function verifyMessageSignature($message, $signature, $expectedAddress)
    {
        try {
            // Remove 0x prefix from signature
            $sigHex = substr($signature, 2);

            // Split signature into r, s, v
            $r = substr($sigHex, 0, 64);
            $s = substr($sigHex, 64, 64);
            $v = hexdec(substr($sigHex, 128, 2));

            // EIP-191 prefix
            $prefix          = "\x19Ethereum Signed Message:\n" . strlen($message);
            $prefixedMessage = $prefix . $message;

            // Hash the prefixed message
            $messageHash = Keccak::hash($prefixedMessage, 256);

            // Initialize elliptic curve
            $ec = new EC('secp256k1');

            // Recover the public key
            $recid     = $v - 27;
            $publicKey = $ec->recoverPubKey(
                $messageHash,
                ['r' => '0x' . $r, 's' => '0x' . $s],
                $recid
            );

            // Get the address from public key
            $publicKeyHex     = $publicKey->encode('hex', false);
            $publicKeyHex     = substr($publicKeyHex, 2); // Remove 04 prefix
            $addressHash      = Keccak::hash(hex2bin($publicKeyHex), 256);
            $recoveredAddress = '0x' . substr($addressHash, 24);

            // Normalize and compare addresses
            $recoveredAddress = strtolower($recoveredAddress);
            $expectedAddress  = strtolower($expectedAddress);

            Log::info("Message verification", [
                'message'   => $message,
                'recovered' => $recoveredAddress,
                'expected'  => $expectedAddress,
                'match'     => ($recoveredAddress === $expectedAddress),
            ]);

            return $recoveredAddress === $expectedAddress;

        } catch (\Exception $e) {
            Log::error('Error in signature verification: ' . $e->getMessage());
            return false;
        }
    }
}
