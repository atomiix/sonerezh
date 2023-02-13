<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\SongManager;
use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Filesystem\Folder;

class ImportCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addArgument('path', [
                'help' => 'The absolute path to the file or the folder you want to import.',
                'required' => true,
            ])->addOption('recursive', [
                'short' => 'r',
                'help' => 'Import a folder, recursively.',
                'boolean' => true,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $path = $args->getArgument('path');
        $recursive = $args->getOption('recursive');

        if (!file_exists($path)) {
            $io->error('Invalid path');

            return static::CODE_ERROR;
        }

        if (Cache::read('import')) {
            $io->out("<warning>[WARN]</warning> The import process is already running via another client or the CLI. You can click on \"Clear cache\" on the settings page to remove the lock, if needed.");

            return self::CODE_ERROR;
        }

        $found = [];
        if (is_dir($path)) {
            $path = new Folder(rtrim($path, DS).DS);
            $io->out("<info>[INFO]</info> Scan $path->path...");

            if ($recursive) {
                $found = $path->findRecursive('^.*\.(mp3|ogg|flac|aac)$');
            } else {
                $found = $path->find('^.*\.(mp3|ogg|flac|aac)$');
                // The Folder::find() method does not return the absolute path of each file, we need to add it:
                $found = preg_filter('/^/', $path->path, $found);
            }
        } else {
            $found[] = $path;
        }

        $Songs = $this->getTableLocator()->get('Songs');
        $already_imported = $Songs->find()->all()->combine('id', 'source_path')->toArray();

        $to_import = array_merge(array_diff($found, $already_imported));
        $to_import_count = count($to_import);
        $found_count = count($found);

        if ($to_import_count === 1) {
            $selection = $io->askChoice("[INFO] You asked to import $to_import[0]. Continue?", [
                'yes',
                'no',
            ], 'yes');
        } elseif ($to_import_count > 1) {
            $diff = $found_count - $to_import_count;
            $selection = $io->askChoice("[INFO] Found $to_import_count audio files ($diff already in the database). Continue?", [
                'yes',
                'no',
            ], 'yes');
        } elseif ($found_count > 0 && $to_import_count == 0) {
            $io->out("<info>[INFO]</info> $found_count file(s) found, but already in the database.");

            return static::CODE_SUCCESS;
        } else {
            $io->out('<info>[INFO]</info> Nothing to do.');

            return static::CODE_SUCCESS;
        }

        if ($selection === 'no') {
            $io->out('<info>[INFO]</info> Ok, bye.');

            return static::CODE_SUCCESS;
        }

        $io->out('<info>[INFO]</info> Run import', 0);

        if (Cache::read('import')) {
            $io->out("<warning>[WARN]</warning> The import process is already running via another client or the CLI. You can click on \"Clear cache\" on the settings page to remove the lock, if needed.");

            return static::CODE_ERROR;
        }

        Cache::write('import', true);

        // Catch SIGINT
        pcntl_signal(SIGINT, function () use ($io): void {
            Cache::delete('import');
            $this->refreshSyncToken();
            $this->abort();
        });

        $i = 1;
        foreach ($to_import as $file) {
            pcntl_signal_dispatch();
            $song_manager = new SongManager($file);
            $parse_result = $song_manager->parseMetadata();

            if ($parse_result['status'] != 'OK') {
                if ($parse_result['status'] == 'WARN') {
                    $io->overwrite("<warning>[WARN]</warning>[$file] - " . $parse_result['message']);
                } elseif ($parse_result['status'] == 'ERR') {
                    $io->overwrite("<error>[ERR]</error>[$file] - " . $parse_result['message']);
                }
            }

            $status = false;
            $message = "<error>[ERR]</error>[$file] - Unable to save the song metadata to the database";
            try {
                $status = $Songs->save($Songs->newEntity($parse_result['data']));
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }
            if (!$status) {
                $io->overwrite($message);
            }

            // Progressbar
            $percent_done = 100 * $i / $to_import_count;
            $hashtags_quantity = (int) round(45 * $percent_done / 100);
            $remaining_spaces = 45 - $hashtags_quantity;

            if ($i < ($to_import_count)) {
                $io->overwrite('<info>[INFO]</info> Run import: [' . round($percent_done) . '%] [' . str_repeat('#', $hashtags_quantity) . str_repeat(' ', $remaining_spaces) . ']', 0);
            } else {
                $io->overwrite('<info>[INFO]</info> Run import: [' . round($percent_done) . '%] [' . str_repeat('#', $hashtags_quantity) . str_repeat(' ', $remaining_spaces) . ']');
            }

            $i++;
        }

        Cache::delete('import');
        $this->refreshSyncToken();

        return static::CODE_SUCCESS;
    }

    private function refreshSyncToken(): void
    {
        // Update the sync_token to refresh the IndexedDB on the browser side
        $Settings = $this->getTableLocator()->get('Settings');
        $settings = $Settings->find()->first();
        $settings->sync_token = time();
        $Settings->save($settings);
    }
}
