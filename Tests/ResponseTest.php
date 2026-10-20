<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\ORM\Result;
use Omnisearch\Typesense\TypesenseDriver;
use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\Query\Response as TypesenseResponse;

/**
 * Typesense's answers, recorded from a Typesense 30 server
 * (Fixtures/responses: a search over a string[] field with a padded
 * facet_by, as the bundle sends it; a refused one), as omnisearch's
 * Response.
 */
final class ResponseTest extends TestCase
{
    private static function recorded(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/Fixtures/responses/'.$name.'.json'), true);
    }

    public function testASearch(): void
    {
        $response = TypesenseDriver::response(new TypesenseResponse(self::recorded('search')));

        self::assertTrue($response->ok());
        self::assertSame([2, 1], [$response->found, $response->page]);
        self::assertSame(['7', '12'], $response->ids(), 'the newest first');
        self::assertSame(['title' => [['Fuji'], ['Fuji']]], $response->hits[0]->highlights, 'per field, as Typesense gives them (a list per value of a string[] field)');
        self::assertSame(['Fuji', 'Fuji'], $response->hits[0]->matchedTokens());
        self::assertSame('Fujisan in music', $response->hits[0]->document['title'][0]);
        self::assertNotNull($response->hits[0]->score);

        self::assertSame(['tags'], array_keys($response->facets), 'the padded facet_by\'s repeats are one facet');
        self::assertSame(['count' => 2, 'highlighted' => 'japon', 'value' => 'japon'], $response->facets['tags']->values[0], 'no "checked": that is the Result\'s');
        self::assertSame(['field_name' => 'tags', 'sampled' => false, 'stats' => ['total_values' => 3]], $response->facets['tags']->extra);

        $facets = Result::facetsOf($response, ['tags' => ['voyages']], true);
        self::assertSame(['en-musique', 'japon', 'voyages'], array_keys($facets['tags']['counts']));
        self::assertTrue($facets['tags']['counts']['voyages']['checked']);
        self::assertFalse($facets['tags']['counts']['japon']['checked']);
    }

    public function testARefusal(): void
    {
        $error = self::recorded('error');
        $response = TypesenseDriver::response(new TypesenseResponse([], 404, [TypesenseResponse::MESSAGE => $error['message']]));

        self::assertFalse($response->ok());
        self::assertSame(404, $response->status);
        self::assertSame('Could not find a facet field named `always` in the schema.', $response->error);
    }
}
