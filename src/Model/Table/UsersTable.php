<?php

declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\ORM\Rule\IsUnique;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Psr\Http\Message\UploadedFileInterface;

class UsersTable extends Table
{
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->setStopOnFailure()
            ->email('email', false, __('Login must be a valid email.'))
            ->allowEmptyString('password', __('Password must be at least 8 characters long.'), 'update')
            ->minLength('password', 8, __('Password must be at least 8 characters long.'))
            ->add('confirm_password', 'custom', [
                'rule' => fn ($item, $context) => $item === $context['data']['password'],
                'message' => __('Wrong confirmation password.'),
            ])
            ->inList('role', ['admin', 'listener'], 'Incorrect role.')
            ->add('avatar_file', 'uploadError', [
                'rule' => ['uploadError', true],
                'message' => __('Something went wrong with the upload.'),
            ])
            ->uploadedFile('avatar_file', [
                'types' => ['image/gif', 'image/jpeg', 'image/png'],
                'optional' => true,
            ], __('Your avatar must be in a correct format (JPEG, PNG, GIF).'))
        ;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules
            ->add(new IsUnique(['email']), null, ['message' => __('Login already used.')])
            ->add([$this, 'isThereAnAdmin'], null, [
                'errorField' => 'role',
                'message' => __('Sonerezh needs at least one administrator. You can not change your own privileges.'),
            ])
        ;
    }

    public function isThereAnAdmin(EntityInterface $entity, array $options)
    {
        return $entity->role === $entity->getOriginal('role') || $entity->id !== $options['current_user'];
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->avatar_file instanceof UploadedFileInterface && $entity->avatar_file->getError() === UPLOAD_ERR_OK) {
            $this->__uploadAvatar($entity->avatar_file, $entity);
            if (!empty($entity->getOriginal('avatar')) && $entity->getOriginal('avatar') !== $entity->avatar) {
                $this->__deleteAvatar($entity->getOriginal('avatar'));
            }
        }
    }

    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->isNew()) {
            $this->getEventManager()->dispatch(new Event('Model.User.add', $entity));
        }
    }

    public function beforeDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!empty($entity->avatar)) {
            $this->__deleteAvatar($entity->avatar);
        }
    }

    private function __deleteAvatar(string $avatar): void
    {
        $oldAvatar = explode('.', $avatar);
        $avatarFinder = preg_grep('/^'.$oldAvatar[0].'\./', scandir(IMAGES.AVATARS_DIR));
        $resizedAvatar = preg_grep('/^'.$oldAvatar[0].'_/', scandir(IMAGES.RESIZED_DIR));

        if (!empty($avatarFinder)) {
            foreach ($avatarFinder as $v) {
                unlink(IMAGES.AVATARS_DIR.DS.$v);
            }
        }
        if (!empty($resizedAvatar)) {
            foreach ($resizedAvatar as $v) {
                unlink(IMAGES.RESIZED_DIR.DS.$v);
            }
        }
    }

    private function __uploadAvatar(UploadedFileInterface $avatarFile, EntityInterface $entity): void
    {
        $avatarFolder = IMAGES . AVATARS_DIR;
        $avatarId = md5((string) microtime(true));
        $ext = strtolower(substr(strrchr($avatarFile->getClientFilename(), "."), 1));
        $uploadPath = $avatarFolder.DS.$avatarId.'.'.$ext;

        if (!file_exists($avatarFolder)) {
            mkdir($avatarFolder);
        }

        $avatarFile->moveTo($uploadPath);
        $entity->avatar = $avatarId.'.'.$ext;
    }
}
