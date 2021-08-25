<?php

namespace eLife\Journal\Controller;

use DateTimeImmutable;
use eLife\ApiClient\Exception\BadResponse;
use eLife\ApiSdk\Model\Image;
use eLife\Journal\Exception\EarlyResponse;
use eLife\Journal\Form\Type\EmailCtaType;
use eLife\Journal\Helper\CanCheckAuthorization;
use eLife\Journal\Helper\CanConvertContent;
use eLife\Journal\Helper\Paginator;
use eLife\Journal\ViewModel\Converter\ViewModelConverter;
use eLife\Patterns\ViewModel;
use eLife\Patterns\ViewModel\ContentHeaderSimple;
use eLife\Patterns\ViewModel\InfoBar;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use UnexpectedValueException;
use function GuzzleHttp\Promise\all;
use function GuzzleHttp\Promise\exception_for;
use function GuzzleHttp\Promise\promise_for;
use function GuzzleHttp\Promise\rejection_for;
use function preg_match;

abstract class Controller implements ContainerAwareInterface
{
    use CanCheckAuthorization;
    use CanConvertContent;

    /**
     * @var ContainerInterface
     */
    private $container;

    final public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    final protected function get(string $id)
    {
        return $this->container->get($id);
    }

    final protected function getParameter(string $id)
    {
        return $this->container->getParameter($id);
    }

    final protected function has(string $id) : bool
    {
        return $this->container->has($id);
    }

    final protected function getViewModelConverter() : ViewModelConverter
    {
        return $this->get('elife.journal.view_model.converter');
    }

    final protected function getAuthorizationChecker() : AuthorizationCheckerInterface
    {
        return $this->get('security.authorization_checker');
    }

    final protected function render(ViewModel ...$viewModels) : string
    {
        return $this->get('elife.patterns.pattern_renderer')->render(...$viewModels);
    }

    final protected function willRender() : callable
    {
        return function (ViewModel $viewModel) {
            return $this->render($viewModel);
        };
    }

    final protected function checkSlug(Request $request, callable $toSlugify) : callable
    {
        return function ($object) use ($request, $toSlugify) {
            $slug = $request->attributes->get('_route_params')['slug'];
            $correctSlug = $this->get('elife.slugify')->slugify($toSlugify($object));

            if ($slug !== $correctSlug) {
                $route = $request->attributes->get('_route');
                $routeParams = $request->attributes->get('_route_params');
                $routeParams['slug'] = $correctSlug;
                $url = $this->get('router')->generate($route, $routeParams);
                if ($queryString = $request->server->get('QUERY_STRING')) {
                    $url .= "?{$queryString}";
                }

                throw new EarlyResponse(new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY));
            }

            return $object;
        };
    }

    final protected function mightNotExist() : callable
    {
        return function ($reason) {
            if ($reason instanceof BadResponse) {
                switch ($reason->getResponse()->getStatusCode()) {
                    case Response::HTTP_GONE:
                    case Response::HTTP_NOT_FOUND:
                        throw new HttpException($reason->getResponse()->getStatusCode(), $reason->getMessage(), $reason);
                }
            }

            return rejection_for($reason);
        };
    }

    final protected function softFailure(string $message = null, $default = null) : callable
    {
        return function ($reason) use ($message, $default) {
            //return new RejectedPromise($reason);
            $e = exception_for($reason);

            if (false === $e instanceof HttpException) {
                $this->get('elife.logger')->error($message ?? $e->getMessage(), ['exception' => $e]);
            }

            return $default;
        };
    }

    final protected function createSubsequentPage(Request $request, array $arguments) : Response
    {
        $arguments['listing'] = $arguments['listing']
            ->otherwise($this->mightNotExist());

        if ($request->isXmlHttpRequest()) {
            $response = new Response($this->render($arguments['listing']->wait()));
        } else {
            $arguments['contentHeader'] = $arguments['paginator']
                ->then(function (Paginator $paginator) {
                    return new ContentHeaderSimple(
                        $paginator->getTitle(),
                        sprintf('Page %s of %s', number_format($paginator->getCurrentPage()), number_format(count($paginator)))
                    );
                });

            $template = $arguments['listing']->wait() instanceof ViewModel\GridListing ? '::pagination-grid.html.twig' : '::pagination.html.twig';

            $response = new Response($this->get('templating')->render($template, $arguments));
        }

        $response->headers->set('Vary', 'X-Requested-With', false);

        return $response;
    }

    final protected function ifFormSubmitted(Request $request, FormInterface $form, callable $onValid)
    {
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $onValid();

                throw new EarlyResponse(new RedirectResponse($request->getUri()));
            }

            if (count($form->getErrors()) > 0) {
                foreach ($form->getErrors() as $error) {
                    if ($error->getMessage() === $form->getConfig()->getOption('honeypot_message')) {
                        $this->get('monolog.logger.honeypot')->info('Honeypot field filled in', ['extra' => ['request' => $request]]);
                    }

                    $this->get('session')
                        ->getFlashBag()
                        ->add(InfoBar::TYPE_ATTENTION, $error->getMessage());
                }
            } else {
                $this->get('session')
                    ->getFlashBag()
                    ->add(InfoBar::TYPE_ATTENTION, 'There were problems submitting the form.');
            }
        }
    }

    private function getCallsToAction(Request $request) : array
    {
        return array_map(
            function (array $callToAction) : ViewModel\CallToAction {
                return new ViewModel\CallToAction(
                    $callToAction['id'],
                    $this->convertTo(
                        $this->get('elife.api_sdk.serializer')->denormalize($callToAction['image'], Image::class),
                        Picture::class,
                        ['width' => 80, 'height' => 80]
                    ),
                    $callToAction['text'],
                    ViewModel\Button::link($callToAction['button']['text'], $callToAction['button']['path']),
                    $callToAction['needsJs'] ?? false,
                    !empty($callToAction['dismissible']['cookieExpires']) ? new DateTimeImmutable($callToAction['dismissible']['cookieExpires']) : null
                );
            },
            array_slice(array_filter(
                // Limit of one call to action until we resolve issues of multiple calls to action.
                $this->getParameter('calls_to_action'),
                function (array $callToAction) use ($request) : bool {
                    if (isset($callToAction['from']) && time() < $callToAction['from']) {
                        return false;
                    }

                    if (
                        isset($callToAction['path'])
                        &&
                        !preg_match("~{$callToAction['path']}~", $request->getPathInfo())
                    ) {
                        return false;
                    }

                    return true;
                }
            ), 0, 1)
        );
    }

    final protected function defaultPageArguments(Request $request, PromiseInterface $item = null) : array
    {
        /** @var FormInterface $form */
        $form = $this->get('form.factory')
            ->create(EmailCtaType::class, null, ['action' => $request->getUri()]);

        $this->ifFormSubmitted($request, $form, function () use ($form) {
            $goutte = $this->get('elife.journal.goutte');

            $crawler = $goutte->request('GET', 'https://crm.elifesciences.org/crm/content-alerts');
            $button = $crawler->selectButton('SUBSCRIBE');

            $crawler = $goutte->submit($button->form(), [
                'submitted[civicrm_1_contact_1_fieldset_fieldset][civicrm_1_contact_1_email_email]' => $form->get('email')->getData(),
                'submitted[civicrm_1_contact_1_fieldset_fieldset][civicrm_1_contact_1_other_group][53]' => true,
            ]);

            if ($crawler->filter('.webform-confirmation:contains("Thank you for subscribing!")')->count()) {
                $this->get('session')
                    ->getFlashBag()
                    ->add(ViewModel\InfoBar::TYPE_SUCCESS, 'Almost finished! Click the link in the email we just sent you to confirm your subscription.');
            } elseif ($crawler->filter('.msg-text:contains("Your information has been saved")')->count()) {
                $this->get('session')
                    ->getFlashBag()
                    ->add(ViewModel\InfoBar::TYPE_SUCCESS, 'You are already subscribed!');
            } else {
                dump($crawler->html());
                throw new UnexpectedValueException('Couldn\'t read CRM response');
            }
        });

        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $user = $this->get('security.token_storage')->getToken()->getUser();
            $profile = $this->get('elife.api_sdk.profiles')->get($user->getUsername())
                ->otherwise(function ($reason) use ($user) {
                    $e = exception_for($reason);
                    $this->get('elife.logger')->error("Logging user {$user->getUsername()} out due to {$e->getMessage()}", ['exception' => $e]);

                    throw new EarlyResponse(new RedirectResponse($this->get('router')->generate('log-out', [], UrlGeneratorInterface::ABSOLUTE_URL)));
                });
        }

        return [
            'header' => all(['item' => promise_for($item), 'profile' => promise_for($profile ?? null)])
                ->then(function (array $parts) {
                    return $this->get('elife.journal.view_model.factory.site_header')->createSiteHeader($parts['item'], $parts['profile']);
                }),
            'infoBars' => [],
            'callsToAction' => $this->getCallsToAction($request),
            'emailCta' => $this->get('elife.journal.view_model.converter')->convert($form->createView()),
            'footer' => $this->get('elife.journal.view_model.factory.footer')->createFooter(),
            'user' => $user ?? null,
            'item' => $item,
        ];
    }
}
