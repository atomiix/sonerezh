<?php

declare(strict_types=1);

use Cake\Database\Schema\TableSchema;

$playlistMemberships = (new TableSchema('playlist_memberships'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )->addColumn('playlist_id', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])->addColumn('song_id', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])->addColumn('sort', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ]);

$playlists = (new TableSchema('playlists'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])
    ->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )
    ->addColumn('title', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])
    ->addColumn('created', [
        'type' => 'datetime',
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('modified', [
        'type' => 'datetime',
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('user_id', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ]);

$settings = (new TableSchema('settings'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])
    ->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )->addColumn('enable_auto_conv', [
        'type' => 'boolean',
        'default' => false,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('convert_from', [
        'type' => 'string',
        'default' => 'aac,flac',
        'limit' => 25,
        'null' => false,
    ])
    ->addColumn('convert_to', [
        'type' => 'string',
        'default' => 'mp3',
        'limit' => 5,
        'null' => false,
    ])
    ->addColumn('quality', [
        'type' => 'integer',
        'default' => '256',
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])
    ->addColumn('enable_mail_notification', [
        'type' => 'boolean',
        'default' => false,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('sync_token', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => true,
    ]);

$rootpaths = (new TableSchema('rootpaths'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )
    ->addColumn('setting_id', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('rootpath', [
        'type' => 'string',
        'default' => null,
        'limit' => 1024,
        'null' => false,
    ]);

$users = (new TableSchema('users'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )->addColumn('email', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])->addColumn('password', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])->addColumn('role', [
        'type' => 'string',
        'default' => null,
        'limit' => 15,
        'null' => false,
    ])->addColumn('avatar', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => true,
    ])->addColumn('preferences', [
        'type' => 'text',
        'default' => null,
        'limit' => null,
        'null' => true,
    ]);

$songs = (new TableSchema('songs'))
    ->addColumn('id', [
        'type' => 'integer',
        'autoIncrement' => true,
        'default' => null,
        'limit' => null,
        'null' => false,
        'signed' => false,
    ])
    ->addConstraint(
        TableSchema::CONSTRAINT_PRIMARY,
        ['type' => TableSchema::CONSTRAINT_PRIMARY, 'columns' => 'id']
    )
    ->addColumn('title', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])
    ->addColumn('album', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])
    ->addColumn('artist', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => false,
    ])
    ->addColumn('source_path', [
        'type' => 'string',
        'default' => null,
        'limit' => 1024,
        'null' => false,
    ])
    ->addColumn('path', [
        'type' => 'string',
        'default' => null,
        'limit' => 1024,
        'null' => true,
    ])
    ->addColumn('cover', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => true,
    ])
    ->addColumn('playtime', [
        'type' => 'string',
        'default' => null,
        'limit' => 9,
        'null' => true,
    ])
    ->addColumn('track_number', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => true,
        'signed' => false,
    ])
    ->addColumn('year', [
        'type' => 'integer',
        'default' => null,
        'limit' => null,
        'null' => true,
        'signed' => false,
    ])
    ->addColumn('disc', [
        'type' => 'string',
        'default' => null,
        'limit' => 7,
        'null' => true,
    ])
    ->addColumn('band', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => true,
    ])
    ->addColumn('genre', [
        'type' => 'string',
        'default' => null,
        'limit' => 255,
        'null' => true,
    ])
    ->addColumn('created', [
        'type' => 'datetime',
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addColumn('modified', [
        'type' => 'datetime',
        'default' => null,
        'limit' => null,
        'null' => false,
    ])
    ->addIndex('ix_songs_album', [
        'columns' => ['album'],
        'type' => TableSchema::INDEX_INDEX,
    ])
    ->addIndex('ix_songs_band', [
        'columns' => ['band'],
        'type' => TableSchema::INDEX_INDEX,
    ]);

return [
    $playlistMemberships,
    $playlists,
    $settings,
    $rootpaths,
    $users,
    $songs,
];
