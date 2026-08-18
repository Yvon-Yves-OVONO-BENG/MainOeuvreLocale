<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class BriefTemplateService
{
    public function __construct(
        private readonly BriefTemplateCatalog $catalog,
        private readonly RouterInterface $router
    ) {
    }

    public function getIndexData(): array
    {
        return [
            'templatesBriefs' => $this->catalog->all(),
        ];
    }

    public function getApiIndexPayload(): array
    {
        return [
            'ok' => true,
            'templatesBriefs' => $this->catalog->all(),
        ];
    }

    public function getTemplateOrFail(string $slug): array
    {
        $template = $this->catalog->findBySlug($slug);

        if (!$template) {
            throw new NotFoundHttpException('Modèle de brief introuvable.');
        }

        return $template;
    }

    public function getUseTemplateData(string $slug): array
    {
        $template = $this->getTemplateOrFail($slug);

        return [
            'template' => $template,
            'slug' => $slug,
            'routeName' => 'job_new',
            'routeParams' => [
                'brief' => $slug,
            ],
            'redirectUrl' => $this->router->generate('job_new', [
                'brief' => $slug,
            ]),
        ];
    }

    public function getApiUseTemplatePayload(string $slug): array
    {
        $data = $this->getUseTemplateData($slug);

        return [
            'ok' => true,
            'message' => 'Modèle de brief chargé avec succès.',
            'template' => $data['template'],
            'slug' => $data['slug'],
            'redirect' => [
                'route' => $data['routeName'],
                'params' => $data['routeParams'],
                'url' => $data['redirectUrl'],
            ],
        ];
    }
}