<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService
{
    protected string $url = '';
    protected string $key = '';
    protected string $bucket = '';
    protected bool $enabled = false;

    public function __construct()
    {
        $this->enabled = Setting::get('supabase_enabled', '0') === '1';
        if (!$this->enabled) {
            return;
        }

        $this->url    = rtrim(Setting::get('supabase_url', ''), '/');
        $this->key    = Setting::get('supabase_service_key', '');
        $this->bucket = Setting::get('supabase_bucket', 'documents');
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && !empty($this->url)
            && !empty($this->key)
            && !empty($this->bucket);
    }

    /**
     * Test connection to Supabase Storage.
     */
    public function testConnection(string $url, string $key, string $bucket): array
    {
        try {
            $url = rtrim($url, '/');

            // Try to list objects in bucket (or create bucket if not exists)
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$key}",
                'apikey'        => $key,
            ])->get("{$url}/storage/v1/bucket/{$bucket}");

            if ($response->status() === 200) {
                return [
                    'success' => true,
                    'message' => "Koneksi berhasil! Bucket '{$bucket}' ditemukan dan dapat diakses.",
                ];
            }

            if ($response->status() === 404) {
                // Bucket doesn't exist, try to create it
                $createResponse = Http::withHeaders([
                    'Authorization' => "Bearer {$key}",
                    'apikey'        => $key,
                    'Content-Type'  => 'application/json',
                ])->post("{$url}/storage/v1/bucket", [
                    'id'     => $bucket,
                    'name'   => $bucket,
                    'public' => false,
                ]);

                if ($createResponse->successful()) {
                    return [
                        'success' => true,
                        'message' => "Koneksi berhasil! Bucket '{$bucket}' berhasil dibuat.",
                    ];
                }

                return [
                    'success' => false,
                    'message' => "Koneksi berhasil ke Supabase, namun bucket '{$bucket}' tidak ditemukan dan gagal dibuat. Buat bucket tersebut secara manual di Supabase Dashboard.",
                ];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi gagal. Pastikan Service Role Key yang digunakan benar (bukan anon key).',
                ];
            }

            return [
                'success' => false,
                'message' => 'Gagal terhubung. Status: ' . $response->status() . ' — ' . $response->body(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Koneksi gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Upload a document file to Supabase Storage.
     * Returns the public/signed URL of the uploaded file.
     */
    public function uploadDocument(string $filePath, string $fileName, string $documentType, ?int $companyId = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception('Integrasi Supabase Storage belum diaktifkan atau belum dikonfigurasi dengan benar.');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Gagal membaca file lokal untuk diupload.');
        }

        // Folder path: company_id/document_type/filename
        $folder    = ($companyId ? "company_{$companyId}" : 'global') . "/{$documentType}";
        $objectPath = "{$folder}/{$fileName}";

        $uploadUrl = "{$this->url}/storage/v1/object/{$this->bucket}/{$objectPath}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->key}",
            'apikey'        => $this->key,
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->withBody($content, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
          ->put($uploadUrl);

        if (!$response->successful()) {
            // Try POST (upsert) if PUT fails
            $response = Http::withHeaders([
                'Authorization'  => "Bearer {$this->key}",
                'apikey'         => $this->key,
                'Content-Type'   => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'x-upsert'       => 'true',
            ])->withBody($content, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
              ->post($uploadUrl);
        }

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error("Supabase upload failed: {$errorBody}");
            throw new \Exception("Gagal mengupload ke Supabase Storage: {$errorBody}");
        }

        // Generate signed URL (valid 10 years = 315360000 seconds)
        $signedUrl = $this->generateSignedUrl($objectPath, 315360000);
        if ($signedUrl) {
            return $signedUrl;
        }

        // Fallback: return the object URL path
        return "{$this->url}/storage/v1/object/{$this->bucket}/{$objectPath}";
    }

    /**
     * Generate a signed URL for a file in Supabase Storage.
     */
    public function generateSignedUrl(string $objectPath, int $expiresIn = 3600): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->key}",
                'apikey'        => $this->key,
                'Content-Type'  => 'application/json',
            ])->post("{$this->url}/storage/v1/object/sign/{$this->bucket}/{$objectPath}", [
                'expiresIn' => $expiresIn,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $signedUrlPath = $data['signedURL'] ?? $data['signedUrl'] ?? null;
                if ($signedUrlPath) {
                    // signedURL is a relative path — prepend base URL
                    if (str_starts_with($signedUrlPath, '/')) {
                        return $this->url . $signedUrlPath;
                    }
                    return $signedUrlPath;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to generate Supabase signed URL: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Delete a file from Supabase Storage by its URL.
     * The URL is expected to be a full URL containing the object path.
     */
    public function deleteFile(string $fileUrl): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            // Extract object path from URL
            // Patterns: /storage/v1/object/{bucket}/{path} or /storage/v1/object/sign/{bucket}/{path}
            $objectPath = $this->extractObjectPath($fileUrl);
            if (!$objectPath) {
                return false;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->key}",
                'apikey'        => $this->key,
                'Content-Type'  => 'application/json',
            ])->delete("{$this->url}/storage/v1/object/{$this->bucket}", [
                'prefixes' => [$objectPath],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Failed to delete Supabase file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract the object path from a Supabase Storage URL.
     */
    private function extractObjectPath(string $url): ?string
    {
        // Match: /storage/v1/object/{bucket}/{path} or /storage/v1/object/sign/{bucket}/{path}?token=...
        if (preg_match('#/storage/v1/object/(?:sign/)?[^/]+/(.+?)(?:\?|$)#', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
