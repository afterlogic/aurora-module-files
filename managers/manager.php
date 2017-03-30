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
 */

/**
 * CApiFilestorageManager class summary
 * 
 * @package Filestorage
 */
class CApiFilesManager extends \Aurora\System\Managers\AbstractManagerWithStorage
{
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\Managers\GlobalManager &$oManager, $sForcedStorage = '', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct('', $oManager, $sForcedStorage, $oModule);
	}
	
	/**
	* Returns Min module decorator.
	* 
	* @return \CApiModuleDecorator
	*/
	public function getMinModuleDecorator()
	{
		static $oMinModuleDecorator = null;
		if ($oMinModuleDecorator === null)
		{
			$oMinModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Min');
		}
		
		return $oMinModuleDecorator;
	}	
	
	/**
	 * Checks if file exists. 
	 * 
	 * @param int $iUserId Account object. 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sName Filename. 
	 * 
	 * @return bool
	 */
	public function isFileExists($iUserId, $iType, $sPath, $sName)
	{
		return $this->oStorage->isFileExists($iUserId, $iType, $sPath, $sName);
	}

	/**
	 * Allows for reading contents of the shared file. [Aurora only.](http://dev.afterlogic.com/aurora)
	 * 
	 * @param int $iUserId
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sName Filename. 
	 * 
	 * @return resource|bool
	 */
	public function getSharedFile($iUserId, $iType, $sPath, $sName)
	{
		return $this->oStorage->getSharedFile($iUserId, $iType, $sPath, $sName);
	}

	/**
	 * Retrieves array of metadata on the specific file. 
	 * 
	 * @param int $iUserId Account object 
	 * @param string $sType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder.
	 * @param string $sName Filename. 
	 * 
	 * @return CFileStorageItem
	 */
	public function getFileInfo($iUserId, $sType, $sPath, $sName)
	{
		$oResult = null;
		if ($this->oStorage->init($iUserId))
		{
			$oDirectory = $this->oStorage->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null)
			{
				$oItem = $oDirectory->getChild($sName);
				if ($oItem !== null)
				{
					$oResult = $this->oStorage->getFileInfo($iUserId, $sType, $oItem);
				}
			}
		}
		return $oResult;
	}

	/**
	 * Retrieves array of metadata on the specific directory. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder. 
	 * 
	 * @return CFileStorageItem
	 */
	public function getDirectoryInfo($iUserId, $iType, $sPath)
	{
		return $this->oStorage->getDirectoryInfo($iUserId, $iType, $sPath);
	}

	/**
	 * Allows for reading contents of the file. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sName Filename. 
	 * 
	 * @return resource|bool
	 */
	public function getFile($iUserId, $iType, $sPath, $sName, $iOffset = 0, $iChunkSize = 0)
	{
		return $this->oStorage->getFile($iUserId, $iType, $sPath, $sName, $iOffset, $iChunkSize);
	}

	/**
	 * Creates public link for specific file or folder. 
	 * 
	 * @param int $iUserId
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder. 
	 * @param string $sName Filename. 
	 * @param string $sSize Size information, it will be displayed when recipient opens the link. 
	 * @param string $bIsFolder If **true**, it is assumed the link is created for a folder, **false** otherwise. 
	 * 
	 * @return string|bool
	 */
	public function createPublicLink($iUserId, $iType, $sPath, $sName, $sSize, $bIsFolder)
	{
		return $this->oStorage->createPublicLink($iUserId, $iType, $sPath, $sName, $sSize, $bIsFolder);
	}
	
	/**
	 * Removes public link created for specific file or folder. 
	 * 
	 * @param int $iUserId
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder. 
	 * @param string $sName Filename. 
	 * 
	 * @return bool
	 */
	public function deletePublicLink($iUserId, $iType, $sPath, $sName)
	{
		return $this->oStorage->deletePublicLink($iUserId, $iType, $sPath, $sName);
	}

	/**
	 * Performs search for files. 
	 * 
	 * @param int $iUserId 
	 * @param string $sType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder. 
	 * @param string $sPattern Search string. 
	 * 
	 * @return array|bool array of \CFileStorageItem. 
	 */
	public function getFiles($iUserId, $sType, $sPath, $sPattern = '')
	{
		return $this->oStorage->getFiles($iUserId, $sType, $sPath, $sPattern);
	}

	/**
	 * Creates a new folder. 
	 * 
	 * @param int $iUserId
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the parent folder, empty string means top-level folder is created. 
	 * @param string $sFolderName Folder name. 
	 * 
	 * @return bool
	 */
	public function createFolder($iUserId, $iType, $sPath, $sFolderName)
	{
		return $this->oStorage->createFolder($iUserId, $iType, $sPath, $sFolderName);
	}
	
	/**
	 * Creates a new file. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is created in the root folder. 
	 * @param string $sFileName Filename. 
	 * @param $mData Data to be stored in the file. 
	 * @param bool $bOverride If **true**, existing file with that name will be overwritten. 
	 * 
	 * @return bool
	 */
	public function createFile($iUserId, $iType, $sPath, $sFileName, $mData, $bOverride = true, $rangeType = 0, $offset = 0, $extendedProps = [])
	{
		if (!$bOverride)
		{
			$sFileName = $this->oStorage->getNonExistentFileName($iUserId, $iType, $sPath, $sFileName);
		}
		return $this->oStorage->createFile($iUserId, $iType, $sPath, $sFileName, $mData, $rangeType, $offset, $extendedProps);
	}
	
	/**
	 * Creates a link to arbitrary online content. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the link. 
	 * @param string $sLink URL of the item to be linked. 
	 * @param string $sName Name of the link. 
	 * 
	 * @return bool
	 */
	public function createLink($iUserId, $iType, $sPath, $sLink, $sName)
	{
		return $this->oStorage->createLink($iUserId, $iType, $sPath, $sLink, $sName);
	}	
	
	/**
	 * Removes file or folder. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sName Filename. 
	 * 
	 * @return bool
	 */
	public function delete($iUserId, $iType, $sPath, $sName)
	{
		$bResult = $this->oStorage->delete($iUserId, $iType, $sPath, $sName);
		if ($bResult)
		{
			$this->getMinModuleDecorator()->DeleteMinByID(
					$this->oStorage->generateHashId($iUserId, $iType, $sPath, $sName)
			);
		}
		
		return $bResult;
	}

	/**
	 * 
	 * @param int $iUserId
	 * @param int $iType
	 * @param string $sPath
	 * @param string $sNewName
	 * @param int $iSize
	 * 
	 * @return array
	 */
	private function generateMinArray($iUserId, $iType, $sPath, $sNewName, $iSize)
	{
		$aData = null;
		if ($iUserId)
		{
			$aData = array(
				'AccountType' => 'wm',
				'Account' => 0,
				'Type' => $iType,
				'Path' => $sPath,
				'Name' => $sNewName,
				'Size' => $iSize
			);
		}

		return $aData;
	}
	
	/**
	 * Renames file or folder. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sName Name of file or folder. 
	 * @param string $sNewName New name. 
	 * @param bool $bIsLink
	 * 
	 * @return bool
	 */
	public function rename($iUserId, $iType, $sPath, $sName, $sNewName, $bIsLink)
	{
		$bResult = /*$bIsLink ? $this->oStorage->renameLink($iUserId, $iType, $sPath, $sName, $sNewName) : */$this->oStorage->rename($iUserId, $iType, $sPath, $sName, $sNewName);
		if ($bResult)
		{
			$sID = $this->oStorage->generateHashId($iUserId, $iType, $sPath, $sName);
			$sNewID = $this->oStorage->generateHashId($iUserId, $iType, $sPath, $sNewName);

			$mData = $this->getMinModuleDecorator()->GetMinByID($sID);
			
			if ($mData && $iUserId)
			{
				$aData = $this->generateMinArray($iUserId, $iType, $sPath, $sNewName, $mData['Size']);
				if ($aData)
				{
					$this->getMinModuleDecorator()->UpdateMinByID($sID, $aData, $sNewID);
				}
			}
		}
		return $bResult;
	}
	
	/**
	 * Move file or folder to a different location. In terms of Aurora, item can be moved to a different storage as well. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iFromType Source storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param int $iToType Destination storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sFromPath Path to the folder which contains the item. 
	 * @param string $sToPath Destination path of the item. 
	 * @param string $sName Current name of file or folder. 
	 * @param string $sNewName New name of the item. 
	 * 
	 * @return bool
	 */
	public function move($iUserId, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName)
	{
		$GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = true;
		$bResult = $this->oStorage->copy($iUserId, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName, true);
		$GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = false;
		if ($bResult)
		{
			$sID = $this->oStorage->generateHashId($iUserId, $iFromType, $sFromPath, $sName);
			$sNewID = $this->oStorage->generateHashId($iUserId, $iToType, $sToPath, $sNewName);

			$mData = $this->getMinModuleDecorator()->GetMinByID($sID);
			if ($mData)
			{
				$aData = $this->generateMinArray($iUserId, $iToType, $sToPath, $sNewName, $mData['Size']);
				if ($aData)
				{
					$this->getMinModuleDecorator()->UpdateMinByID($sID, $aData, $sNewID);
				}
			}
		}
		return $bResult;
	}

	/**
	 * Copies file or folder, optionally renames it. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iFromType Source storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param int $iToType Destination storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sFromPath Path to the folder which contains the item. 
	 * @param string $sToPath Destination path of the item.
	 * @param string $sName Current name of file or folder. 
	 * @param string $sNewName New name of the item. 
	 * 
	 * @return bool
	 */
	public function copy($iUserId, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName = null)
	{
		return $this->oStorage->copy($iUserId, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName);
	}

	/**
	 * Returns space used by the user in specified storages, in bytes.
	 * 
	 * @param int $iUserId User identifier.
	 * @param string $aTypes Storage type list. Accepted values in array: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**.
	 * 
	 * @return int;
	 */
	public function getUserSpaceUsed($iUserId, $aTypes = array(EFileStorageTypeStr::Personal))
	{
		return $this->oStorage->getUserSpaceUsed($iUserId, $aTypes);
	}
	
	/**
	 * Allows for obtaining filename which doesn't exist in current directory. For example, if you need to store **data.txt** file but it already exists, this method will return **data_1.txt**, or **data_2.txt** if that one already exists, and so on. 
	 * 
	 * @param int $iUserId Account object 
	 * @param int $iType Storage type. Accepted values: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**. 
	 * @param string $sPath Path to the folder which contains the file, empty string means the file is in the root folder. 
	 * @param string $sFileName Filename. 
	 * 
	 * @return string
	 */
	public function getNonExistentFileName($iUserId, $iType, $sPath, $sFileName)
	{
		return $this->oStorage->getNonExistentFileName($iUserId, $iType, $sPath, $sFileName);
	}	
	
	/**
	 * 
	 * @param int $iUserId
	 */
	public function ClearPrivateFiles($iUserId)
	{
		$this->oStorage->clearPrivateFiles($iUserId);
	}

	/**
	 * 
	 * @param int $iUserId
	 */
	public function ClearCorporateFiles($iUserId)
	{
		$this->oStorage->clearPrivateFiles($iUserId);
	}

	/**
	 * 
	 * @param int $iUserId
	 */
	public function ClearFiles($iUserId)
	{
		$this->ClearPrivateFiles($iUserId);
		$this->ClearCorporateFiles($iUserId);
	}
}
