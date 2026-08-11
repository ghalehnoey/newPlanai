<?php

use App\Models\AiModel;
use App\Services\AiApiService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$imageName = '22.png';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--image=')) {
        $imageName = basename(substr($arg, 8));
    }
}

$sourceImage = __DIR__ . '/' . $imageName;

if (!is_file($sourceImage)) {
    fwrite(STDERR, "Missing test image: {$sourceImage}\n");
    exit(1);
}

$targetDir = storage_path('app/public/codex_vision_test');

if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    fwrite(STDERR, "Cannot create storage test directory: {$targetDir}\n");
    exit(1);
}

$targetImage = $targetDir . '/22.png';
copy($sourceImage, $targetImage);

$model = AiModel::query()
    ->where('is_active', true)
    ->where('supports_files', true)
    ->where(function ($query) {
        $query->where('provider', 'openai')
            ->orWhere('key', 'like', '%chatgpt%');
    })
    ->orderBy('sort_order')
    ->first();

if (!$model) {
    $model = AiModel::query()
        ->where('is_active', true)
        ->where('supports_files', true)
        ->orderBy('sort_order')
        ->first();
}

if (!$model) {
    fwrite(STDERR, "No active file-capable AI model found.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $args, true);
$modelKey = $args[0] ?? $model->key;

if ($modelKey === '--dry-run' || str_starts_with($modelKey, '--image=')) {
    $modelKey = $model->key;
}

$mime = mime_content_type($targetImage) ?: 'image/png';
$file = (object) [
    'path' => 'codex_vision_test/22.png',
    'mime_type' => $mime,
    'original_name' => $imageName,
];

$service = app(AiApiService::class);
$reflection = new ReflectionMethod(AiApiService::class, 'buildOpenAiSimpleContent');
$reflection->setAccessible(true);
$content = $reflection->invoke($service, 'Describe this image briefly.', [$file]);
$imageParts = is_array($content)
    ? count(array_filter($content, fn($part) => ($part['type'] ?? null) === 'image_url'))
    : 0;

echo "MODEL={$modelKey}\n";
echo "IMAGE={$file->path}; MIME={$mime}; SIZE=" . filesize($targetImage) . "\n";
echo "CONTENT_PARTS=" . (is_array($content) ? count($content) : 1) . "\n";
echo "IMAGE_PARTS={$imageParts}\n";

if ($dryRun) {
    exit($imageParts > 0 ? 0 : 1);
}

echo "ANSWER_START\n";

$service->stream(
    'Describe this image briefly. If you can see it, mention the main visible elements.',
    $modelKey,
    'You are a concise vision tester.',
    [],
    [$file],
    function (string $chunk): void {
        echo $chunk;
    }
);

echo "\nANSWER_END\n";
