# omnischolar/openalex

OpenAlex for [omniscient/omnischolar](https://github.com/glitchr-studio/omnischolar): a researcher's
profile with their counts (works, citations, h-index, i10-index, by year) and the institutions
seen on their works; their works with abstracts, citation counts and open-access links; any work
by its OpenAlex id, DOI or PubMed id; a search. No key needed.

```php
$openalex = (new OpenAlexSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$openalex->author('A5108007452')->metrics->hIndex;                      // 51
$openalex->works('A5108007452', new Query(from: 2024, to: 2024));       // a Page of Work
$openalex->work('10.1039/d4sc04973j');
```

```yaml
omnischolar:
    sources:
        openalex:
            factory: openalex
            options:
                mailto: 'support@glitchr.io'      # sent with every call, as OpenAlex asks
                api_key: ~                        # free, from openalex.org/settings/api: ten times the keyless daily budget
                paratext: false                   # true keeps covers, tables of contents...
```

See [docs/](docs/index.md) for what is read, how it maps, and what OpenAlex does not give.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by OurResearch (OpenAlex). It reaches their service on behalf of whoever runs it, and it
does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with OpenAlex's terms
([openalex.org/terms](https://openalex.org/terms)), for obtaining whatever they require (an account,
a key, a licence or subscription, an attribution or a citation, a rate limit to respect), and for
any use made of what it returns. The authors publish it free of charge, make no commercial use of
it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
