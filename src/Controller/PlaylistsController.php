<?php

namespace App\Controller;

use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;

/**
 * Class PlaylistsController
 * Manage adding, editing and deleting playlists.
 */
class PlaylistsController extends AppController
{

    /**
     * Retrieve the current user playlists, and songs of a given playlist before pass them to the view.
     *
     * @param int|null $id The playlist ID.
     */
    public function index($id = null)
    {
        /**
         * @var array Array of playlist songs.
         */
        $playlist = array();

        /**
         * @var string Name of playlist songs.
         */
        $playlistName = null;

        $playlistInfo = array();

        /**
         * @var array Array of user playlists.
         */
		$playlists = $this->Playlists->find('list')
			->select(['id', 'title'])
			->where(['user_id' => $this->Authentication->getIdentityData('id')])
			->toArray();

        // Find playlist content
        if (!empty($playlists)) {
            if ($id == null) {
                $id = key($playlists);
            }
            $playlistInfo = array('id' => $id, 'name' => $playlists[$id]);
			$playlist = $this->getTableLocator()->get('PlaylistMemberships')
				->find()
				->contain('Songs')
				->where(['playlist_id' => $id])
				->order(['sort'])
				->all();
        }

        $this->set(compact('playlists', 'playlist', 'playlistInfo'));
    }

    /**
     * Manage playlist creation. Each playlist is linked to the user that creates it.
     */
    public function add()
    {
        if ($this->request->is('post')) {
			$playlist = $this->Playlists->newEntity(
				$this->request->getData() + ['user_id' => $this->Authentication->getIdentityData('id')]
			);

            if ($this->Playlists->save($playlist)) {
                $this->Flash->success(__('Playlist created: {0}', $this->request->getData('title')));
            } else {
                $this->Flash->error(__('Unable to create the playlist: {0}', $this->request->getData('title')));
            }

            return $this->redirect(array('action' => 'index'));
        }
    }

    /**
     * Manage playlist edition.
     *
     * @param int $id The playlist to rename.
     */
    public function edit($id)
    {
        if (!$id) {
            throw new NotFoundException(__('Invalid playlist ID'));
        }

        $playlist = $this->Playlists->get($id);

        if (!$playlist) {
            throw new NotFoundException(__('Invalid playlist ID'));
        }

        if ($this->request->is(array('post', 'put'))) {
			$this->Playlists->patchEntity($playlist, $this->request->getData());
            if ($this->Playlists->save($playlist)) {
                $this->Flash->success(__('Playlist renamed: {0}', $this->request->getData('title')));
            } else {
                $this->Flash->error(__('Unable to rename the playlist: {0}', $playlist->title));
            }

            return $this->redirect(array('controller' => 'playlists', 'action' => 'index'));
        }

		throw new MethodNotAllowedException();
    }

    /**
     * Manage playlists deletion.
     *
     * @param int $id The playlist ID to delete.
     */
    public function delete($id)
    {
        if ($this->request->is('get')) {
            throw new MethodNotAllowedException();
        }

        $playlist = $this->Playlists->get($id);

        if ($this->Playlists->delete($playlist)) {
            $this->Flash->success(__('Playlist deleted: {0}', $playlist->title));
        } else {
            $this->Flash->error(__('Unable to delete the playlist: {0}', $playlist->title));
        }
        return $this->redirect(array('action' => 'index'));
    }
}
