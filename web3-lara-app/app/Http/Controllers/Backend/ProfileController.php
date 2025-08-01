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

        // Validasi data - hanya username dan avatar
        $validator = Validator::make($request->all(), [
            'username'   => 'nullable|string|max:50|unique:profiles,username,' . $profile->id,
            'avatar_url' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Proses upload avatar jika ada
            if ($request->hasFile('avatar_url')) {
                // Hapus avatar lama jika ada
                if ($profile->avatar_url && file_exists(public_path($profile->avatar_url))) {
                    unlink(public_path($profile->avatar_url));
                }

                $file     = $request->file('avatar_url');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/avatars'), $filename);
                $profile->avatar_url = 'uploads/avatars/' . $filename;
            }

            // Update data profil - hanya username
            $profile->username = $request->input('username');

            // Simpan perubahan
            $profile->save();

            // Hapus cache yang mungkin terkait dengan profil user
            $this->clearUserProfileCache($user->user_id);

            return redirect()->route('panel.profile.edit')
                ->with('success', 'Profil berhasil diperbarui.');
        } catch (\Exception $e) {
            // Hapus file yang diupload jika terjadi error
            if ($request->hasFile('avatar_url') && isset($filename)) {
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
     * Hapus cache terkait profil pengguna
     */
    private function clearUserProfileCache($userId)
    {
        $cacheKeys = [
            "dashboard_personal_recs_{$userId}",
            "user_profile_{$userId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
