<?php

namespace test\eLife\Journal\Controller;

use DateTimeImmutable;
use eLife\ApiSdk\ApiSdk;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ML\JsonLD\JsonLD;
use ML\JsonLD\RdfConstants;
use ML\JsonLD\TypedValue;
use test\eLife\Journal\Providers;

final class JobAdvertControllerTest extends PageTestCase
{
    use Providers;

    /**
     * @test
     */
    public function it_displays_the_job_advert_page()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('Job advert title', $crawler->filter('.content-header__title')->text());
        $this->assertContains('Closing date for applications is '.date('F j, Y', strtotime('+1 day')).'.', $crawler->filter('main')->text());
        $this->assertContains('Job advert text.', $crawler->filter('main')->text());
    }

    /**
     * @test
     */
    public function it_has_metadata()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl().'?foo');

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $this->assertSame('Job advert title | Jobs | eLife', $crawler->filter('title')->text());
        $this->assertSame('/jobs/1/job-advert-title', $crawler->filter('link[rel="canonical"]')->attr('href'));
        $this->assertSame('http://localhost/jobs/1/job-advert-title', $crawler->filter('meta[property="og:url"]')->attr('content'));
        $this->assertSame('Job advert title', $crawler->filter('meta[property="og:title"]')->attr('content'));
        $this->assertSame('article', $crawler->filter('meta[property="og:type"]')->attr('content'));
        $this->assertSame('summary', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        $this->assertSame('http://localhost/'.ltrim(self::$kernel->getContainer()->get('elife.assets.packages')->getUrl('assets/images/social/icon-600x600@1.png'), '/'), $crawler->filter('meta[name="twitter:image"]')->attr('content'));
        $this->assertSame('http://localhost/'.ltrim(self::$kernel->getContainer()->get('elife.assets.packages')->getUrl('assets/images/social/icon-600x600@1.png'), '/'), $crawler->filter('meta[property="og:image"]')->attr('content'));
        $this->assertSame('600', $crawler->filter('meta[property="og:image:width"]')->attr('content'));
        $this->assertSame('600', $crawler->filter('meta[property="og:image:height"]')->attr('content'));
        $this->assertSame('job-advert/1', $crawler->filter('meta[name="dc.identifier"]')->attr('content'));
        $this->assertSame('elifesciences.org', $crawler->filter('meta[name="dc.relation.ispartof"]')->attr('content'));
        $this->assertSame('Job advert title', $crawler->filter('meta[name="dc.title"]')->attr('content'));
        $this->assertEmpty($crawler->filter('meta[name="dc.description"]'));
        $this->assertSame('2010-01-01', $crawler->filter('meta[name="dc.date"]')->attr('content'));
        $this->assertSame('© 2010 eLife Sciences Publications Limited. This article is distributed under the terms of the Creative Commons Attribution License, which permits unrestricted use and redistribution provided that the original author and source are credited.', $crawler->filter('meta[name="dc.rights"]')->attr('content'));
    }

    /**
     * @test
     */
    public function it_displays_a_message_if_the_job_advert_has_finished()
    {
        $client = static::createClient();

        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/job-adverts/1',
                ['Accept' => 'application/vnd.elife.job-advert+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.job-advert+json; version=1'],
                json_encode([
                    'id' => '1',
                    'title' => 'Job advert title',
                    'published' => '2010-01-01T00:00:00Z',
                    'closingDate' => (new DateTimeImmutable('-1 day'))->format(ApiSdk::DATE_FORMAT),
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'text' => 'Job advert text.',
                        ],
                    ],
                ])
            )
        );

        $crawler = $client->request('GET', '/jobs/1/job-advert-title');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertContains('This position is now closed to applications.', trim($crawler->filter('main')->text()));
        $this->assertSame('noindex', $crawler->filter('head > meta[name="robots"]')->attr('content'));
    }

    /**
     * @test
     * @dataProvider incorrectSlugProvider
     */
    public function it_redirects_if_the_slug_is_not_correct(string $slug = null, string $queryString = null)
    {
        $client = static::createClient();

        $url = "/jobs/1{$slug}";

        $expectedUrl = $this->getUrl();
        if ($queryString) {
            $url .= "?{$queryString}";
            $expectedUrl .= "?{$queryString}";
        }

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isRedirect($expectedUrl));
    }

    /**
     * @test
     */
    public function it_has_schema_org_metadata()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl());

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $script = $crawler->filter('script[type="application/ld+json"]');
        $this->assertNotEmpty($script);

        $value = $script->text();
        $this->assertJson($value);

        $this->markTestIncomplete('This test fails if schema.org is broken!');

        $graph = JsonLD::getDocument($value)->getGraph();
        $node = $graph->getNodes()[0];

        $this->assertEquals('http://schema.org/JobPosting', $node->getType()->getId());
        $this->assertEquals(new TypedValue('Job advert title', RdfConstants::XSD_STRING), $node->getProperty('http://schema.org/name'));
    }

    /**
     * @test
     */
    public function it_displays_a_404_if_the_job_advert_is_not_found()
    {
        $client = static::createClient();

        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/job-adverts/1',
                ['Accept' => 'application/vnd.elife.job-advert+json; version=1']
            ),
            new Response(
                404,
                [
                    'Content-Type' => 'application/problem+json',
                ],
                json_encode([
                    'title' => 'Not found',
                ])
            )
        );

        $client->request('GET', '/jobs/1');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    protected function getUrl() : string
    {
        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/job-adverts/1',
                ['Accept' => 'application/vnd.elife.job-advert+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.job-advert+json; version=1'],
                json_encode([
                    'id' => '1',
                    'title' => 'Job advert title',
                    'published' => '2010-01-01T00:00:00Z',
                    'closingDate' => (new DateTimeImmutable('+1 day'))->format(ApiSdk::DATE_FORMAT),
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'text' => 'Job advert text.',
                        ],
                    ],
                ])
            )
        );

        return '/jobs/1/job-advert-title';
    }
}
