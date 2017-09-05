<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files\Classes;

/**
 * FileItem class summary
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
class \Aurora\Modules\Files\Classes\FileItem  extends \Aurora\System\AbstractContainer
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
			'Shared' => false,
			'Owner' => '',
			'Content' => '',
			'IsExternal' => false,
			'RealPath' => '',
			'Actions' => array(),
			'ExtendedProps' => array()
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
			'Name' => $this->Name
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
			'Shared' => array('bool'),
			'Owner' => array('string'),		
			'Content' => array('string'),
			'IsExternal' => array('bool'),
			'RealPath' => array('string'),
			'Actions' => array('array'),
			'Hash' => array('string'),
			'ExtendedProps' => array('array')
		);
	}
	
	public function toResponseArray($aParameters = array())
	{
		$aResult = array(
			'Id' => $this->Id,
			'Type' => $this->TypeStr,
			'Path' => $this->Path,
			'FullPath' => $this->FullPath,
			'Name' => $this->Name,
			'Size' => $this->Size,
			'IsFolder' => $this->IsFolder,
			'IsLink' => $this->IsLink,
			'LinkType' => $this->LinkType,
			'LinkUrl' => $this->LinkUrl,
			'LastModified' => $this->LastModified,
			'ContentType' => $this->ContentType,
			'OembedHtml' => $this->OembedHtml,
			'Shared' => $this->Shared,
			'Owner' => $this->Owner,
			'Content' => $this->Content,
			'IsExternal' => $this->IsExternal,
			'Actions' => $this->Actions,
			'Hash' => $this->getHash(),
			'ExtendedProps' => $this->ExtendedProps
		);		
		
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
}
