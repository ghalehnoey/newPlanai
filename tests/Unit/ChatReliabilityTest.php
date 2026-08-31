<?php

use App\Exceptions\AiRequestException;
use App\Services\ChatReliability;
use App\Services\FileService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class ChatReliabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(sys_get_temp_dir());
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        $app->instance('validator', new Factory($app['translator'], $app));
        Facade::setFacadeApplication($app);
    }

    public function testShortHistoryIsUnchanged(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'system'],
            ['role' => 'user', 'content' => 'old'],
            ['role' => 'assistant', 'content' => 'answer'],
            ['role' => 'user', 'content' => 'latest'],
        ];
        $result = ChatReliability::prepareMessages($messages);
        self::assertSame($messages, $result['messages']);
        self::assertFalse($result['trimmed']);
    }

    public function testLargeHistoryRetainsSystemAndLatestWithoutOrphanAssistant(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'important policy'],
            ['role' => 'user', 'content' => str_repeat('ا', 18000)],
            ['role' => 'assistant', 'content' => 'old reply'],
            ['role' => 'user', 'content' => 'recent question'],
            ['role' => 'assistant', 'content' => 'recent reply'],
            ['role' => 'user', 'content' => 'latest question'],
        ];
        $result = ChatReliability::prepareMessages($messages);
        self::assertTrue($result['trimmed']);
        self::assertSame('important policy', $result['messages'][0]['content']);
        self::assertSame('recent question', $result['messages'][1]['content']);
        self::assertSame('latest question', end($result['messages'])['content']);
    }

    public function testOversizedCurrentQuestionIsRejectedNotSilentlyTruncated(): void
    {
        $this->expectException(LengthException::class);
        ChatReliability::prepareMessages([
            ['role' => 'system', 'content' => 'system'],
            ['role' => 'user', 'content' => str_repeat('x', 23000)],
        ]);
    }

    public function testOversizedAttachmentIsRejectedNotIgnored(): void
    {
        $this->expectException(LengthException::class);
        ChatReliability::prepareMessages([
            ['role' => 'system', 'content' => 'system'],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => str_repeat('x', 23000)]]],
        ]);
    }

    public function testTransientRetriesExcludeQuotaAuthAndOversizedRequests(): void
    {
        self::assertTrue(ChatReliability::canRetry(new AiRequestException('empty reply', 0, 52)));
        self::assertTrue(ChatReliability::canRetry(new AiRequestException('rate limit', 429)));
        self::assertTrue(ChatReliability::canRetry(new AiRequestException('unavailable', 503)));
        self::assertFalse(ChatReliability::canRetry(new AiRequestException('insufficient_quota', 429)));
        self::assertFalse(ChatReliability::canRetry(new AiRequestException('Request too large', 429)));
        self::assertFalse(ChatReliability::canRetry(new AiRequestException('bad token', 401)));
        self::assertFalse(ChatReliability::canRetry(new RuntimeException('unknown')));
    }

    public function testPublicErrorsNeverContainUpstreamSecrets(): void
    {
        $error = new AiRequestException('secret-token user-prompt https://upstream/private', 500);
        $public = json_encode(ChatReliability::publicError($error));
        self::assertStringNotContainsString('secret-token', $public);
        self::assertStringNotContainsString('user-prompt', $public);
        self::assertSame('incomplete_response', ChatReliability::publicError($error, true)['code']);
        self::assertSame('context_too_large', ChatReliability::publicError(new LengthException())['code']);
    }

    private function withDocx(string $xml, callable $test): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chat-docx-test-') . '.docx';
        // tempnam's base file is also removed below.
        $base = substr($path, 0, -5);
        try {
            $zip = new ZipArchive();
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('word/document.xml', $xml);
            $zip->close();
            $test($path);
        } finally {
            if (is_file($path)) unlink($path);
            if (is_file($base)) unlink($base);
        }
    }

    public function testDocxParagraphsTablesAndPersianText(): void
    {
        $this->withDocx('<w:document xmlns:w="urn:word"><w:body><w:p><w:r><w:t>سلام</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>جدول</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>', static function ($path) {
            $text = (new FileService())->extractText($path);
            self::assertStringContainsString('سلام', $text);
            self::assertStringContainsString('جدول', $text);
        });
    }

    public function testDocxRejectsEntities(): void
    {
        $this->expectException(ValidationException::class);
        $this->withDocx('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///not-a-real-secret">]><w:document xmlns:w="urn:word"><w:t>&e;</w:t></w:document>',
            static fn($path) => (new FileService())->extractText($path));
    }

    public function testDocxRejectsLargeExpandedXml(): void
    {
        $this->expectException(ValidationException::class);
        $this->withDocx(str_repeat('x', 8 * 1024 * 1024 + 1),
            static fn($path) => (new FileService())->extractText($path));
    }

    public function testDocxRejectsMalformedXml(): void
    {
        $this->expectException(ValidationException::class);
        $this->withDocx('<w:document xmlns:w="urn:word"><w:t>unclosed',
            static fn($path) => (new FileService())->extractText($path));
    }
}
