<?php

// ۱. تابع برای خواندن فایل TXT
function read_txt($filePath) {
    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    }
    return "فایل متنی یافت نشد.";
}

// ۲. تابع برای خواندن فایل Word (DOCX) با استفاده از کلاس پیش‌فرض ZipArchive
function read_docx($filePath) {
    if (!file_exists($filePath)) {
        return "فایل ورد یافت نشد.";
    }
    
    $zip = new ZipArchive;
    if ($zip->open($filePath) === true) {
        // فایل‌های docx در واقع فایل‌های zip هستند. متن در document.xml قرار دارد.
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $zip->close();
            // حذف تگ‌های XML برای رسیدن به متن خالص
            return strip_tags($data); 
        }
        $zip->close();
    }
    return "خطا در خواندن فایل ورد.";
}

// ---------------------------------------------------------
// مسیر فایل‌های خود را اینجا قرار دهید
#$txtFile = 'sample.txt';
$docxFile = 'sample.docx';

// استخراج متن‌ها (می‌توانید هر کدام را که نیاز دارید فعال کنید)
//$extractedText = read_txt($txtFile); 
 $extractedText = read_docx($docxFile); 

// ---------------------------------------------------------
// ۳. ارسال به API گپ جی‌پی‌تی

$apiKey = getenv('OPENAI_API_KEY') ?: '';
$url = "https://api.gapgpt.app/v1/chat/completions";

// ساخت پرامپت با متن استخراج شده
$prompt = "لطفاً متن زیر را که از یک فایل استخراج شده بررسی کن و یک خلاصه از آن ارائه بده:\n\n" . $extractedText;

$data = [
    "model" => "gpt-4o", // یا هر مدل متنی دیگر مانند claude-sonnet-4-20250514
    "messages" => [
        [
            "role" => "user",
            "content" => $prompt
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

// نمایش نتیجه
$responseData = json_decode($response, true);
if (isset($responseData['choices'][0]['message']['content'])) {
    echo "پاسخ هوش مصنوعی:\n";
    echo $responseData['choices'][0]['message']['content'];
} else {
    echo "خطا در دریافت پاسخ:\n";
    print_r($response);
}

?>
