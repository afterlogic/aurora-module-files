<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files\Classes;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property string $Id
 * @property int $Type
 * @property string $TypeStr
 * @property string $Path
 * @property string $FullPath
 * @property string $Name
 * @property int $Size
 * @property bool $IsFolder
 * @property bool $IsLink
 * @property string $LinkType
 * @property string $LinkUrl
 * @property bool $LastModified
 * @property string $ContentType
 * @property bool $Thumb
 * @property string $ThumbnailUrl
 * @property string $OembedHtml
 * @property bool $Shared
 * @property string $Owner
 * @property string $Content
 * @property bool $IsExternal
 * @property string $RealPath
 * @property array $Actions
 * @property string $Hash
 * @property int $GroupId
 * @property bool $Published
 * @property string $ETag
 * @property array $ExtendedProps
 * @property string $Initiator
 *
 * @package Classes
 * @subpackage FileStorage
 */
class FileItem
{
    public $Id = '';
    public $TypeStr = \Aurora\System\Enums\FileStorageType::Personal;
    public $Path = '';
    public $FullPath = '';
    public $Name = '';
    public $Size = 0;
    public $IsFolder = false;
    public $IsLink = false;
    public $LinkType = '';
    public $LinkUrl = '';
    public $LastModified = 0;
    public $ContentType = '';
    public $Thumb = false;
    public $ThumbnailUrl = '';
    public $OembedHtml = '';
    public $Published = false;
    public $Owner = '';
    public $Content = '';
    public $IsExternal = false;
    public $RealPath = '';
    public $Actions = [];
    public $ETag = '';
    public $ExtendedProps = [];
    public $Shared = false;
    public $GroupId = null;
    public $Initiator = null;

    /**
     *
     * @param string $sPublicHash
     * @return string
     */
    public function getHash($sPublicHash = null)
    {
        $aResult = [
            'UserId' => \Aurora\System\Api::getAuthenticatedUserId(),
            'Id' => $this->Id,
            'Type' => $this->TypeStr,
            'Path' => $this->Path,
            'Name' => $this->Id,
            'FileName' => $this->Name,
            'Shared' => $this->Shared,
            'GroupId' => $this->GroupId
        ];

        if (isset($sPublicHash)) {
            $aResult['PublicHash'] = $sPublicHash;
        }

        return \Aurora\System\Api::EncodeKeyValues($aResult);
    }

    public function toResponseArray($aParameters = [])
    {
        $aArgs = [$this];
        $aResult = [];

        \Aurora\System\EventEmitter::getInstance()->emit(
            'Files',
            'FileItemtoResponseArray',
            $aArgs,
            $aResult
        );

        $aResult['Id'] = $this->Id;
        $aResult['Type'] = $this->TypeStr;
        $aResult['Path'] = $this->Path;
        $aResult['FullPath'] = $this->FullPath;
        $aResult['Name'] = $this->Name;
        $aResult['Size'] = $this->Size;
        $aResult['IsFolder'] = $this->IsFolder;
        $aResult['IsLink'] = $this->IsLink;
        $aResult['LinkType'] = $this->LinkType;
        $aResult['LinkUrl'] = $this->LinkUrl;
        $aResult['LastModified'] = $this->LastModified;
        $aResult['ContentType'] = $this->ContentType;
        $aResult['OembedHtml'] = $this->OembedHtml;
        $aResult['Published'] = $this->Published;
        $aResult['Owner'] = $this->Owner;
        $aResult['Content'] = $this->Content;
        $aResult['IsExternal'] = $this->IsExternal;
        $aResult['Actions'] = $this->Actions;
        $aResult['Hash'] = $this->getHash();
        $aResult['ETag'] = $this->ETag;
        $aResult['ExtendedProps'] = $this->ExtendedProps;
        $aResult['Shared'] = $this->Shared;
        $aResult['GroupId'] = $this->GroupId === null ? 0 : $this->GroupId;
        $aResult['Initiator'] = $this->Initiator === null ? '' : $this->Initiator;

        if ($this->Thumb) {
            if (empty($this->ThumbnailUrl) && $this->GetActionUrl('download')) {
                $this->ThumbnailUrl = $this->GetActionUrl('download') . '/thumb';
            }
            $aResult['ThumbnailUrl'] = $this->ThumbnailUrl;
        }

        return $aResult;
    }

    public function UnshiftAction($aAction)
    {
        $sKey = key($aAction);
        $aActions = $this->Actions;
        if (isset($aActions[$sKey])) {
            unset($aActions[$sKey]);
        }

        $aActions = \array_merge($aAction, $aActions);
        $this->Actions = $aActions;
    }

    public function AddAction($aAction)
    {
        $sKey = key($aAction);
        $aActions = $this->Actions;
        $aActions[$sKey] = $aAction[$sKey];
        $this->Actions = $aActions;
    }

    public function GetActionUrl($sAction)
    {
        $bResult = false;
        $aActions = $this->Actions;
        if (isset($aActions[$sAction]) && isset($aActions[$sAction]['url'])) {
            $bResult = $aActions[$sAction]['url'];
        }

        return $bResult;
    }

    public function GetActions()
    {
        return $this->Actions;
    }
}
