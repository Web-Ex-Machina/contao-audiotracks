<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use WEM\AudioTracksBundle\AudioTracksBundle;

/**
 * Plugin for the Contao Manager.
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(WEM\AudioTracksBundle\AudioTracksBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class
                ])
                ->setReplace(['wemaudiotracks']),
        ];
    }
}
