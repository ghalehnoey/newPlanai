<?php

namespace App\Services;

use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\UploadedFile as UploadedFileModel;
use Illuminate\Support\Facades\Log;

class FileService
{
    /**
     * ذخیره تصویر + ذخیره در دیتابیس + تولید URL
     */
    public function handleImageUpload(HttpUploadedFile $file, int $userId, int $conversationId): UploadedFileModel
    {
        // تولید نام فایل منحصر به فرد
        $storedName = Str::random(40) . '.' . $file->getClientOriginalExtension();

        // مسیر ذخیره در public/storage/uploads/images/YYYY/MM
        $folder = 'uploads/images/' . now()->format('Y/m');

        // ذخیره در دیسک public
        $path = $file->storeAs($folder, $storedName, 'public');

        // URL عمومی (فقط برای مرورگر، GPT از Base64 استفاده می‌کند)
        $publicUrl = Storage::disk('public')->url($path);

        // ذخیره در دیتابیس
        return UploadedFileModel::create([
            'user_id'         => $userId,
            'conversation_id' => $conversationId,
            'original_name'   => $file->getClientOriginalName(),
            'stored_name'     => $storedName,
            'path'            => $path,        // مسیر نسبی
            'public_url'      => $publicUrl,   // لازم برای نمایش در تاریخچه
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
            'type'            => 'image',
            'extracted_text'  => null,         // OCR غیرفعال
        ]);
    }


    /**
     * استخراج متن از فایل (پشتیبانی از txt, docx, doc, pdf)
     */
    public function extractText(HttpUploadedFile|string $file, ?string $mimeType = null): ?string
    {
        // ۱. تشخیص مسیر واقعی فایل
        $filePath = $file instanceof HttpUploadedFile ? $file->getRealPath() : $file;

        // ۲. تشخیص پسوند فایل
        $extension = $file instanceof HttpUploadedFile 
            ? $file->getClientOriginalExtension() 
            : pathinfo($file, PATHINFO_EXTENSION);
            
        $extension = strtolower($extension);

        if (!file_exists($filePath)) {
            return null;
        }

        try {
            // فایل‌های متنی ساده
            if ($extension === 'txt') {
                return file_get_contents($filePath);
            }

            // فایل‌های Word
            if (in_array($extension, ['doc', 'docx'])) {
                // بررسی نصب بودن کلاس PhpWord (در صورتی که از این پکیج استفاده می‌کنید)
                if (class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . " ";
                            } elseif (method_exists($element, 'getElements')) {
                                foreach ($element->getElements() as $childElement) {
                                    if (method_exists($childElement, 'getText')) {
                                        $text .= $childElement->getText() . " ";
                                    }
                                }
                            }
                        }
                    }
                    return trim($text) ?: null;
                }
            }

            // فایل‌های PDF (در صورت استفاده از پکیج smalot/pdfparser)
            if ($extension === 'pdf') {
                if (class_exists(\Smalot\PdfParser\Parser::class)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    return $pdf->getText() ?: null;
                }
            }

            // اگر نیاز به OCR تصویر داشتید در آینده اینجا اضافه می‌شود
            
        } catch (\Exception $e) {
            Log::error("خطا در استخراج متن فایل: " . $e->getMessage());
            return null;
        }

        return null;
    }
}
