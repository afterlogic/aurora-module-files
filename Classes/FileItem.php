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
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
 *
 * @package Classes
 * @subpackage FileStorage
 */
class FileItem  extends \Aurora\System\AbstractContainer
{
	public function __construct()
	{
		parent::__construct(get_class($this));

		$this->SetDefaults(array(
			'Id' => '',
			'TypeStr' => \Aurora\System\Enums\FileStorageType::Personal,
			'Path' => '',
			'FullPath' => '',
			'Name' => '',
			'Size' => 0,
			'IsFolder' => false,
			'IsLink' => false,
			'LinkType' => '',
			'LinkUrl' => '',
			'LastModified' => 0,
			'ContentType' => '',
			'Thumb' => false,
			'ThumbnailUrl' => '',
			'OembedHtml' => '',
			'Published' => false,
			'Owner' => '',
			'Content' => '',
			'IsExternal' => false,
			'RealPath' => '',
			'Actions' => array(),
			'ETag' => '',
			'ExtendedProps' => array(),
			'Shared' => false,
			'GroupId' => null,
			'Initiator' => null
		));
	}

	/**
	 *
	 * @param string $sPublicHash
	 * @return type
	 */
	public function getHash($sPublicHash = null)
	{
		$aResult = array(
			'UserId' => \Aurora\System\Api::getAuthenticatedUserId(),
			'Id' => $this->Id,
			'Type' => $this->TypeStr,
			'Path' => $this->Path,
			'Name' => $this->Id,
			'FileName' => $this->Name,
			'Shared' => $this->Shared,
			'GroupId' => $this->GroupId
		);

		if (isset($sPublicHash))
		{
			$aResult['PublicHash'] = $sPublicHash;
		}

		return \Aurora\System\Api::EncodeKeyValues($aResult);
	}

	/**
	 * @return array
	 */
	public function getMap()
	{
		return self::getStaticMap();
	}

	/**
	 * @return array
	 */
	public static function getStaticMap()
	{
		return array(
			'Id' => array('string'),
			'Type' => array('int'),
			'TypeStr' => array('string'),
			'FullPath' => array('string'),
			'Path' => array('string'),
			'Name' => array('string'),
			'Size' => array('int'),
			'IsFolder' => array('bool'),
			'IsLink' => array('bool'),
			'LinkType' => array('string'),
			'LinkUrl' => array('string'),
			'LastModified' => array('int'),
			'ContentType' => array('string'),
			'Thumb' => array('bool'),
			'ThumbnailUrl' => array('string'),
			'OembedHtml' => array('string'),
			'Published' => array('bool'),
			'Owner' => array('string'),
			'Content' => array('string'),
			'IsExternal' => array('bool'),
			'RealPath' => array('string'),
			'Actions' => array('array'),
			'Hash' => array('string'),
			'ETag' => array('string'),
			'ExtendedProps' => array('array'),
			'Shared' => array('bool'),
			'GroupId' => array('int'),
			'Initiator' => array('string'),
		);
	}

	public function toResponseArray($aParameters = array())
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
		$aResult['GroupId'] = $this->GroupId;
		$aResult['Initiator'] = $this->Initiator;

		if ($this->Thumb)
		{
			if (empty($this->ThumbnailUrl) && $this->GetActionUrl('download'))
			{
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
		if (isset($aActions[$sKey]))
		{
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
		if (isset($aActions[$sAction]) && isset($aActions[$sAction]['url']))
		{
			$bResult = $aActions[$sAction]['url'];
		}

		return $bResult;
	}

	public function GetActions()
	{
		return $this->Actions;
	}
}
