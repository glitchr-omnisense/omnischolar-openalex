<?php

namespace Omnischolar\OpenAlex\Tests;

use Omnischolar\Exception\NotSupportedException;
use Omnischolar\Exception\RateLimitedException;
use Omnischolar\Exception\UnavailableException;
use Omnischolar\Model\Affiliation;
use Omnischolar\Model\Identifier;
use Omnischolar\Model\Scheme;
use Omnischolar\Model\Work;
use Omnischolar\Model\WorkType;
use Omnischolar\OpenAlex\OpenAlexSource;
use Omnischolar\OpenAlex\OpenAlexSourceFactory;
use Omnischolar\Source\Capability;
use Omnischolar\Source\Query;
use Omnischolar\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from api.openalex.org on 2026-11-13, without a key (Alain
 * Aspect, A5046427077): author.json (authors/A5046427077), works-2022.json
 * (works?filter=author.id:A5046427077,publication_year:2022-2022,is_paratext:false),
 * works-page1.json and works-page2.json (the first two pages of 25, newest
 * first: the first by cursor, the second by page number), work-doi.json
 * (works/doi:10.1140/epjd/s10053-022-00549-6).
 */
final class OpenAlexSourceTest extends TestCase
{
    private const NEXT = 'IlsxNTczMDg0ODAwMDAwLCA5OC4wLCAzMywgJ2h0dHBzOi8vb3BlbmFsZXgub3JnL1cyOTQwNzE0ODE3J10i';

    /** @var list<array{string, array<string, string>}> */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = ['mailto' => 'support@glitchr.io']): SourceInterface
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$path, $query];
            if ($response) {
                return $response;
            }
            $filter = \is_string($query['filter'] ?? null) ? $query['filter'] : '';
            $fixture = match (true) {
                '/authors/A5046427077' === $path, '/authors/orcid:0000-0003-0094-5584' === $path => 'author.json',
                '/works/doi:10.1140/epjd/s10053-022-00549-6' === $path => 'work-doi.json',
                '/works' === $path && str_contains($filter, 'publication_year:2022-2022') => 'works-2022.json',
                '/works' === $path && str_contains($filter, 'author.id:A5046427077') && '*' === ($query['cursor'] ?? null) => 'works-page1.json',
                '/works' === $path && self::NEXT === ($query['cursor'] ?? null) => 'works-page2.json',
                '/works' === $path && str_contains($filter, 'author.id:A5046427077') && '1' === ($query['page'] ?? null) => 'works-page1.json',
                '/works' === $path && str_contains($filter, 'author.id:A5046427077') && \in_array($query['page'] ?? null, ['2', '26'], true) => 'works-page2.json',
                default => null,
            };

            return $fixture ? new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture)) : new MockResponse('{"error":"Not found"}', ['http_code' => 404]);
        });

        return (new OpenAlexSourceFactory($http))->create($options);
    }

    public function testTheProfileWithItsCountsAndInstitutions(): void
    {
        $author = $this->source()->author('A5046427077');

        self::assertSame('Alain Aspect', $author->name);
        self::assertSame('0000-0003-0094-5584', $author->orcid());
        self::assertSame('A5046427077', $author->identifiers->value(Scheme::OPENALEX));
        self::assertSame(686, $author->metrics->works);
        self::assertSame(64, $author->metrics->hIndex);
        self::assertSame(173, $author->metrics->i10Index);
        self::assertSame(28400, $author->metrics->citations);
        self::assertSame(['works' => 25, 'citations' => 1185], $author->metrics->byYear[2016]);
        self::assertContains('Aspect, A.', $author->alternativeNames);
        self::assertSame('Cold Atom Physics and Bose-Einstein Condensates', $author->keywords[0]);

        $iogs = array_values(array_filter($author->affiliations, static fn (Affiliation $a) => str_contains($a->organization, 'Optique Graduate School')))[0];
        self::assertSame(Affiliation::OBSERVED, $iogs->kind);
        self::assertTrue($iogs->isCurrent(), 'a last known institution');
        self::assertSame('1970', $iogs->start);
        self::assertSame('https://ror.org/00d0rke27', $iogs->ror);
        self::assertSame('FR', $author->country);

        self::assertSame('support@glitchr.io', $this->calls[0][1]['mailto'], 'the polite pool');
        self::assertArrayNotHasKey('api_key', $this->calls[0][1], 'no key, none sent');
    }

    public function testAnOrcidNamesTheAuthorToo(): void
    {
        self::assertSame('Alain Aspect', $this->source()->author(Identifier::orcid('0000-0003-0094-5584'))->name);
        self::assertSame('/authors/orcid:0000-0003-0094-5584', $this->calls[0][0]);
    }

    public function testThe2022ArticlesAreThere(): void
    {
        $page = $this->source()->works('A5046427077', new Query(from: 2022, to: 2022));

        self::assertSame(6, $page->total);
        self::assertTrue($page->isLast());
        $venues = array_map(static fn (Work $w) => $w->venue?->name, $page->works);
        self::assertContains('The European Physical Journal D', $venues);
        self::assertContains('Physical Review A', $venues);
        self::assertSame('author.id:A5046427077,publication_year:2022-2022,is_paratext:false', $this->calls[0][1]['filter']);
        self::assertSame('publication_date:desc', $this->calls[0][1]['sort']);

        $speckle = array_values(array_filter($page->works, static fn (Work $w) => '10.1140/epjd/s10053-022-00549-6' === $w->doi()))[0];
        self::assertSame('Bichromatic state-dependent disordered potential for Anderson localization of ultracold atoms', $speckle->title);
        self::assertSame(WorkType::ARTICLE, $speckle->type);
        self::assertSame(2022, $speckle->year);
        self::assertSame(['1434-6060', '1434-6079'], $speckle->venue->issn);
        self::assertSame('76', $speckle->volume);
        self::assertSame('11', $speckle->issue);
        self::assertSame('218', $speckle->pages);
        self::assertTrue($speckle->openAccess);
        self::assertNotNull($speckle->identifiers->value(Scheme::OPENALEX));
        self::assertSame('openalex', $speckle->source);
        $aspect = array_values(array_filter($speckle->authors, static fn ($c) => 'Aspect' === $c->familyName()))[0];
        self::assertSame('A5046427077', $aspect->identifiers->value(Scheme::OPENALEX));
    }

    /**
     * paratext as a site's YAML or environment gives it, a string: "false" (or "0", "no", "off")
     * keeps the filter, "true" drops it; a value that is no boolean is a configuration error.
     */
    public function testParatextReadAsABooleanWhateverItsSpelling(): void
    {
        foreach (['false' => true, '0' => true, 'no' => true, 'off' => true, 'true' => false, '1' => false, 'yes' => false] as $value => $filtered) {
            $this->calls = [];
            $this->source(null, ['mailto' => 'support@glitchr.io', 'paratext' => (string) $value])->works('A5046427077', new Query(from: 2022, to: 2022));
            self::assertSame($filtered, str_contains($this->calls[0][1]['filter'], 'is_paratext:false'), "paratext: '$value'");
        }

        $this->expectException(\Omnischolar\Exception\InvalidConfigException::class);
        $this->expectExceptionMessage('paratext');
        $this->source(null, ['mailto' => 'support@glitchr.io', 'paratext' => 'maybe']);
    }

    public function testWithAKeyThePagesFollowTheCursor(): void
    {
        $source = $this->source(null, ['mailto' => 'support@glitchr.io', 'api_key' => 'not-a-real-key']);
        $first = $source->works('A5046427077', new Query(limit: 25));
        self::assertCount(25, $first);
        self::assertSame(self::NEXT, $first->next);
        self::assertSame('*', $this->calls[0][1]['cursor']);
        self::assertSame('25', $this->calls[0][1]['per-page']);

        $second = $source->works('A5046427077', new Query(limit: 25, cursor: $first->next));
        self::assertCount(25, $second);
        self::assertSame(self::NEXT, $this->calls[1][1]['cursor']);
        self::assertNotSame($first->works[0]->title, $second->works[0]->title);

        $all = [...$first->works, ...$second->works];
        self::assertNotEmpty(array_filter($all, static fn (Work $w) => WorkType::CHAPTER === $w->type), 'book chapters');
        self::assertNotEmpty(array_filter($all, static fn (Work $w) => null !== $w->abstract), 'abstracts rebuilt from the inverted index');
    }

    /** Without a key OpenAlex refuses a cursor past the first page (401, "API key required for cursor paging"). */
    public function testWithoutAKeyThePagesAreNumbered(): void
    {
        $source = $this->source();
        $first = $source->works('A5046427077', new Query(limit: 25));
        self::assertCount(25, $first);
        self::assertSame('page:2', $first->next);
        self::assertSame(641, $first->total);
        self::assertSame('1', $this->calls[0][1]['page']);
        self::assertArrayNotHasKey('cursor', $this->calls[0][1]);
        self::assertSame('25', $this->calls[0][1]['per-page']);

        $second = $source->works('A5046427077', new Query(limit: 25, cursor: $first->next));
        self::assertCount(25, $second);
        self::assertSame('2', $this->calls[1][1]['page']);
        self::assertArrayNotHasKey('cursor', $this->calls[1][1]);
        self::assertSame('page:3', $second->next);
        self::assertNotSame($first->works[0]->title, $second->works[0]->title);

        $last = $source->works('A5046427077', new Query(limit: 25, cursor: 'page:26'));
        self::assertCount(25, $last);
        self::assertNull($last->next, 'the 641 works end on the twenty-sixth page of 25');
    }

    public function testAWorkByItsDoi(): void
    {
        $work = $this->source()->work('https://doi.org/10.1140/EPJD/S10053-022-00549-6');

        self::assertSame('10.1140/epjd/s10053-022-00549-6', $work->doi());
        self::assertSame('The European Physical Journal D', $work->venue->name);
        self::assertSame('/works/doi:10.1140/epjd/s10053-022-00549-6', $this->calls[0][0]);
        self::assertNull($this->source()->work('10.1140/unknown'), 'a 404 is unknown');
    }

    public function testAbstractsAreRebuilt(): void
    {
        self::assertSame('Light switches it on', OpenAlexSource::abstract(['Light' => [0], 'switches' => [1], 'it' => [2], 'on' => [3]]));
        self::assertNull(OpenAlexSource::abstract(null));
    }

    public function testWhatItCannotDo(): void
    {
        self::assertContains(Capability::METRICS, $this->source()->capabilities());
        $this->expectException(NotSupportedException::class);
        $this->source()->works('Alain Aspect');
    }

    public function testDomainsAreRefused(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->source()->search(new Query(text: 'Anderson localization', domains: ['shs.droit']));
    }

    public function testTheKeyAndTheSearch(): void
    {
        $this->source(new MockResponse('{"meta":{"count":0},"results":[]}'), ['api_key' => 'k3y'])->search(new Query(text: 'ultracold atoms', author: 'Aspect', types: [WorkType::ARTICLE], sort: Query::RELEVANCE));
        [, $query] = $this->calls[0];
        self::assertSame('k3y', $query['api_key']);
        self::assertSame('ultracold atoms', $query['search']);
        self::assertSame('relevance_score:desc', $query['sort']);
        self::assertSame('raw_author_name.search:Aspect,type:article|review|letter,is_paratext:false', $query['filter']);
    }

    public function testErrors(): void
    {
        try {
            $this->source(new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['retry-after' => '30']]))->author('A5046427077');
            self::fail();
        } catch (RateLimitedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        $this->expectException(UnavailableException::class);
        $this->source(new MockResponse('', ['http_code' => 503]))->author('A5046427077');
    }

    /** The throttle every factory takes (SourceFactory): seconds left between two calls. */
    public function testTheThrottleLeavesItsSeconds(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/work-doi.json')));
        $source = (new OpenAlexSourceFactory($http))->create(['throttle' => 0.3]);
        $start = microtime(true);
        $source->work('doi:10.1140/EPJD/S10053-022-00549-6');
        $source->work('doi:10.1140/EPJD/S10053-022-00549-6');
        self::assertGreaterThanOrEqual(0.29, microtime(true) - $start);
    }
}
