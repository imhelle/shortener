<?php

namespace App\Controller;

use App\Service\Exception\InvalidUrlException;
use App\Service\Exception\LinkShortenerException;
use App\Service\LinkShortenerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShortenController extends AbstractController
{

    /**
     * Declared before the redirect route would never matter: "/" cannot match
     * "/{code}", which requires exactly eight characters.
     */
    #[Route('/', name: 'app_shorten_form', methods: ['GET'])]
    public function form(): Response
    {
        return $this->renderForm();
    }

    #[Route('/', name: 'app_shorten_submit', methods: ['POST'])]
    public function submit(Request $request, LinkShortenerInterface $linkShortener): Response
    {
        // The string exactly as typed. Nothing is trusted and nothing is repaired
        // here: judging the input is the normalizer's single job, and a second
        // opinion in the controller is how the two of them start disagreeing.
        // InputBag::get() answers 400 by itself if "url" arrives as an array.
        $input = trim((string) $request->request->get('url', ''));

        try {
            $link = $linkShortener->shorten($input);
        } catch (InvalidUrlException) {
            // 422, not 400: the request is well formed, its content is not.
            // The branch below picks wording, not a verdict — whether the input
            // was a URL has already been decided by the normalizer.
            return $this->renderForm(
                url: $input,
                error: '' === $input
                    ? 'Введите ссылку.'
                    : 'Не похоже на ссылку. Нужен адрес вида example.com или https://example.com/path.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (LinkShortenerException) {
            // Already logged with its cause inside the shortener; repeating it
            // here would only produce two records of one failure.
            return $this->renderForm(
                url: $input,
                error: 'Не удалось сократить ссылку. Попробуйте ещё раз.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->renderForm(
            // The host comes from the current request, so a proxy in front of the
            // app must be listed in trusted_proxies or the link points at nginx.
            shortUrl: $this->generateUrl(
                'app_redirect',
                ['code' => $link->code],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            // What was stored, which is rarely what was typed.
            target: $link->url->toString(),
        );
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
