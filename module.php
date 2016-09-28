<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
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

class FilesModule extends AApiModule
{
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
	
	protected $aSettingsMap = array(
		'EnableUploadSizeLimit' => array(true, 'bool'),
		'UploadSizeLimitMb' => array(5, 'int'),
		'Disabled' => array(false, 'bool'),
		'EnableCorporate' => array(true, 'bool'),
		'UserSpaceLimitMb' => array(100, 'int'),
		'CustomTabTitle' => array('', 'string'),
		'MaxFileSizeForMakingThumbnail' => array(5242880, 'int'), // 5MB
	);

	public function init() 
	{
		$this->incClass('item');
		$this->oApiFilesManager = $this->GetManager('', 'sabredav');
		
		$this->AddEntry('pub', 'EntryPub');
		
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'));
		$this->subscribeEvent('Files::CreateFile', array($this, 'onCreateFile'));
		$this->subscribeEvent('Files::GetLinkType', array($this, 'onGetLinkType'));
		$this->subscribeEvent('Files::CheckUrl', array($this, 'onCheckUrl'));

		$this->subscribeEvent('Files::PopulateFileItem', array($this, 'onPopulateFileItem'));

		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
	}
	
	/**
	 * Obtaines list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetAppData()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aPath = \System\Service::GetPaths();
		
		$aAppData = array(
			'EnableModule' => true,
			'EnableUploadSizeLimit' => $this->getConfig('EnableUploadSizeLimit', false),
			'UploadSizeLimitMb' => $this->getConfig('EnableUploadSizeLimit', false) ? $this->getConfig('UploadSizeLimitMb', 0) : 0,
			'EnableCorporate' => $this->getConfig('EnableCorporate', false),
			'UserSpaceLimitMb' => $this->getConfig('UserSpaceLimitMb', 0),
			'CustomTabTitle' => $this->getConfig('CustomTabTitle', '')
		);
		if (isset($aPath[2]))
		{
			$sPublicHash = $aPath[2];
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
	 * @apiParam {string=Files} Module Module=Files name
	 * @apiParam {string=UpdateSettings} Method=UpdateSettings Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **EnableUploadSizeLimit** *boolean* Enable file upload size limit setting.<br>
	 * &emsp; **UploadSizeLimitMb** *int* Upload file size limit setting in Mb.<br>
	 * &emsp; **UserSpaceLimitMb** *int* User space limit setting in Mb.<br>
	 * &emsp; **EnableCorporate** *boolean* Enable corporate storage in Files.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{EnableUploadSizeLimit: true,UploadSizeLimitMb: 5,EnableCorporate: true,UserSpaceLimitMb: 10}'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result Indicates if request execution was successfull
	 * @apiSuccess {int} ErrorCode *optional* Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Result: true,
	 *	'@Time': 0.31426095962524
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Result: false,
	 *	ErrorCode: 102,
	 *	'@Time': 0.31426095962524
	 * }
	 */
	/**
	 * Updates module's settings - saves them to config.json file.
	 * 
	 * @param boolean $EnableUploadSizeLimit Enable file upload size limit setting.
	 * @param int $UploadSizeLimitMb Upload file size limit setting in Mb.
	 * @param boolean $EnableCorporate Enable corporate storage in Files.
	 * @param int $UserSpaceLimitMb User space limit setting in Mb.
	 */
	public function UpdateSettings($EnableUploadSizeLimit, $UploadSizeLimitMb, $EnableCorporate, $UserSpaceLimitMb)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$this->setConfig('EnableUploadSizeLimit', $EnableUploadSizeLimit);
		$this->setConfig('UploadSizeLimitMb', $UploadSizeLimitMb);
		$this->setConfig('EnableCorporate', $EnableCorporate);
		$this->setConfig('UserSpaceLimitMb', $UserSpaceLimitMb);
		$this->saveModuleConfig();
		return true;
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
	

	protected function checkStorageType($Type)
	{
		return in_array($Type, array('personal', 'corporate'));
	}	
	
	/**
	 * Downloads file, views file or makes thumbnail for file.
	 * 
	 * @param string $sType Storage type - personal, corporate.
	 * @param string $sPath Path to folder contained file.
	 * @param string $sFileName File name.
	 * @param string $sAuthToken Authorization token.
	 * @param boolean $bDownload Indicates if file should be downloaded or viewed.
	 * @param boolean $bThumbnail Indicates if thumbnail should be created for file.
	 * 
	 * @return boolean
	 */
	private function getRawFile($iUserId, $sType, $sPath, $sFileName, $SharedHash = null, $bDownload = true, $bThumbnail = false)
	{
		$sPath = urldecode($sPath);
		$sFileName = urldecode($sFileName);
		
		$oModuleDecorator = $this->getMinModuleDecorator();
		$mMin = ($oModuleDecorator && $SharedHash !== null) ? $oModuleDecorator->GetMinByHash($SharedHash) : array();
		
		$iUserId = (!empty($mMin['__hash__'])) ? $mMin['UserId'] : $iUserId;

		if ($iUserId && $SharedHash !== null)
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		}
		else 
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		}
		
		if ($this->oApiCapabilityManager->isFilesSupported($iUserId) && 
			isset($sType, $sPath, $sFileName)) 
		{
			$sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
			
			$mResult = false;
			$this->broadcastEvent(
				'GetFile', 
				array(
					'UserId' => $iUserId,
					'Type' => $sType,
					'Path' => $sPath,
					'Name' => &$sFileName,
					'IsThumb' => $bThumbnail,
					'@Result' => &$mResult
				)
			);			
			
			if (false !== $mResult) 
			{
				if (is_resource($mResult)) 
				{
//					$sFileName = $this->clearFileName($oFileInfo->Name, $sContentType); // todo
					$sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);
					\CApiResponseManager::OutputHeaders($bDownload, $sContentType, $sFileName);
			
					if ($bThumbnail) 
					{
//						$this->cacheByKey($sRawKey);	// todo
						return \CApiResponseManager::GetThumbResource($iUserId, $mResult, $sFileName, false);
					} 
					else if ($sContentType === 'text/html') 
					{
						echo(\MailSo\Base\HtmlUtils::ClearHtmlSimple(stream_get_contents($mResult)));
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
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {array[]} Result 
	 * @apiSuccess {string} Result.Name Original file name.
	 * @apiSuccess {string} Result.TempName Temporary file name.
	 * @apiSuccess {string} Result.MimeType Mime type of file.
	 * @apiSuccess {int} Result.Size File size.
	 * @apiSuccess {string} Result.Hash Hash used for file download, file view or getting file thumbnail.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	/**
	 * Uploads file from client side.
	 * 
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to folder than should contain uploaded file.
	 * @param array $FileData Uploaded file information. Contains fields size, name, tmp_name.
	 * @param string $AuthToken Authentication token.
	 * 
	 * @return array {
	 *		*string* **Name** Original file name.
	 *		*string* **TempName** Temporary file name.
	 *		*string* **MimeType** Mime type of file.
	 *		*int* **Size** File size.
	 *		*string* **Hash** Hash used for file download, file view or getting file thumbnail.
	 * }
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UploadFile($Type, $Path, $FileData, $AuthToken)
	{
		$iUserId = \CApi::getAuthenticatedUserId($AuthToken);
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oApiFileCacheManager = \CApi::GetSystemManager('filecache');

		$sError = '';
		$aResponse = array();

		if ($iUserId)
		{
			if (is_array($FileData))
			{
				$iSize = (int) $FileData['size'];
				if ($Type === \EFileStorageTypeStr::Personal)
				{
					$aQuota = $this->GetQuota($iUserId);
					if ($aQuota['Limit'] > 0 && $aQuota['Used'] + $iSize > $aQuota['Limit'])
					{
						throw new \System\Exceptions\AuroraApiException(\System\Notifications::CanNotUploadFileQuota);
					}
				}
				
				$sUploadName = $FileData['name'];
				$sMimeType = \MailSo\Base\Utils::MimeContentType($sUploadName);

				$sSavedName = 'upload-post-'.md5($FileData['name'].$FileData['tmp_name']);
				$rData = false;
				if (is_resource($FileData['tmp_name']))
				{
					$rData = $FileData['tmp_name'];
				}
				else if ($oApiFileCacheManager->moveUploadedFile($iUserId, $sSavedName, $FileData['tmp_name']))
				{
					$rData = $oApiFileCacheManager->getFile($iUserId, $sSavedName);
				}
				if ($rData)
				{
					$this->broadcastEvent(
						'CreateFile', 
						array(
							'UserId' => $iUserId,
							'Type' => $Type,
							'Path' => $Path,
							'Name' => $sUploadName,
							'Data' => $rData,
							'@Result' => &$mResult
						)
					);			
					
					$aResponse['File'] = array(
						'Name' => $sUploadName,
						'TempName' => $sSavedName,
						'MimeType' => $sMimeType,
						'Size' =>  (int) $iSize,
						'Hash' => \CApi::EncodeKeyValues(array(
							'TempFile' => true,
							'UserID' => $iUserId,
							'Name' => $sUploadName,
							'TempName' => $sSavedName
						))
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
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Downloads file.
	 * 
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @param string $AuthToken Authorization token.
	 * @param string $SharedHash Shared hash.
	 * 
	 * @return boolean
	 */
	public function DownloadFile($Type, $Path, $Name, $AuthToken, $SharedHash)
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		$iUserId = \CApi::getAuthenticatedUserId($AuthToken);
		$this->getRawFile($iUserId, $Type, $Path, $Name, $SharedHash, true);
	}

	/**
	 * @api {post} ?/Api/ ViewFile
	 * @apiDescription Views file.
	 * @apiName ViewFile
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=ViewFile} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Views file.
	 * 
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @param string $AuthToken Authorization token.
	 * @param string $SharedHash Shared hash.
	 * 
	 * @return boolean
	 */
	public function ViewFile($Type, $Path, $Name, $AuthToken, $SharedHash)
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		$iUserId = \CApi::getAuthenticatedUserId($AuthToken);
		$this->getRawFile($iUserId, $Type, $Path, $Name, $SharedHash, false);
	}

	/**
	 * @api {post} ?/Api/ GetFileThumbnail
	 * @apiDescription Makes thumbnail for file.
	 * @apiName GetFileThumbnail
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFileThumbnail} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Storage type - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to folder contained file.<br>
	 * &emsp; **Name** *string* File name.<br>
	 * &emsp; **SharedHash** *string* Shared hash.<br>
	 * }
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * @apiParam {string} Parameters.Type Storage type - personal, corporate.<br>
	 * @apiParam {string} Parameters..Path Path to folder contained file.<br>
	 * @apiParam {string} Parameters.Name File name.<br>
	 * @apiParam {string} Parameters.SharedHash Shared hash.<br>
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Makes thumbnail for file.
	 * 
	 * @param string $Type Storage type - personal, corporate.
	 * @param string $Path Path to folder contained file.
	 * @param string $Name File name.
	 * @param string $AuthToken Authorization token.
	 * @param string $SharedHash Shared hash.
	 * 
	 * @return boolean
	 */
	public function GetFileThumbnail($Type, $Path, $Name, $AuthToken, $SharedHash)
	{
		if (empty($AuthToken) && isset($_COOKIE[\System\Service::AUTH_TOKEN_KEY]))
		{
			$AuthToken = $_COOKIE[\System\Service::AUTH_TOKEN_KEY];
		}
		// checkUserRoleIsAtLeast is called in getRawFile
		$iUserId = \CApi::getAuthenticatedUserId($AuthToken);
		return base64_encode($this->getRawFile($iUserId, $Type, $Path, $Name, $SharedHash, false, true));
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
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {array[]} Result 
	 * @apiSuccess {string} Result.Type Storage type - personal, corporate.
	 * @apiSuccess {string} Result.DisplayName Storage display name.
	 * @apiSuccess {bool} Result.IsExternal Indicates if storage external or not.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Returns storages avaliable for logged in user.
	 * 
	 * @return array {
	 *		*string* **Type** Storage type - personal, corporate.
	 *		*string* **DisplayName** Storage display name.
	 *		*bool* **IsExternal** Indicates if storage external or not.
	 * }
	 */
	public function GetStorages()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		$aStorages = [
			[
				'Type' => 'personal', 
				'DisplayName' => $this->i18N('LABEL_PERSONAL_STORAGE', $iUserId), 
				'IsExternal' => false
			]
		];
		if ($this->getConfig('EnableCorporate', false))
		{
			$aStorages[] = [
				'Type' => 'corporate', 
				'DisplayName' => $this->i18N('LABEL_CORPORATE_STORAGE', $iUserId), 
				'IsExternal' => false
			];
		}
		return $aStorages;
	}	
	
	/**
	 * @ignore
	 * @return array
	 */
	public function GetExternalStorages()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array();
	}

	/**
	 * @api {post} ?/Api/ GetQuota
	 * @apiDescription Returns used space and space limit for specified user.
	 * @apiName GetQuota
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetQuota} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **iUserId** *int* User identifier.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {array[]} Result 
	 * @apiSuccess {int} Result.Used Amount of space used by user.
	 * @apiSuccess {int} Result.Limit Limit of space for user.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Returns used space and space limit for specified user.
	 * 
	 * @param int $iUserId User identifier.
	 * 
	 * @return array {
	 *		*int* **Used** Amount of space used by user.
	 *		*int* **Limit** Limit of space for user.
	 * }
	 */
	public function GetQuota($iUserId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(
			'Used' => $this->oApiFilesManager->getUserSpaceUsed($iUserId, [\EFileStorageTypeStr::Personal]),
			'Limit' => $this->getConfig('UserSpaceLimitMb', 0) * 1024 * 1024
		);
	}
	
	/**
	 * Returns file contents.
	 * 
	 * @param string $Type Type of storage.
	 * @param string $Path Path to folder files are obtained from.
	 * @param string $FileName Name of file.
	 * 
	 * @return string/resource/bool
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function onGetFile($UserId, $Type, $Path, $Name, $IsThumb, &$Result)
	{
		if ($this->checkStorageType($Type))
		{
			if (!$this->oApiCapabilityManager->isFilesSupported($UserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}
			
			$Result = $this->oApiFilesManager->getFile($UserId, $Type, $Path, $Name);
		}
	}	
	
	
	public function onCreateFile($UserId, $Type, $Path, $Name, $Data, &$Result)
	{
		if ($this->checkStorageType($Type))
		{
			$Result = $this->oApiFilesManager->createFile($UserId, $Type, $Path, $Name, $Data, false);
		}
	}
	
	public function onGetLinkType($Link, &$Result)
	{
		$Result = '';
	}	

	/**
	 * @api {post} ?/Api/ GetFiles
	 * @apiDescription Returns file list and user quota information.
	 * @apiName GetFiles
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFiles} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage.<br>
	 * &emsp; **Path** *string* Path to folder files are obtained from.<br>
	 * &emsp; **Pattern** *string* String for search files and folders with such string in name.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {array[]} Result 
	 * @apiSuccess {array} Result.Items Array of files objects.
	 * @apiSuccess {array} Result.Quota Array of items with fields Used, Limit.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Returns file list and user quota information.
	 * 
	 * @param string $Type Type of storage.
	 * @param string $Path Path to folder files are obtained from.
	 * @param string $Pattern String for search files and folders with such string in name.
	 * 
	 * @return array {
	 *		*array* **Items** Array of files objects.
	 *		*array* **Quota** Array of items with fields Used, Limit.
	 * }
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function GetFiles($Type, $Path, $Pattern)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if ($this->checkStorageType($Type))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			$aUsers = array();
			$aFiles = $this->oApiFilesManager->getFiles($iUserId, $Type, $Path, $Pattern);
			foreach ($aFiles as $oFile)
			{
				if (!isset($aUsers[$oFile->Owner]))
				{
					$oUser = \CApi::GetModuleDecorator('Core')->GetUser($oFile->Owner);
					$aUsers[$oFile->Owner] = $oUser ? $oUser->Name : '';
				}
				$oFile->Owner = $aUsers[$oFile->Owner];
			}

			return array(
				'Items' => $aFiles,
				'Quota' => $this->GetQuota($iUserId)
			);
		}
	}

	/**
	 * @api {post} ?/Api/ GetPublicFiles
	 * @apiDescription Returns list of public files.
	 * @apiName GetPublicFiles
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetPublicFiles} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Hash** *string* Hash to identify the list of files to return. Containes information about user identificator, type of storage, path to public folder, name of public folder.<br>
	 * &emsp; **Path** *string* Path to folder contained files to return.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {array[]} Result 
	 * @apiSuccess {array} Result.Items Array of files objects.
	 * @apiSuccess {array} Result.Quota Array of items with fields Used, Limit.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */

	/**
	 * Returns list of public files.
	 * 
	 * @param string $Hash Hash to identify the list of files to return. Containes information about user identificator, type of storage, path to public folder, name of public folder.
	 * @param string $Path Path to folder contained files to return.
	 * 
	 * @return array {
	 *		*array* **Items** Array of files objects.
	 *		*array* **Quota** Array of items with fields Used, Limit.
	 * }
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function GetPublicFiles($Hash, $Path)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
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
					if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
					{
						throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
					}
					$Path =  implode('/', array($mMin['Path'], $mMin['Name'])) . $Path;

					$oResult['Items'] = $this->oApiFilesManager->getFiles($iUserId, $mMin['Type'], $Path);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to new folder.<br>
	 * &emsp; **FolderName** *string* New folder name.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Creates folder.
	 * 
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to new folder.
	 * @param string $FolderName New folder name.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateFolder($Type, $Path, $FolderName)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($this->checkStorageType($Type))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId)) {

				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			return $this->oApiFilesManager->createFolder($iUserId, $Type, $Path, $FolderName);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to new link.<br>
	 * &emsp; **Link** *string* Link value.<br>
	 * &emsp; **Name** *string* Link name.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Creates link.
	 * 
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to new link.
	 * @param string $Link Link value.
	 * @param string $Name Link name.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateLink($Type, $Path, $Link, $Name)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		if ($this->checkStorageType($Type))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			$Name = \trim(\MailSo\Base\Utils::ClearFileName($Name));
			return $this->oApiFilesManager->createLink($iUserId, $Type, $Path, $Link, $Name);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Items** *array* Array of items to delete.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Deletes files and folder specified with list.
	 * 
	 * @param string $Type Type of storage - personal, corporate.
	 * @param array $Items Array of items to delete.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Delete($Type, $Items)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($this->checkStorageType($Type))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			$oResult = false;

			foreach ($Items as $oItem)
			{
				$oResult = $this->oApiFilesManager->delete($iUserId, $Type, $oItem['Path'], $oItem['Name']);
			}

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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage - personal, corporate.<br>
	 * &emsp; **Path** *string* Path to item to rename.<br>
	 * &emsp; **Name** *string* Current name of the item.<br>
	 * &emsp; **NewName** *string* New name of the item.<br>
	 * &emsp; **IsLink** *boolean* Indicates if the item is link or not.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Renames folder, file or link.
	 * 
	 * @param string $Type Type of storage - personal, corporate.
	 * @param string $Path Path to item to rename.
	 * @param string $Name Current name of the item.
	 * @param string $NewName New name of the item.
	 * @param boolean $IsLink Indicates if the item is link or not.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Rename($Type, $Path, $Name, $NewName, $IsLink)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($this->checkStorageType($Type))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			$NewName = \trim(\MailSo\Base\Utils::ClearFileName($NewName));

			$NewName = $this->oApiFilesManager->getNonExistentFileName($iUserId, $Type, $Path, $NewName);
			return $this->oApiFilesManager->rename($iUserId, $Type, $Path, $Name, $NewName, $IsLink);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **FromType** *string* Storage type of folder items will be copied from.<br>
	 * &emsp; **ToType** *string* Storage type of folder items will be copied to.<br>
	 * &emsp; **FromPath** *string* Folder items will be copied from.<br>
	 * &emsp; **ToPath** *string* Folder items will be copied to.<br>
	 * &emsp; **Files** *array* List of items to copy<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Copies files and/or folders from one folder to another.
	 * 
	 * @param string $FromType storage type of folder items will be copied from.
	 * @param string $ToType storage type of folder items will be copied to.
	 * @param string $FromPath folder items will be copied from.
	 * @param string $ToPath folder items will be copied to.
	 * @param array $Files list of items to copy {
	 *		*string* **Name** Name of item to copy.
	 *		*boolean* **IsFolder** Indicates if the item to copy is folder or not.
	 * }
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Copy($FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($this->checkStorageType($FromType) && $this->checkStorageType($ToType))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}

			$oResult = null;

			foreach ($Files as $aItem)
			{
				$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
				if (!$bFolderIntoItself)
				{
					$NewName = $this->oApiFilesManager->getNonExistentFileName($iUserId, $ToType, $ToPath, $aItem['Name']);
					$oResult = $this->oApiFilesManager->copy($iUserId, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **FromType** *string* Storage type of folder items will be moved from.<br>
	 * &emsp; **ToType** *string* Storage type of folder items will be moved to.<br>
	 * &emsp; **FromPath** *string* Folder items will be moved from.<br>
	 * &emsp; **ToPath** *string* Folder items will be moved to.<br>
	 * &emsp; **Files** *array* List of items to move<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {boolean} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */
	
	/**
	 * Moves files and/or folders from one folder to another.
	 * 
	 * @param string $FromType storage type of folder items will be moved from.
	 * @param string $ToType storage type of folder items will be moved to.
	 * @param string $FromPath folder items will be moved from.
	 * @param string $ToPath folder items will be moved to.
	 * @param array $Files list of items to move {
	 *		*string* **Name** Name of item to copy.
	 *		*boolean* **IsFolder** Indicates if the item to copy is folder or not.
	 * }
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Move($FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($this->checkStorageType($FromType) && $this->checkStorageType($ToType))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
			{
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
			}
			$oResult = null;

			foreach ($Files as $aItem)
			{
				$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
				if (!$bFolderIntoItself)
				{
					$NewName = $this->oApiFilesManager->getNonExistentFileName($iUserId, $ToType, $ToPath, $aItem['Name']);
					$oResult = $this->oApiFilesManager->move($iUserId, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
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
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage contains the item.<br>
	 * &emsp; **Path** *string* Path to the item.<br>
	 * &emsp; **Name** *string* Name of the item.<br>
	 * &emsp; **Size** *int* Size of the file.<br>
	 * &emsp; **IsFolder** *boolean* Indicates if the item is folder or not.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {mixed} Result Public link to the item.
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */

	/**
	 * Creates public link for file or folder.
	 * 
	 * @param string $Type Type of storage contains the item.
	 * @param string $Path Path to the item.
	 * @param string $Name Name of the item.
	 * @param int $Size Size of the file.
	 * @param boolean $IsFolder Indicates if the item is folder or not.
	 * 
	 * @return string|false Public link to the item.
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreatePublicLink($Type, $Path, $Name, $Size, $IsFolder)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
		}
		
		$bFolder = $IsFolder === '1' ? true : false;
		return $this->oApiFilesManager->createPublicLink($iUserId, $Type, $Path, $Name, $Size, $bFolder);
	}	
	
	/**
	 * @api {post} ?/Api/ DeletePublicLink
	 * @apiDescription Deletes public link from file or folder.
	 * @apiName DeletePublicLink
	 * @apiGroup Files
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=DeletePublicLink} Method Method name
	 * @apiParam {string} AuthToken Auth token
	 * 
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Type of storage contains the item.<br>
	 * &emsp; **Path** *string* Path to the item.<br>
	 * &emsp; **Name** *string* Name of the item.<br>
	 * }
	 * 
	 * @apiSuccess {string} Module Module name
	 * @apiSuccess {string} Method Method name
	 * @apiSuccess {bool} Result 
	 * @apiSuccess {int} ErrorCode Error code
	 * @apiSuccess {float} Time Request execution time on the server
	 */

	/**
	 * Deletes public link from file or folder.
	 * 
	 * @param string $Type Type of storage contains the item.
	 * @param string $Path Path to the item.
	 * @param string $Name Name of the item.
	 * 
	 * @return bool
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function DeletePublicLink($Type, $Path, $Name)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::FilesNotAllowed);
		}
		
		return $this->oApiFilesManager->deletePublicLink($iUserId, $Type, $Path, $Name);
	}

	/**
	 * @ignore
	 * 
	 * @param string $Url
	 * 
	 * @return array|bool {
	 *		Name
	 *		Thumb
	 *		Size
	 *		LinkType
	 * }
	 */
	public function CheckUrl($Url)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		$mResult = false;
		
		$this->broadcastEvent(
			'CheckUrl', 
			array(
				'Url' => $Url,
				'@Result' => &$mResult
			)
		);
		
		return $mResult;
	}	
	
	public function EntryPub()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aPaths = \System\Service::GetPaths();
		$sHash = empty($aPaths[2]) ? '' : $aPaths[2];
		$bDownload = !(!empty($aPaths[3]) && $aPaths[3] === 'view');
		$bList = (!empty($aPaths[3]) && $aPaths[3] === 'list');
		
		if ($bList)
		{
			$sResult = '';

			$oMinDecorator =  $this->getMinModuleDecorator();
			if ($oMinDecorator)
			{
				$mData = $oMinDecorator->GetMinByHash($sHash);

				if (is_array($mData) && isset($mData['IsFolder']) && $mData['IsFolder'])
				{
					$oApiIntegrator = \CApi::GetSystemManager('integrator');

					if ($oApiIntegrator)
					{
						$oCoreClientModule = \CApi::GetModule('CoreWebclient');
						if ($oCoreClientModule instanceof \AApiModule) {
							$sResult = file_get_contents($oCoreClientModule->GetPath().'/templates/Index.html');
							if (is_string($sResult)) {
								$sFrameOptions = \CApi::GetConf('labs.x-frame-options', '');
								if (0 < \strlen($sFrameOptions)) {
									@\header('X-Frame-Options: '.$sFrameOptions);
								}

								$sAuthToken = isset($_COOKIE[\System\Service::AUTH_TOKEN_KEY]) ? $_COOKIE[\System\Service::AUTH_TOKEN_KEY] : '';
								$sResult = strtr($sResult, array(
									'{{AppVersion}}' => PSEVEN_APP_VERSION,
									'{{IntegratorDir}}' => $oApiIntegrator->isRtl() ? 'rtl' : 'ltr',
									'{{IntegratorLinks}}' => $oApiIntegrator->buildHeadersLink(),
									'{{IntegratorBody}}' => $oApiIntegrator->buildBody('-files-pub')
								));
							}
						}
					}
				}
				else if ($mData && isset($mData['__hash__'], $mData['Name'], $mData['Size']))
				{
					$sUrl = (bool) \CApi::GetConf('labs.server-use-url-rewrite', false) ? '/download/' : '?/pub/files/';

					$sUrlRewriteBase = (string) \CApi::GetConf('labs.server-url-rewrite-base', '');
					if (!empty($sUrlRewriteBase))
					{
						$sUrlRewriteBase = '<base href="'.$sUrlRewriteBase.'" />';
					}

					$sResult = file_get_contents($this->GetPath().'/templates/FilesPub.html');
					if (is_string($sResult))
					{
						$sResult = strtr($sResult, array(
							'{{Url}}' => $sUrl.$mData['__hash__'], 
							'{{FileName}}' => $mData['Name'],
							'{{FileSize}}' => \api_Utils::GetFriendlySize($mData['Size']),
							'{{FileType}}' => \api_Utils::GetFileExtension($mData['Name']),
							'{{BaseUrl}}' => $sUrlRewriteBase 
						));
					}
					else
					{
						\CApi::Log('Empty template.', \ELogLevel::Error);
					}
				}
			}

			return $sResult;
		}
		else
		{
			$oModuleDecorator = $this->getMinModuleDecorator();
			if ($oModuleDecorator)
			{
				$aHash = $oModuleDecorator->GetMinByHash($sHash);
				if (isset($aHash['__hash__']))
				{
					if ((isset($aHash['IsFolder']) && (bool) $aHash['IsFolder'] === false) || !isset($aHash['IsFolder']) )
					{
						echo $this->getRawFile($aHash['UserId'], $aHash['Type'], $aHash['Path'], $aHash['Name'], $sHash, $bDownload);
					}
					else 
					{
						header('File not found', true, 404);
					}
				}
			}
		}
	}
	
	public function onCheckUrl($sUrl, &$mResult)
	{
		$iUserId = \CApi::getAuthenticatedUserId();

		if ($iUserId)
		{
			if (!empty($sUrl))
			{
				$sUrl = \api_Utils::GetRemoteFileRealUrl($sUrl);
				if ($sUrl)
				{
					$aRemoteFileInfo = \api_Utils::GetRemoteFileInfo($sUrl);
					$sFileName = basename($sUrl);
					$sFileExtension = \api_Utils::GetFileExtension($sFileName);

					if (empty($sFileExtension))
					{
						$sFileExtension = \api_Utils::GetFileExtensionFromMimeContentType($aRemoteFileInfo['content-type']);
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
	
	public function onPopulateFileItem(&$oItem)
	{
		if ($oItem->IsLink)
		{
			$sFileName = basename($oItem->LinkUrl);
			$sFileExtension = \api_Utils::GetFileExtension($sFileName);
			if ($sFileExtension === 'htm' || $sFileExtension === 'html')
			{
				$oItem->Name = $this->getHtmlTitle($oItem->LinkUrl);
				return true;
			}
		}
	}	
	
	public function onAfterDeleteUser($iUserId)
	{
		$this->oApiFilesManager->ClearFiles($iUserId);
	}
}
