<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route('panel.profile.edit');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        $user = Auth::user();

        // Dapatkan atau buat profil
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        return view('backend.profile.edit', [
            'user'    => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Dapatkan atau buat profil
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        // Validasi data
        $validator = Validator::make($request->all(), [
            'username'         => 'nullable|string|max:50|unique:profiles,username,' . $profile->id,
            'risk_tolerance'   => 'nullable|string|in:' . implode(',', Profile::$validRiskTolerances),
            'investment_style' => 'nullable|string|in:' . implode(',', Profile::$validInvestmentStyles),
            'avatar_url'       => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'categories'       => 'nullable|array',
            'chains'           => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Proses upload avatar jika ada
            if ($request->hasFile('avatar_url')) {
                $file     = $request->file('avatar_url');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/avatars'), $filename);
                $profile->avatar_url = 'uploads/avatars/' . $filename;
            }

            // Update data profil
            $profile->username         = $request->input('username');
            $profile->risk_tolerance   = $request->input('risk_tolerance');
            $profile->investment_style = $request->input('investment_style');

            // Update preferensi
            $preferences = $profile->preferences ?? [];
            if ($request->has('categories')) {
                $preferences['categories'] = $request->input('categories');
            }
            if ($request->has('chains')) {
                $preferences['chains'] = $request->input('chains');
            }
            $profile->preferences = $preferences;

            // Simpan perubahan
            $profile->save();

            // DIOPTIMALKAN: Hapus semua cache terkait rekomendasi untuk user ini
            // karena preferensi baru bisa mempengaruhi rekomendasi
            $this->clearUserRecommendationCache($user->user_id);

            return redirect()->route('panel.profile.edit')
                ->with('success', 'Profil berhasil diperbarui.');
        } catch (\Exception $e) {
            // Hapus file yang diupload jika terjadi error
            if ($request->hasFile('avatar_url')) {
                $filePath = public_path('uploads/avatars/' . $filename);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            return redirect()->back()
                ->with('error', 'Gagal memperbarui profil: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show notification settings page
     */
    public function notificationSettings()
    {
        $user = Auth::user();

        // Dapatkan atau buat profil
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        return view('backend.profile.notification_settings', [
            'user'    => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(Request $request)
    {
        $user = Auth::user();

        // Dapatkan atau buat profil
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        // Validasi data
        $validator = Validator::make($request->all(), [
            'price_alerts'          => 'nullable|boolean',
            'recommendation_alerts' => 'nullable|boolean',
            'market_events'         => 'nullable|boolean',
            'portfolio_updates'     => 'nullable|boolean',
            'email_notifications'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Update pengaturan notifikasi
            $notificationSettings = $profile->notification_settings ?? [];

            $notificationSettings['price_alerts']          = $request->boolean('price_alerts');
            $notificationSettings['recommendation_alerts'] = $request->boolean('recommendation_alerts');
            $notificationSettings['market_events']         = $request->boolean('market_events');
            $notificationSettings['portfolio_updates']     = $request->boolean('portfolio_updates');
            $notificationSettings['email_notifications']   = $request->boolean('email_notifications');

            $profile->notification_settings = $notificationSettings;
            $profile->save();

            return redirect()->route('panel.profile.notification-settings')
                ->with('success', 'Pengaturan notifikasi berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal memperbarui pengaturan notifikasi: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * DIOPTIMALKAN: Metode baru untuk membersihkan cache rekomendasi pengguna
     */
    private function clearUserRecommendationCache($userId)
    {
        $cacheKeys = [
            "personal_recommendations_{$userId}_hybrid_10",
            "personal_recommendations_{$userId}_fecf_10",
            "personal_recommendations_{$userId}_ncf_10",
            "rec_personal_{$userId}_10",
            "rec_personal_hybrid_{$userId}",
            "rec_personal_fecf_{$userId}",
            "rec_personal_ncf_{$userId}",
            "dashboard_personal_recs_{$userId}",
            "rec_interactions_{$userId}",
            "dashboard_interactions_{$userId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
