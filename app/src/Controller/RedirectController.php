<?php

namespace App\Controller;

use App\Service\LinkStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class RedirectController extends AbstractController
{

    /**
     * The length is spelled out here instead of reusing the generator constant:
     * old codes must keep resolving even if new ones grow longer one day.
     */
    #[Route(
        '/{code}',
        name: 'app_redirect',
        requirements: ['code' => '[0-9A-Za-z]{8}'],
        methods: ['GET'],
    )]
    public function __invoke(string $code, LinkStorageInterface $linkStorage): RedirectResponse
    {
        $url = $linkStorage->findUrlByCode($code);

        if (null === $url) {
            throw $this->createNotFoundException(sprintf('No link is registered for code "%s"', $code));
        }

        // 302, not 301: browsers cache a permanent redirect for good, which would
        // freeze the target forever and hide every later hit from our stats.
        return $this->redirect($url);
    }

}
