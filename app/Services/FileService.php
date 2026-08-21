<?php

namespace App\Services;

use App\Models\UploadedFile as UploadedFileModel;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;
use Throwable;

class FileService
{
    private const PDF_TO_TEXT_BINARY = '/usr/bin/pdftotext';
    private const PDF_INFO_BINARY = '/usr/bin/pdfinfo';
    private const PDF_TO_IMAGE_BINARY = '/usr/bin/pdftoppm';

    /**
     * Store an uploaded image and create its database record.
     */
    public function handleImageUpload(HttpUploadedFile $file, int $userId, int $conversationId): UploadedFileModel
    {
        $storedName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $folder = 'uploads/images/' . now()->format('Y/m');
        $path = $file->storeAs($folder, $storedName, 'public');

        return UploadedFileModel::create([
            'user_id'         => $userId,
            'conversation_id' => $conversationId,
            'original_name'   => $file->getClientOriginalName(),
            'stored_name'     => $storedName,
            'path'            => $path,
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
            'type'            => 'image',
            'extracted_text'  => null,
        ]);
    }

    /**
     * Extract searchable UTF-8 text from txt, Word and text-based PDF files.
     */
    public function extractText(HttpUploadedFile|string $file, ?string $mimeType = null): ?string
    {
        $filePath = $file instanceof HttpUploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower($file instanceof HttpUploadedFile
            ? $file->getClientOriginalExtension()
            : pathinfo($file, PATHINFO_EXTENSION));

        if (!$filePath || !is_readable($filePath)) {
            return null;
        }

        try {
            if ($extension === 'txt') {
                return $this->normalizeExtractedText((string) file_get_contents($filePath));
            }

            if (in_array($extension, ['doc', 'docx'], true)
                && class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                $text = '';

                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $childElement) {
                                if (method_exists($childElement, 'getText')) {
                                    $text .= $childElement->getText() . "\n";
                                }
                            }
                        }
                    }
                }

                return $this->normalizeExtractedText($text);
            }

            if ($extension === 'pdf' && class_exists(Pdf::class)) {
                $binary = is_executable(self::PDF_TO_TEXT_BINARY)
                    ? self::PDF_TO_TEXT_BINARY
                    : null;

                $text = Pdf::getText($filePath, $binary, ['layout'], 45);

                return $this->normalizeExtractedText($text);
            }
        } catch (Throwable $e) {
            Log::error('File text extraction failed', [
                'path' => $filePath,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function pdfPageCount(HttpUploadedFile|string $file): ?int
    {
        $filePath = $file instanceof HttpUploadedFile ? $file->getRealPath() : $file;

        if (!$filePath || !is_readable($filePath) || !is_executable(self::PDF_INFO_BINARY)) {
            return null;
        }

        try {
            $process = new Process([self::PDF_INFO_BINARY, $filePath]);
            $process->setTimeout(15);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            if (preg_match('/^Pages:\s+(\d+)$/mi', $process->getOutput(), $matches) === 1) {
                return (int) $matches[1];
            }
        } catch (Throwable $e) {
            Log::warning('Could not inspect PDF page count', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Render PDF pages as optimized JPEG images for vision-capable AI models.
     * Returned image bytes are kept in memory; temporary files are always removed.
     *
     * @return array<int, array{page:int, mime_type:string, data:string}>
     */
    public function renderPdfPagesAsImages(HttpUploadedFile|string $file, int $maxPages = 12): array
    {
        $filePath = $file instanceof HttpUploadedFile ? $file->getRealPath() : $file;
        $maxPages = max(1, min($maxPages, 20));

        if (!$filePath || !is_readable($filePath) || !is_executable(self::PDF_TO_IMAGE_BINARY)) {
            return [];
        }

        $temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'newplan_pdf_' . Str::random(24);

        if (!mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
            Log::error('Could not create temporary PDF rendering directory');
            return [];
        }

        $outputPrefix = $temporaryDirectory . DIRECTORY_SEPARATOR . 'page';

        try {
            $process = new Process([
                self::PDF_TO_IMAGE_BINARY,
                '-f', '1',
                '-l', (string) $maxPages,
                '-jpeg',
                '-scale-to', '1600',
                '-jpegopt', 'quality=75,optimize=y',
                $filePath,
                $outputPrefix,
            ]);
            $process->setTimeout(90);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::warning('PDF page rendering failed', [
                    'path' => $filePath,
                    'error' => trim($process->getErrorOutput()),
                ]);

                return [];
            }

            $renderedFiles = glob($outputPrefix . '-*.jpg') ?: [];
            natsort($renderedFiles);
            $pages = [];

            foreach (array_values($renderedFiles) as $index => $renderedFile) {
                $imageData = file_get_contents($renderedFile);

                if ($imageData === false || $imageData === '') {
                    continue;
                }

                $pages[] = [
                    'page' => $index + 1,
                    'mime_type' => 'image/jpeg',
                    'data' => $imageData,
                ];
            }

            return $pages;
        } catch (Throwable $e) {
            Log::error('PDF page rendering exception', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [];
        } finally {
            foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }

            @rmdir($temporaryDirectory);
        }
    }

    private function normalizeExtractedText(string $text): ?string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1256, ISO-8859-1');
        }

        $text = str_replace(["\r\n", "\r", "\f", "\0"], ["\n", "\n", "\n\n[صفحه بعد]\n\n", ''], $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{4,}/u', "\n\n\n", $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : null;
    }
}
