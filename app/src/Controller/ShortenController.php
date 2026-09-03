<?php

namespace App\Controller;

use App\Service\Exception\InvalidUrlException;
use App\Service\Exception\LinkShortenerException;
use App\Service\LinkShortenerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShortenController extends AbstractController
{

    /**
     * Declared before the redirect route would never matter: "/" cannot match
     * "/{code}", which requires exactly eight characters.
     */
    #[Route('/', name: 'app_shorten_form', methods: ['GET'])]
    public function form(Request $request): Response
    {
        // The result of the previous submission, left here by the redirect below.
        // The template is unchanged: it still receives the same two variables,
        // only their source moved.
        $result = $this->takeResult($request);

        return $this->renderForm(
            shortUrl: $result['shortUrl'] ?? null,
            target: $result['target'] ?? null,
        );
    }

    #[Route('/', name: 'app_shorten_submit', methods: ['POST'])]
    public function submit(Request $request, LinkShortenerInterface $linkShortener): Response
    {
        // The string exactly as typed. Nothing is trusted and nothing is repaired
        // here: judging the input is the normalizer's single job, and a second
        // opinion in the controller is how the two of them start disagreeing.
        // InputBag::get() answers 400 by itself if "url" arrives as an array.
        $input = trim((string) $request->request->get('url', ''));

        // A token that does not match means the request cannot prove it came
        // from our own form. In practice that is a stale tab or an expired
        // session far more often than an attack, so the answer offers a retry
        // instead of accusing anyone — and the re-rendered form carries a fresh
        // token, which makes the second attempt work.
        if (!$this->isCsrfTokenValid('shorten', (string) $request->request->get('_csrf_token'))) {
            return $this->renderForm(
                url: $input,
                error: 'The form is out of date. Please submit again.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $link = $linkShortener->shorten($input);
        } catch (InvalidUrlException) {
            // 422, not 400: the request is well formed, its content is not.
            // The branch below picks wording, not a verdict — whether the input
            // was a URL has already been decided by the normalizer.
            return $this->renderForm(
                url: $input,
                error: '' === $input
                    ? 'Please enter a link.'
                    : 'That does not look like a link. Use an address like example.com or https://example.com/path.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (LinkShortenerException) {
            // Already logged with its cause inside the shortener; repeating it
            // here would only produce two records of one failure.
            return $this->renderForm(
                url: $input,
                error: 'Could not shorten the link. Please try again.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $this->addFlash('shortened', [
            // The host comes from the current request, so a proxy in front of the
            // app must be listed in trusted_proxies or the link points at nginx.
            'shortUrl' => $this->generateUrl(
                'app_redirect',
                ['code' => $link->code],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            // What was stored, which is rarely what was typed.
            'target' => $link->url->toString(),
        ]);

        // Post/Redirect/Get: answering the POST directly would leave the browser
        // holding a form submission, and F5 would offer to send it again — which
        // here means a second short code for the same address, silently.
        //
        // 303, not 302: only 303 actually means "fetch the result with a separate
        // GET". Browsers turn a 302 into a GET too, but that is history, not the
        // specification, which says a 302 should have kept the method.
        //
        // Errors above deliberately keep answering on the POST: their status codes
        // are the answer, and a redirect would flatten 422 and 403 into a plain 200.
        return $this->redirectToRoute('app_shorten_form', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * Reads the flash and clears it in one move, which is what makes the result
     * show exactly once: reload the page and the form is empty again, because
     * the message is already gone.
     *
     * @return array{shortUrl?: string, target?: string}
     */
    private function takeResult(Request $request): array
    {
        $session = $request->getSession();

        // Sessions are configured, but the interface Request::getSession() promises
        // knows nothing about flashes; only this one does.
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return [];
        }

        return $session->getFlashBag()->get('shortened')[0] ?? [];
    }

    /**
     * Every parameter the template needs, always passed: a template that reads
     * an undefined variable fails loudly in tests and silently in prod.
     */
    private function renderForm(
        string $url = '',
        ?string $error = null,
        ?string $shortUrl = null,
        ?string $target = null,
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->render(
            'shorten/form.html.twig',
            [
                'url' => $url,
                'error' => $error,
                'shortUrl' => $shortUrl,
                'target' => $target,
            ],
            new Response(status: $status),
        );
    }

}
