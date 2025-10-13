<?php

namespace WEM\AudioTracksBundle\EventListener;

use Contao\Environment;
use Contao\Input;
use Contao\Module;
use WEM\AudioTracksBundle\Model\AudioTrack;

class GenerateBreadcrumbListener
{
    public function __invoke(array $items, Module $module): array
    {
        // Check if we have an auto_item and if it's a audio item
        if (Input::get('auto_item') && $objItem = AudioTrack::findByIdOrAlias(Input::get('auto_item'))) {
            array_pop($items);
            $items[] = [
                'isRoot' => false,
                'isActive' => true,
                'href' => Environment::get('request'),
                'title' => $objItem->title,
                'link' => $objItem->title,
                'class' => '',
            ];
        }

        return $items;
    }
}