<?php

namespace Tests\Unit;

use App\Services\SiteTranslations;
use App\Services\WorkshopDivisions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SiteTranslationsTest extends TestCase
{
    private const JAPANESE = '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u';

    /**
     * @return list<string>
     */
    private function japaneseStrings(): array
    {
        $strings = [];
        $collect = function (mixed $value) use (&$collect, &$strings): void {
            if (is_array($value)) {
                array_walk($value, $collect);
            } elseif (is_string($value) && preg_match(self::JAPANESE, $value)) {
                $strings[$value] = true;
            }
        };

        $collect((new ReflectionClass(WorkshopDivisions::class))->getReflectionConstant('DIVISIONS')->getValue());
        $collect(WorkshopDivisions::company());

        foreach (glob(dirname(__DIR__, 2).'/resources/views/workshop/{,division/}*.blade.php', GLOB_BRACE) as $view) {
            preg_match_all('/\$t\(\s*(?:\'([^\']+)\'|"([^"]+)")\s*\)/u', file_get_contents($view), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $strings[($match[1] ?? '') !== '' ? $match[1] : str_replace('\n', "\n", $match[2])] = true;
            }
        }

        return array_map('strval', array_keys($strings));
    }

    public function test_every_japanese_string_on_the_site_has_an_english_and_an_indonesian_version(): void
    {
        $missing = [];

        foreach ($this->japaneseStrings() as $text) {
            foreach (['EN' => SiteTranslations::EN, 'ID' => SiteTranslations::ID] as $name => $table) {
                if (! array_key_exists($text, $table)) {
                    $missing[] = "{$name}: {$text}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_translated_text_contains_no_japanese(): void
    {
        foreach (['EN' => SiteTranslations::EN, 'ID' => SiteTranslations::ID] as $table) {
            foreach ($table as $japanese => $text) {
                $this->assertDoesNotMatchRegularExpression(self::JAPANESE, $text, "Still Japanese for: {$japanese}");
            }
        }
    }

    public function test_a_division_and_the_company_text_follow_the_page_language(): void
    {
        $this->assertSame('板金・塗装', WorkshopDivisions::find('body-paint')['title']);
        $this->assertSame('Body & Paint', WorkshopDivisions::find('body-paint', 'en')['title']);
        $this->assertSame('Bodi & Cat', WorkshopDivisions::find('body-paint', 'id')['title']);
        $this->assertSame('President & CEO', WorkshopDivisions::company('en')['role']);
        $this->assertSame('Presiden Direktur', WorkshopDivisions::company('id')['role']);
        $this->assertSame('未登録の文', WorkshopDivisions::translate('未登録の文', 'en'));
    }
}
