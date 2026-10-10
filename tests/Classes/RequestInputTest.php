<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use WEM\AudioTracksBundle\Classes\RequestInput;

class RequestInputTest extends TestCase
{
    public function testAMissingParameterIsNull(): void
    {
        $this->assertNull(RequestInput::get(self::request(['a' => '1']), 'b'));
    }

    public function testTextAndArraysAreReadFromTheGivenRequest(): void
    {
        $request = self::request(['search' => 'half life', 'tags' => ['rpg', 'indie'], 'empty' => '']);

        $this->assertSame('half life', RequestInput::get($request, 'search'));
        $this->assertSame(['rpg', 'indie'], RequestInput::get($request, 'tags'));
        $this->assertSame('', RequestInput::get($request, 'empty'));
    }

    public function testTheValueIsCleanedLikeInputGet(): void
    {
        $request = self::request(['search' => '{{file::secret}} <script>alert(1)</script>"x', 'a' => ['{{date::Y}}']]);

        $search = RequestInput::get($request, 'search');
        $this->assertStringNotContainsString('{{', $search);
        $this->assertStringNotContainsString('<script>', $search);
        $this->assertStringContainsString('&#123;&#123;file::secret&#125;&#125;', $search);
        $this->assertSame(['&#123;&#123;date::Y&#125;&#125;'], RequestInput::get($request, 'a'));
    }

    private static function request(array $query): Request
    {
        return Request::create('https://www.example.org/podcasts', 'GET', $query);
    }
}
