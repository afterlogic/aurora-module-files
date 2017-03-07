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
 * @internal
 * 
 * @package Filestorage
 * @subpackage Storages
 */
class CApiFilesStorage extends \Aurora\System\Managers\AbstractManagerStorage
{
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct($sStorageName, \Aurora\System\Managers\AbstractManager &$oManager)
	{
		parent::__construct('filestorage', $sStorageName, $oManager);
	}
	
	/**
	 * @param CAccount $oAccount
	 */
	public function init($oAccount)
	{
	
	}
	
	public function isFileExists($oAccount, $iType, $sPath, $sName)
	{
		return false;
	}
	
	public function getFileInfo($iUserId, $sType, $oItem)
	{
	
	}
	
	public function getDirectoryInfo($oAccount, $iType, $sPath)
	{
		
	}
	
	public function getFile($oAccount, $iType, $sPath, $sName)
	{

	}

	public function getFiles($oAccount, $iType, $sPath, $sPattern)
	{
		
	}
	
	public function createFolder($oAccount, $iType, $sPath, $sFolderName)
	{

	}
	
	public function createFile($oAccount, $iType, $sPath, $sFileName, $sData)
	{

	}
	
	public function createLink($oAccount, $iType, $sPath, $sLink, $sName)
	{
	
		
	}	
	
	public function delete($oAccount, $iType, $sPath, $sName)
	{

	}

	public function rename($oAccount, $iType, $sPath, $sName, $sNewName)
	{

	}
	
	public function copy($oAccount, $iFromType, $iToType, $sFromPath, $sToPath, $sName, $sNewName)
	{
		
	}

	public function getNonExistentFileName($oAccount, $iType, $sPath, $sFileName)
	{
	
	}	
	
	public function clearPrivateFiles($oAccount)
	{
		
	}

	public function clearCorporateFiles($oAccount)
	{
		
	}
}
