<?php

namespace App\Controller;

use Cake\Http\Exception\MethodNotAllowedException;

/**
 * Class PlaylistMembershipsController
 * Manage adding and deleting tracks in playlists. This controller binds SongsController and PlaylistController.
 */
class PlaylistMembershipsController extends AppController
{

    /**
     * This function adds songs into your favorites playlists.
     * All the information is passed through a POST request. To add multiple songs at the same time you can use a list
     * of song IDs separated by dashes : $this->request->data['Song']['id'] = '1-2-3-4-5'
     */
    public function add()
    {
        if ($this->request->is('post')) {

            // Verify that Playlist.id is correct
            if ($this->request->getData('id') === null && $this->request->getData('title') === null) {
                $this->Flash->error(__('You must specify a valid playlist'));
                return $this->redirect($this->referer());
            }

            $playlist_length = 0;
            // Verify that Playlist.id exists
            if ($this->request->getData('id') !== null) {
                $playlist = $this->getTableLocator()->get('Playlists')->get($this->request->getData('id'));

                if (empty($playlist)) {
                    $this->Flash->error(__('You must specify a valid playlist'));
                    return $this->redirect($this->referer());
                }

                // Get playlist length to add the song at the end of the playlist
				$playlist_length = $this->PlaylistMemberships->find()
					->where(['playlist_id' => $this->request->getData('id')])
					->count();

            }

            $data = $this->request->getData() + ['playlist_memberships' => []];
            //Simple song id
            if ($this->request->getData("song") !== null) {
                $data['playlist_memberships'][] = array(
                    'song_id' => $this->request->getData('song'),
                    'sort' => $playlist_length + 1
                );

            } elseif ($this->request->getData('band') !== null) { // It's a band!
                $conditions = array('band' => $this->request->getData('band'));
                $order = 'band';

                if ($this->request->getData('album') !== null) { // It's an album!
                    $conditions['album'] = $this->request->getData('album');
                    $order = 'disc';
                }

                $songs = $this->getTableLocator()->get('Songs')->find()
					->select(['id', 'title', 'album', 'band', 'track_number', 'disc'])
					->where($conditions)
					->toArray();

                $this->loadComponent('Sort');

                if ($order === 'band') {
                    $songs = $this->Sort->sortByBand($songs);
                } elseif ($order === 'disc') {
                    $songs = $this->Sort->sortByDisc($songs);
                }

                foreach ($songs as $song) {
                    $data['playlist_memberships'][] = array(
                        'song_id' => $song->id,
                        'sort' => ++$playlist_length
                    );
                }
            }

            // Save data
            $Playlists = $this->getTableLocator()->get('Playlists');
            $data['user_id'] = $this->Authentication->getIdentityData('id');
            if (!empty($data['id'])) {
            	// Unset Playlist.title if Playlist.id is set to avoid erase Playlist.title
                unset($data['title']);
                $entity = $Playlists->get($data['id']);
                $Playlists->patchEntity($entity, $data, ['associated' => 'PlaylistMemberships']);
            } else {
                $entity = $Playlists->newEntity($data, ['associated' => 'PlaylistMemberships']);
            }
            if ($Playlists->save($entity)) {
                $this->Flash->success(__('Song successfully added to playlist'));
            } else {
                $this->Flash->error(__('Unable to add the song'));
            }

            $playlists = $Playlists->find('list')
                ->select(['id', 'title'])
                ->where(['user_id' => $this->Authentication->getIdentityData('id')])
                ->all();
            $this->set('playlistOptions', json_encode($playlists->toArray()));
            $this->set('playlists', $playlists);
        } else {
            throw new MethodNotAllowedException();
        }
    }

    /**
     * This function removes songs from a playlist.
     *
     * @param int $id The ID of the song to be removed.
     * @todo Add the ability to remove multiple songs at once.
     */
    public function remove($id)
    {
        $song = $this->PlaylistMemberships->get($id);
        if ($this->PlaylistMemberships->delete($song)) {
            $this->Flash->success(__('Song successfully removed from playlist'));
            return $this->redirect($this->referer());
        }
    }
}
