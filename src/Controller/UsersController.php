<?php

namespace App\Controller;

use App\Event\UsersEventListener;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Http\Exception\MethodNotAllowedException;

/**
 * Class UsersController
 */
class UsersController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        $this->Users->getEventManager()
            ->on('Model.beforeRules', function ($event, $entity, $options): void {
                $options['current_user'] = $this->Authentication->getIdentity() === null
                    ? $entity->id
                    : $this->Authentication->getIdentityData('id');
            })
            ->on('Model.afterSave', function ($event, $entity): void {
                if ($this->Authentication->getIdentity() !== null && $entity->id === $this->Authentication->getIdentityData('id')) {
                    $this->Authentication->setIdentity($entity);
                }
            });
        $this->Authentication->allowUnauthenticated(['login', 'setResetPasswordToken', 'resetPassword']);
        parent::beforeFilter($event);
    }

    public function isAuthorized($user): bool
    {
        if ($user->role === "admin") {
            return true;
        } elseif (in_array($this->request->getParam('action'), ['logout','login'], true)) {
            return true;
        } elseif (in_array($this->request->getParam('action'), ['edit', 'deleteAvatar'], true)
            && $this->request->getParam('pass')[0] == $this->Authentication->getIdentityData('id')
        ) {
            return true;
        }
        return false;
    }

    public function index()
    {
        $this->set('users', $this->Users->find()->all());
    }

    public function add()
    {
        // Send email on user creation
        $this->Users->getEventManager()->on(new UsersEventListener());

        if ($this->request->is('post')) {
            if ($this->Users->save($this->Users->newEntity($this->request->getData()))) {
                $this->Flash->success(__('User created: {0}', $this->request->getData('email')));
            } else {
                $this->Flash->error(__('Unable to create a user. Make sure its email is not already used and his password is at least 8 characters long.'));
            }
            return $this->redirect(array('action' => 'index'));
        }
    }

    public function edit($id = null)
    {
        if ($id === null) {
            return $this->redirect($this->referer());
        }

		$user = $this->Users->get($id);
        if ($this->request->is(array('post', 'put'))) {
            if ($this->Users->save($this->Users->patchEntity($user, $this->request->getData()))) {
                $this->Flash->success(__('User updated: {0}', $this->request->getData('email')));
            } else {
                $this->Flash->error(__('Something went wrong!'));
            }
        }

        $this->set('user', $user);
    }

    public function delete($id)
    {
        if ($this->request->is('get')) {
            throw new MethodNotAllowedException();
        }

        $user = $this->Users->get($id);

        if ($this->Users->delete($user)) {
            $this->Flash->success(__('User deleted: {0}', $user->email));
        }
        return $this->redirect($this->referer());
    }

    public function deleteAvatar($id = null)
    {
        if (!isset($id)) {
            return $this->redirect($this->referer());
        }

        $user = $this->Users->get($id);
		$user->avatar = null;
		if ($this->Users->save($user)) {
			$this->Flash->success(__('Avatar has been successfully removed!'));
		} else {
			$this->Flash->error(__('Something went wrong!'));
		}
		return $this->redirect(array('action' => 'edit/' . $id));
	}

	public function login()
	{
		$this->viewBuilder()->setLayout('login');

		$result = $this->Authentication->getResult();
		if ($result->isValid()) {
			return $this->redirect($this->Authentication->getLoginRedirect() ?? '/');
		}
		if ($this->request->is('post')) {
			$this->Flash->error(__('Wrong credentials!'));
			$this->log('Failed authentication for ' . $this->request->getData('email') . ' from ' . $this->request->clientIp(), 'error');
		}

		$settings = $this->getTableLocator()->get('Settings')->find()->first();
		$this->set(compact('settings'));
	}

    public function logout()
    {
		$this->Authentication->logout();
		return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

    /**
     * This function allows users to reset their password
     * A token is forged from user informations and send to the provided email
     * Thanks to @bdelespierre (http://bdelespierre.fr/article/bien-plus-quun-simple-jeton/)
     */
    public function setResetPasswordToken()
    {
        if ($this->request->is('POST')) {

			$user = $this->Users->find()->where(['email' => $this->request->getData('email')])->first();

            if ($user === null) {
                $this->Flash->error(__('Unable to find your account.'));
                return $this->redirect(array('action' => 'login'));
            }

            $this->loadComponent('Date');
            $this->loadComponent('Url');

            $date = $this->Date->date16_encode(date('y'), date('m'), date('d'));
            $entropy = mt_rand();
            $password_crc32 = crc32($user->password);
            $binary_token = pack('ISSL', $user->id, $date, $entropy, $password_crc32);
            $urlsafe_token = $this->Url->base64url_encode($binary_token);

            // Send the token
            if ($urlsafe_token) {
                $usersEventListener = new UsersEventListener();
				$event = new Event('Controller.User.resetPassword', $user, ['token' => $urlsafe_token]);

                $this->Users->getEventManager()->on($usersEventListener);
                $this->Users->getEventManager()->dispatch($event);

                $this->Flash->success(__('Email successfully sent.'));
            } else {
                $this->Flash->error(__('Unable to generate a token.'));
            }
            return $this->redirect(array('action' => 'login'));
        }
    }

    public function resetPassword()
    {
        $this->viewBuilder()->setLayout('login');

        $token = $this->request->getQuery('t');

        if (!$token) {
            $this->Flash->error(__('You need to provide a token.'));
            return $this->redirect(array('action' => 'login'));
        }

        $this->loadComponent('Url');
        $binary_token = $this->Url->base64url_decode($token);

        if (!$binary_token) {
            $this->Flash->error(__('Unable to decode your token.'));
            return $this->redirect(array('action' => 'login'));
        }

        $token_data = @unpack('Iid/Sdate/Sentropy/Lpassword_crc32', $binary_token);

        if (!$token_data) {
            $this->Flash->error(__('Unable to read your token.'));
            return $this->redirect(array('action' => 'login'));
        }

        $this->loadComponent('Date');
        list($year, $month, $day) = $this->Date->date16_decode($token_data['date']);
        $token_date = "{$year}-{$month}-{$day}";
        $today = date('y-n-d');

        if ($token_date !== $today) {
            $this->Flash->error(__('The token has expired.'));
            return $this->redirect(array('action' => 'login'));
        }

        $token_id = $token_data['id'];
		$user = $this->Users->find()->select(['id', 'email', 'password'])->where(['id' => $token_id])->first();

        if ($user === null) {
            $this->Flash->error(__('Unable to find your account.'));
            return $this->redirect(array('action' => 'login'));
        } elseif (crc32($user->password) !== $token_data['password_crc32']) {
            $this->Flash->error(__('Wrong token.'));
            return $this->redirect(array('action' => 'login'));
        }

        $this->set(compact('user'));

        if ($this->request->is(array('post', 'put'))) {
			$this->Users->patchEntity($user, $this->request->getData());
            if ($this->Users->save($user)) {
                $this->Flash->success(__('Your password has been updated.'));
                return $this->redirect(array('action' => 'login'));
            } else {
                $this->Flash->error(__('Unable to update your password.'));
            }
        }
    }
}
