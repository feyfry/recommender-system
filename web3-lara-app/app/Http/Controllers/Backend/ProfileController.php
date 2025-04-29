<?php

namespace App\Http\Controllers\Backend;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first() ?? new Profile(['user_id' => $user->id]);

        return view('backend.profile.edit', [
            'user' => $user,
            'profile' => $profile
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $profile = $user->profile;

        // Basic rules validation
        $rules = [
            'username' => 'nullable|string|max:50|unique:profiles,username,' . $profile->id ?? null,
            'preferences' => 'nullable|json',
            'risk_tolerance' => 'nullable|string|in:low,medium,high',
            'investment_style' => 'nullable|string|in:conservative,balanced,aggressive',
            'notification_settings' => 'nullable|json',
        ];

        // Add conditional validation rules for files
        if ($request->hasFile('avatar_url')) {
            $rules['avatar_url'] = 'nullable|image|mimes:jpeg,png,jpg,svg||mimetypes:image/jpeg,image/png,image/jpg,image/svg|max:2048';
        }

        $validatedData = $request->validate($rules);

        try {
            // Handle image upload
            if ($request->hasFile('avatar_url')) {
                $file = $request->file('avatar_url');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/avatars'), $filename);
                $validatedData['avatar_url'] = 'uploads/avatars/' . $filename;
            }

            // Update or create profile
            $profile->fill($validatedData);
            $profile->user_id = $user->id;
            $profile->save();

            return redirect()->route('panel.profile.edit')->with('success', 'Profile updated successfully.');
        } catch (\Exception $e) {
            // Only delete the file if it was created in this request
            if (isset($validatedData['avatar_url'])) {
                $filePath = public_path($validatedData['avatar_url']);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            return redirect()->back()->with('error', 'Failed to update profile: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
