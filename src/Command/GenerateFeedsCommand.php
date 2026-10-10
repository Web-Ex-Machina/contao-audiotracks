<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WEM\AudioTracksBundle\Classes\FeedGeneration;
use WEM\AudioTracksBundle\Classes\FeedWithoutTracksException;
use WEM\AudioTracksBundle\Classes\RssFeed;

#[AsCommand(name: 'wem:audiotracks:generate-feeds', description: 'Generates the RSS feeds of all the local categories of audiotracks')]
class GenerateFeedsCommand extends Command
{
    public function __construct(private readonly RssFeed $rssFeed)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->rssFeed->generateAll();

        if ([] === $results) {
            $io->note('No category has an RSS feed.');

            return Command::SUCCESS;
        }

        $failed = false;
        $rows = [];

        foreach ($results as $id => $result) {
            if ($result instanceof FeedGeneration) {
                $rows[] = [$id, 'Written' === $result->name ? 'written' : 'up to date'];
            } elseif ($result instanceof FeedWithoutTracksException) {
                $rows[] = [$id, 'skipped: no published track'];
            } else {
                $failed = true;
                $rows[] = [$id, 'ERROR: '.$result->getMessage()];
            }
        }

        $io->table(['Category', 'Feed'], $rows);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
