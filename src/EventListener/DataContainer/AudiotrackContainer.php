<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Database;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Message;
use Contao\System;
use Symfony\Component\Filesystem\Path;
use WEM\AudioTracksBundle\Classes\FeedWithoutTracksException;
use WEM\AudioTracksBundle\Classes\PublicationPeriod;
use WEM\AudioTracksBundle\Classes\TagSynchronizer;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Util\AudioDuration;
use WEM\UtilsBundle\Classes\StringUtil;

class AudiotrackContainer
{
    public function __construct(private readonly TagSynchronizer $tagSynchronizer)
    {
    }

    /**
     * Update palette for remote tracks.
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'config.onload')]
    public function updatePalettes(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = AudioTrack::findById($dc->id);
        $objCategory = $objItem->getRelated('pid');

        // Remote tracks have no local file, only the remote urls
        if ('remote' !== $objCategory?->type) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_wem_audiotrack']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['palettes']['remote'];
    }

    /**
     * Generate the RSS feed.
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'config.onsubmit')]
    public function generateRssFeed(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = AudioTrack::findById($dc->id);

        try {
            // Nothing is said if the feed is not enabled for the category
            if (System::getContainer()->get('wem.audiotracks.rss_feed')->generate((int) $objItem->pid)) {
                Message::addConfirmation($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssSaved']);
            }
        } catch (FeedWithoutTracksException) {
            Message::addError($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssNoTracks']);
        } catch (\Exception $exception) {
            Message::addError($exception->getMessage());
        }
    }

    /**
     * Format items list.
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'list.sorting.child_record')]
    public function listItems(array $r): string
    {
        return \sprintf(
            '%s',
            $r['title'],
        );
    }

    /**
     * Auto-generate an article alias if it has not been set yet.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.alias.save')]
    public function generateAlias($varValue, DataContainer $dc): string
    {
        $aliasExists = static fn (string $alias): bool => Database::getInstance()->prepare('SELECT id FROM tl_wem_audiotrack WHERE alias=? AND id!=?')->execute($alias, $dc->id)->numRows > 0;

        // Generate an alias if there is none
        if (!$varValue) {
            $varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->title, $dc->activeRecord->id, $aliasExists);
        } elseif ($aliasExists($varValue)) {
            throw new \Exception(\sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }

    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.duration.save')]
    public function retrieveAudioTrackDuration($varValue, $dc)
    {
        if (!$varValue && $objFile = FilesModel::findByUuid($dc->activeRecord->audio)) {
            // Read from the header of the file (mp3, wav, ogg)
            return AudioDuration::forFile(Path::join(System::getContainer()->getParameter('kernel.project_dir'), $objFile->path)) ?: $varValue;
        }

        return $varValue;
    }

    /**
     * Retrieve tags in the parent table.
     *
     * @return array ['tag1','tag2', ...]
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.tags.options')]
    public function getTags(DataContainer|null $dc, array|null $arrPids = null): array
    {
        if ($dc instanceof DataContainer) {
            $objItem = AudioTrack::findById($dc->id);
            $objCategory = $objItem->getRelated('pid');

            if (!$objCategory->tags) {
                return [];
            }

            return StringUtil::deserialize($objCategory->tags);
        }

        if (null !== $arrPids) {
            $arrTags = [];

            foreach ($arrPids as $id) {
                $objCategory = Category::findById($id);
                if (!$objCategory) {
                    continue;
                }

                if (!$objCategory->tags) {
                    continue;
                }

                $arrTags = array_merge($arrTags, StringUtil::deserialize($objCategory->tags));
            }

            return array_unique($arrTags);
        }

        return [];
    }

    /**
     * The track must stop after it starts: the start date is saved before the stop
     * date (see the palette), so the record already has the new one.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.stop.save')]
    public function validateStopDate($varValue, DataContainer $dc)
    {
        if (!PublicationPeriod::isValid((int) ($dc->activeRecord->start ?? 0), (int) $varValue)) {
            throw new \Exception($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['stopBeforeStart'] ?? 'The stop date must be after the start date.');
        }

        return $varValue;
    }

    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.tags.save')]
    public function syncAudioTrackTagsPivotTable($varValue, $dc)
    {
        $this->tagSynchronizer->sync((int) $dc->id, StringUtil::deserialize($varValue, true));

        return $varValue;
    }

    #[AsCallback(table: 'tl_wem_audiotrack', target: 'fields.authors.load')]
    public function getParentValue($varValue, DataContainer $dc)
    {
        if (!$varValue) {
            $objItem = AudioTrack::findById($dc->id);
            $varValue = $objItem->getRelated('pid')->authors;
        }

        return $varValue;
    }
}
