<?php

declare(strict_types=1);

namespace App\Controller;

use App\SongManager;
use Cake\Cache\Cache;
use Cake\Event\EventInterface;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\NotFoundException;
use Cake\Routing\Asset;
use Cake\Routing\Router;
use Cake\View\JsonView;

class SongsController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        if ($this->request->getParam('action') === 'import') {
            $this->FormProtection->setConfig('validate', false);
        }

        parent::beforeFilter($event);
    }

    /**
     * The import view function.
     * The function does the following action:
     *      - Check the root path,
     *      - Search every media files (mp3, ogg, flac, aac) to load them in an array
     *      - Compare this array with the list of existing songs to keep only new tracks
     *      - Pass this array to the view.
     *
     * @see SongsController::_importSong
     */
    public function import()
    {
        $settings = $this->getTableLocator()->get('Settings')->find()->contain('Rootpaths')->first();

        if ($this->request->is('get')) {
            if ($settings) {
                $paths = $settings->rootpaths;
            } else {
                $this->Flash->error(__('Please define a root path.'));
                return $this->redirect(['controller' => 'settings', 'action' => 'index']);
            }

            // The files found via Folder->findRecursive()
            $found = [];

            foreach ($paths as $path) {
                $directory = new Folder($path['rootpath']);
                $tree = $directory->tree();

                foreach ($tree[0] as $subdirectory) {
                    $subdirectory = new Folder($subdirectory);

                    // Do not follow symlinks to avoid infinite loops
                    if (!is_link($subdirectory->path)) {
                        $found_in_this_directory = $subdirectory->find('^.*\.(mp3|ogg|flac|aac)$');

                        // The find method does not return absolute paths.
                        foreach ($found_in_this_directory as $file) {
                            // CakePHP adds a trailing slash when the path contains two dots in sequence
                            if (substr($subdirectory->path, -1) === '/') {
                                $found[$subdirectory->path . $file] = filemtime($subdirectory->path . $file);
                            } else {
                                $found[$subdirectory->path . '/' . $file] = filemtime($subdirectory->path . '/' . $file);
                            }
                        }
                    }
                }
            }

            // The files already imported
            $already_imported = $this->Songs->find()->all()->combine('source_path', 'modified')->toArray();

            // The difference between $found and $already_imported
            $to_import = array_keys(array_diff_key($found, $already_imported));
            $to_remove = array_keys(array_diff_key($already_imported, $found));

            // Find what already imported files still on the filesystem that have been modified since import
            $to_update = [];
            foreach (array_intersect_key($found, $already_imported) as $source_path => $value) {
                if ($value > $already_imported[$source_path]->getTimestamp()) {
                    $to_update[] = $source_path;
                }
            }

            $to_import_count = count($to_import);
            $to_update_count = count($to_update);
            $to_remove_count = count($to_remove);
            $already_imported_count = count($already_imported);

            $this->request->getSession()->write('to_import', $to_import);
            $this->request->getSession()->write('to_update', $to_update);
            $this->request->getSession()->write('to_remove', $to_remove);
            $this->set(compact('to_import_count', 'to_remove_count', 'to_update_count', 'already_imported_count'));
        } elseif ($this->request->is('post')) {
            $this->viewBuilder()
                ->setClassName(JsonView::class)
                ->setOption('serialize', true);
            $update_result = [];

            if (Cache::read('import')) { // Read lock to avoid multiple import processes in the same time
                $update_result[0]['status'] = 'ERR';
                $update_result[0]['message'] = __('The import process is already running via another client or the CLI.');
                $this->set(compact('update_result'));
                $this->set('_serialize', ['update_result']);
            } else {
                // Write lock
                Cache::write('import', true);

                $to_import = $this->request->getSession()->read('to_import');
                $to_update = $this->request->getSession()->read('to_update');
                $to_remove = $this->request->getSession()->read('to_remove');
                $imported = [];
                $updated = [];
                $removed = [];

                $i = 0;
                foreach ($to_import as $file) {
                    if ($i >= SYNC_BATCH_SIZE) {
                        break;
                    }

                    $song_manager = new SongManager($file);
                    $parse_result = $song_manager->parseMetadata();

                    $entity = $this->Songs->newEntity($parse_result['data']);
                    if (!$this->Songs->save($entity)) {
                        $update_result[$file]['status'] = 'ERR';
                        $update_result[$file]['message'] = __('Unable to save the song metadata to the database');
                    } else {
                        unset($parse_result['data']);
                        $update_result[$i]['file'] = $file;
                        $update_result[$i]['status'] = $parse_result['status'];
                        $update_result[$i]['message'] = $parse_result['message'];
                    }

                    $imported[] = $file;
                    $i++;
                }

                foreach ($to_update as $file) {
                    if ($i >= SYNC_BATCH_SIZE) {
                        break;
                    }

                    $song_manager = new SongManager($file);
                    $parse_result = $song_manager->parseMetadata();

                    // Get the song id and enrich the array
                    $result = $this->Songs->find()->select(['id'])->where(['source_path' => $file])->first();
                    $this->Songs->patchEntity($result, $parse_result['data']);

                    if (!$this->Songs->save($result)) {
                        $update_result[$file]['status'] = 'ERR';
                        $update_result[$file]['message'] = __('Unable to update the song metadata in the database');
                    } else {
                        unset($parse_result['data']);
                        $update_result[$i]['file'] = $file;
                        $update_result[$i]['status'] = $parse_result['status'];
                        $update_result[$i]['message'] = $parse_result['message'];
                    }

                    $updated[] = $file;
                    $i++;
                }

                foreach ($to_remove as $file) {
                    if ($i >= SYNC_BATCH_SIZE) {
                        break;
                    }

                    $query = $this->Songs->find();
                    $result = $query
                        ->leftJoin(['Songs2' => 'Songs'], ['Songs2.cover = Songs.cover'])
                        ->select([
                            'id' => $query->func()->max('Songs.id'),
                            'cover' => $query->func()->max('Songs.cover', ['string']),
                            'files_with_cover' => $query->func()->count('Songs2.id'),
                        ])
                        ->where(['Songs.source_path' => $file])
                        ->first();


                    $update_result[$i]['file'] = $file;
                    if ($this->Songs->delete($result)) {
                        $update_result[$i]['status'] = "OK";
                        $update_result[$i]['message'] = "";
                    } else {
                        $update_result[$i]['status'] = "ERR";
                        $update_result[$i]['message'] = __('Unable to delete song from the database');
                    }

                    // Last file using this cover file
                    if ($result->files_with_cover == 1) {
                        // Remove cover files from file system
                        if (file_exists(IMAGES . THUMBNAILS_DIR . DS . $result->cover)) {
                            unlink(IMAGES . THUMBNAILS_DIR . DS . $result->cover);
                        }

                        // Remove resized cover files from file system
                        $resized_filename_base = explode(".", $result->cover)[0];
                        $resized_files = glob(IMAGES . RESIZED_DIR . DS . $resized_filename_base . "_*");
                        foreach ($resized_files as $resized_file) {
                            unlink($resized_file);
                        }
                    }

                    $removed[] = $file;
                    $i++;
                }

                if ($i) {
                    $settings->sync_token = time();
                    $this->getTableLocator()->get('Settings')->save($settings);
                }

                // Delete lock
                Cache::delete('import');

                $sync_token = $settings->sync_token;

                $remaining_to_import = array_diff($to_import, $imported);
                $remaining_to_update = array_diff($to_update, $updated);
                $remaining_to_remove = array_diff($to_remove, $removed);
                $this->request->getSession()->write('to_import', $remaining_to_import);
                $this->request->getSession()->write('to_update', $remaining_to_update);
                $this->request->getSession()->write('to_remove', $remaining_to_remove);
                $this->set(compact('sync_token', 'update_result'));
            }
        }
    }

    public function sync(): void
    {
        $this->loadComponent('Sort');

        $songs = $this->Songs->find()->select(['id', 'album', 'artist', 'band', 'cover', 'title', 'disc', 'track_number', 'playtime'])->order('title')->toArray();
        $songs = $this->Sort->sortByBand($songs);
        foreach ($songs as $song) {
            $song->url = Router::url(['controller' => 'songs', 'action' => 'download', $song->id]);
            $song->cover = Asset::imageUrl(empty($song->cover) ? "no-cover.png" : THUMBNAILS_DIR . '/' . $song->cover);
        }

        $this->set('data', $songs);
        $this->viewBuilder()
            ->setClassName(JsonView::class)
            ->setOption('serialize', 'data');
    }

    /**
     * The albums view function.
     * Find songs in the database, alphabetically and grouped by album.
     */
    public function albums(): void
    {
        $sort = ['album' => 'asc'];
        $user_preferences = json_decode($this->Authentication->getIdentityData('preferences') ?? '', true);

        if (isset($user_preferences['albums_sort']) && $user_preferences['albums_sort'] === 'band') {
            $sort = ['band' => 'asc', 'album' => 'asc'];
        }

        if ($this->request->getQuery('sort') === 'band') {
            $sort = ['band' => 'asc', 'album' => 'asc'];

            if (!isset($user_preferences['sort']) || $user_preferences['sort'] !== 'band') {
                $user = $this->getTableLocator()->get('Users')->find()
                    ->select(['id', 'preferences'])
                    ->where(['id' => $this->Authentication->getIdentityData('id')])
                    ->first();

                $new_preferences = json_decode($user->preferences, true);
                $new_preferences['albums_sort'] = 'band';
                $user->preferences = json_encode($new_preferences);

                if ($this->getTableLocator()->get('Users')->save($user)) {
                    $identity = $this->Authentication->getIdentity()->getOriginalData();
                    $identity->preferences = $user->preferences;
                    $this->Authentication->setIdentity($identity);
                }
            }
        } elseif ($this->request->getQuery('sort') === 'album') {
            $sort = ['album' => 'asc'];

            if (!isset($user_preferences['sort']) || $user_preferences['sort'] !== 'album') {
                $user = $this->getTableLocator()->get('Users')->find()
                    ->select(['id', 'preferences'])
                    ->where(['id' => $this->Authentication->getIdentityData('id')])
                    ->first();

                $new_preferences = json_decode($user->preferences ?? '', true);
                unset($new_preferences['albums_sort']);
                $user->preferences = json_encode($new_preferences);

                if ($this->getTableLocator()->get('Users')->save($user)) {
                    $identity = $this->Authentication->getIdentity()->getOriginalData();
                    $identity->preferences = $user->preferences;
                    $this->Authentication->setIdentity($identity);
                }
            }
        }

        $playlists = $this->getTableLocator()->get('Playlists')->find('list')
            ->select(['id', 'title'])
            ->where(['user_id' => $this->Authentication->getIdentityData('id')])
            ->all();

        $latests = [];
        // Is this the first page requested?
        $page = $this->request->getQuery('page', 1);

        if ($page == 1) {
            $query = $this->Songs->find();
            $latests = $query
                ->select(['band', 'album', 'cover' => $query->func()->min('cover', ['string'])])
                ->group(['album', 'band'])
                ->orderDesc($query->func()->max('created', ['date']))
                ->limit(6)
                ->all();
        }

        $this->paginate = [
            'Songs' => [
                'allowedParameters' => [],
                'fields' => ['band', 'album', 'cover' => $this->Songs->query()->func()->min('cover', ['string'])],
                'group' => ['album', 'band'],
                'order' => $sort,
                'limit' => 36,
            ],
        ];

        $songs = $this->paginate($this->Songs);

        foreach ($songs as $song) {
            $song->cover = empty($song->cover) ? "no-cover.png" : THUMBNAILS_DIR . '/' . $song->cover;
        }

        foreach ($latests as $latest) {
            $latest->cover = empty($latest->cover) ? "no-cover.png" : THUMBNAILS_DIR . '/' . $latest->cover;
        }

        if ($songs->count() === 0) {
            $this->Flash->info(__('Oops! The database is empty...'));
        }

        $this->set(compact('songs', 'playlists', 'latests'));
    }

    /**
     * Get album content.
     * This function is called when you click on a cover from the albums view.
     */
    public function album(): void
    {
        $band = $this->request->getQuery('band');
        $album = $this->request->getQuery('album');
        $songs = $this->Songs->find()
            ->select(['id', 'title', 'album', 'artist', 'band', 'playtime', 'track_number', 'year', 'disc'])
            ->where(['band' => $band, 'album' => $album])
            ->toArray();

        $this->loadComponent('Sort');
        $songs = $this->Sort->sortByDisc($songs);

        $parsed = [];
        foreach ($songs as $song) {
            $currentDisc = 1;
            if (!empty($song->disc)) {
                $setsQuantity = explode('/', $song->disc);
                $currentDisc = (int)($setsQuantity[0]);
            }

            $parsed[$currentDisc][] = $song;
        }

        $this->set(['songs' => $parsed, 'band' => $band, 'album' => $album]);
    }

    /**
     * The artists view function.
     * Generate a list of 5 bands, in alphabetical order. This list is then read to find all the songs of each band, grouped by album and disc.
     */
    public function artists(): void
    {
        $playlists = $this->getTableLocator()->get('Playlists')->find('list')
            ->select(['id', 'title'])
            ->where(['user_id' => $this->Authentication->getIdentityData('id')])
            ->all();

        // Get 5 band names
        $this->paginate = [
            'Songs' => [
                'limit' => 5,
                'fields' => ['band'],
                'group' => ['band'],
                'order' => ['band' => 'ASC'],
            ],
        ];

        $bands = $this->paginate($this->Songs);

        $band_list = [];
        foreach ($bands as $band) {
            $band_list[] = $band->band;
        }

        // Get songs from the previous band names
        $songs = empty($band_list) ? [] : $this->Songs->find()
            ->select(['id', 'title', 'album', 'band', 'artist', 'cover', 'playtime', 'track_number', 'year', 'disc', 'genre'])
            ->where(['band IN' => $band_list])
            ->toArray();

        $this->loadComponent('Sort');
        $songs = $this->Sort->sortByBand($songs);

        // Then we can group the songs by band name, album and disc.
        $parsed = [];
        foreach ($songs as $song) {
            $currentDisc = 1;
            if (!empty($song->disc)) {
                $setsQuantity = explode('/', $song->disc);
                $currentDisc = (int)($setsQuantity[0]);
            }

            if (!isset($parsed[$song->band]['albums'][$song->album])) {
                $parsed[$song->band]['albums'][$song->album] = [
                    'album' => $song->album,
                    'cover' => empty($song->cover) ? "no-cover.png" : THUMBNAILS_DIR . '/' . $song->cover,
                    'year' => $song->year,
                    'genre' => [],
                ];
            }

            if (!in_array($song->genre, $parsed[$song->band]['albums'][$song->album]['genre'], true)) {
                $parsed[$song->band]['albums'][$song->album]['genre'][] = $song->genre;
            }

            if (!isset($parsed[$song->band]['sCount'])) {
                $parsed[$song->band]['sCount'] = 1;
            } else {
                $parsed[$song->band]['sCount'] += 1;
            }

            $parsed[$song->band]['albums'][$song->album]['discs'][$currentDisc]['songs'][] = $song;
        }

        if (empty($parsed)) {
            $this->Flash->info(__('Oops! The database is empty...'));
        }
        $this->set(['songs' => $parsed, 'playlists' => $playlists]);
    }

    /**
     * The index view function
     * Get songs from database, ordered by artist.
     */
    public function index(): void
    {
        $playlists = $this->getTableLocator()->get('Playlists')->find('list')
            ->select(['id', 'title'])
            ->where(['user_id' => $this->Authentication->getIdentityData('id')])
            ->all();

        // Get 5 band names
        $this->paginate = [
            'Songs' => [
                'limit' => 5,
                'fields' => ['band'],
                'group' => ['band'],
                'order' => ['band' => 'ASC'],
            ],
        ];

        $bands = $this->paginate($this->Songs);

        $band_list = [];
        foreach ($bands as $band) {
            $band_list[] = $band->band;
        }

        // Get songs from the previous band names
        $songs = empty($band_list) ? [] : $this->Songs->find()
            ->select(['id', 'title', 'album', 'band', 'artist', 'cover', 'playtime', 'track_number', 'year', 'disc', 'genre'])
            ->where(['band IN' => $band_list])
            ->toArray();

        $this->loadComponent('Sort');
        $songs = $this->Sort->sortByBand($songs);

        if (empty($songs)) {
            $this->Flash->info(__('Oops! The database is empty...'));
        }

        $this->set(compact('songs', 'playlists'));
    }

    /**
     * Search view function
     * We just make a SQL request...
     */
    public function search(): void
    {
        $query = $this->request->getQuery('q', false);

        if ($query) {
            $this->paginate = [
                'Songs' => [
                    'fields' => ['band'],
                    'group' => ['band'],
                    'limit' => 5,
                    'conditions' => ['OR' => [
                        'LOWER(Songs.title) like' => '%' . strtolower($query) . '%',
                        'LOWER(Songs.band) like' => '%' . strtolower($query) . '%',
                        'LOWER(Songs.artist) like' => '%' . strtolower($query) . '%',
                        'LOWER(Songs.album) like' => '%' . strtolower($query) . '%',
                    ],
                    ],
                ],
            ];

            $bands = $this->paginate($this->Songs);
            $band_list = [];

            foreach ($bands as $band) {
                $band_list[] = $band->band;
            }

            $songs = empty($band_list) ? [] : $this->Songs->find()
                ->select(['id', 'title', 'album', 'band', 'artist', 'cover', 'playtime', 'track_number', 'year', 'disc', 'genre'])
                ->where(['OR' => [
                    'LOWER(Songs.title) like' => '%' . strtolower($query) . '%',
                    'LOWER(Songs.artist) like' => '%' . strtolower($query) . '%',
                    'LOWER(Songs.album) like' => '%' . strtolower($query) . '%'], 'band IN' => $band_list])
                ->toArray();

            $this->loadComponent('Sort');
            $songs = $this->Sort->sortByBand($songs);

            $parsed = [];
            foreach ($songs as $song) {
                $currentDisc = 1;
                if (!empty($song->disc)) {
                    $setsQuantity = explode('/', $song->disc);
                    $currentDisc = (int)($setsQuantity[0]);
                }

                if (!isset($parsed[$song->band]['albums'][$song->album])) {
                    $parsed[$song->band]['albums'][$song->album] = [
                        'album' => $song->album,
                        'cover' => empty($song->cover) ? "no-cover.png" : THUMBNAILS_DIR . '/' . $song->cover,
                        'year' => $song->year,
                        'genre' => [],
                    ];
                }

                if (!in_array($song->genre, $parsed[$song->band]['albums'][$song->album]['genre'], true)) {
                    $parsed[$song->band]['albums'][$song->album]['genre'][] = $song->genre;
                }

                if (!isset($parsed[$song->band]['sCount'])) {
                    $parsed[$song->band]['sCount'] = 1;
                } else {
                    $parsed[$song->band]['sCount'] += 1;
                }

                $parsed[$song->band]['albums'][$song->album]['discs'][$currentDisc]['songs'][] = $song;
            }

            if (empty($parsed)) {
                $this->Flash->error(__('Oops! No results.'));
            }
            $this->set('songs', $parsed);
        }

        $playlists = $this->getTableLocator()->get('Playlists')->find('list')
            ->select(['id', 'title'])
            ->where(['user_id' => $this->Authentication->getIdentityData('id')])
            ->all();

        $this->set(compact('query', 'playlists'));
    }

    /**
     * This function is called by the player when you click on 'Play'
     * The file extension is checked to know if Sonerezh must convert the track.
     *
     * @param null $id
     * @return \Cake\Http\Response audio file
     */
    public function download($id = null)
    {
        $settings = $this->getTableLocator()->get('Settings')->find()->first();

        $song = $this->Songs->get($id);
        if (!$song) {
            throw new NotFoundException();
        }

        if (empty($song->path)) {
            $file_extension = substr(strrchr($song->source_path, "."), 1);
        } else {
            $file_extension = substr(strrchr($song->path, "."), 1);
        }

        if (empty($song->path) || $file_extension != $settings->convert_to) {
            if (in_array($file_extension, explode(',', $settings->convert_from), true)) {
                $bitrate = (string) $settings->quality;
                $avconv = 'ffmpeg';

                if (shell_exec('which avconv') || shell_exec('where avconv')) {
                    $avconv = 'avconv';
                }

                $orig_locale = setlocale(LC_CTYPE, 0);
                setlocale(LC_CTYPE, 'C.UTF-8');

                if ($settings->convert_to === 'mp3') {
                    $path = TMP . date('YmdHis') . ".mp3";
                    $song->path = $path;
                    passthru($avconv . " -i " . escapeshellarg($song->source_path) . " -threads 4 -c:a libmp3lame -b:a " . escapeshellarg($bitrate . 'k') . " " . escapeshellarg($path));
                } elseif ($settings->convert_to === 'ogg') {
                    $path = TMP . date('YmdHis') . ".ogg";
                    $song->path = $path;
                    passthru($avconv . " -i " . escapeshellarg($song->source_path) . " -threads 4 -c:a libvorbis -q:a " . escapeshellarg($bitrate) . " " . escapeshellarg($path));
                }

                setlocale(LC_CTYPE, $orig_locale);
            } elseif (empty($song->path)) {
                $song->path = $song->source_path;
            }

            $this->Songs->save($song);
        }

        // Symlink files whose name contains '..' to avoid CakePHP request error.
        if (str_contains($song->path, '..')) {
            $symlinkPath = TMP . md5($song->path) . '.' . substr(strrchr($song->path, "."), 1);
            if (!file_exists($symlinkPath)) {
                symlink($song->path, $symlinkPath);
            }
            $song->path = $symlinkPath;
        }

        return $this->response->withFile($song->path, ['download' => true])->withCache('-1 minute', '+2 hours');
    }
}
