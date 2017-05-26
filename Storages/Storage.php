<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files\Storages;

class Storage extends \Aurora\System\Managers\AbstractManagerStorage
{
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct($sStorageName, \Aurora\System\Managers\AbstractManager &$oManager)
	{
		parent::__construct($oManager);
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
	
	public function createFile($iUserId, $sType, $sPath, $sFileName, $sData, $rangeType, $offset)
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
