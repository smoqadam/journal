<?php

namespace test\eLife\Journal\Controller;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class EventControllerTest extends PageTestCase
{
    /**
     * @test
     */
    public function it_displays_an_event_page()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->getUrl());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('Event title', $crawler->filter('.content-header__title')->text());
    }

    /**
     * @test
     */
    public function it_displays_a_message_if_the_event_has_finished()
    {
        $client = static::createClient();

        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/events/1',
                ['Accept' => 'application/vnd.elife.event+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.event+json; version=1'],
                json_encode([
                    'id' => '1',
                    'title' => 'Event title',
                    'starts' => (new DateTimeImmutable('-2 days'))->format(DATE_RFC3339),
                    'ends' => (new DateTimeImmutable('-1 day'))->format(DATE_RFC3339),
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'text' => 'Event text.',
                        ],
                    ],
                ])
            )
        );

        $crawler = $client->request('GET', '/events/1');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('This event has finished.', trim($crawler->filter('.info-bar--attention')->text()));
        $this->assertSame('noindex', $crawler->filter('head > meta[name="robots"]')->attr('content'));
    }

    /**
     * @test
     */
    public function it_displays_a_404_if_the_event_is_not_found()
    {
        $client = static::createClient();

        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/events/1',
                [
                    'Accept' => 'application/vnd.elife.event+json; version=1',
                ]
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

        $client->request('GET', '/events/1');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    protected function getUrl() : string
    {
        $this->mockApiResponse(
            new Request(
                'GET',
                'http://api.elifesciences.org/events/1',
                ['Accept' => 'application/vnd.elife.event+json; version=1']
            ),
            new Response(
                200,
                ['Content-Type' => 'application/vnd.elife.event+json; version=1'],
                json_encode([
                    'id' => '1',
                    'title' => 'Event title',
                    'starts' => (new DateTimeImmutable('+1 day'))->format(DATE_RFC3339),
                    'ends' => (new DateTimeImmutable('+2 days'))->format(DATE_RFC3339),
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'text' => 'Event text.',
                        ],
                    ],
                ])
            )
        );

        return '/events/1';
    }
}