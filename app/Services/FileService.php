<?php

namespace App\Services;

use App\Models\UploadedFile as UploadedFileModel;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;
use Throwable;
use Illuminate\Validation\ValidationException;
use XMLReader;
use ZipArchive;

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
                if (filesize($filePath) > 240000) {
                    throw ValidationException::withMessages(['files' => 'متن فایل طولانی است؛ لطفاً آن را به بخش‌های کوچک‌تر تقسیم کنید.']);
                }
                return $this->normalizeExtractedText((string) file_get_contents($filePath));
            }

            if ($extension === 'docx') {
                return $this->extractDocxText($filePath);
            }

            if ($extension === 'doc') {
                throw ValidationException::withMessages([
                    'files' => 'لطفاً فایل Word قدیمی (DOC) را به DOCX یا PDF تبدیل و دوباره ارسال کنید.',
                ]);
            }

            if ($extension === 'pdf' && class_exists(Pdf::class)) {
                $binary = is_executable(self::PDF_TO_TEXT_BINARY)
                    ? self::PDF_TO_TEXT_BINARY
                    : null;

                $text = Pdf::getText($filePath, $binary, ['layout'], 45);

                return $this->normalizeExtractedText($text);
            }
        } catch (ValidationException $e) {
            throw $e;
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

    private function extractDocxText(string $path): ?string
    {
        $reject = static fn(string $message) => ValidationException::withMessages(['files' => $message]);
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw $reject('فایل Word قابل خواندن نیست؛ لطفاً نسخه سالم DOCX یا PDF را ارسال کنید.');
        }
        try {
            $stat = $zip->statName('word/document.xml');
            $total = 0;
            if (!$stat || $zip->numFiles > 2000 || $stat['size'] > 8 * 1024 * 1024) {
                throw $reject('ساختار این Word بسیار بزرگ یا نامعتبر است. لطفاً فایل را به چند بخش کوچک‌تر تقسیم کنید.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
                if ($total > 64 * 1024 * 1024) {
                    throw $reject('حجم بازشدهٔ فایل Word زیاد است. لطفاً تصاویر اضافی را حذف یا فایل را تقسیم کنید.');
                }
            }
            $xml = $zip->getFromName('word/document.xml', 8 * 1024 * 1024 + 1);
        } finally {
            $zip->close();
        }
        if (!is_string($xml) || stripos($xml, '<!DOCTYPE') !== false) {
            throw $reject('ساختار این فایل Word پشتیبانی نمی‌شود. لطفاً آن را دوباره با Word ذخیره کنید.');
        }
        $previousErrors = libxml_use_internal_errors(true);
        $reader = new XMLReader();
        $text = '';
        $nodes = 0;
        try {
            if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw $reject('محتوای فایل Word قابل خواندن نیست.');
            }
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            while ($reader->read()) {
                if (++$nodes > 200000) {
                    throw $reject('این Word پیچیده یا بسیار بزرگ است. لطفاً آن را تقسیم کنید.');
                }
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                    $text .= $reader->readString();
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && in_array($reader->localName, ['p', 'tr'], true)) {
                    $text .= "\n";
                } elseif ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->localName, ['tab', 'br'], true)) {
                    $text .= $reader->localName === 'tab' ? "\t" : "\n";
                }
                if (strlen($text) > 240000) {
                    throw $reject('متن Word برای یک درخواست زیاد است. لطفاً آن را به چند بخش کوچک‌تر تقسیم کنید.');
                }
            }
            if (libxml_get_errors()) {
                throw $reject('فایل Word ناقص یا خراب است؛ لطفاً نسخه سالم آن را ارسال کنید.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        return $this->normalizeExtractedText($text);
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
