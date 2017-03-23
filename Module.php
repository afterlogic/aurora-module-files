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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

namespace Aurora\Modules\Files;

class Module extends \Aurora\System\Module\AbstractModule
{
	/* 
	 * @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager 
	 */	
	public $oApiFileCache = null;

	/**
	 *
	 * @var \CApiFilesManager
	 */
	public $oApiFilesManager = null;

	/**
	 *
	 * @var \CApiModuleDecorator
	 */
	protected $oMinModuleDecorator = null;

	/***** private functions *****/
	/**
	 * Initializes Files Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->incClass('item');
		$this->oApiFilesManager = $this->GetManager('', 'sabredav');
		$this->oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');
		
		$this->AddEntries(
			array(
				'upload' => 'UploadFileData',
				'download-file' => 'EntryDownloadFile'
			)
		);
		
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'onAfterGetFileInfo'));
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'));
		$this->subscribeEvent('Files::CreateFile', array($this, 'onCreateFile'));
		$this->subscribeEvent('Files::GetLinkType', array($this, 'onGetLinkType'));
		$this->subscribeEvent('Files::CheckUrl', array($this, 'onCheckUrl'));

		$this->subscribeEvent('Files::Rename::after', array($this, 'onAfterRename'));
		$this->subscribeEvent('Files::Delete::after', array($this, 'onAfterDelete'));

		$this->subscribeEvent('Files::PopulateFileItem', array($this, 'onPopulateFileItem'));

		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
	}
	
	/**
	* Returns Min module decorator.
	* 
	* @return \CApiModuleDecorator
	*/
	private function getMinModuleDecorator()
	{
		return $this->oApiFilesManager->getMinModuleDecorator();
	}
	
	/**
	 * Checks if storage type is personal or corporate.
	 * 
	 * @param string $Type Storage type.
	 * @return bool
	 */
	protected function checkStorageType($Type)
	{
		return in_array($Type, array('personal', 'corporate'));
	}	
	
	/**
	 * Downloads file, views file or makes thumbnail for file.
	 * 
	 * @param int $iUserId User identifier.
	 * @param string $sType Storage type - personal, corporate.
	 * @param string $sPath Path to folder contained file.
	 * @param string $sFileName File name.
	 * @param string $SharedHash Indicates if file should be downloaded or viewed.
	 * @param string $sAction Indicates if thumbnail should be created for file.
	 * 
	 * @return bool
	 */
	public function getRawFile($iUserId, $sType, $sPath, $sFileName, $SharedHash = null, $sAction = '')
	{
		$bDownload = true;
		$bThumbnail = false;
		
		switch ($sAction)
		{
			case 'view':
				$bDownload = false;
				$bThumbnail = false;
			break;
			case 'thumb':
				$bDownload = false;
				$bThumbnail = true;
			break;
			default:
				$bDownload = true;
				$bThumbnail = false;
			break;
		}
		
		$oModuleDecorator = $this->getMinModuleDecorator();
		$mMin = ($oModuleDecorator && $SharedHash !== null) ? $oModuleDecorator->GetMinByHash($SharedHash) : array();
		
		$iUserId = (!empty($mMin['__hash__'])) ? $mMin['UserId'] : $iUserId;

		try
		{
			if ($iUserId && $SharedHash !== null)
			{
				\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
			}
			else 
			{
				\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
				if ($iUserId !== \Aurora\System\Api::getAuthenticatedUserId())
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
				}
			}
		}
		catch (\Aurora\System\Exceptions\ApiException $oEx)
		{
			header('Location: ./');
		}
		
		if ($this->oApiCapabilityManager->isFilesSupported($iUserId) && 
			isset($sType, $sPath, $sFileName)) 
		{
			$sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
			
			$aArgs = array(
				'UserId' => $iUserId,
				'Type' => $sType,
				'Path' => $sPath,
				'Name' => &$sFileName,
				'IsThumb' => $bThumbnail
			);
			$mResult = false;
			$this->broadcastEvent(
				'GetFile', 
				$aArgs,
				$mResult
			);			
			
			if (false !== $mResult) 
			{
				if (is_resource($mResult)) 
				{
					$sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);
					\Aurora\System\Managers\Response::OutputHeaders($bDownload, $sContentType, $sFileName);
			
					if ($bThumbnail) 
					{
//						$this->cacheByKey($sRawKey);	// todo
						return \Aurora\System\Managers\Response::GetThumbResource($iUserId, $mResult, $sFileName);
					} 
					else if ($sContentType === 'text/html') 
					{
						echo(\MailSo\Base\HtmlUtils::ClearHtmlSimple(stream_get_contents($mResult)));
					} 
					else if ($sContentType === 'text/plain') 
					{
						echo(stream_get_contents($mResult));
					} 
					else 
					{
						\MailSo\Base\Utils::FpassthruWithTimeLimitReset($mResult);
					}
					
					@fclose($mResult);
				}
			}
		}
	}
	
	/**
	 * Returns html title for specified URL.
	 * @param string $sUrl
	 * @return string
	 */
	protected function getHtmlTitle($sUrl)
	{
		$oCurl = curl_init();
		\curl_setopt_array($oCurl, array(
			CURLOPT_URL => $sUrl,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING => '',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_SSL_VERIFYPEER => false, //required for https urls
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_MAXREDIRS => 5
		));
		$sContent = curl_exec($oCurl);
		//$aInfo = curl_getinfo($oCurl);
		curl_close($oCurl);

		preg_match('/<title>(.*?)<\/title>/s', $sContent, $aTitle);
		return isset($aTitle['1']) ? trim($aTitle['1']) : '';
	}
	
	public function getUUIDById($UserId)
	{
		if (is_numeric($UserId))
		{
			$oManagerApi = \Aurora\System\Api::GetSystemManager('eav', 'db');
			$oEntity = $oManagerApi->getEntity((int) \Aurora\System\Api::getAuthenticatedUserId());
			if ($oEntity instanceof \Aurora\System\EAV\Entity)
			{
				$UserId = $oEntity->UUID;
			}
		}
		
		return $UserId;
	}
	
	/**
	 * Returns file contents.
	 * 
	 * @ignore
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage.
	 * @param string $Path Path to folder files are obtained from.
	 * @param string $Name Name of file.
	 * @param bool $IsThumb Inticates if thumb is required.
	 * @param string|resource|bool $Result Is passed by reference.
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function onGetFile($aArgs, &$Result)
	{
		if ($this->checkStorageType($aArgs['Type']))
		{
			$sUUID = $this->getUUIDById($aArgs['UserId']);
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}
			
			$Result = $this->oApiFilesManager->getFile($sUUID, $aArgs['Type'], $aArgs['Path'], $aArgs['Name']);
		}
	}	
	
	/**
	 * Create file.
	 * 
	 * @ignore
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage.
	 * @param string $Path Path to folder files are obtained from.
	 * @param string $Name Name of file.
	 * @param string|resource $Data Data to be stored in the file.
	 * @param string|resource|bool $Result Is passed by reference.
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function onCreateFile($aArgs, &$Result)
	{
		if ($this->checkStorageType($aArgs['Type']))
		{
			$Result = $this->oApiFilesManager->createFile(
				$this->getUUIDById($aArgs['UserId']), 
				$aArgs['Type'], 
				$aArgs['Path'], 
				$aArgs['Name'], 
				$aArgs['Data'], 
				false,
				$aArgs['RangeType'], 
				$aArgs['Offset']
			);
		}
	}
	
	/**
	 * @ignore
	 * @param string $Link
	 * @param string $Result
	 */
	public function onGetLinkType($Link, &$Result)
	{
		$Result = '';
	}	
	
	/**
	 * @ignore
	 * @param string $sUrl
	 * @param mixed $mResult
	 */
	public function onCheckUrl($aArgs, &$mResult)
	{
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();

		if ($iUserId)
		{
			if (!empty($aArgs['Url']))
			{
//				$sUrl = \Aurora\System\Utils::GetRemoteFileRealUrl($aArgs['Url']);
				$sUrl = $aArgs['Url'];
				if ($sUrl)
				{
					$aRemoteFileInfo = \Aurora\System\Utils::GetRemoteFileInfo($sUrl);
					$sFileName = basename($sUrl);
					$sFileExtension = \Aurora\System\Utils::GetFileExtension($sFileName);

					if (empty($sFileExtension))
					{
						$sFileExtension = \Aurora\System\Utils::GetFileExtensionFromMimeContentType($aRemoteFileInfo['content-type']);
						$sFileName .= '.'.$sFileExtension;
					}

					if ($sFileExtension === 'htm' || $sFileExtension === 'html')
					{
						$sTitle = $this->getHtmlTitle($sUrl);
					}

					$mResult['Name'] = isset($sTitle) && strlen($sTitle)> 0 ? $sTitle : urldecode($sFileName);
					$mResult['Size'] = $aRemoteFileInfo['size'];
				}
			}
		}		
	}
	
	/**
	 * @ignore
	 * @param \CFileStorageItem $oItem
	 * @return bool
	 */
	public function onPopulateFileItem($aArgs, &$oItem)
	{
		if ($oItem->IsLink)
		{
			$sFileName = basename($oItem->LinkUrl);
			$sFileExtension = \Aurora\System\Utils::GetFileExtension($sFileName);
			if ($sFileExtension === 'htm' || $sFileExtension === 'html')
			{
				$oItem->Name = $this->getHtmlTitle($oItem->LinkUrl);
				return true;
			}
		}
	}	
	
	/**
	 * @ignore
	 * @param int $iUserId
	 */
	public function onAfterDeleteUser($aArgs, $iUserId)
	{
		$this->oApiFilesManager->ClearFiles($iUserId);
	}
	/***** private functions *****/
	
	/***** public functions *****/
	/**
	 * Uploads file from client side.
	 * 
	 * @return string "true" or "false"
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UploadFileData()
	{
		$mResult = false;
		$aPaths = \Aurora\System\Application::GetPaths();
		if (isset($aPaths[1]) && strtolower($aPaths[1]) === strtolower($this->GetName()))
		{
			$sType = isset($aPaths[2]) ? strtolower($aPaths[2]) : 'personal';
			$rData = fopen("php://input", "r");
			$aFilePath = array_slice($aPaths, 3);
			$sFilePath = urldecode(implode('/', $aFilePath));
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId(
				$this->oHttp->GetHeader('Auth-Token')
			);
			$oUser = \Aurora\System\Api::getAuthenticatedUser($iUserId);
			if ($oUser)
			{
				if ($rData)
				{
					$aArgs = array(
						'UserId' => $oUser->UUID,
						'Type' => $sType,
						'Path' => dirname($sFilePath),
						'Name' => basename($sFilePath),
						'Data' => $rData
					);
					$this->broadcastEvent(
						'CreateFile', 
						$aArgs,
						$mResult
					);			
				}
				else 
				{
					$mResult = false;
				}
			}
			else
			{
				$mResult = false;
			}
		}
		if ($mResult)
		{
			echo 'true';
		}
		else 
		{
			echo 'false';
		}
	}
	
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * @apiDefine Files Files Module
	 * Main Files module. It provides PHP and Web APIs for managing files.
	 */
	
	/**
	 * @api {post} ?/Api/ GetSettings
	 * @apiName GetSettings
	 * @apiGroup Files
	 * @apiDescription Obtains list of module settings for authenticated user.
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetSettings} Method Method name
	 * @apiParam {string} [AuthToken] Auth token
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetSettings',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
	 * @apiSuccess {bool} Result.Result.EnableModule=false Indicates if Files module is enabled.
	 * @apiSuccess {bool} Result.Result.EnableUploadSizeLimit=false Indicates if upload size limit is enabled.
	 * @apiSuccess {int} Result.Result.UploadSizeLimitMb=0 Value of upload size limit in Mb.
	 * @apiSuccess {bool} Result.Result.EnableCorporate=false Indicates if corporate storage is enabled.
	 * @apiSuccess {int} Result.Result.UserSpaceLimitMb=0 Value of user space limit in Mb.
	 * @apiSuccess {string} Result.Result.CustomTabTitle=&quot;&quot; Custom tab title.
	 * @apiSuccess {string} [Result.Result.PublicHash=&quot;&quot;] Public hash.
	 * @apiSuccess {string} [Result.Result.PublicFolderName=&quot;&quot;] Public folder name.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetSettings',
	 *	Result: { EnableModule: true, EnableUploadSizeLimit: true, UploadSizeLimitMb: 5, EnableCorporate: true, UserSpaceLimitMb: 100, CustomTabTitle: "", PublicHash: "", PublicFolderName: "" }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aAppData = array(
			'EnableModule' => true,
			'EnableUploadSizeLimit' => $this->getConfig('EnableUploadSizeLimit', false),
			'UploadSizeLimitMb' => $this->getConfig('EnableUploadSizeLimit', false) ? $this->getConfig('UploadSizeLimitMb', 0) : 0,
			'EnableCorporate' => $this->getConfig('EnableCorporate', false),
			'UserSpaceLimitMb' => $this->getConfig('UserSpaceLimitMb', 0),
			'CustomTabTitle' => $this->getConfig('CustomTabTitle', '')
		);
		$sPublicHash = \Aurora\System\Application::GetPathItemByIndex(1);
		if (isset($sPublicHash))
		{
			$aAppData['PublicHash'] = $sPublicHash;
			$oModuleDecorator = $this->getMinModuleDecorator();
			$mMin = ($oModuleDecorator && $sPublicHash !== null) ? $oModuleDecorator->GetMinByHash($sPublicHash) : array();
			if (isset($mMin['__hash__']) && $mMin['IsFolder'])
			{
				$aAppData['PublicFolderName'] = $mMin['Name'];
			}
		}
		return $aAppData;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateSettings
	 * @apiName UpdateSettings
	 * @apiGroup Files
	 * @apiDescription Updates module's settings - saves them to config.json file.
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=UpdateSettings} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **EnableUploadSizeLimit** *bool* Enable file upload size limit setting.<br>
	 * &emsp; **UploadSizeLimitMb** *int* Upload file size limit setting in Mb.<br>
	 * &emsp; **UserSpaceLimitMb** *int* User space limit setting in Mb.<br>
	 * &emsp; **EnableCorporate** *bool* Enable corporate storage in Files.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ EnableUploadSizeLimit: true, UploadSizeLimitMb: 5, EnableCorporate: true, UserSpaceLimitMb: 10 }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if settings were updated successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates module's settings - saves them to config.json file.
	 * 
	 * @param bool $EnableUploadSizeLimit Enable file upload size limit setting.
	 * @param int $UploadSizeLimitMb Upload file size limit setting in Mb.
	 * @param bool $EnableCorporate Enable corporate storage in Files.
	 * @param int $UserSpaceLimitMb User space limit setting in Mb.
	 * @return bool
	 */
	public function UpdateSettings($EnableUploadSizeLimit, $UploadSizeLimitMb, $EnableCorporate, $UserSpaceLimitMb)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$this->setConfig('EnableUploadSizeLimit', $EnableUploadSizeLimit);
		$this->setConfig('UploadSizeLimitMb', $UploadSizeLimitMb);
		$this->setConfig('EnableCorporate', $EnableCorporate);
		$this->setConfig('UserSpaceLimitMb', $UserSpaceLimitMb);
		$this->saveModuleConfig();
		return true;
	}
	
		/**
	 * @api {post} ?/Upload/ UploadFile
	 * @apiDescription Uploads file from client side.
	 * @apiName UploadFile
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=UploadFile} Method Method name
	 * @apiParam {string} AuthToken Authentication token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder than should contain uploaded file.<br>
	 * &emsp; **FileData** *string* Uploaded file information. Contains fields size, name, tmp_name.<br>
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result File object in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.Name Original file name.
	 * @apiSuccess {string} Result.Result.TempName Temporary file name.
	 * @apiSuccess {string} Result.Result.MimeType Mime type of file.
	 * @apiSuccess {int} Result.Result.Size File size.
	 * @apiSuccess {string} Result.Result.Hash Hash used for file download, file view or getting file thumbnail.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UploadFile',
	 *	Result: { File: { Name: 'image.png', TempName: 'upload-post-6149f2cda5c58c6951658cce9f2b1378', MimeType: 'image/png', Size: 1813 } }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UploadFile',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Uploads file from client side.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to folder than should contain uploaded file.
	 * @param array $UploadData Uploaded file information. Contains fields size, name, tmp_name.
	 * @return array {
	 *		*string* **Name** Original file name.
	 *		*string* **TempName** Temporary file name.
	 *		*string* **MimeType** Mime type of file.
	 *		*int* **Size** File size.
	 *		*string* **Hash** Hash used for file download, file view or getting file thumbnail.
	 * }
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UploadFile($UserId, $Type, $Path, $UploadData, $RangeType = 2, $Offset = 0)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		$oApiFileCacheManager = \Aurora\System\Api::GetSystemManager('Filecache');

		$sError = '';
		$aResponse = array();

		if ($sUUID)
		{
			if (is_array($UploadData))
			{
				$iSize = (int) $UploadData['size'];
				if ($Type === \EFileStorageTypeStr::Personal)
				{
					$aQuota = $this->GetQuota($sUUID);
					if ($aQuota['Limit'] > 0 && $aQuota['Used'] + $iSize > $aQuota['Limit'])
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileQuota);
					}
				}
				
				$sUploadName = $UploadData['name'];
				$sMimeType = \MailSo\Base\Utils::MimeContentType($sUploadName);

				$sSavedName = 'upload-post-'.md5($UploadData['name'].$UploadData['tmp_name']);
				$rData = false;
				if (\is_resource($UploadData['tmp_name']))
				{
					$rData = $UploadData['tmp_name'];
				}
				else if ($oApiFileCacheManager->moveUploadedFile($sUUID, $sSavedName, $UploadData['tmp_name']))
				{
					$rData = $oApiFileCacheManager->getFile($sUUID, $sSavedName);
				}
				if ($rData)
				{
					$aArgs = array(
						'UserId' => $sUUID,
						'Type' => $Type,
						'Path' => $Path,
						'Name' => $sUploadName,
						'Data' => $rData,
						'RangeType' => $RangeType,
						'Offset' => $Offset
						
					);
					$this->broadcastEvent(
						'CreateFile', 
						$aArgs,
						$mResult
					);			
					
					$aResponse['File'] = array(
						'Name' => $sUploadName,
						'TempName' => $sSavedName,
						'MimeType' => $sMimeType,
						'Size' =>  (int) $iSize
					);
				}
			}
		}
		else
		{
			$sError = 'auth';
		}

		if (0 < strlen($sError))
		{
			$aResponse['Error'] = $sError;
		}
		
		return $aResponse;
	}

	/**
	 * @api {post} ?/Api/ DownloadFile
	 * @apiDescription Downloads file.
	 * @apiName DownloadFile
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=DownloadFile} Method Method name
	 * @apiParam {string} [AuthToken] Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash. *optional*<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'DownloadFile',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "image.png" }'
	 * }
	 * 
	 * @apiSuccess {string} Result Content of the file with headers for download.
	 */
	
	/**
	 * Downloads file.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @return bool
	 */
	public function EntryDownloadFile()
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		
		$sHash = (string) \Aurora\System\Application::GetPathItemByIndex(1, '');
		$sAction = (string) \Aurora\System\Application::GetPathItemByIndex(2, '');

		$aValues = \Aurora\System\Api::DecodeKeyValues($sHash);
		
		$iUserId = isset($aValues['UserId']) ? (int) $aValues['UserId'] : 0;
		$sType = isset($aValues['Type']) ? $aValues['Type'] : '';
		$sPath = isset($aValues['Path']) ? urldecode($aValues['Path']) : '';
		$sFileName = isset($aValues['Name']) ? urldecode($aValues['Name']) : '';
		$SharedHash = isset($aValues['SharedHash']) ? $aValues['SharedHash'] : null;

		$this->getRawFile($iUserId, $sType, $sPath, $sFileName, $SharedHash, $sAction);		
	}

	/**
	 * @api {post} ?/Api/ ViewFile
	 * @apiDescription Views file.
	 * @apiName ViewFile
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=ViewFile} Method Method name
	 * @apiParam {string} [AuthToken] Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'ViewFile',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "image.png" }'
	 * }
	 * 
	 * @apiSuccess {string} Result Content of the file with headers for view.
	 */
	
	/**
	 * Views file.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @param string $SharedHash Shared hash.
	 * @return bool
	 */
	public function ViewFile($UserId, $Type, $Path, $Name, $SharedHash)
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		$this->getRawFile(
			$this->getUUIDById($UserId), 
			$Type, 
			$Path, 
			$Name, 
			$SharedHash, 
			false
		);
	}

	/**
	 * @api {post} ?/Api/ GetFileThumbnail
	 * @apiDescription Makes thumbnail for file.
	 * @apiName GetFileThumbnail
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFileThumbnail} Method Method name
	 * @apiParam {string} [AuthToken] Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetFileThumbnail',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "image.png" }'
	 * }
	 * 
	 * @apiSuccess {string} Result Content of the file thumbnail with headers for view.
	 */
	
	/**
	 * Makes thumbnail for file.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @param string $SharedHash Shared hash.
	 * @return bool
	 */
	public function GetFileThumbnail($UserId, $Type, $Path, $Name, $SharedHash)
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		return \base64_encode(
			$this->getRawFile(
				$this->getUUIDById($UserId), 
				$Type, 
				$Path, 
				$Name, 
				$SharedHash, 
				false, 
				true
			)
		);
	}

	/**
	 * @api {post} ?/Api/ GetStorages
	 * @apiDescription Returns storages avaliable for logged in user.
	 * @apiName GetStorages
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetStorages} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetStorages',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result List of storages in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.Type Storage type - personal, corporate.
	 * @apiSuccess {string} Result.Result.DisplayName Storage display name.
	 * @apiSuccess {bool} Result.Result.IsExternal Indicates if storage external or not.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetStorages',
	 *	Result: [{ Type: "personal", DisplayName: "Personal", IsExternal: false }, { Type: "corporate", DisplayName: "Corporate", IsExternal: false }, { Type: "google", IsExternal: true, DisplayName: "GoogleDrive" }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetStorages',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns storages avaliable for logged in user.
	 * 
	 * @param int $UserId User identifier.
	 * @return array {
	 *		*string* **Type** Storage type - personal, corporate.
	 *		*string* **DisplayName** Storage display name.
	 *		*bool* **IsExternal** Indicates if storage external or not.
	 * }
	 */
	public function GetStorages($UserId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sUUID = $this->getUUIDById($UserId);
		$aStorages = [
			[
				'Type' => 'personal', 
				'DisplayName' => $this->i18N('LABEL_PERSONAL_STORAGE', $sUUID), 
				'IsExternal' => false
			]
		];
		if ($this->getConfig('EnableCorporate', false))
		{
			$aStorages[] = [
				'Type' => 'corporate', 
				'DisplayName' => $this->i18N('LABEL_CORPORATE_STORAGE', $sUUID), 
				'IsExternal' => false
			];
		}
		return $aStorages;
	}	
	
	/**
	 * @api {post} ?/Api/ GetQuota
	 * @apiDescription Returns used space and space limit for specified user.
	 * @apiName GetQuota
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetQuota} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* User identifier.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateAccount',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ UserId: 123 }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object in case of success, otherwise **false**.
	 * @apiSuccess {int} Result.Result.Used Amount of space used by user.
	 * @apiSuccess {int} Result.Result.Limit Limit of space for user.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetQuota',
	 *	Result: { Used: 21921, Limit: 62914560 }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetQuota',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns used space and space limit for specified user.
	 * 
	 * @param int $UserId User identifier.
	 * @return array {
	 *		*int* **Used** Amount of space used by user.
	 *		*int* **Limit** Limit of space for user.
	 * }
	 */
	public function GetQuota($UserId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sUUID = $this->getUUIDById($UserId);
		return array(
			'Used' => $this->oApiFilesManager->getUserSpaceUsed($sUUID, [\EFileStorageTypeStr::Personal]),
			'Limit' => $this->getConfig('UserSpaceLimitMb', 0) * 1024 * 1024
		);
	}

	/**
	 * @api {post} ?/Api/ GetFiles
	 * @apiDescription Returns file list and user quota information.
	 * @apiName GetFiles
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFiles} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage.<br>
	 * &emsp; **Path** *string* Path to folder files are obtained from.<br>
	 * &emsp; **Pattern** *string* String for search files and folders with such string in name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetFiles',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Pattern: "" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object in case of success, otherwise **false**.
	 * @apiSuccess {array} Result.Result.Items Array of files objects.
	 * @apiSuccess {array} Result.Result.Quota Array of items with fields Used, Limit.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetFiles',
	 *	Result: { Items: [{Id: "image.png", Type: "personal", Path: "", FullPath: "/image.png", Name: "image.png", Size: 1813, IsFolder: false, IsLink: false, LinkType: "", LinkUrl: "", LastModified: 1475498855, ContentType: "image/png", Iframed: false, Thumb: true, ThumbnailLink: "", OembedHtml: "", Shared: false, Owner: "", Content: "", IsExternal: false }], Quota: { Used: 21921, Limit: 62914560 } }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetFiles',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Returns file list and user quota information.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage.
	 * @param string $Path Path to folder files are obtained from.
	 * @param string $Pattern String for search files and folders with such string in name.
	 * @return array {
	 *		*array* **Items** Array of files objects.
	 *		*array* **Quota** Array of items with fields Used, Limit.
	 * }
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetFiles($UserId, $Type, $Path, $Pattern)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sUUID = $this->getUUIDById($UserId);
		if ($this->checkStorageType($Type))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			$aUsers = array();
			$aFiles = $this->oApiFilesManager->getFiles($sUUID, $Type, $Path, $Pattern);
			foreach ($aFiles as $oFile)
			{
				if (!isset($aUsers[$oFile->Owner]))
				{
					$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUser($oFile->Owner);
					$aUsers[$oFile->Owner] = $oUser ? $oUser->PublicId : '';
				}
				$oFile->Owner = $aUsers[$oFile->Owner];
			}

			return array(
				'Items' => $aFiles,
				'Quota' => $this->GetQuota($UserId)
			);
		}
	}
	
	/**
	 * Return information about file. Subscribers of "Files::GetFileInfo::after" event are used for collecting information.
	 * 
	 * @param int $UserId
	 * @param string $Type
	 * @param string $Path
	 * @param string $Name
	 */
	public function GetFileInfo($UserId, $Type, $Path, $Name) {}
	
	public function onAfterGetFileInfo($aArgs, &$mResult)
	{
		$sUUID = $this->getUUIDById($aArgs['UserId']);
		$mResult = $this->oApiFilesManager->getFileInfo($sUUID, $aArgs['Type'], $aArgs['Path'], $aArgs['Name']);
	}

	/**
	 * @api {post} ?/Api/ GetPublicFiles
	 * @apiDescription Returns list of public files.
	 * @apiName GetPublicFiles
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetPublicFiles} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Hash** *string* Hash to identify the list of files to return. Containes information about user identifier, type of storage, path to public folder, name of public folder.<br>
	 * &emsp; **Path** *string* Path to folder contained files to return.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetPublicFiles',
	 *	Parameters: '{ Hash: "hash_value", Path: "" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Object in case of success, otherwise **false**.
	 * @apiSuccess {array} Result.Result.Items Array of files objects.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetPublicFiles',
	 *	Result: { Items: [{ Id: "image.png", Type: "personal", Path: "/shared_folder", FullPath: "/shared_folder/image.png", Name: "image.png", Size: 43549, IsFolder: false, IsLink: false, LinkType: "", LinkUrl: "", LastModified: 1475500277, ContentType: "image/png", Iframed: false, Thumb: true, ThumbnailLink: "", OembedHtml: "", Shared: false, Owner: "62a6d548-892e-11e6-be21-0cc47a041d39", Content: "", IsExternal: false }] }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetPublicFiles',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */

	/**
	 * Returns list of public files.
	 * 
	 * @param string $Hash Hash to identify the list of files to return. Containes information about user identifier, type of storage, path to public folder, name of public folder.
	 * @param string $Path Path to folder contained files to return.
	 * @return array {
	 *		*array* **Items** Array of files objects.
	 *		*array* **Quota** Array of items with fields Used, Limit.
	 * }
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function GetPublicFiles($Hash, $Path)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$iUserId = null;
		$oResult = array();

		$oMinDecorator =  $this->getMinModuleDecorator();
		if ($oMinDecorator)
		{
			$mMin = $oMinDecorator->GetMinByHash($Hash);
			if (!empty($mMin['__hash__']))
			{
				$iUserId = $mMin['UserId'];
				if ($iUserId)
				{
					$sUUID = $this->getUUIDById($iUserId);
					if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
					}
					$Path =  implode('/', array($mMin['Path'], $mMin['Name'])) . $Path;

					$oResult['Items'] = $this->oApiFilesManager->getFiles($sUUID, $mMin['Type'], $Path);
					$oResult['Quota'] = $this->GetQuota($iUserId);
				}
			}
		}

		return $oResult;
	}	

	/**
	 * @api {post} ?/Api/ CreateFolder
	 * @apiDescription Creates folder.
	 * @apiName CreateFolder
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreateFolder} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to new folder.<br>
	 * &emsp; **FolderName** *string* New folder name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateFolder',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", FolderName: "new_folder" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if folder was created successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateFolder',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateFolder',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Creates folder.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to new folder.
	 * @param string $FolderName New folder name.
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateFolder($UserId, $Type, $Path, $FolderName)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		if ($this->checkStorageType($Type))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID)) 
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			return $this->oApiFilesManager->createFolder($sUUID, $Type, $Path, $FolderName);
		}
	}

	/**
	 * @api {post} ?/Api/ CreateLink
	 * @apiDescription Creates link.
	 * @apiName CreateLink
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreateLink} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to new link.<br>
	 * &emsp; **Link** *string* Link value.<br>
	 * &emsp; **Name** *string* Link name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateLink',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Link: "link_value", Name: "name_value" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Link object in case of success, otherwise **false**.
	 * @apiSuccess {string} Result.Result.Type Type of storage.
	 * @apiSuccess {string} Result.Result.Path Path to link.
	 * @apiSuccess {string} Result.Result.Link Link URL.
	 * @apiSuccess {string} Result.Result.Name Link name.
	 * @apiSuccess {int} [Result.ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateLink',
	 *	Result: { Type: "personal", Path: "", Link: "https://www.youtube.com/watch?v=1WPn4NdQnlg&t=1124s", Name: "Endless Numbers counting 90 to 100 - Learn 123 Numbers for Kids" }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreateLink',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Creates link.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to new link.
	 * @param string $Link Link value.
	 * @param string $Name Link name.
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateLink($UserId, $Type, $Path, $Link, $Name)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		$sUUID = $this->getUUIDById($UserId);
		if ($this->checkStorageType($Type))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			$Name = \trim(\MailSo\Base\Utils::ClearFileName($Name));
			return $this->oApiFilesManager->createLink($sUUID, $Type, $Path, $Link, $Name);
		}
	}

	/**
	 * @api {post} ?/Api/ Delete
	 * @apiDescription Deletes files and folder specified with list.
	 * @apiName Delete
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Delete} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Items** *array* Array of items to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Delete',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Items: [{ "Path": "", "Name": "2.png" }, { "Path": "", "Name": "logo.png" }] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if files and (or) folders were deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Delete',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Delete',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Deletes files and folder specified with list.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage - personal, corporate.
	 * @param array $Items Array of items to delete.
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Delete($UserId, $Type, $Items)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	public function onAfterDelete(&$aArgs, &$mResult)
	{
		$sUUID = $this->getUUIDById($aArgs['UserId']);
		if ($this->checkStorageType($aArgs['Type']))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			$oResult = false;

			foreach ($aArgs['Items'] as $oItem)
			{
				$oResult = $this->oApiFilesManager->delete($sUUID, $aArgs['Type'], $oItem['Path'], $oItem['Name']);
				if (!$oResult)
				{
					break;
				}
			}
			$mResult = $oResult;
			return $oResult;
		}
	}

	/**
	 * @api {post} ?/Api/ Rename
	 * @apiDescription Renames folder, file or link.
	 * @apiName Rename
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Rename} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to item to rename.<br>
	 * &emsp; **Name** *string* Current name of the item.<br>
	 * &emsp; **NewName** *string* New name of the item.<br>
	 * &emsp; **IsLink** *bool* Indicates if the item is link or not.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Rename',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "old_name.png", NewName: "new_name.png", IsLink: false }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if file or folder was renamed successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Rename',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Rename',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Renames folder, file or link.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to item to rename.
	 * @param string $Name Current name of the item.
	 * @param string $NewName New name of the item.
	 * @param bool $IsLink Indicates if the item is link or not.
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Rename($UserId, $Type, $Path, $Name, $NewName, $IsLink)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}	
	
	public function onAfterRename(&$aArgs, &$mResult)
	{
		$sUUID = $this->getUUIDById($aArgs['UserId']);
		if ($this->checkStorageType($aArgs['Type']))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			$sNewName = \trim(\MailSo\Base\Utils::ClearFileName($aArgs['NewName']));

			$sNewName = $this->oApiFilesManager->getNonExistentFileName($sUUID, $aArgs['Type'], $aArgs['Path'], $sNewName);
			$mResult = $this->oApiFilesManager->rename($sUUID, $aArgs['Type'], $aArgs['Path'], $aArgs['Name'], $sNewName, $aArgs['IsLink']);
		}
	}

	/**
	 * @api {post} ?/Api/ Copy
	 * @apiDescription Copies files and/or folders from one folder to another.
	 * @apiName Copy
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Copy} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **FromType** *string* Storage type of folder items will be copied from.<br>
	 * &emsp; **ToType** *string* Storage type of folder items will be copied to.<br>
	 * &emsp; **FromPath** *string* Folder items will be copied from.<br>
	 * &emsp; **ToPath** *string* Folder items will be copied to.<br>
	 * &emsp; **Files** *array* List of items to copy<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Copy',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ FromType: "personal", ToType: "corporate", FromPath: "", ToPath: "", Files: [{ Name: "logo.png", IsFolder: false }, { Name: "details.png", IsFolder: false }] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if files and (or) folders were copied successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Copy',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Copy',
	 *		Result: false,
	 *		ErrorCode: 102
	 *	}]
	 * }
	 */
	
	/**
	 * Copies files and/or folders from one folder to another.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $FromType storage type of folder items will be copied from.
	 * @param string $ToType storage type of folder items will be copied to.
	 * @param string $FromPath folder items will be copied from.
	 * @param string $ToPath folder items will be copied to.
	 * @param array $Files list of items to copy {
	 *		*string* **Name** Name of item to copy.
	 *		*bool* **IsFolder** Indicates if the item to copy is folder or not.
	 * }
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Copy($UserId, $FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		if ($this->checkStorageType($FromType) && $this->checkStorageType($ToType))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}

			$oResult = null;

			foreach ($Files as $aItem)
			{
				$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
				if (!$bFolderIntoItself)
				{
					$NewName = $this->oApiFilesManager->getNonExistentFileName($sUUID, $ToType, $ToPath, $aItem['Name']);
					$oResult = $this->oApiFilesManager->copy($sUUID, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
				}
			}
			return $oResult;
		}
	}	

	/**
	 * @api {post} ?/Api/ Move
	 * @apiDescription Moves files and/or folders from one folder to another.
	 * @apiName Move
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Move} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **FromType** *string* Storage type of folder items will be moved from.<br>
	 * &emsp; **ToType** *string* Storage type of folder items will be moved to.<br>
	 * &emsp; **FromPath** *string* Folder items will be moved from.<br>
	 * &emsp; **ToPath** *string* Folder items will be moved to.<br>
	 * &emsp; **Files** *array* List of items to move<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Move',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ FromType: "personal", ToType: "corporate", FromPath: "", ToPath: "", Files: [{ "Name": "logo.png", "IsFolder": false },{ "Name": "details.png", "IsFolder": false }] }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicates if files and (or) folders were moved successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Move',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'Move',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	
	/**
	 * Moves files and/or folders from one folder to another.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $FromType storage type of folder items will be moved from.
	 * @param string $ToType storage type of folder items will be moved to.
	 * @param string $FromPath folder items will be moved from.
	 * @param string $ToPath folder items will be moved to.
	 * @param array $Files list of items to move {
	 *		*string* **Name** Name of item to copy.
	 *		*bool* **IsFolder** Indicates if the item to copy is folder or not.
	 * }
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function Move($UserId, $FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		if ($this->checkStorageType($FromType) && $this->checkStorageType($ToType))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
			}
			$oResult = null;

			foreach ($Files as $aItem)
			{
				if ($ToType === \EFileStorageTypeStr::Personal)
				{
					$oFileItem = $this->oApiFilesManager->getFileInfo($sUUID, $FromType, $FromPath, $aItem['Name']);
					$aQuota = $this->GetQuota($sUUID);
					if ($aQuota['Limit'] > 0 && $aQuota['Used'] + $oFileItem->Size > $aQuota['Limit'])
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileQuota);
					}
				}
				
				$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
				if (!$bFolderIntoItself)
				{
					$NewName = $this->oApiFilesManager->getNonExistentFileName($sUUID, $ToType, $ToPath, $aItem['Name']);
					$oResult = $this->oApiFilesManager->move($sUUID, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
				}
			}
			return $oResult;
		}
	}	
	
	/**
	 * @api {post} ?/Api/ CreatePublicLink
	 * @apiDescription Creates public link for file or folder.
	 * @apiName CreatePublicLink
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreatePublicLink} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage contains the item.<br>
	 * &emsp; **Path** *string* Path to the item.<br>
	 * &emsp; **Name** *string* Name of the item.<br>
	 * &emsp; **Size** *int* Size of the file.<br>
	 * &emsp; **IsFolder** *bool* Indicates if the item is folder or not.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreatePublicLink',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "image.png", Size: 100, "IsFolder": false }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {mixed} Result.Result Public link to the item in case of success, otherwise **false**.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreatePublicLink',
	 *	Result: 'AppUrl/?/files-pub/shared_item_hash/list'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'CreatePublicLink',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */

	/**
	 * Creates public link for file or folder.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage contains the item.
	 * @param string $Path Path to the item.
	 * @param string $Name Name of the item.
	 * @param int $Size Size of the file.
	 * @param bool $IsFolder Indicates if the item is folder or not.
	 * @return string|false Public link to the item.
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreatePublicLink($UserId, $Type, $Path, $Name, $Size, $IsFolder)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
		}
		
		$bFolder = $IsFolder === '1' ? true : false;
		return $this->oApiFilesManager->createPublicLink($sUUID, $Type, $Path, $Name, $Size, $bFolder);
	}	
	
	/**
	 * @api {post} ?/Api/ DeletePublicLink
	 * @apiDescription Deletes public link from file or folder.
	 * @apiName DeletePublicLink
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=DeletePublicLink} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage contains the item.<br>
	 * &emsp; **Path** *string* Path to the item.<br>
	 * &emsp; **Name** *string* Name of the item.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateAccount',
	 *	AuthToken: 'DeletePublicLink',
	 *	Parameters: '{ Type: "personal", Path: "", Name: "image.png" }'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name
	 * @apiSuccess {string} Result.Method Method name
	 * @apiSuccess {bool} Result.Result Indicated if public link was deleted successfully.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'DeletePublicLink',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'DeletePublicLink',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */

	/**
	 * Deletes public link from file or folder.
	 * 
	 * @param int $UserId User identifier.
	 * @param string $Type Type of storage contains the item.
	 * @param string $Path Path to the item.
	 * @param string $Name Name of the item.
	 * @return bool
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function DeletePublicLink($UserId, $Type, $Path, $Name)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sUUID = $this->getUUIDById($UserId);
		if (!$this->oApiCapabilityManager->isFilesSupported($sUUID))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed);
		}
		
		return $this->oApiFilesManager->deletePublicLink($sUUID, $Type, $Path, $Name);
	}

	/**
	 * Checks URL and returns information about it.
	 * 
	 * @param string $Url URL to check.
	 * @return array|bool {
	 *		Name
	 *		Thumb
	 *		Size
	 *		LinkType
	 * }
	 */
	public function CheckUrl($Url)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		$mResult = false;
		
		$aArgs = array(
			'Url' => $Url
		);
		$this->broadcastEvent(
			'CheckUrl', 
			$aArgs,
			$mResult
		);
		
		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function GetFilesForUpload($UserId, $Hashes = array())
	{
		$sUUID = $this->getUUIDById($UserId);
		
		$mResult = false;
		if (is_array($Hashes) && 0 < count($Hashes))
		{
			$mResult = array();
			foreach ($Hashes as $sHash)
			{
				$aData = \Aurora\System\Api::DecodeKeyValues($sHash);
				if (\is_array($aData) && 0 < \count($aData))
				{
					$oFileInfo = $this->oApiFilesManager->getFileInfo($sUUID, $aData['Type'], $aData['Path'], $aData['Name']);
					$rFile = $this->oApiFilesManager->getFile($sUUID, $aData['Type'], $aData['Path'], $aData['Name']);

					$sTempName = md5('Files/Tmp/'.$aData['Type'].$aData['Path'].$aData['Name'].microtime(true).rand(1000, 9999));

					if (is_resource($rFile) && $this->oApiFileCache->putFile($sUUID, $sTempName, $rFile))
					{
						$aItem = array(
							'Name' => $oFileInfo->Name,
							'TempName' => $sTempName,
							'Size' => $oFileInfo->Size,
							'Hash' => $sHash,
							'MimeType' => ''
						);

						$aItem['MimeType'] = \MailSo\Base\Utils::MimeContentType($aItem['Name']);
						$aItem['NewHash'] = \Aurora\System\Api::EncodeKeyValues(array(
							'TempFile' => true,
							'UserId' => $UserId,
							'Name' => $aItem['Name'],
							'TempName' => $sTempName
						));

						$mResult[] = $aItem;

						if (is_resource($rFile))
						{
							@fclose($rFile);
						}
					}
				}
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\ProjectCore\Notifications::InvalidInputParameter);
		}

		return $mResult;
	}	
	
	/***** public functions might be called with web API *****/
}
