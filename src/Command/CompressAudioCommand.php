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

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WEM\AudioTracksBundle\Classes\AudioOptimizer;
use WEM\AudioTracksBundle\EventListener\DataContainer\AudiotrackContainer;
use WEM\AudioTracksBundle\Model\AudioTrack;

#[AsCommand(name: 'wem:audiotracks:compress', description: 'Compresses the MP3 files of the audiotracks to the configured bit rate (audio_tracks.compression)')]
class CompressAudioCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AudioOptimizer $audioOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only lists the files that would be compressed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $compressor = $this->audioOptimizer->getCompressor();
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$dryRun && !$compressor->isAvailable()) {
            $io->error('ffmpeg is not available, install it or set audio_tracks.compression.ffmpeg.');

            return Command::FAILURE;
        }

        $this->framework->initialize();
        $tracks = AudioTrack::findAll();
        $rows = [];
        $failed = false;
        $saved = 0;

        foreach ($tracks ?? [] as $track) {
            $path = $this->audioOptimizer->getPath($track);

            if (null === $path || !$compressor->needsCompression($path)) {
                continue;
            }

            $label = $track->id.' '.basename($path);

            if ($dryRun) {
                $rows[] = [$label, AudiotrackContainer::formatSize((int) filesize($path)), 'would be compressed'];

                continue;
            }

            try {
                $result = $this->audioOptimizer->optimize($track);
                $rows[] = null === $result ? [$label, '', 'unchanged'] : [$label, AudiotrackContainer::formatSize($result['before']).' → '.AudiotrackContainer::formatSize($result['after']), 'compressed'];
                $saved += null === $result ? 0 : $result['before'] - $result['after'];
            } catch (\RuntimeException $runtimeException) {
                $failed = true;
                $rows[] = [$label, '', 'ERROR: '.$runtimeException->getMessage()];
            }
        }

        if ([] === $rows) {
            $io->success('Nothing to compress.');

            return Command::SUCCESS;
        }

        $io->table(['Track', 'Size', 'Result'], $rows);

        if ($saved > 0) {
            $io->success(\sprintf('%s saved.', AudiotrackContainer::formatSize($saved)));
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
