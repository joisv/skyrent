<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppReleaseController extends Controller
{
    /**
     * Check if a newer version of the application is available.
     * GET /api/v1/app/check-update
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $currentBuild = $request->query('current_build') ?? $request->query('build_number');
        $latest = AppRelease::latestActive();

        if (!$latest) {
            return response()->json([
                'status' => 'success',
                'has_update' => false,
                'is_mandatory' => false,
                'message' => 'Tidak ada rilis pembaruan aktif saat ini.',
                'data' => null,
            ]);
        }

        $hasUpdate = $currentBuild === null || (int) $currentBuild < $latest->build_number;
        $isMandatory = $hasUpdate
            && $latest->min_supported_build !== null
            && $currentBuild !== null
            && (int) $currentBuild < $latest->min_supported_build;

        $formattedSize = number_format($latest->apk_size / 1048576, 2) . ' MB';

        $data = [
            'update_available' => $hasUpdate,
            'has_update' => $hasUpdate,
            'is_mandatory' => $isMandatory,
            'current_build' => $currentBuild !== null ? (int) $currentBuild : null,
            'latest_version' => $latest->version_name,
            'latest_build' => $latest->build_number,
            'min_supported_build' => $latest->min_supported_build,
            'title' => $isMandatory ? 'Pembaruan Wajib Tersedia' : 'Pembaruan Tersedia',
            'release_notes' => $latest->release_notes,
            'download_url' => route('api.v1.app.download', ['buildNumber' => $latest->build_number]),
            'sha256_checksum' => $latest->apk_checksum_sha256,
            'checksum_sha256' => $latest->apk_checksum_sha256,
            'file_size' => $latest->apk_size,
            'formatted_size' => $formattedSize,
            'published_at' => $latest->created_at?->toIso8601String(),
        ];

        return response()->json([
            'status' => 'success',
            'update_available' => $hasUpdate,
            'has_update' => $hasUpdate,
            'is_mandatory' => $isMandatory,
            'message' => $hasUpdate ? 'Versi baru tersedia.' : 'Aplikasi Anda sudah menggunakan versi terbaru.',
            'data' => $data,
        ]);
    }

    /**
     * Securely stream/download the release APK file.
     * GET /api/v1/app/download/{buildNumber}
     */
    public function download(Request $request, string $buildNumber): StreamedResponse|JsonResponse
    {
        if (strtolower($buildNumber) === 'latest') {
            $release = AppRelease::latestActive();
        } else {
            $release = AppRelease::where('build_number', (int) $buildNumber)
                ->where('is_active', true)
                ->first();
        }

        if (!$release) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas rilis APK tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        if (!Storage::disk('local')->exists($release->apk_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas APK fisik tidak ditemukan pada penyimpanan server.',
            ], 404);
        }

        $downloadFilename = "skymobile-v{$release->version_name}-b{$release->build_number}.apk";

        return Storage::disk('local')->download($release->apk_path, $downloadFilename, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Checksum-SHA256' => $release->apk_checksum_sha256,
        ]);
    }
}
