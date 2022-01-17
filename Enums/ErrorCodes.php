<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files\Enums;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class ErrorCodes
{
	const NotFound = 4001;
	const NotPermitted = 4002;
	const AlreadeExists = 4003;
	const CantDeleteSharedItem = 4004;

	/**
	 * @var array
	 */
	protected $aConsts = [
		'NotFound' => self::NotFound,
		'NotPermitted' => self::NotPermitted,
		'AlreadeExists' => self::AlreadeExists,
		'CantDeleteSharedItem' => self::CantDeleteSharedItem
	];
}
