<?php

namespace App\Services;

use App\Models\Setting;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected ?Drive $driveService = null;
    protected bool $enabled = false;

    public function __construct()
    {
        $this->enabled = Setting::get('google_drive_enabled', '0') === '1';
        if (!$this->enabled) {
            return;
        }

        $jsonCredentials = Setting::get('google_drive_service_account_json');
        if (empty($jsonCredentials)) {
            return;
        }

        try {
            $credentials = json_decode($jsonCredentials, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($credentials)) {
                Log::error("Google Drive: Invalid service account JSON formatting.");
                return;
            }

            $client = new Client();
            $client->setAuthConfig($credentials);
            $client->addScope(Drive::DRIVE);
            
            $this->driveService = new Drive($client);
        } catch (\Exception $e) {
            Log::error("Google Drive connection failed to initialize: " . $e->getMessage());
        }
    }

    /**
     * Check if integration is enabled and service is fully initialized.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->driveService !== null;
    }

    /**
     * Test a specific JSON credentials string and optional folder ID.
     */
    public function testConnection(string $jsonCredentials, ?string $testFolderId = null): array
    {
        try {
            $credentials = json_decode($jsonCredentials, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($credentials)) {
                return [
                    'success' => false,
                    'message' => 'Format JSON tidak valid. Pastikan Anda menyalin seluruh isi file kredensial JSON dengan benar.'
                ];
            }

            $client = new Client();
            $client->setAuthConfig($credentials);
            $client->addScope(Drive::DRIVE);
            
            $service = new Drive($client);
            
            // Try to list files - supportsAllDrives required for Shared Drives
            $service->files->listFiles([
                'pageSize' => 1,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            // If a test folder ID is provided, try to verify access
            if (!empty($testFolderId)) {
                try {
                    $folder = $service->files->get($testFolderId, [
                        'fields' => 'id, name, mimeType',
                        'supportsAllDrives' => true,
                    ]);
                    if ($folder->getMimeType() !== 'application/vnd.google-apps.folder') {
                        return [
                            'success' => false,
                            'message' => 'Koneksi berhasil, tetapi ID Folder "' . $testFolderId . '" bukan merupakan folder yang valid di Google Drive.'
                        ];
                    }
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Koneksi berhasil, tetapi Folder ID "' . $testFolderId . '" tidak dapat diakses. Pastikan Anda telah membagikan (share) folder tersebut dengan email Service Account: ' . ($credentials['client_email'] ?? 'tidak diketahui') . ' sebagai Editor.'
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Koneksi ke Google Drive berhasil! Email Service Account: ' . ($credentials['client_email'] ?? '')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Koneksi gagal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Google Drive Folder ID by document type and company.
     */
    public function getFolderIdByType(string $type, ?int $companyId = null): ?string
    {
        if ($companyId) {
            $companyFolder = Setting::get("google_drive_folder_{$type}", null, $companyId);
            if ($companyFolder) {
                return $companyFolder;
            }
        }
        return Setting::get("google_drive_folder_{$type}");
    }

    /**
     * Upload a document to Google Drive folder depending on its type and company.
     */
    public function uploadDocument(string $filePath, string $fileName, string $documentType, ?int $companyId = null): string
    {
        if (!$this->isEnabled()) {
            throw new \Exception("Integrasi Google Drive belum diaktifkan atau belum dikonfigurasi dengan benar.");
        }

        $folderId = $this->getFolderIdByType($documentType, $companyId);

        if (!$folderId) {
            throw new \Exception("ID Folder Google Drive untuk tipe dokumen '{$documentType}' belum dikonfigurasi.");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId]
        ]);

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Gagal membaca file lokal untuk diupload.");
        }

        try {
            $file = $this->driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'uploadType' => 'multipart',
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]);

            return $file->id;
        } catch (\Exception $e) {
            Log::error("Gagal mengupload file '{$fileName}' ke Google Drive: " . $e->getMessage());
            throw new \Exception("Gagal mengupload ke Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Delete a file from Google Drive.
     */
    public function deleteFile(string $fileId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->driveService->files->delete($fileId, ['supportsAllDrives' => true]);
            return true;
        } catch (\Exception $e) {
            Log::error("Gagal menghapus file '{$fileId}' dari Google Drive: " . $e->getMessage());
            return false;
        }
    }
}
