<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class PlaylistsTable extends Table
{
	public function initialize(array $config): void
	{
		$this->addBehavior('Timestamp');
		$this->hasMany('PlaylistMemberships', ['dependent' => true]);
	}

	public function validationDefault(Validator $validator): Validator
	{
		return $validator->notBlank('title', __('The playlist must have a title'));
	}

    public function validationDefault(Validator $validator): Validator
    {
        return $validator->notBlank('title', __('The playlist must have a title'));
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules->add(
            fn (EntityInterface $entity, array $options) => $entity->isNew() || $entity->getOriginal('user_id') === $entity->get('user_id'),
            'userDidNotChange',
            [
                'errorField' => 'user_id',
                'message' => __('You cannot add a song to someone else\'s playlist'),
            ]
        );
    }
}
