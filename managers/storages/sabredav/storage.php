<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @internal
 * 
 * @package Filestorage
 * @subpackage Storages
 */
class CApiFilesSabredavStorage extends CApiFilesStorage
{
	/**
	 * @var bool
	 */
	protected $initialized;
	
	/**
	 * @var $oApiMinManager \CApiMinManager
	 */
	protected $oApiMinManager = null;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(AApiManager &$oManager)
	{
		parent::__construct('sabredav', $oManager);
		
		$this->initialized = false;
	}

	/**
	 * @param CAccount|CHelpdeskUser $oAccount
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sFileName
	 * 
	 * @return string
	 */
	public function generateShareHash($oAccount, $sType, $sPath, $sFileName)
	{
		$sId = '';
		if ($oAccount instanceof CAccount)
		{
			$sId = $oAccount->IdAccount;
		}
		else if ($oAccount instanceof CHelpdeskUser)
		{
			$sId = 'hd/'.$oAccount->IdHelpdeskUser;
		}

		return implode('|', array($sId, $sType, $sPath, $sFileName));
	}
	
	public function getApiMinManager()
	{
		if ($this->oApiMinManager === null)
		{
			
			$oMinModule = \CApi::GetModule('Min');
			if ($oMinModule)
			{
				$this->oApiMinManager = $oMinModule->oApiMinManager;
			}
		}
		
		return $this->oApiMinManager;
	}
	
	/**
	 * @param int $iUserId
	 *
	 * @return bool
	 */
	public function init($iUserId)
	{
		$bResult = false;
		if ($iUserId) {
			if (!$this->initialized) {
				\Afterlogic\DAV\Server::getInstance()->setUser($iUserId);
				$this->initialized = true;
			}
			$bResult = true;
		}
		
		return $bResult;
	}	

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param bool $bUser
	 *
	 * @return string|null
	 */
	protected function getRootPath($iUserId, $sType, $bUser = false)
	{
		$sRootPath = null;
		if ($iUserId)
		{
			$sUser = $bUser ? '/' . $iUserId : '';
			$sRootPath = \CApi::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL . $sUser;

			if ($sType === \EFileStorageTypeStr::Corporate)
			{
				$iTenantId = /*$oAccount ? $oAccount->IdTenant :*/ 0;

				$sTenant = $bUser ? $sTenant = '/' . $iTenantId : '';
				$sRootPath = \CApi::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE . $sTenant;
			}
			else if ($sType === \EFileStorageTypeStr::Shared)
			{
				$sRootPath = \CApi::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_SHARED . $sUser;
			}
		}

		return $sRootPath;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 *
	 * @return Afterlogic\DAV\FS\Directory|null
	 */
	protected function getDirectory($iUserId, $sType, $sPath = '')
	{
		$oDirectory = null;
		
		if ($iUserId)
		{
			$sRootPath = $this->getRootPath($iUserId, $sType);
			
			if ($sType === \EFileStorageTypeStr::Personal) {
				
				$oDirectory = new \Afterlogic\DAV\FS\RootPersonal($sRootPath);
				
			} else if ($sType === \EFileStorageTypeStr::Corporate) {
				
				$oDirectory = new \Afterlogic\DAV\FS\RootPublic($sRootPath);
				
			} else if ($sType === \EFileStorageTypeStr::Shared) {
				
				$oDirectory = new \Afterlogic\DAV\FS\RootShared($sRootPath);
				
			}
			
			if ($oDirectory) {
				
				$oDirectory->setUser($iUserId);
				
				if ($sPath !== '') {
					
					$oDirectory = $oDirectory->getChild($sPath);
					
				}
				
			}
		}

		return $oDirectory;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return bool
	 */
	public function isFileExists($iUserId, $sType, $sPath, $sName)
	{
		$bResult = false;
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null)
			{
				if($oDirectory->childExists($sName))
				{
					$oItem = $oDirectory->getChild($sName);
					if ($oItem instanceof \Afterlogic\DAV\FS\File)
					{
						$bResult = true;
					}
				}
			}
		}
		
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return string|null
	 */
	public function getSharedFile($iUserId, $sType, $sPath, $sName)
	{
		$sResult = null;
		if ($this->init($iUserId))
		{
			$sRootPath = $this->getRootPath($iUserId, $sType, true);
			$FilePath = $sRootPath . '/' . $sPath . '/' . $sName;
			if (file_exists($FilePath))
			{
				$sResult = fopen($FilePath, 'r');
			}
		}
		
		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return CFileStorageItem|null
	 */
	public function getFileInfo($iUserId, $sType, $sPath, $sName)
	{
		$oResult = null;
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null)
			{
				$oItem = $oDirectory->getChild($sName);
				if ($oItem !== null)
				{
					$aProps = $oItem->getProperties(false);
					$oResult = new \CFileStorageItem();
					if (isset($aProps['Owner']))
					{
						$oResult->Owner = $aProps['Owner'];
					}
					$oResult->Path = $sPath;
					$oResult->Type = $sType;
					$oResult->Name = $sName;
					if (isset($aProps['Link']))
					{
        				$oResult->Name = isset($aProps['Name']) ? $aProps['Name'] : $oResult->Name;
						$oResult->IsLink = true;
						$oResult->LinkUrl = $aProps['Link'];
						$oResult->LinkType = (int) $aProps['LinkType'];
					}
				}
			}
		}

		return $oResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 *
	 * @return Afterlogic\DAV\FS\Directory|null
	 */
	public function getDirectoryInfo($iUserId, $sType, $sPath)
	{
		$sResult = null;
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null && $oDirectory instanceof Afterlogic\DAV\FS\Directory)
			{
				$sResult = $oDirectory->getChildrenProperties();
			}
		}

		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return Afterlogic\DAV\FS\File|null
	 */
	public function getFile($iUserId, $sType, $sPath, $sName)
	{
		$sResult = null;
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null)
			{
				$oItem = $oDirectory->getChild($sName);
				if ($oItem !== null)
				{
					$sResult = $oItem->get();
				}
			}
		}

		return $sResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return string|false
	 */
	public function createPublicLink($iUserId, $sType, $sPath, $sName, $sSize, $bIsFolder)
	{
		$mResult = false;

		$sID = implode('|', array($iUserId,	$sType, $sPath, $sName));
		$oMin = $this->getApiMinManager();
		$mMin = $oMin->getMinByID($sID);
		if (!empty($mMin['__hash__']))
		{
			$mResult = $mMin['__hash__'];
		}
		else
		{
			$mResult = $oMin->createMin($sID, array(
					'UserId' => $iUserId,
					'Type' => $sType, 
					'Path' => $sPath, 
					'Name' => $sName,
					'Size' => $sSize,
					'IsFolder' => $bIsFolder
				)
			);
		}
		
		$bServerUseUrlRewrite = \CApi::GetConf('labs.server-use-url-rewrite', false);

		return \api_Utils::GetAppUrl() . '?files-pub=' . $mResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return bool
	 */
	public function deletePublicLink($iUserId, $sType, $sPath, $sName)
	{
		$sID = implode('|', array($iUserId,	$sType, $sPath, $sName));

		$oMin = $this->getApiMinManager();

		return $oMin->deleteMinByID($sID);
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sPattern
	 *
	 * @return array
	 */
	public function getFiles($iUserId, $sType = \EFileStorageTypeStr::Personal, $sPath = '', $sPattern = '')
	{
		$oDirectory = null;
		$aItems = array();
		$aResult = array();
		$oMin = $this->getApiMinManager();
		
		if ($this->init($iUserId))
		{
			$oTenant = null;
			$oApiTenants = false; //\CApi::GetCoreManager('tenants');
			if ($oApiTenants)
			{
				$oTenant = /*(0 < $oAccount->IdTenant) ? $oApiTenants->getTenantById($oAccount->IdTenant) :*/
					$oApiTenants->getDefaultGlobalTenant();
			}

			$sRootPath = $this->getRootPath($iUserId, $sType, true);
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			if ($oDirectory !== null)
			{
				if (!empty($sPattern) || is_numeric($sPattern))
				{
					$aItems = $oDirectory->Search($sPattern);
					$aDirectoryInfo = $oDirectory->getChildrenProperties();
					foreach ($aDirectoryInfo as $oDirectoryInfo)
					{
						if (isset($oDirectoryInfo['Link']) && strpos($oDirectoryInfo['Name'], $sPattern) !== false)
						{
							$aItems[] = new \Afterlogic\DAV\FS\File($oDirectory->getPath() . '/' . $oDirectoryInfo['@Name']);
						}
					}
				}
				else
				{
					$aItems = $oDirectory->getChildren();
				}

				$iThumbnailLimit = 1024 * 1024 * 2; // 2MB

				foreach ($aItems as $oValue) 
				{
					$sFilePath = str_replace($sRootPath, '', dirname($oValue->getPath()));
					$aProps = array();//TODO: $oValue->getProperties(array('Owner', 'Shared', 'Name' ,'Link', 'LinkType'));
					$oItem /*@var $oItem \CFileStorageItem */ = new  \CFileStorageItem();
					
					$oItem->Type = $sType;
					$oItem->TypeStr = $sType;
					$oItem->Path = $sFilePath;
					$oItem->Name = $oValue->getName();
					$oItem->Id = $oValue->getName();
					$oItem->FullPath = $oItem->Name !== '' ? $oItem->Path . '/' . $oItem->Name : $oItem->Path ;

					$sID = '';
					if ($oValue instanceof \Afterlogic\DAV\FS\Directory)
					{
//						$sID = $this->generateShareHash($oAccount, $sType, $sFilePath, $oValue->getName());
						$oItem->IsFolder = true;
					}

					if ($oValue instanceof \Afterlogic\DAV\FS\File)
					{
//						$sID = $this->generateShareHash($oAccount, $sType, $sFilePath, $oValue->getName());
						$oItem->IsFolder = false;
						$oItem->Size = $oValue->getSize();
						$oFileInfo = null;
						$oEmbedFileInfo = null;
								
						if (isset($aProps['Link']))
						{
							$oItem->IsLink = true;
							$iLinkType = api_Utils::GetLinkType($aProps['Link']);
							$oItem->LinkType = $iLinkType;
							$oItem->LinkUrl = $aProps['Link'];
							if (isset($iLinkType) && $oTenant)
							{
								$oEmbedFileInfo = \api_Utils::GetOembedFileInfo($oItem->LinkUrl);
								if(\EFileStorageLinkType::GoogleDrive === $iLinkType)
								{
									$oSocial = $oTenant->getSocialByName('google');
									if ($oSocial)
									{
										$oFileInfo = \api_Utils::GetGoogleDriveFileInfo($aProps['Link'], $oSocial->SocialApiKey);
										if ($oFileInfo)
										{
											$oItem->Name = isset($oFileInfo->title) ? $oFileInfo->title : $oItem->Name;
											$oItem->Size = isset($oFileInfo->fileSize) ? $oFileInfo->fileSize : $oItem->Size;
										}
									}
								}
								else if ($oEmbedFileInfo)
								{
									$oFileInfo = $oEmbedFileInfo;
									$oItem->Name = isset($oFileInfo->title) ? $oFileInfo->title : $oItem->Name;
									$oItem->Size = isset($oFileInfo->fileSize) ? $oFileInfo->fileSize : $oItem->Size;
									$oItem->OembedHtml = isset($oFileInfo->html) ? $oFileInfo->html : $oItem->OembedHtml;
								}
								else/* if (\EFileStorageLinkType::DropBox === (int)$aProps['LinkType'])*/
								{
									if (\EFileStorageLinkType::DropBox === $iLinkType)
									{
										$aProps['Link'] = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $aProps['Link']);
									}
									
									$oItem->Name = isset($aProps['Name']) ? $aProps['Name'] : basename($aProps['Link']);
									$aRemoteFileInfo = \api_Utils::GetRemoteFileInfo($aProps['Link']);

									$oItem->Size = $aRemoteFileInfo['size'];
								}
							}
						}
						else
						{
							$oItem->IsLink = false;
						}
						
						$oItem->LastModified = $oValue->getLastModified();
						$oItem->ContentType = $oValue->getContentType();
						if (!$oItem->ContentType)
						{
							$oItem->ContentType = \api_Utils::MimeContentType($oItem->Name);
						}

						if (\CApi::GetConf('labs.allow-thumbnail', true))
						{
							$iItemLinkType = $oItem->LinkType;
							if ($oItem->IsLink && $iItemLinkType === \EFileStorageLinkType::GoogleDrive && isset($oFileInfo) && isset($oFileInfo->thumbnailLink))
							{
								$oItem->Thumb = true;
								$oItem->ThumbnailLink = $oFileInfo->thumbnailLink;
							}
							else if ($oItem->IsLink && $oEmbedFileInfo)
							{
								$oItem->Thumb = true;
								$oItem->ThumbnailLink = $oFileInfo->thumbnailLink;
							}
							else 
							{
								$oItem->Thumb = $oItem->Size < $iThumbnailLimit && \api_Utils::IsGDImageMimeTypeSuppoted($oItem->ContentType, $oItem->Name);
							}
						}

						$oItem->Iframed = !$oItem->IsFolder && !$oItem->IsLink &&
							\CApi::isIframedMimeTypeSupported($oItem->ContentType, $oItem->Name);

						$oItem->Hash = \CApi::EncodeKeyValues(array(
							'Type' => $sType,
							'Path' => $sFilePath,
							'Name' => $oValue->getName(),
							'FileName' => $oValue->getName(),
							'MimeType' => $oItem->ContentType,
							'Size' => $oValue->getSize(),
							'Iframed' => $oItem->Iframed
						));
					}
					
					$mMin = $oMin->getMinByID($sID);

					$oItem->Shared = isset($aProps['Shared']) ? $aProps['Shared'] : empty($mMin['__hash__']) ? false : true;
					$oItem->Owner = isset($aProps['Owner']) ? $aProps['Owner'] : $iUserId;
					
					if ($oItem && '.asc' === \strtolower(\substr(\trim($oItem->Name), -4)))
					{
						$mResult = $this->getFile($oAccount, $oItem->Type, $oItem->Path, $oItem->Name);

						if (is_resource($mResult))
						{
							$oItem->Content = stream_get_contents($mResult);
						}
					}
					
					$aResult[] = $oItem;
				}
			}
		}
		
		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sFolderName
	 *
	 * @return bool
	 */
	public function createFolder($iUserId, $sType, $sPath, $sFolderName)
	{
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);

			if ($oDirectory !== null)
			{
				$oDirectory->createDirectory($sFolderName);
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sLink
	 * @param string $sName
	 *
	 * @return bool
	 */
	public function createLink($iUserId, $sType, $sPath, $sLink, $sName)
	{
		$iLinkType = \api_Utils::GetLinkType($sLink);
		if (/*\EFileStorageLinkType::Unknown !== $iLinkType && */$this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);

			if ($oDirectory !== null)
			{
				$sFileName = \Sabre\VObject\UUIDUtil::getUUID();
				$oDirectory->createFile($sFileName);
				$oItem = $oDirectory->getChild($sFileName);
				$oItem->updateProperties(array(
					'Owner' => $iUserId,
					'Name' => $sName,
					'Link' => $sLink,
					'LinkType' => $iLinkType
				));
				
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sFileName
	 * @param string $sData
	 *
	 * @return bool
	 */
	public function createFile($iUserId, $sType, $sPath, $sFileName, $sData)
	{
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);

			if ($oDirectory !== null)
			{
				$oDirectory->createFile($sFileName, $sData);
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 *
	 * @return bool
	 */
	public function delete($iUserId, $sType, $sPath, $sName)
	{
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			$oItem = $oDirectory->getChild($sName);
			if ($oItem !== null)
			{
				if ($oItem instanceof \Afterlogic\DAV\FS\Directory)
				{
					$this->updateMin($iUserId, $sType, $sPath, $sName, $sName, $oItem, true);
				}
				$oItem->delete();
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param string $sNewName
	 * @param Afterlogic\DAV\FS\File|Afterlogic\DAV\FS\Directory
	 * @param bool $bDelete Default value is **false**.
	 *
	 * @return bool
	 */
	public function updateMin($iUserId, $sType, $sPath, $sName, $sNewName, $oItem, $bDelete = false)
	{
		if ($iUserId)
		{
			$oApiMinManager = $this->getApiMinManager();

			$sRootPath = $this->getRootPath($iUserId, $sType, true);

			$sOldPath = $sPath . '/' . $sName;
			$sNewPath = $sPath . '/' . $sNewName;

			if ($oItem instanceof \Afterlogic\DAV\FS\Directory)
			{
				foreach ($oItem->getChildren() as $oChild)
				{
					if ($oChild instanceof \Afterlogic\DAV\FS\File)
					{
						$sChildPath = substr(dirname($oChild->getPath()), strlen($sRootPath));
						$sID = $this->generateShareHash($iUserId, $sType, $sChildPath, $oChild->getName());
						if ($bDelete)
						{
							$oApiMinManager->deleteMinByID($sID);
						}
						else
						{
							$mMin = $oApiMinManager->getMinByID($sID);
							if (!empty($mMin['__hash__']))
							{
								$sNewChildPath = $sNewPath . substr($sChildPath, strlen($sOldPath));
								$sNewID = $this->generateShareHash($iUserId, $sType, $sNewChildPath, $oChild->getName());
								$mMin['Path'] = $sNewChildPath;
								$oApiMinManager->updateMinByID($sID, $mMin, $sNewID);
							}					
						}
					}
					if ($oChild instanceof \Afterlogic\DAV\FS\Directory)
					{
						$this->updateMin($iUserId, $sType, $sPath, $sName, $sNewName, $oChild, $bDelete);
					}
				}
			}
		}
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param string $sNewName
	 *
	 * @return bool
	 */
	public function rename($iUserId, $sType, $sPath, $sName, $sNewName)
	{
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			$oItem = $oDirectory->getChild($sName);
			if ($oItem !== null)
			{
				$this->updateMin($iUserId, $sType, $sPath, $sName, $sNewName, $oItem);
				$oItem->setName($sNewName);
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param string $sNewName
	 *
	 * @return bool
	 */
	public function renameLink($iUserId, $sType, $sPath, $sName, $sNewName)
	{
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);
			$oItem = $oDirectory->getChild($sName);

			if ($oItem)
			{
				$oItem->updateProperties(array(
					'Name' => $sNewName
				));
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sFromType
	 * @param string $sToType
	 * @param string $sFromPath
	 * @param string $sToPath
	 * @param string $sName
	 * @param string $sNewName
	 * @param bool $bMove Default value is **false**.
	 *
	 * @return bool
	 */
	public function copy($iUserId, $sFromType, $sToType, $sFromPath, $sToPath, $sName, $sNewName, $bMove = false)
	{
		if ($this->init($iUserId))
		{
			$oApiMinManager = $this->getApiMinManager();

			if (empty($sNewName) && !is_numeric($sNewName))
			{
				$sNewName = $sName;
			}

			$sFromRootPath = $this->getRootPath($iUserId, $sFromType, true);
			$sToRootPath = $this->getRootPath($iUserId, $sToType, true);

			$oFromDirectory = $this->getDirectory($iUserId, $sFromType, $sFromPath);
			$oToDirectory = $this->getDirectory($iUserId, $sToType, $sToPath);

			if ($oToDirectory && $oFromDirectory)
			{
				$oItem = $oFromDirectory->getChild($sName);
				if ($oItem !== null)
				{
					if ($oItem instanceof \Afterlogic\DAV\FS\File)
					{
						$oToDirectory->createFile($sNewName, $oItem->get());

						$oItemNew = $oToDirectory->getChild($sNewName);
						$aProps = $oItem->getProperties(array());
						if (!$bMove)				
						{
							$aProps['Owner'] = $iUserId;
						}
						else
						{
							$sChildPath = substr(dirname($oItem->getPath()), strlen($sFromRootPath));
							$sID = $this->generateShareHash($iUserId, $sFromType, $sChildPath, $oItem->getName());

							$sNewChildPath = substr(dirname($oItemNew->getPath()), strlen($sToRootPath));

							$mMin = $oApiMinManager->getMinByID($sID);
							if (!empty($mMin['__hash__']))
							{
								$sNewID = $this->generateShareHash($iUserId, $sToType, $sNewChildPath, $oItemNew->getName());

								$mMin['Path'] = $sNewChildPath;
								$mMin['Type'] = $sToType;
								$mMin['Name'] = $oItemNew->getName();

								$oApiMinManager->updateMinByID($sID, $mMin, $sNewID);
							}					
						}
						$oItemNew->updateProperties($aProps);
					}
					if ($oItem instanceof \Afterlogic\DAV\FS\Directory)
					{
						$oToDirectory->createDirectory($sNewName);
						$oChildren = $oItem->getChildren();
						foreach ($oChildren as $oChild)
						{
							$sChildNewName = $this->getNonExistingFileName($iUserId, $sToType, $sToPath . '/' . $sNewName, $oChild->getName());
							$this->copy($iUserId, $sFromType, $sToType, $sFromPath . '/' . $sName, $sToPath . '/' . $sNewName, $oChild->getName(), $sChildNewName, $bMove);
						}
					}
					if ($bMove)
					{
						$oItem->delete();
					}
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Returns user used space in bytes for specified storages.
	 * 
	 * @param int $iUserId User identificator.
	 * @param string $aTypes Storage type list. Accepted values in array: **EFileStorageType::Personal**, **EFileStorageType::Corporate**, **EFileStorageType::Shared**.
	 * 
	 * @return int;
	 */
	public function getUserUsedSpace($iUserId, $aTypes)
	{
		$iUsageSize = 0;
		
		if ($iUserId)
		{
			foreach ($aTypes as $sType)
			{
				$sRootPath = $this->getRootPath($iUserId, $sType, true);
				$aSize = \api_Utils::GetDirectorySize($sRootPath);
				$iUsageSize += (int) $aSize['size'];
			}
		}
		
		return $iUsageSize;
	}

	/**
	 * @param CAccount $oAccount
	 * @param int $iType
	 * @param string $sPath
	 * @param string $sFileName
	 *
	 * @return string
	 */
	public function getNonExistingFileName($oAccount, $iType, $sPath, $sFileName)
	{
		$iIndex = 0;
		$sFileNamePathInfo = pathinfo($sFileName);
		$sUploadNameExt = '';
		$sUploadNameWOExt = $sFileName;
		if (isset($sFileNamePathInfo['extension']))
		{
			$sUploadNameExt = '.'.$sFileNamePathInfo['extension'];
		}

		if (isset($sFileNamePathInfo['filename']))
		{
			$sUploadNameWOExt = $sFileNamePathInfo['filename'];
		}

		while ($this->isFileExists($oAccount, $iType, $sPath, $sFileName))
		{
			$sFileName = $sUploadNameWOExt.'_'.$iIndex.$sUploadNameExt;
			$iIndex++;
		}

		return $sFileName;
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function clearPrivateFiles($oAccount)
	{
		if ($oAccount)
		{
			$sRootPath = $this->getRootPath($oAccount, \EFileStorageTypeStr::Personal, true);
			api_Utils::RecRmdir($sRootPath);
		}
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function clearCorporateFiles($oAccount)
	{
	}
}

