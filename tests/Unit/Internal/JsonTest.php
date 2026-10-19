<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Internal;

use GoSuccess\WordpressOrg\Exception\SerializationException;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Internal\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    public function testObjectDecodesAnObject(): void
    {
        self::assertSame(['a' => 1], Json::object(new Response(200, " \n{\"a\":1}"), 'ep'));
    }

    public function testObjectAcceptsEmptyArrayAsEmptyObject(): void
    {
        self::assertSame([], Json::object(new Response(200, '[]'), 'ep'));
    }

    public function testObjectRejectsList(): void
    {
        $this->expectException(SerializationException::class);

        Json::object(new Response(200, '[1]'), 'ep');
    }

    public function testObjectRejectsHtml(): void
    {
        $this->expectException(SerializationException::class);

        Json::object(new Response(200, '<!DOCTYPE html><html></html>'), 'ep');
    }

    public function testObjectRejectsTruncatedJson(): void
    {
        $this->expectException(SerializationException::class);

        Json::object(new Response(200, '{"a":'), 'ep');
    }

    public function testListDecodesAList(): void
    {
        self::assertSame([1, 'x'], Json::list(new Response(200, '[1,"x"]'), 'ep'));
    }

    public function testListRejectsObject(): void
    {
        $this->expectException(SerializationException::class);

        Json::list(new Response(200, '{"a":1}'), 'ep');
    }

    public function testListRejectsTruncatedJson(): void
    {
        $this->expectException(SerializationException::class);

        Json::list(new Response(200, '[1,'), 'ep');
    }

    public function testMessageNamesEndpointNotBody(): void
    {
        try {
            Json::object(new Response(200, '<!DOCTYPE html>secret-body'), 'plugins/info');
            self::fail('Expected a SerializationException.');
        } catch (SerializationException $exception) {
            self::assertStringStartsWith('plugins/info: ', $exception->getMessage());
            self::assertStringContainsString('(HTTP 200)', $exception->getMessage());
            self::assertStringNotContainsString('secret-body', $exception->getMessage());
        }
    }
}
