<?php

class FilesModule extends AApiModule
{
	public $oApiFilesManager = null;
	
	protected $oMinModuleDecorator = null;
	
	public function init() 
	{
		$this->oApiFilesManager = $this->GetManager('main', 'sabredav');
		
		$this->AddEntry('files-pub', 'EntryFilesPub');
	}
	
	public function GetAppData($oUser = null)
	{
		return array(
			'EnableModule' => true, // AppData.User.FilesEnable
			'AllowCollaboration' => true, // AppData.User.IsCollaborationSupported
			'AllowSharing' => true, // AppData.User.AllowFilesSharing
			'PublicHash' => '', // AppData.FileStoragePubHash
			'PublicName' => '', // AppData.FileStoragePubParams.Name
			'UploadSizeLimitMb' => 0 // AppData.App.FileSizeLimit
		);
	}
	
	public function GetMinModuleDecorator()
	{
		if ($this->oMinModuleDecorator === null)
		{
			$this->oMinModuleDecorator = \CApi::GetModuleDecorator('Min');
		}
		
		return $this->oMinModuleDecorator;
	}
	
	private function GetRawFile($sType, $sPath, $sName, $sFileName, $sAuthToken, $bDownload = true, $bThumbnail = false)
	{
		if ($bThumbnail)
		{
//			\CApiResponseManager::verifyCacheByKey($sRawKey);
		}

		$sHash = ""; // TODO: 
		$oModuleDecorator = $this->GetMinModuleDecorator();
		$mMin = ($oModuleDecorator) ? $oModuleDecorator->GetMinByHash($sHash) : array();
		
		$iUserId = (!empty($mMin['__hash__'])) ? $mMin['UserId'] : \CApi::getLogginedUserId($sAuthToken);

		$oTenant = null;

/*		$oApiTenants = \CApi::GetCoreManager('tenants');
		if ($iUserId && $oApiTenants) {
			$oTenant = (0 < $iUserId->IdTenant) ? $oApiTenants->getTenantById($iUserId->IdTenant) :
				$oApiTenants->getDefaultGlobalTenant();
		}
 * 
 */
		
		if ($this->oApiCapabilityManager->isFilesSupported($iUserId) && 
				/*$oTenant &&*/ isset($sType, $sPath, $sName)) {
			
			$mResult = false;
			
			$sFileName = $sName;
			$sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
			
			$oFileInfo = $this->oApiFilesManager->getFileInfo($iUserId, $sType, $sPath, $sName);
			
			
			if ($oFileInfo->IsLink) {
				
				$iLinkType = \api_Utils::GetLinkType($oFileInfo->LinkUrl);

				if (isset($iLinkType)) {
					
					if (\EFileStorageLinkType::GoogleDrive === $iLinkType) {
						
						$oSocial = $oTenant->getSocialByName('google');
						if ($oSocial) {
							
							$oInfo = \api_Utils::GetGoogleDriveFileInfo($oFileInfo->LinkUrl, $oSocial->SocialApiKey);
							$sFileName = isset($oInfo->title) ? $oInfo->title : $sFileName;
							$sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);

							if (isset($oInfo->downloadUrl)) {
								
								$mResult = \MailSo\Base\ResourceRegistry::CreateMemoryResource();
								$this->oHttp->SaveUrlToFile($oInfo->downloadUrl, $mResult); // todo
								rewind($mResult);
							}
						}
					} else/* if (\EFileStorageLinkType::DropBox === (int)$aFileInfo['LinkType'])*/ {
						
						if (\EFileStorageLinkType::DropBox === $iLinkType) {
							
							$oFileInfo->LinkUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $oFileInfo->LinkUrl);
						}
						$mResult = \MailSo\Base\ResourceRegistry::CreateMemoryResource();
						$sFileName = basename($oFileInfo->LinkUrl);
						$sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);
						
						$this->oHttp->SaveUrlToFile($oFileInfo->LinkUrl, $mResult); // todo
						rewind($mResult);
					}
				}
			} else {
				
				$mResult = $this->oApiFilesManager->getFile($iUserId, $sType, $sPath, $sName);
			}
			if (false !== $mResult) {
				
				if (is_resource($mResult)) {
					
//					$sFileName = $this->clearFileName($oFileInfo->Name, $sContentType); // todo
					$sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);
					\CApiResponseManager::OutputHeaders($bDownload, $sContentType, $sFileName);
			
					if ($bThumbnail) {
						
//						$this->cacheByKey($sRawKey);	// todo
						\CApiResponseManager::GetThumbResource($iUserId, $mResult, $sFileName);
					} else if ($sContentType === 'text/html') {
						
						echo(\MailSo\Base\HtmlUtils::ClearHtmlSimple(stream_get_contents($mResult)));
					} else {
						
						\MailSo\Base\Utils::FpassthruWithTimeLimitReset($mResult);
					}
					
					@fclose($mResult);
				}

				return true;
			}
		}

		return false;		
	}
	
	public function UploadFile($Type, $SubPath, $Path, $FileData, $IsExt, $TenantName, $Token, $AuthToken)
	{
		$iUserId = \CApi::getLogginedUserId($AuthToken);
		$oApiFileCacheManager = \CApi::GetCoreManager('filecache');

		$sError = '';
		$aResponse = array();

		if ($iUserId) {
			
			if (is_array($FileData)) {
				
				$sUploadName = $FileData['name'];
				$iSize = (int) $FileData['size'];
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
					$this->oApiFilesManager->createFile($iUserId, $Type, $Path, $sUploadName, $rData, false);

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
	
	public function DownloadFile($Type, $Path, $Name, $FileName, $MimeType, $Size, $Iframed, $AuthToken)
	{
		return $this->GetRawFile($Type, $Path, $Name, $FileName, $AuthToken, true);
	}
	
	public function ViewFile($Type, $Path, $Name, $FileName, $MimeType, $Size, $Iframed, $AuthToken)
	{
		return $this->GetRawFile($Type, $Path, $Name, $FileName, $AuthToken, false);
	}

	public function GetFileThumbnail($Type, $Path, $Name, $FileName, $MimeType, $Size, $Iframed, $AuthToken)
	{
		return $this->GetRawFile($Type, $Path, $Name, $FileName, $AuthToken, false, true);
	}

	public function GetExternalStorages()
	{
		$oResult = array();
		return $oResult; // TODO
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		
		\CApi::Plugin()->RunHook('filestorage.get-external-storages', array($iUserId, &$oResult));

		return $oResult;
	}

	public function GetFiles($Type, $Path, $Pattern)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}

		return array(
			'Items' => $this->oApiFilesManager->getFiles($iUserId, $Type, $Path, $Pattern),
			'Quota' => $this->oApiFilesManager->getQuota($iUserId)
		);
	}	

	public function GetPublicFiles($Hash, $Path)
	{
		$iUserId = null;
		$oResult = array();

		$mMin = \CApi::ExecuteMethod('Min::GetMinByHash', array('Hash' => $Hash));
		if (!empty($mMin['__hash__'])) {
			
			$iUserId = $mMin['UserId'];
			if ($iUserId) {
				
				if (!$this->oApiCapabilityManager->isFilesSupported($iUserId)) {
					
					throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
				}
				$Path =  implode('/', array($mMin['Path'], $mMin['Name']))  . $Path;

				$oResult['Items'] = $this->oApiFilesManager->getFiles($iUserId, $mMin['Type'], $Path);
				$oResult['Quota'] = $this->oApiFilesManager->getQuota($iUserId);
				
			}
		}

		return $oResult;
	}	

	public function GetQuota()
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId)) {
			
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		
		return array(
			'Quota' => $this->oApiFilesManager->getQuota($iUserId)
		);
	}	

	public function CreateFolder($Type, $Path, $FolderName)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId)) {
			
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}

		return $this->oApiFilesManager->createFolder($iUserId, $Type, $Path, $FolderName);
	}
	
	public function CreateLink($Type, $Path, $Link, $Name)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}

		return $this->oApiFilesManager->createLink($iUserId, $Type, $Path, $Link, $Name);
	}
	
	public function Delete($Type, $Path, $Items)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}

		$oResult = false;
		
		foreach ($Items as $oItem)
		{
			$oResult = $this->oApiFilesManager->delete($iUserId, $Type, $oItem['Path'], $oItem['Name']);
		}
		
		return $oResult;
	}	

	public function Rename($Type, $Path, $Name, $NewName, $IsLink)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		
		$NewName = \trim(\MailSo\Base\Utils::ClearFileName($NewName));
		
		$NewName = $this->oApiFilesManager->getNonExistingFileName($iUserId, $Type, $Path, $NewName);
		return $this->oApiFilesManager->rename($iUserId, $Type, $Path, $Name, $NewName, $IsLink);
	}	

	public function Copy($FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}

		$oResult = null;
		
		foreach ($Files as $aItem)
		{
			$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
			if (!$bFolderIntoItself)
			{
				$sNewName = $this->oApiFilesManager->getNonExistingFileName($iUserId, $ToType, $ToPath, $aItem['Name']);
				$oResult = $this->oApiFilesManager->copy($iUserId, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
			}
		}
		return $oResult;
	}	

	public function Move($FromType, $ToType, $FromPath, $ToPath, $Files)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		$oResult = null;
		
		foreach ($Files as $aItem)
		{
			$bFolderIntoItself = $aItem['IsFolder'] && $ToPath === $FromPath.'/'.$aItem['Name'];
			if (!$bFolderIntoItself)
			{
				$NewName = $this->oApiFilesManager->getNonExistingFileName($iUserId, $ToType, $ToPath, $aItem['Name']);
				$oResult = $this->oApiFilesManager->move($iUserId, $FromType, $ToType, $FromPath, $ToPath, $aItem['Name'], $NewName);
			}
		}
		return $oResult;
	}	
	
	public function CreatePublicLink($Type, $Path, $Name, $Size, $IsFolder)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		$IsFolder = $IsFolder === '1' ? true : false;
		
		return $this->oApiFilesManager->createPublicLink($iUserId, $Type, $Path, $Name, $Size, $IsFolder);
	}	
	
	public function DeletePublicLink($sType, $sPath, $sName)
	{
		$iUserId = \CApi::getLogginedUserId();
		if (!$this->oApiCapabilityManager->isFilesSupported($iUserId))
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::FilesNotAllowed);
		}
		
		return $this->oApiFilesManager->deletePublicLink($iUserId, $sType, $sPath, $sName);
	}
	
	/**
	 * @return array
	 */
	public function CheckUrl()
	{
		$iUserId = \CApi::getLogginedUserId();
		$mResult = false;

		if ($iUserId)
		{
			$sUrl = trim($this->getParamValue('Url', ''));

			if (!empty($sUrl))
			{
				$iLinkType = \api_Utils::GetLinkType($sUrl);
				if ($iLinkType === \EFileStorageLinkType::GoogleDrive)
				{
					$oApiTenants = \CApi::GetCoreManager('tenants');
					if ($oApiTenants)
					{
						$oTenant = (0 < $iUserId->IdTenant) ? $oApiTenants->getTenantById($iUserId->IdTenant) :
							$oApiTenants->getDefaultGlobalTenant();
					}
					$oSocial = $oTenant->getSocialByName('google');
					if ($oSocial)
					{
						$oInfo = \api_Utils::GetGoogleDriveFileInfo($sUrl, $oSocial->SocialApiKey);
						if ($oInfo)
						{
							$mResult['Size'] = 0;
							if (isset($oInfo->fileSize))
							{
								$mResult['Size'] = $oInfo->fileSize;
							}
							else
							{
								$aRemoteFileInfo = \api_Utils::GetRemoteFileInfo($sUrl);
								$mResult['Size'] = $aRemoteFileInfo['size'];
							}
							$mResult['Name'] = isset($oInfo->title) ? $oInfo->title : '';
							$mResult['Thumb'] = isset($oInfo->thumbnailLink) ? $oInfo->thumbnailLink : null;
						}
					}
				}
				else
				{
					//$sUrl = \api_Utils::GetRemoteFileRealUrl($sUrl);
					$oInfo = \api_Utils::GetOembedFileInfo($sUrl);
					if ($oInfo)
					{
						$mResult['Size'] = isset($oInfo->fileSize) ? $oInfo->fileSize : '';
						$mResult['Name'] = isset($oInfo->title) ? $oInfo->title : '';
						$mResult['LinkType'] = $iLinkType;
						$mResult['Thumb'] = isset($oInfo->thumbnail_url) ? $oInfo->thumbnail_url : null;
					}
					else
					{
						if (\api_Utils::GetLinkType($sUrl) === \EFileStorageLinkType::DropBox)
						{
							$sUrl = str_replace('?dl=0', '', $sUrl);
						}

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

							if ($sFileExtension === 'htm')
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
								$sTitle = isset($aTitle['1']) ? trim($aTitle['1']) : '';
							}

							$mResult['Name'] = isset($sTitle) && strlen($sTitle)> 0 ? $sTitle : urldecode($sFileName);
							$mResult['Size'] = $aRemoteFileInfo['size'];
						}
					}
				}
			}
		}
		
		return $mResult;
	}	
	
	public function EntryFilesPub()
	{
		$sResult = '';
		
		$sFilesPub = \MailSo\Base\Http::NewInstance()->GetQuery('files-pub');
		$mData = \CApi::ExecuteMethod('Min::GetMinByHash', array('Hash' => $sFilesPub));
		
		if (is_array($mData) && isset($mData['IsFolder']) && $mData['IsFolder'])
		{
			$oApiIntegrator = \CApi::GetCoreManager('integrator');

			if ($oApiIntegrator)
			{
				$oCoreModule = \CApi::GetModule('Core');
				if ($oCoreModule instanceof \AApiModule) {
					$sResult = file_get_contents($oCoreModule->GetPath().'/templates/Index.html');
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
			$sUrl = (bool) \CApi::GetConf('labs.server-use-url-rewrite', false) ? '/download/' : '?/Min/Download/';

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

		return $sResult;
	}
	
	/**
	 * @return array
	 */
	public function MinShare()
	{
		$mData = $this->getParamValue('Result', false);

		if ($mData && isset($mData['__hash__'], $mData['Name'], $mData['Size']))
		{
			$bUseUrlRewrite = (bool) \CApi::GetConf('labs.server-use-url-rewrite', false);			
			$sUrl = '?/Min/Download/';
			if ($bUseUrlRewrite)
			{
				$sUrl = '/download/';
			}
			
			$sUrlRewriteBase = (string) \CApi::GetConf('labs.server-url-rewrite-base', '');
			if (!empty($sUrlRewriteBase))
			{
				$sUrlRewriteBase = '<base href="'.$sUrlRewriteBase.'" />';
			}
		
			return array(
				'Template' => 'templates/FilesPub.html',
				'{{Url}}' => $sUrl.$mData['__hash__'], 
				'{{FileName}}' => $mData['Name'],
				'{{FileSize}}' => \api_Utils::GetFriendlySize($mData['Size']),
				'{{FileType}}' => \api_Utils::GetFileExtension($mData['Name']),
				'{{BaseUrl}}' => $sUrlRewriteBase 
			);
		}
		return false;
	}	

}