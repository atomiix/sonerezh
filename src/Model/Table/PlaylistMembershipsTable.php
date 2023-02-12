<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class PlaylistMembershipsTable extends Table
{
	public function initialize(array $config): void
	{
		$this->belongsTo('Songs');
		$this->belongsTo('Playlists');
	}
}
