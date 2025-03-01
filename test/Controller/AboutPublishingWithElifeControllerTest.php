<?php

namespace test\eLife\Journal\Controller;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class AboutPublishingWithElifeControllerTest extends PageTestCase
{
    /**
     * @test
     */
    public function it_displays_the_publishing_with_elife_page()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('Publishing with eLife', $crawler->filter('.content-header__title')->text());
    }

    /**
     * @test
     */
    public function it_shows_aims_and_scope_for_each_subject()
    {
        $client = static::createClient();

        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/subjects?page=1&per-page=100&order=asc',
                ['Accept' => 'application/vnd.elife.subject-list+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.subject-list+json; version=1'],
                json_encode([
                    'total' => 2,
                    'items' => [
                        [
                            'id' => 'subject1',
                            'name' => 'Subject 1',
                            'impactStatement' => 'Subject 1 impact statement.',
                            'aimsAndScope' => [
                                [
                                    'type' => 'paragraph',
                                    'text' => 'Paragraph 1.',
                                ],
                                [
                                    'type' => 'paragraph',
                                    'text' => 'Paragraph 2.',
                                ],
                            ],
                            'image' => [
                                'banner' => [
                                    'uri' => 'https://www.example.com/iiif/ban%2Fner',
                                    'alt' => '',
                                    'source' => [
                                        'mediaType' => 'image/jpeg',
                                        'uri' => 'https://www.example.com/banner.jpg',
                                        'filename' => 'image.jpg',
                                    ],
                                    'size' => [
                                        'width' => 1800,
                                        'height' => 1600,
                                    ],
                                ],
                                'thumbnail' => [
                                    'uri' => 'https://www.example.com/iiif/image',
                                    'alt' => '',
                                    'source' => [
                                        'mediaType' => 'image/jpeg',
                                        'uri' => 'https://www.example.com/image.jpg',
                                        'filename' => 'image.jpg',
                                    ],
                                    'size' => [
                                        'width' => 800,
                                        'height' => 600,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'subject2',
                            'name' => 'Subject 2',
                            'impactStatement' => 'Subject 2 impact statement.',
                            'image' => [
                                'banner' => [
                                    'uri' => 'https://www.example.com/iiif/ban%2Fner',
                                    'alt' => '',
                                    'source' => [
                                        'mediaType' => 'image/jpeg',
                                        'uri' => 'https://www.example.com/banner.jpg',
                                        'filename' => 'image.jpg',
                                    ],
                                    'size' => [
                                        'width' => 1800,
                                        'height' => 1600,
                                    ],
                                ],
                                'thumbnail' => [
                                    'uri' => 'https://www.example.com/iiif/image',
                                    'alt' => '',
                                    'source' => [
                                        'mediaType' => 'image/jpeg',
                                        'uri' => 'https://www.example.com/image.jpg',
                                        'filename' => 'image.jpg',
                                    ],
                                    'size' => [
                                        'width' => 800,
                                        'height' => 600,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
            )
        );

        $crawler = $client->request('GET', '/about/publishing-with-elife');

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $aimsAndScopeSection = $crawler->filter('.article-section');
        $this->assertSame('Aims and Scope', $aimsAndScopeSection->filter('h2')->text());

        $aimsAndScopes = $aimsAndScopeSection->filter('.article-section__body .article-section');

        $this->assertCount(2, $aimsAndScopes);
        $this->assertSame('Subject 1', $aimsAndScopes->eq(0)->filter('h3')->text());
        $this->assertSame("Paragraph 1.\nParagraph 2. See editors", trim($aimsAndScopes->eq(0)->filter('.article-section__body')->text()));
        $this->assertSame('Subject 2', $aimsAndScopes->eq(1)->filter('h3')->text());
        $this->assertSame("See editors", trim($aimsAndScopes->eq(1)->filter('.article-section__body')->text()));
    }

    /**
     * @test
     */
    public function it_has_metadata()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl().'?foo');

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $this->assertSame('Publishing with eLife | About | eLife', $crawler->filter('title')->text());
        $this->assertSame('/about/publishing-with-elife', $crawler->filter('link[rel="canonical"]')->attr('href'));
        $this->assertSame('http://localhost/about/publishing-with-elife', $crawler->filter('meta[property="og:url"]')->attr('content'));
        $this->assertSame('Publishing with eLife', $crawler->filter('meta[property="og:title"]')->attr('content'));
        $this->assertSame('eLife welcomes the submission of Research Articles, Short Reports, Tools and Resources articles, Research Advances, Scientific Correspondence and Review Articles in the subject areas below.', $crawler->filter('meta[property="og:description"]')->attr('content'));
        $this->assertSame('eLife welcomes the submission of Research Articles, Short Reports, Tools and Resources articles, Research Advances, Scientific Correspondence and Review Articles in the subject areas below.', $crawler->filter('meta[name="description"]')->attr('content'));
        $this->assertSame('summary', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        $this->assertSame('http://localhost/'.ltrim(self::$kernel->getContainer()->get('elife.assets.packages')->getUrl('assets/images/social/icon-600x600@1.png'), '/'), $crawler->filter('meta[name="twitter:image"]')->attr('content'));
        $this->assertSame('http://localhost/'.ltrim(self::$kernel->getContainer()->get('elife.assets.packages')->getUrl('assets/images/social/icon-600x600@1.png'), '/'), $crawler->filter('meta[property="og:image"]')->attr('content'));
        $this->assertSame('600', $crawler->filter('meta[property="og:image:width"]')->attr('content'));
        $this->assertSame('600', $crawler->filter('meta[property="og:image:height"]')->attr('content'));
        $this->assertEmpty($crawler->filter('meta[name="dc.identifier"]'));
        $this->assertEmpty($crawler->filter('meta[name="dc.relation.ispartof"]'));
        $this->assertEmpty($crawler->filter('meta[name="dc.title"]'));
        $this->assertEmpty($crawler->filter('meta[name="dc.description"]'));
        $this->assertEmpty($crawler->filter('meta[name="dc.date"]'));
        $this->assertEmpty($crawler->filter('meta[name="dc.rights"]'));
    }

    protected function getUrl() : string
    {
        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/subjects?page=1&per-page=100&order=asc',
                ['Accept' => 'application/vnd.elife.subject-list+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.subject-list+json; version=1'],
                json_encode([
                    'total' => 0,
                    'items' => [],
                ])
            )
        );

        return '/about/publishing-with-elife';
    }
}
