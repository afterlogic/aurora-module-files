<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	protected static $sStorageType = '';
	
	/* 
	 * @var $oApiFileCache \Aurora\System\Managers\Filecache 
	 */	
	public $oApiFileCache = null;

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
		$this->oApiFileCache = new \Aurora\System\Managers\Filecache();
		
		$this->subscribeEvent('Files::GetFiles::after', array($this, 'onAfterGetFiles'), 1000);
		$this->AddEntries(
			array(
				'upload' => 'UploadFileData',
				'download-file' => 'EntryDownloadFile'
			)
		);
		$this->denyMethodsCallByWebApi(['getRawFile']);
	}
	
	/**
	* Returns Min module decorator.
	* 
	* @return \CApiModuleDecorator
	*/
	private function getMinModuleDecorator()
	{
		return \Aurora\System\Api::GetModuleDecorator('Min');
	}	
	
	/**
	 * Checks if storage type is personal or corporate.
	 * 
	 * @param string $Type Storage type.
	 * @return bool
	 */
	protected function checkStorageType($Type)
	{
		return $Type === static::$sStorageType;
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
	public function getRawFile($iUserId, $sType, $sPath, $sFileName, $SharedHash = null, $sAction = '', $iOffset = 0, $iChunkSize = 0)
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
			case 'download':
				$bDownload = true;
				$bThumbnail = false;

			break;
			default:
				$bDownload = true;
				$bThumbnail = false;
			break;
		}
		if (!$bDownload || $iChunkSize == 0)
		{
			$iLength = -1;
			$iOffset = -1;
		}
		else
		{
			$iLength = $iChunkSize;
			$iOffset = $iChunkSize * $iOffset;
		}
		
		$oModuleDecorator = $this->getMinModuleDecorator();
		$mMin = ($oModuleDecorator && $SharedHash !== null) ? $oModuleDecorator->GetMinByHash($SharedHash) : array();
		
		$iUserId = (!empty($mMin['__hash__'])) ? $mMin['UserId'] : $iUserId;

		try
		{
			if ($iUserId && $SharedHash !== null)
			{
				\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
				\Afterlogic\DAV\Server::setUser($iUserId);
			}
			else 
			{
				\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
				if ($iUserId !== \Aurora\System\Api::getAuthenticatedUserId())
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
				}
			}
		}
		catch (\Aurora\System\Exceptions\ApiException $oEx)
		{
			echo 'Access denied';
			exit();
		}
		
		if (isset($sType, $sPath, $sFileName)) 
		{
			$sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
			
			$aArgs = array(
				'UserId' => $iUserId,
				'Type' => $sType,
				'Path' => $sPath,
				'Name' => &$sFileName,
				'Id' => $sFileName,
				'IsThumb' => $bThumbnail,
				'Offset' => $iOffset,
				'ChunkSize' => $iChunkSize
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
						$sRawKey = (string) \Aurora\System\Application::GetPathItemByIndex(1, '');
						if (!empty($sRawKey))
						{
							\Aurora\System\Managers\Response::verifyCacheByKey($sRawKey);
						}
						return \Aurora\System\Managers\Response::GetThumbResource($iUserId, $mResult, $sFileName);
					} 
					else if ($sContentType === 'text/html' && !$bDownload)
					{
						echo(\MailSo\Base\HtmlUtils::ClearHtmlSimple(stream_get_contents($mResult, $iLength, $iOffset)));
					} 
					else if ($sContentType === 'text/plain')
					{
						echo(stream_get_contents($mResult, $iLength, $iOffset));
					}
					else if ($iLength > -1 && $iOffset > -1)
					{
						\MailSo\Base\Utils::GetFilePart($mResult, $iLength, $iOffset);
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
	/***** private functions *****/
	
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * Uploads file from client side.
	 * 
	 * @return string "true" or "false"
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UploadFileData()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$mResult = false;
		$aPaths = \Aurora\System\Application::GetPaths();
		if (isset($aPaths[1]) && strtolower($aPaths[1]) === strtolower($this->GetName()))
		{
			$sType = isset($aPaths[2]) ? strtolower($aPaths[2]) : 'personal';
			$rData = fopen("php://input", "r");
			$aFilePath = array_slice($aPaths, 3);
			$sFilePath = urldecode(implode('/', $aFilePath));
			
			$bOverwrite = true;
			if (strpos($sFilePath, '!') === 0)
			{
				$sFilePath = substr($sFilePath, 1);
				$bOverwrite = false;
			}
			
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId(
				\Aurora\System\Api::getAuthTokenFromHeaders()
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
						'Data' => $rData,
						'Overwrite' => $bOverwrite, 
						'RangeType' => 0, 
						'Offset' => 0,
						'ExtendedProps' => array()
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
	 * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetSettings} Method Method name
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetSettings'
	 * }
	 * 
	 * @apiSuccess {object[]} Result Array of response objects.
	 * @apiSuccess {string} Result.Module Module name.
	 * @apiSuccess {string} Result.Method Method name.
	 * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
	 * @apiSuccess {bool} Result.Result.EnableModule=false Indicates if Files module is enabled.
	 * @apiSuccess {bool} Result.Result.EnableUploadSizeLimit=false Indicates if upload size limit is enabled.
	 * @apiSuccess {int} Result.Result.UploadSizeLimitMb=0 Value of upload size limit in Mb.
	 * @apiSuccess {string} Result.Result.CustomTabTitle=&quot;&quot; Custom tab title.
	 * @apiSuccess {string} [Result.Result.PublicHash=&quot;&quot;] Public hash.
	 * @apiSuccess {string} [Result.Result.PublicFolderName=&quot;&quot;] Public folder name.
	 * @apiSuccess {int} [Result.ErrorCode] Error code
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetSettings',
	 *	Result: { EnableModule: true, EnableUploadSizeLimit: true, UploadSizeLimitMb: 5, 
	 *		CustomTabTitle: "", PublicHash: "", PublicFolderName: "" }
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$aAppData = array(
			'EnableModule' => true,
			'EnableUploadSizeLimit' => $this->getConfig('EnableUploadSizeLimit', false),
			'UploadSizeLimitMb' => $this->getConfig('UploadSizeLimitMb', 0),
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
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=UpdateSettings} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **EnableUploadSizeLimit** *bool* Enable file upload size limit setting.<br>
	 * &emsp; **UploadSizeLimitMb** *int* Upload file size limit setting in Mb.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateSettings',
	 *	Parameters: '{ EnableUploadSizeLimit: true, UploadSizeLimitMb: 5 }'
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
	 * @return bool
	 */
	public function UpdateSettings($EnableUploadSizeLimit, $UploadSizeLimitMb)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$this->setConfig('EnableUploadSizeLimit', $EnableUploadSizeLimit);
		$this->setConfig('UploadSizeLimitMb', $UploadSizeLimitMb);
		return (bool) $this->saveModuleConfig();
	}
	
		/**
	 * @api {post} ?/Upload/ UploadFile
	 * @apiDescription Uploads file from client side.
	 * @apiName UploadFile
	 * @apiGroup Files
		 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=UploadFile} Method Method name
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
	 *	Result: { File: { Name: 'image.png', TempName: 'upload-post-6149f2cda5c58c6951658cce9f2b1378',
	 *		MimeType: 'image/png', Size: 1813 } }
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
	 * @param bool $Overwrite Overwrite a file if it already exists.
	 * @param int $RangeType The type of update we're doing.
	 * *	0 - overwrite
	 * *	1 - append
     * *	2 - update based on a start byte
     * *	3 - update based on an end byte
     *;
	 * @param int $Offset The start or end byte.
	 * @param array $ExtendedProps Additional parameters.
	 * @return array {
	 *		*string* **Name** Original file name.
	 *		*string* **TempName** Temporary file name.
	 *		*string* **MimeType** Mime type of file.
	 *		*int* **Size** File size.
	 *		*string* **Hash** Hash used for file download, file view or getting file thumbnail.
	 * }
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UploadFile($UserId, $Type, $Path, $UploadData, $Overwrite = true, $RangeType = 0, $Offset = 0, $ExtendedProps = [])
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

		$sError = '';
		$mResponse = array();

		if ($sUserPublicId)
		{
			if (is_array($UploadData))
			{
				if (isset($ExtendedProps['FirstChunk']) && $RangeType == 1 && self::Decorator()->IsFileExists($sUserPublicId, $Type, $Path, $UploadData['name']))
				{// It is forbidden to write first сhunk to the end of a existing file
					$sError = \Aurora\System\Notifications::FileAlreadyExists;
				}
				else if (!isset($ExtendedProps['FirstChunk']) && $RangeType == 1 && !self::Decorator()->IsFileExists($sUserPublicId, $Type, $Path, $UploadData['name']))
				{ // It is forbidden to write to the end of a nonexistent file
					$sError = \Aurora\System\Notifications::FileNotFound;
				}
				else
				{
					$iSize = (int) $UploadData['size'];
					$iUploadSizeLimitMb = $this->getConfig('UploadSizeLimitMb', 0);
					if ($iUploadSizeLimitMb > 0 && $iSize/(1024*1024) > $iUploadSizeLimitMb)
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileLimit);
					}
					
					
					$aArgs = [
						'PublicId' => $sUserPublicId,
						'Type' => $Type,
						'Size' => $iSize
					];
					$mCheckFilesQuotaResult = true;
					$this->broadcastEvent(
							'CheckFilesQuota', 
							$aArgs, 
							$mCheckFilesQuotaResult
					);
					if (!$mCheckFilesQuotaResult)
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileQuota);
					}
					
					$sUploadName = $UploadData['name'];
					$sMimeType = \MailSo\Base\Utils::MimeContentType($sUploadName);

					$sSavedName = 'upload-post-'.md5($UploadData['name'].$UploadData['tmp_name']);
					$rData = false;
					if (\is_resource($UploadData['tmp_name']))
					{
						$rData = $UploadData['tmp_name'];
					}
					else if ($this->oApiFileCache->moveUploadedFile($sUserPublicId, $sSavedName, $UploadData['tmp_name'], '', $this->GetName()))
					{
						$rData = $this->oApiFileCache->getFile($sUserPublicId, $sSavedName, '', $this->GetName());
					}
					if ($rData)
					{
						$aArgs = array(
							'UserId' => $UserId,
							'Type' => $Type,
							'Path' => $Path,
							'Name' => $sUploadName,
							'Data' => $rData,
							'Overwrite' => $Overwrite,
							'RangeType' => $RangeType,
							'Offset' => $Offset,
							'ExtendedProps' => $ExtendedProps

						);
						$mResult = false;
						$this->broadcastEvent(
							'CreateFile',
							$aArgs,
							$mResult
						);
						
						if ($mResult)
						{
							$mResponse['File'] = array(
								'Name' => $sUploadName,
								'TempName' => $sSavedName,
								'MimeType' => $sMimeType,
								'Size' =>  (int) $iSize
							);
						}
						else
						{
							$mResponse = false;
						}
					}
					else
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileLimit);
					}
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotUploadFileLimit);
			}
			
		}
		else
		{
			$sError = 'auth';
		}

		if (0 < strlen($sError))
		{
			$mResponse['Error'] = $sError;
		}
		
		return $mResponse;
	}

	public function EntryDownloadFile()
	{
		// checkUserRoleIsAtLeast is called in getRawFile
		
		$sHash = (string) \Aurora\System\Application::GetPathItemByIndex(1, '');
		$sAction = (string) \Aurora\System\Application::GetPathItemByIndex(2, '');
		$iOffset = (int) \Aurora\System\Application::GetPathItemByIndex(3, '');
		$iChunkSize = (int) \Aurora\System\Application::GetPathItemByIndex(4, '');

		$aValues = \Aurora\System\Api::DecodeKeyValues($sHash);
		
		$iUserId = isset($aValues['UserId']) ? (int) $aValues['UserId'] : 0;
		$sType = isset($aValues['Type']) ? $aValues['Type'] : '';
		$sPath = isset($aValues['Path']) ? $aValues['Path'] : '';
		$sFileName = isset($aValues['Name']) ? $aValues['Name'] : '';
		$sPublicHash = isset($aValues['PublicHash']) ? $aValues['PublicHash'] : null;
		
		$this->getRawFile($iUserId, $sType, $sPath, $sFileName, $sPublicHash, $sAction, $iOffset, $iChunkSize);
	}

	/**
	 * @api {post} ?/Api/ ViewFile
	 * @apiDescription Views file.
	 * @apiName ViewFile
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=ViewFile} Method Method name
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
			\Aurora\System\Api::getUserPublicIdById($UserId), 
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
	 * 
	 * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFileThumbnail} Method Method name
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
				\Aurora\System\Api::getUserPublicIdById($UserId), 
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
	 * @apiDescription Returns storages available for logged in user.
	 * @apiName GetStorages
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetStorages} Method Method name
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'GetStorages'
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
	 *	Result: [{ Type: "personal", DisplayName: "Personal", IsExternal: false },
	 *		{ Type: "corporate", DisplayName: "Corporate", IsExternal: false },
	 *		{ Type: "google", IsExternal: true, DisplayName: "GoogleDrive" }]
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
	 * Returns storages available for logged in user.
	 * 
	 * @return array {
	 *		*string* **Type** Storage type - personal, corporate.
	 *		*string* **DisplayName** Storage display name.
	 *		*bool* **IsExternal** Indicates if storage external or not.
	 * }
	 */
	public function GetStorages()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return array();
	}
	
	/**
	 * @api {post} ?/Api/ GetQuota
	 * @apiDescription Returns used space and space limit for specified user.
	 * @apiName GetQuota
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetQuota} Method Method name
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* User identifier.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Files',
	 *	Method: 'UpdateAccount',
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
	public function GetQuota($UserId, $Type)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		return [
			'Limit' => 0, 
			'Used' => 0
		];
	}

	/**
	 * @api {post} ?/Api/ GetFiles
	 * @apiDescription Returns file list and user quota information.
	 * @apiName GetFiles

	 * 	 * @apiGroup Files
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=GetFiles} Method Method name
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
	 *	Result: { Items: [{ Id: "image.png", Type: "personal", Path: "", FullPath: "/image.png",
	 * Name: "image.png", Size: 1813, IsFolder: false, IsLink: false, LinkType: "", LinkUrl: "",
	 * LastModified: 1475498855, ContentType: "image/png", Thumb: true, ThumbnailLink: "", OembedHtml: "",
	 * Shared: false, Owner: "", Content: "", IsExternal: false }], Quota: { Used: 21921, Limit: 62914560 } }
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return [];
	}
	
	/**
	 * 
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterGetFiles($aArgs, &$mResult)
	{
		$aItems = [];
		if (is_array($mResult))
		{
			foreach ($mResult as $oItem)
			{
				if ($oItem instanceof Classes\FileItem)
				{
					$aItems[] = $this->Decorator()->PopulateFileItem($aArgs['UserId'], $oItem);
				}
			}
		}
		
		$mResult = array(
			'Items' => $aItems,
			'Quota' => $this->Decorator()->GetQuota($aArgs['UserId'], $aArgs['Type'])
		);
		
	}	
	
	/**
	 * 
	 * @param array $aItems
	 */
	public function PopulateFileItem($UserId, $Item)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		return $Item;
	}
	
	/**
	 * Return content of a file.
	 * 
	 * @param int $UserId
	 * @param string $Type
	 * @param string $Path
	 * @param string $Name
	 */
	public function GetFileContent($UserId, $Type, $Path, $Name) 
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		// File content is obtained in subscribers methods
	}
	
	/**
	 * Return information about file. Subscribers of "Files::GetFileInfo::after" event are used for collecting information.
	 * 
	 * @param int $UserId
	 * @param string $Type
	 * @param string $Path
	 * @param string $Id
	 */
	public function GetFileInfo($UserId, $Type, $Path, $Id) 
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
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
	 *	Result: { Items: [{ Id: "image.png", Type: "personal", Path: "/shared_folder",
	 * FullPath: "/shared_folder/image.png", Name: "image.png", Size: 43549, IsFolder: false,
	 * IsLink: false, LinkType: "", LinkUrl: "", LastModified: 1475500277, ContentType: "image/png",
	 * Thumb: true, ThumbnailLink: "", OembedHtml: "", Shared: false, Owner: "62a6d548-892e-11e6-be21-0cc47a041d39",
	 * Content: "", IsExternal: false }] }
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		return array();
	}	

	/**
	 * @api {post} ?/Api/ CreateFolder
	 * @apiDescription Creates folder.
	 * @apiName CreateFolder
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreateFolder} Method Method name
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
	}

	/**
	 * @api {post} ?/Api/ CreateLink
	 * @apiDescription Creates link.
	 * @apiName CreateLink
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreateLink} Method Method name
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
	 *	Result: { Type: "personal", Path: "", Link: "https://www.youtube.com/watch?v=1WPn4NdQnlg&t=1124s",
	 *		Name: "Endless Numbers counting 90 to 100 - Learn 123 Numbers for Kids" }
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return false;
	}

	/**
	 * @api {post} ?/Api/ Delete
	 * @apiDescription Deletes files and folder specified with list.
	 * @apiName Delete
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Delete} Method Method name
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
	 *	Parameters: '{ Type: "personal", Items: [{ "Path": "", "Name": "2.png" },
	 *		{ "Path": "", "Name": "logo.png" }] }'
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		foreach ($Items as $aItem)
		{
			$oItem = new Classes\FileItem();
			$oItem->Id = $aItem['Name'];
			$oItem->Name = $aItem['Name'];
			$oItem->TypeStr = $Type;
			$oItem->Path = $aItem['Path'];
			
			\Aurora\System\Managers\Response::RemoveThumbFromCache($UserId, $oItem->getHash(), $aItem['Name']);
		}
	}

	/**
	 * @api {post} ?/Api/ Rename
	 * @apiDescription Renames folder, file or link.
	 * @apiName Rename
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Rename} Method Method name
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
	 *	Parameters: '{ Type: "personal", Path: "", Name: "old_name.png", NewName: "new_name.png",
	 *		IsLink: false }'
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		if ($Name === '')
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		$oItem = new Classes\FileItem();
		$oItem->Id = $Name;
		$oItem->Name = $Name;
		$oItem->TypeStr = $Type;
		$oItem->Path = $Path;

		\Aurora\System\Managers\Response::RemoveThumbFromCache($UserId, $oItem->getHash(), $Name);
		
		// Actual renaming is proceeded in subscribed methods. Look for it by "Files::Rename::after"
	}	

	/**
	 * @api {post} ?/Api/ Copy
	 * @apiDescription Copies files and/or folders from one folder to another.
	 * @apiName Copy
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Copy} Method Method name
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
	 *	Parameters: '{ FromType: "personal", ToType: "corporate", FromPath: "", ToPath: "",
	 * Files: [{ Name: "logo.png", IsFolder: false }, { Name: "details.png", IsFolder: false }] }'
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return null;
	}	

	/**
	 * @api {post} ?/Api/ Move
	 * @apiDescription Moves files and/or folders from one folder to another.
	 * @apiName Move
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=Move} Method Method name
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
	 *	Parameters: '{ FromType: "personal", ToType: "corporate", FromPath: "", ToPath: "",
	 *		Files: [{ "Name": "logo.png", "IsFolder": false },
	 *		{ "Name": "details.png", "IsFolder": false }] }'
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		foreach ($Files as $aFile)
		{
			if (!$aFile['IsFolder'])
			{
				$oItem = new Classes\FileItem();
				$oItem->Id = $aFile['Name'];
				$oItem->Name = $aFile['Name'];
				$oItem->TypeStr = $FromType;
				$oItem->Path = $FromPath;
			}
			
			\Aurora\System\Managers\Response::RemoveThumbFromCache($UserId, $oItem->getHash(), $aFile['Name']);
		}
	}	
	
	/**
	 * @api {post} ?/Api/ CreatePublicLink
	 * @apiDescription Creates public link for file or folder.
	 * @apiName CreatePublicLink
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=CreatePublicLink} Method Method name
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return false;
	}	
	
	/**
	 * @api {post} ?/Api/ DeletePublicLink
	 * @apiDescription Deletes public link from file or folder.
	 * @apiName DeletePublicLink
	 * @apiGroup Files
	 * 
	 * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
	 * @apiHeaderExample {json} Header-Example:
	 *	{
	 *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
	 *	}
	 * 
	 * @apiParam {string=Files} Module Module name
	 * @apiParam {string=DeletePublicLink} Method Method name
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
	 *	Method: 'DeletePublicLink',
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return false;
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$mResult = false;
		
		if (substr($Url, 0, 11) === 'javascript:')
		{
			$Url = substr($Url, 11);
		}
		
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
		
		$mResult = false;
		if (is_array($Hashes) && 0 < count($Hashes))
		{
			$mResult = array();
			foreach ($Hashes as $sHash)
			{
				$aData = \Aurora\System\Api::DecodeKeyValues($sHash);
				if (\is_array($aData) && 0 < \count($aData))
				{
					$oFileInfo = self::Decorator()->GetFileInfo($UserId, $aData['Type'], $aData['Path'], $aData['Id']);
					
					$aArgs = array(
						'UserId' => $UserId,
						'Type' => $aData['Type'],
						'Path' => $aData['Path'],
						'Name' => $aData['Name'],
						'Id' => $aData['Id']
					);
					$rFile = false;
					$this->broadcastEvent(
						'GetFile', 
						$aArgs,
						$rFile
					);						
					
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
						
						$aActions = array(
							'view' => array(
								'url' => '?file-cache/' . $aItem['NewHash'] .'/view'
							),
							'download' => array(
								'url' => '?file-cache/' . $aItem['NewHash']
							)
						);
						$aItem['Actions'] = $aActions;

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
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		return $mResult;
	}

	/**
	 * Checks if file exists.
	 *
	 * @param int $UserId
	 * @param string $Type
	 * @param string $Path
	 * @param string $Name
	 */

	public function IsFileExists($UserId, $Type, $Path, $Name)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		return true;
	}
	
	/**
	 * 
	 * @param type $UserId
	 * @param type $Storage
	 * @param type $Path
	 * @param type $Name
	 */
	public function SaveFilesAsTempFiles($UserId, $Files)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$mResult = false;

		if (is_array($Files) && count($Files) > 0)
		{
			$mResult = array();
			foreach ($Files as $aFile)
			{
				$Storage = $aFile['Storage'];
				$Path = $aFile['Path'];
				$Name = $aFile['Name'];
				$Id = $aFile['Id'];
						
				$aArgs = array(
					'UserId' => $UserId,
					'Type' => $Storage,
					'Path' => $Path,
					'Name' => &$Name,
					'Id' => $Id,
					'IsThumb' => false,
					'Offset' => 0,
					'ChunkSize' => 0
				);
				$mFileResource = false;
				$this->broadcastEvent(
					'GetFile', 
					$aArgs,
					$mFileResource
				);			

				if (is_resource($mFileResource)) 
				{
					$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
					try
					{
						$sTempName = md5($sUUID.$Storage.$Path.$Name);

						if (!$this->oApiFileCache->isFileExists($sUUID, $sTempName))
						{
							$this->oApiFileCache->putFile($sUUID, $sTempName, $mFileResource);
						}

						if ($this->oApiFileCache->isFileExists($sUUID, $sTempName))
						{
							$mResult[] = \Aurora\System\Utils::GetClientFileResponse(
								null, $UserId, $Name, $sTempName, $this->oApiFileCache->fileSize($sUUID, $sTempName)
							);
						}
					}
					catch (\Exception $oException)
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::FilesNotAllowed, $oException);
					}
				}
			}
		}
		
		return $mResult;		
	}	
	
	/***** public functions might be called with web API *****/
}
