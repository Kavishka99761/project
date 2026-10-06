<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded files on the private disk, outside the public web root,
 * under a per-user directory with random file names. Files are only ever
 * served back through authenticated API routes.
 */
class DocumentStorage
{
    /**
     * @return array{path: string, original_name: string, mime_type: string, extension: string, size_bytes: int, checksum: string, absolute_path: string}
     */
    public function store(UploadedFile $file, string $area, int $userId): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $directory = "{$area}/{$userId}/".now()->format('Y/m');
        $name = Str::ulid()->toBase32().'.'.$extension;

        $path = $file->storeAs($directory, $name, ['disk' => $this->disk()]);

        return [
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'absolute_path' => $this->absolutePath($path),
        ];
    }

    /** Persist generated content (e.g. typed lecture notes) as a text file. */
    public function storeText(string $content, string $area, int $userId, string $extension = 'md'): array
    {
        $path = "{$area}/{$userId}/".now()->format('Y/m').'/'.Str::ulid()->toBase32().'.'.$extension;
        Storage::disk($this->disk())->put($path, $content);

        return [
            'path' => $path,
            'mime_type' => $extension === 'md' ? 'text/markdown' : 'text/plain',
            'extension' => $extension,
            'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content),
            'absolute_path' => $this->absolutePath($path),
        ];
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk($this->disk())->path($path);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function disk(): string
    {
        return config('edusmart.uploads.disk', 'local');
    }
}
