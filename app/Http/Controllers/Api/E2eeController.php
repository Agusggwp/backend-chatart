<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\E2ee\StoreE2eeKeyRequest;
use App\Models\User;
use App\Models\UserE2eeKey;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class E2eeController extends Controller
{
    public function store(StoreE2eeKeyRequest $request): JsonResponse
    {
        $key = UserE2eeKey::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'identity_key' => $request->validated('identity_key'),
                'signed_prekey' => $request->validated('signed_prekey'),
                'prekey_signature' => $request->validated('prekey_signature'),
                'one_time_prekeys' => $request->validated('one_time_prekeys', []),
            ]
        );

        return ApiResponse::success(
            data: [
                'keys' => $key,
            ],
            message: 'Kunci enkripsi E2EE berhasil disimpan.',
            status: 201
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $key = UserE2eeKey::where('user_id', $user->id)->first();

        if (! $key) {
            return ApiResponse::error(
                message: 'Pengguna belum mengunggah kunci E2EE.',
                code: 404
            );
        }

        return ApiResponse::success(
            data: [
                'user_id' => $user->id,
                'identity_key' => $key->identity_key,
                'signed_prekey' => $key->signed_prekey,
                'prekey_signature' => $key->prekey_signature,
                'one_time_prekey' => !empty($key->one_time_prekeys) ? $key->one_time_prekeys[0] : null,
            ],
            message: 'Kunci publik E2EE pengguna berhasil diambil.'
        );
    }
}
