# omnischolar/openalex

OpenAlex for [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar): an
author's profile with their counts (works, citations, h-index, i10-index, by year) and the
institutions seen on their works; their works with abstracts, citations and open-access links;
any work by its OpenAlex id, DOI or PubMed id; a search. The source calls OpenAlex's API:

| Operation | Request |
|---|---|
| `author($id)` | `GET https://api.openalex.org/authors/<A...>` or `authors/orcid:<orcid>` |
| `works($author)` | `GET .../works?filter=author.id:<A...>` (or `author.orcid:`)`,...&sort=&per-page=&cursor=&select=` |
| `work($id)` | `GET .../works/<W...>`, `works/doi:<doi>`, `works/pmid:<id>`, `works/pmcid:<id>` |
| `search($query)` | `GET .../works?search=<text>&filter=title.search:...,raw_author_name.search:...` |

Every call carries `mailto` and `api_key` when they are set. The base address is
`OpenAlexSource::BASE_URI` (`https://api.openalex.org/`). There is no sandbox.

## Installation

```sh
composer require omnischolar/openalex
```

Requires PHP 8.2, `omnisense/omnischolar` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `OmnischolarBundle`, the factory is registered as soon as the package is
installed.

## Credentials

None needed: OpenAlex is free without a key within a daily budget. A free key
(openalex.org/settings/api) raises it tenfold (`api_key`). OpenAlex asks polite callers for a
contact address (`mailto`).

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `mailto` | string | `null` | no | sent with every call, and in the `User-Agent` |
| `api_key` | string | `null` | no | sent with every call |
| `paratext` | bool | `false` | no | `true` keeps covers, tables of contents and the like; else `is_paratext:false` filters them out |
| `user_agent` | string | `omnischolar/1.x (+https://github.com/glitchr-omnisense/omnischolar)` and the `mailto` | no | |
| `base_uri` | string | `https://api.openalex.org/` | no | |
| `throttle` | float | `0.0` | no | seconds left between two calls of the source |

`paratext` is read as a boolean whatever its spelling (`Config::bool()`): `'false'`, `'0'`, `'no'`
or `'off'` keeps the filter, a value that is no boolean is an `InvalidConfigException`.

## Capabilities

`capabilities()`: `author`, `works`, `work`, `search`, `metrics`, `citations`, `abstracts`,
`open_access`, `affiliations`.

| Operation | Supported | Reads |
|---|---|---|
| `author()` | yes | OpenAlex `A...`, ORCID |
| `works()` | yes | OpenAlex `A...`, ORCID; a name is refused (resolve the author, or `search()` with `author`) |
| `work()` | yes | OpenAlex `W...`, DOI, PMID, PMCID; not an arXiv id |
| `search()` | yes | `text` (full-text `search`), `title` (`title.search`), `author` (`raw_author_name.search`) |

## Behaviour

**Lists.** `from`/`to` → `publication_year:>N`, `<N` or `from-to`; `types` → `type:` with
OpenAlex's types for each kind (`article`, `review`, `letter`; `book`; `book-chapter`,
`reference-entry`; `conference-paper`; `preprint`; `dissertation`; `report`, `standard`;
`dataset`, `supplementary-materials`; `software`; `peer-review`; `editorial`, `erratum`,
`retraction`, `paratext`; `other`, `libguides`); `openAccess` → `open_access.is_oa`; `domains` is
refused. `sort`: `oldest` → `publication_date:asc`, `cited` → `cited_by_count:desc`, `relevance`
→ `relevance_score:desc` with a text (else newest), any other → `publication_date:desc`.
`per-page` = `limit` 1 to 100; the cursor is OpenAlex's (`*` first, `next_cursor` after);
`Page::$total` is `meta.count`. Only the fields the models read are asked (`select`).

**An author.** The display name and its alternatives, OpenAlex id and ORCID; `Metrics` (works,
citations, h-index, i10-index, two-year mean citedness, works and citations by year, oldest
first); the institutions seen on the works as `Affiliation::OBSERVED`, with their years, first and
last, country, ROR, OpenAlex address, the last known ones current; the OpenAlex page as `urls`;
the top ten topics as keywords; the country of the last known institution. No positions nor
degrees: the institutions are inferred from the works.

**A work.** Title; type (an `article` in a conference source is a `conference`); authors with
their OpenAlex id, ORCID and institutions (the family name from the raw name when it holds a
comma); year and date (`publication_date`: a work known by its year alone is dated January 1st -
the Merger prefers other sources' dates); venue (journal, conference, repository, book series,
ebook platform; ISSNs; its host organisation as publisher; its OpenAlex id); publisher for books
and chapters; volume, issue, pages; the abstract rebuilt from OpenAlex's inverted index
(`OpenAlexSource::abstract()`); language; open access; landing page; the best open-access PDF;
licence; citation count; keywords; identifiers: OpenAlex, DOI, PMID, PMCID, and the arXiv or HAL
id when a landing page is there (or arXiv's DataCite DOI, `10.48550/arxiv.*`).

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnischolar\OpenAlex\OpenAlexSourceFactory;
use Omnischolar\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$openalex = (new OpenAlexSourceFactory(HttpClient::create()))->create([
    'mailto' => getenv('OMNISCHOLAR_MAILTO') ?: null,
    'api_key' => getenv('OPENALEX_API_KEY') ?: null,
]);

$a = $openalex->author('A5108007452');
echo $a->name, ': ', $a->metrics->works, ' works, ', $a->metrics->citations, ' citations, h-index ', $a->metrics->hIndex, ', ORCID ', $a->orcid(), "\n";
foreach ($openalex->works('A5108007452', new Query(from: 2024, to: 2024)) as $work) {
    printf("  %s  %-10s %s - %s cited %d\n", $work->date, $work->type->value, mb_substr($work->fullTitle(), 0, 80), $work->venue?->name, $work->citations);
}
```

```
Keitaro Nakatani: 387 works, 9717 citations, h-index 53, ORCID 0009-0005-1387-7295
  2024-10-04  article    Complete kinetic and photochemical characterization of the multi-step photochrom - Physical Chemistry Chemical Physics cited 1
  2024-09-25  article    Cascade Fluorescence Modulation in Photochromic Microcapsules - ACS Applied Materials & Interfaces cited 5
  2024-09-03  article    Tuning the Thermal Stability of Tetra ‐ o– chloroazobenzene Derivatives by Trans - Chemistry - A European Journal cited 7
  2024-01-01  article    Acid-sensitive photoswitches: towards catalytic on-demand release of stored ligh - Chemical Science cited 8
```

(called live on 2026-10-26 at 14:18 UTC, from the core's harness image, without a `mailto` or a
key; the last work, known by its year, dated January 1st)

In Symfony:

```yaml
omnischolar:
    sources:
        openalex: { factory: openalex, options: { mailto: '%env(OMNISCHOLAR_MAILTO)%', api_key: '%env(default::OPENALEX_API_KEY)%' } }
```

## Errors

| Case | Result |
|---|---|
| an unknown author or work | `null` |
| a name given to `works()`, an arXiv id or another identifier it does not read; `domains` | `NotSupportedException` |
| the daily budget spent, a 429 | `RateLimitedException` |
| another 4xx (a filter OpenAlex refuses) | `ProviderException` |
| a 5xx, a timeout | `UnavailableException` |

## Verified, and not

Tested offline: `Tests/OpenAlexSourceTest.php` (10 tests), on `MockHttpClient` with answers
recorded on 2026-10-04 (Keitaro Nakatani, A5108007452): his profile, his 2024 works, the first two
pages of his works, a work by DOI. Called live on 2026-10-04, 2026-10-05 and 2026-10-26 (the
example above, and the core's [first script](https://github.com/glitchr-omnisense/omnischolar/blob/1.x/docs/installation.md)).
Not run with an `api_key`.

## Responsibility

An independent, unofficial client, not affiliated with OurResearch (OpenAlex). Whoever runs it
complies with OpenAlex's terms ([openalex.org/terms](https://openalex.org/terms)): see the
README's Responsibility section.
