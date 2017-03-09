<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * CFileStorageItem class summary
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
 * @property bool $Iframed
 * @property string $ThumbnailLink
 * @property string $OembedHtml
 * @property bool $Shared
 * @property string $Owner
 * @property string $Content
 * @property bool $IsExternal
 * @property string $RealPath
 * @property string $MainAction
 * @property array $Actions
 * @property string $Hash
 * 
 * @package Classes
 * @subpackage FileStorage
 */
class CFileStorageItem  extends \Aurora\System\AbstractContainer
{
	public function __construct()
	{
		parent::__construct(get_class($this));

		$this->SetDefaults(array(
			'Id' => '',
			'Type' => \EFileStorageType::Personal,
			'TypeStr' => \EFileStorageTypeStr::Personal,
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
			'Iframed' => false,
			'ThumbnailLink' => '',
			'OembedHtml' => '',
			'Shared' => false,
			'Owner' => '',
			'Content' => '',
			'IsExternal' => false,
			'RealPath' => '',
			'MainAction' => '',
			'Actions' => array()
		));
	}
	
	public function getHash()
	{
		return \Aurora\System\Api::EncodeKeyValues(array(
			'UserId' => \Aurora\System\Api::getAuthenticatedUserId(), 
			'Type' => $this->TypeStr,
			'Path' => $this->Path,
			'Name' => $this->Name
		));		
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
			'Iframed' => array('bool'),
			'ThumbnailLink' => array('string'),
			'OembedHtml' => array('string'),
			'Shared' => array('bool'),
			'Owner' => array('string'),		
			'Content' => array('string'),
			'IsExternal' => array('bool'),
			'RealPath' => array('string'),
			'MainAction' => array('string'),
			'Actions' => array('array'),
			'Hash' => array('string')
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
			'Iframed' => $this->Iframed,
			'Thumb' => $this->Thumb,
			'ThumbnailLink' => $this->ThumbnailLink,
			'OembedHtml' => $this->OembedHtml,
			'Shared' => $this->Shared,
			'Owner' => $this->Owner,
			'Content' => $this->Content,
			'IsExternal' => $this->IsExternal,
			'MainAction' => $this->MainAction,
			'Actions' => $this->Actions
		);		
		
		if ($this->Thumb)
		{
			$aResult['ThumbnailUrl'] = '?download-file/' . $this->getHash() .'/thumb';
		}
		
		return $aResult;
	}
	
	public function UnshiftAction($sAction)
	{
		$aActions = array_diff($this->Actions, array($sAction));
		array_unshift($aActions, $sAction);
		$this->Actions = $aActions;
	}
	
	public function AddAction($aAction)
	{
		$sKey = key($aAction);
		$aActions = $this->Actions;
		$aActions[$sKey] = $aAction[$sKey];
		$this->Actions = $aActions;
	}
}
