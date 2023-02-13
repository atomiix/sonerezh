<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Class SettingsController
 * Manage Sonerezh settings panel. Add the ability to clear the cache, or reset the entire song database.
 */
class SettingsController extends AppController
{
    /**
     * This function manages the Sonerezh settings panel.
     * It also calculates some statistics and checks if avconv command is available.
     */
    public function index(): void
    {
        $settings = $this->Settings->find()->contain(['Rootpaths'])->first();

        if ($this->request->is(['POST', 'PUT'])) {
            $this->Settings->patchEntity($settings, $this->request->getData());
            if ($this->Settings->save($settings, ['associated' => 'Rootpaths'])) {
                $this->Flash->success(__('Settings saved!'));
            } else {
                $this->Flash->error(__('Unable to save settings!'));
            }
        }

        $Songs = $this->getTableLocator()->get('Songs');

        $stats['artists'] = $Songs->find()->select(['artist'])->distinct()->count();

        $stats['albums'] = $Songs->find()->select(['album', 'band'])->distinct()->count();

        $stats['songs'] = $Songs->find()->count();

        // Thumbnails cache size
        $stats['thumbCache'] = 0;

        if (is_dir(IMAGES.RESIZED_DIR)) {
            $recursiveResizedDirectoryIterator = new RecursiveDirectoryIterator(IMAGES.RESIZED_DIR, FilesystemIterator::SKIP_DOTS);
            $recursiveResizedIteratorIterator = new RecursiveIteratorIterator($recursiveResizedDirectoryIterator);

            foreach ($recursiveResizedIteratorIterator as $file) {
                $stats['thumbCache'] += $file->getSize();
            }
        }

        // Audio cache size
        $stats['audioCache'] = 0;
        $recursiveTmpDirectoryIterator = new RecursiveDirectoryIterator(TMP);
        $recursiveTmpIteratorIterator = new RecursiveIteratorIterator($recursiveTmpDirectoryIterator);
        $regexTmpIterator = new RegexIterator($recursiveTmpIteratorIterator, '/^.+\.(mp3|ogg)$/i');

        foreach ($regexTmpIterator as $audio_file) {
            if (!$audio_file->isLink()) {
                $stats['audioCache'] += $audio_file->getSize();
            }
        }

        // Check if avconv / ffpmeg shell command is available
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $avconv = shell_exec('where avconv') || shell_exec('where ffmpeg');  //WIN
        } else {
            $avconv = shell_exec('which avconv') || shell_exec('which ffmpeg');  //NO WIN
        }

        $db_source = explode('\\', get_class(ConnectionManager::get('default')->getDriver()));
        $sonerezh_docker = ', ';
        if (DOCKER) {
            $sonerezh_docker = ' on Docker, ';
        }

        $stats['sonerezh_version'] = 'Sonerezh ' . SONEREZH_VERSION . $sonerezh_docker . end($db_source);

        $this->set(compact('settings', 'stats', 'avconv'));
    }

    /**
     * This function clears the Sonerezh caches.
     * It deletes all the .(mp3|ogg) files in tmp/ and the thumbnails cache.
     */
    public function clear()
    {
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP));
        $songs = new RegexIterator($dir, '/^.*\.(mp3|ogg)$/');
        foreach ($songs as $song) {
            unlink($song->getRealPath());
        }

        if (file_exists(IMAGES.RESIZED_DIR)) {
            $dir = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(IMAGES.RESIZED_DIR, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($dir as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
        }

        $this->getTableLocator()->get('Songs')->updateAll(['path' => null], []);

        Cache::delete('import');

        $this->Flash->success(__('Yeah! Cache cleared!'));
        return $this->redirect(['controller' => 'settings', 'action' => 'index']);
    }

    /**
     * This function truncate the songs table and clear the Sonerezh cache.
     * Users data and playlist are preserved, but playlists are emptied.
     *
     * @see SettingsController::clear()
     */
    public function truncate()
    {
        try {
            $this->getTableLocator()->get('Songs')->deleteAll([null]);
            $this->getTableLocator()->get('Playlists')->deleteAll([null]);

            $thumbnails_dir = new Folder(IMAGES . THUMBNAILS_DIR . DS);
            $resized_dir = new Folder(IMAGES . RESIZED_DIR);
            $tmp_dir = new Folder(TMP);
            $songs = $tmp_dir->findRecursive('^.*\.(mp3|ogg)$');

            $thumbnails_dir->delete();
            $resized_dir->delete();

            foreach ($songs as $song) {
                $file = new File($song);
                $file->delete();
            }

            $this->Flash->success(__('All entries have been deleted!'));
            return $this->redirect(['action' => 'index']);
        } catch (Exception $e) {
            $this->Flash->success(__('Unable to clean the database!'));
            return $this->redirect(['action' => 'index']);
        }
    }
}
