<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\DBAL\Filter;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\ORM\Query;
use Omnisearch\Typesense\Tests\Fixtures\Post;
use Omnisearch\Typesense\TypesenseDriver;
use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\Query\Query as TypesenseQuery;

/**
 * A Request as the bundle's Query: the very headers an application built
 * by hand with the bundle's setters.
 */
final class QueryTest extends TestCase
{
    public function testTheHeadersAreTheBundlesOwn(): void
    {
        $request = (new Query('fuji'))->in('title', 'excerpt', 'state')->perPage(10)->page(2)->sortBy('publishedAt', 'desc')
            ->facet('tags')->where('tags', 'en-musique')->where('state', 'publish')->request();

        $byHand = (new TypesenseQuery('title, excerpt, state', 'fuji'))->perPage(10)->page(2)->sortBy('publishedAt:desc')->facetBy('tags');
        $byHand->addFilterBy('tags:=`en-musique`');
        $byHand->addFilterBy('state:=`publish`');

        self::assertSame($byHand->getHeaders(), TypesenseDriver::query(AttributeLoader::load(Post::class), $request)->getHeaders());
    }

    public function testInfixAndTheEnginesOwnOptions(): void
    {
        $headers = TypesenseDriver::query(AttributeLoader::load(Post::class), (new Query('isa'))->in('title', 'tags')->option('infix', 'always')->option('num_typos', 0)->maxFacetValues(250)->request())->getHeaders();

        self::assertSame('always,always', $headers['infix'], 'one mode per query_by field');
        self::assertSame(0, $headers['num_typos']);
        self::assertSame(250, $headers['max_facet_values']);
    }

    public function testWithoutFieldsTheTextFieldsAreSearched(): void
    {
        self::assertSame('state, title, tags', TypesenseDriver::query(AttributeLoader::load(Post::class), (new Query())->request())->getHeader('query_by'));
        self::assertSame('', TypesenseDriver::query(AttributeLoader::load(Post::class), (new Query())->request())->getHeader('q'));
    }

    public function testFiltersInTypesensesSyntax(): void
    {
        self::assertSame('state:=`publish`', TypesenseDriver::filter(Filter::equals('state', 'publish')));
        self::assertSame('audio:=true', TypesenseDriver::filter(Filter::equals('audio', true)));
        self::assertSame('rank:>=3', TypesenseDriver::filter(new Filter('rank', Filter::GREATER_OR_EQUAL, 3)));
        self::assertSame('tags:=[`a`,`b`]', TypesenseDriver::filter(Filter::in('tags', ['a', 'b'])));
        self::assertSame('tags:!=[`a`]', TypesenseDriver::filter(Filter::notIn('tags', ['a'])));
        self::assertSame('state:!=`draft`', TypesenseDriver::filter(Filter::notEquals('state', 'draft')));
    }
}
