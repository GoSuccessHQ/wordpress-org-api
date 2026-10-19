<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Tests\Unit\Translations;

use GoSuccess\WordpressOrg\ClientOptions;
use GoSuccess\WordpressOrg\Http\Response;
use GoSuccess\WordpressOrg\Tests\Support\MockHttpClient;
use GoSuccess\WordpressOrg\Translations\Translation;
use GoSuccess\WordpressOrg\Translations\Translations;
use GoSuccess\WordpressOrg\WordpressOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Translations::class)]
#[CoversClass(Translation::class)]
final class TranslationsTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../Fixtures/translations/' . $name . '.json');
    }

    private static function translations(MockHttpClient $http): Translations
    {
        return new WordpressOrg(new ClientOptions(), $http)->translations;
    }

    public function testCoreTranslations(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('core')));
        $list = self::translations($http)->core('6.8');

        self::assertSame('https://api.wordpress.org/translations/core/1.0/?version=6.8', $http->requests[0]->uri);
        self::assertCount(2, $list);
        self::assertSame('af', $list[0]->language);
        self::assertSame('Afrikaans', $list[0]->englishName);
        self::assertSame('Afrikaans', $list[0]->nativeName);
        self::assertSame('5.8-beta', $list[0]->version);
        self::assertSame('https://downloads.wordpress.org/translation/core/5.8-beta/af.zip', $list[0]->package);
        self::assertSame('2021-05-13 15:59:22', $list[0]->updated?->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $list[0]->updated->getTimezone()->getName());
        self::assertSame('af', $list[0]->iso[1]);
        self::assertSame('afr', $list[0]->iso[2]);
        self::assertSame('Gaan voort', $list[0]->strings['continue']);
    }

    public function testPluginTranslationsHaveNoStrings(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('plugin')));
        $list = self::translations($http)->plugin('Akismet', '5.3');

        self::assertSame(
            'https://api.wordpress.org/translations/plugins/1.0/?slug=akismet&version=5.3',
            $http->requests[0]->uri,
        );
        self::assertCount(2, $list);
        self::assertSame('am', $list[0]->language);
        self::assertSame([], $list[0]->strings);
        self::assertSame(['1' => 'am', '2' => 'amh'], $list[0]->iso);
    }

    public function testThemeTranslations(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('plugin')));
        $list = self::translations($http)->theme('twentytwentyfive', '1.0');

        self::assertSame(
            'https://api.wordpress.org/translations/themes/1.0/?slug=twentytwentyfive&version=1.0',
            $http->requests[0]->uri,
        );
        self::assertCount(2, $list);
    }

    public function testUnknownSlugIsEmpty(): void
    {
        // Measured on 2026-10-19: HTTP 200 with an empty list.
        $http = new MockHttpClient(new Response(200, '{"translations":[]}'), new Response(200, '[]'));
        $translations = self::translations($http);

        self::assertSame([], $translations->plugin('no-such-plugin-zzzq', '1.0'));
        self::assertSame([], $translations->theme('no-such-theme-zzzq', '1.0'));
    }

    public function testUnsendableSlugSendsNothing(): void
    {
        $http = new MockHttpClient();
        $translations = self::translations($http);

        self::assertSame([], $translations->plugin('a/b', '1.0'));
        self::assertSame([], $translations->theme('..', '1.0'));
        self::assertSame([], $translations->plugin('', '1.0'));
        self::assertSame([], $http->requests);
    }

    public function testToleratesBadItems(): void
    {
        $body = '{"translations":["x",{"language":5,"updated":"nope","iso":"x","strings":{"a":"b","c":1}},{}]}';
        $list = self::translations(new MockHttpClient(new Response(200, $body)))->core('6.8');

        self::assertCount(2, $list);
        self::assertSame('5', $list[0]->language);
        self::assertNull($list[0]->updated);
        self::assertSame([], $list[0]->iso);
        self::assertSame(['a' => 'b'], $list[0]->strings);
        self::assertNull($list[1]->language);
    }

    public function testMissingTranslationsIsEmpty(): void
    {
        $http = new MockHttpClient(new Response(200, '{"translations":"x"}'), new Response(200, '{}'));
        $translations = self::translations($http);

        self::assertCount(0, $translations->core('6.8'));
        self::assertCount(0, $translations->core('6.8'));
    }
}
