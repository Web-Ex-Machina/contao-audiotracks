<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use Symfony\Component\HttpFoundation\RequestStack;
use WEM\AudioTracksBundle\Classes\RequestInput;
use WEM\AudioTracksBundle\Model\AudioTrack;

#[AsHook('generateBreadcrumb', priority: 100)]
class GenerateBreadcrumbListener
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(array $items, Module $module): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $autoItem = null === $request ? null : RequestInput::get($request, 'auto_item');

        // Check if we have an auto_item and if it's a audio item
        if ($autoItem && \is_string($autoItem) && ($objItem = AudioTrack::findByIdOrAlias($autoItem)) && $objItem->isPublished()) {
            array_pop($items);
            $items[] = [
                'isRoot' => false,
                'isActive' => true,
                // The request, relative to the base of the website (as Contao's
                // Environment::get('request'))
                'href' => ltrim(substr($request->getRequestUri(), \strlen($request->getBasePath())), '/'),
                'title' => $objItem->title,
                'link' => $objItem->title,
                'class' => '',
            ];
        }

        return $items;
    }
}
