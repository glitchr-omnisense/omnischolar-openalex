<?php

namespace Omnischolar\OpenAlex\Tests;

use Omnischolar\OpenAlex\OpenAlexSourceFactory;
use Omnischolar\Source\HttpSource;
use Omnischolar\Source\Query;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\Unit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** OpenAlex's budget as its policy: an entity free, a list 1 credit, a search 10; ten times more with a key. */
final class PolicyTest extends TestCase
{
    public function testTheDailyBudgetCountsEachRequestAtItsCost(): void
    {
        $limiter = new InMemoryRateLimiter();
        $source = (new OpenAlexSourceFactory(new MockHttpClient(static fn (): MockResponse => new MockResponse('{"results": [], "meta": {"count": 0}}'))))->create(['omnischolar.limiter' => $limiter]);
        \assert($source instanceof HttpSource);
        [$perSecond, $daily] = $source->guard()->policy()->rateLimits ?? [];
        self::assertSame(10, $perSecond->max);
        self::assertSame(Unit::Units, $daily->unit);
        self::assertSame(1000, $daily->max);

        $source->search(new Query('photochromic'));
        self::assertSame(990, $limiter->allowance('openalex/', $daily)->remaining, 'a search= query cost 10 credits');
        $source->search(new Query(title: 'photochromic'));
        self::assertSame(989, $limiter->allowance('openalex/', $daily)->remaining, 'a filtered list cost 1');
        self::assertSame(8, $limiter->allowance('openalex/', $perSecond)->remaining);
    }

    public function testAKeyRaisesTheFigures(): void
    {
        $source = (new OpenAlexSourceFactory(new MockHttpClient()))->create(['api_key' => 'test-key']);
        \assert($source instanceof HttpSource);
        [$perSecond, $daily] = $source->guard()->policy()->rateLimits ?? [];
        self::assertSame(30, $perSecond->max);
        self::assertSame(10000, $daily->max);
        self::assertSame(10, $daily->cost('search'));
    }
}
