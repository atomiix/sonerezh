<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RootpathsTable extends Table
{
	public function validationDefault(Validator $validator): Validator
	{
		return $validator
			->setStopOnFailure()
			->add('rootpath', 'file_exists', [
				'rule' => fn ($field) => file_exists($field),
				'message' => __('The music library\'s directory does not exist.'),
			])->add('rootpath', 'is_readable', [
				'rule' => fn ($field) => is_readable($field) && preg_match('/^\/?\s|\s\/?$/', $field) === 0,
				'message' => __('The music library\'s directory is not readable.'),
			])
		;
	}
}
