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
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 */
class ErrorCodes
{
    public const NotFound = 4001;
    public const NotPermitted = 4002;
    public const AlreadeExists = 4003;
    public const CantDeleteSharedItem = 4004;
    public const CannotCopyOrMoveItemToItself = 4005;
    public const NotPossibleToMoveSharedFileToCorporateStorage = 4006;

    /**
     * @var array
     */
    protected $aConsts = [
        'NotFound' => self::NotFound,
        'NotPermitted' => self::NotPermitted,
        'AlreadeExists' => self::AlreadeExists,
        'CantDeleteSharedItem' => self::CantDeleteSharedItem,
        'CannotCopyOrMoveItemToItself' => self::CannotCopyOrMoveItemToItself,
        'NotPossibleToMoveSharedFileToCorporateStorage' => self::NotPossibleToMoveSharedFileToCorporateStorage,
    ];
}
