<?php

namespace arjanbrinkman\craftentrypostdatechecker\models;

use Craft;
use craft\base\Model;

/**
 * Entry PostDate Checker settings
 */
class Settings extends Model
{
	public int $timeScopeMinutes = 15;
	
	public function rules(): array
	{
		return [
			[['timeScopeMinutes'], 'integer', 'min' => 1],
		];
	}
}
