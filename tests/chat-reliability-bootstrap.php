<?php
require __DIR__ . '/../vendor/autoload.php';
foreach (['app/Exceptions/AiRequestException.php', 'app/Services/ChatReliability.php', 'app/Services/FileService.php'] as $file) { require_once __DIR__ . '/../' . $file; }
