<?php

namespace App\Event;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Mailer\Mailer;
use Cake\ORM\TableRegistry;

class UsersEventListener implements EventListenerInterface
{

    public function implementedEvents(): array
    {
        return array(
            'Model.User.add' => 'sendUserCreationEmail',
            'Controller.User.resetPassword' => 'sendResetPasswordEmail'
        );
    }

    public function sendUserCreationEmail(EventInterface $event)
    {
		$settings = TableRegistry::getTableLocator()->get('Settings')->find()->select(['enable_mail_notification'])->first();

        if ($settings->enable_mail_notification) {
            $user_email = $event->getSubject()->email;
            $email = new Mailer();
            $email->setTo($user_email)
                ->setSubject(__('Welcome on Sonerezh!'))
                ->setEmailFormat('html')
                ->viewBuilder()
				->setTemplate('userAdd')
				->setVars(compact('user_email'));
			$email->deliver();
        }
    }

    public function sendResetPasswordEmail(EventInterface $event)
    {
		$settings = TableRegistry::getTableLocator()->get('Settings')->find()->select(['enable_mail_notification'])->first();

		if ($settings->enable_mail_notification) {
            $user = $event->getSubject();
            $token = $event->getData('token');

            $email = new Mailer();
            $email->setTo($user->email)
                ->setSubject(__('Forgot your password?'))
                ->setEmailFormat('html')
				->viewBuilder()
				->setTemplate('sendToken')
				->setVars(compact('token'));
			$email->deliver();

        }
    }
}
