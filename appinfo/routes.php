<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#groups', 'url' => '/groups', 'verb' => 'GET'],
		['name' => 'history#index', 'url' => '/history', 'verb' => 'GET'],
		['name' => 'history#show', 'url' => '/history/{id}', 'verb' => 'GET'],
		['name' => 'history#create', 'url' => '/history', 'verb' => 'POST'],
		['name' => 'history#share', 'url' => '/history/{id}/share', 'verb' => 'POST'],
		['name' => 'history#destroy', 'url' => '/history/{id}', 'verb' => 'DELETE'],
		['name' => 'preset#index', 'url' => '/presets', 'verb' => 'GET'],
		['name' => 'preset#show', 'url' => '/presets/{id}', 'verb' => 'GET'],
		['name' => 'preset#create', 'url' => '/presets', 'verb' => 'POST'],
		['name' => 'preset#update', 'url' => '/presets/{id}', 'verb' => 'PUT'],
		['name' => 'preset#destroy', 'url' => '/presets/{id}', 'verb' => 'DELETE'],
		['name' => 'export#saveToFiles', 'url' => '/export/save', 'verb' => 'POST'],
		['name' => 'folder#listFolders', 'url' => '/folders', 'verb' => 'GET'],
	],
];
