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
	 * @param CAccount|CHelpdeskUser $iUserId
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sFileName
	 * 
	 * @return string
	 */
	public function generateHashId($iUserId, $sType, $sPath, $sFileName)
	{
		return implode('|', array($iUserId, $sType, $sPath, $sFileName));
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
		if ($iUserId) 
		{
			if (!$this->initialized) 
			{
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
			
			if ($sType === \EFileStorageTypeStr::Personal) 
			{
				$oDirectory = new \Afterlogic\DAV\FS\RootPersonal($sRootPath);
			} 
			else if ($sType === \EFileStorageTypeStr::Corporate) 
			{
				$oDirectory = new \Afterlogic\DAV\FS\RootPublic($sRootPath);
			} 
			else if 
			($sType === \EFileStorageTypeStr::Shared) 
			{
				$oDirectory = new \Afterlogic\DAV\FS\RootShared($sRootPath);
			}
			
			if ($oDirectory) 
			{
				$oDirectory->setUser($iUserId);
				if ($sPath !== '') 
				{
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

		$sID = $this->generateHashId($iUserId, $sType, $sPath, $sName);
		$oMin = $this->getApiMinManager();
		$mMin = $oMin->getMinByID($sID);
		if (!empty($mMin['__hash__']))
		{
			$mResult = $mMin['__hash__'];
		}
		else
		{
			$mResult = $oMin->createMin(
				$sID, 
				array(
					'UserId' => $iUserId,
					'Type' => $sType, 
					'Path' => $sPath, 
					'Name' => $sName,
					'Size' => $sSize,
					'IsFolder' => $bIsFolder
				)
			);
		}
		
		return \api_Utils::GetAppUrl() . '?/pub/files/' . $mResult . '/list';
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
		$oMin = $this->getApiMinManager();

		return $oMin->deleteMinByID(
			$this->generateHashId($iUserId, $sType, $sPath, $sName)
		);
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

				$iThumbnailLimit = $this->oManager->GetModule()->getConfig(
					'MaxFileSizeForMakingThumbnail', 
					1024 * 1024 * 5 // 5MB
				);

				foreach ($aItems as $oValue) 
				{
					$sFilePath = str_replace($sRootPath, '', dirname($oValue->getPath()));
					if ($oValue instanceof Afterlogic\DAV\FS\File)
					{
						$aProps = $oValue->getProperties(array('Owner', 'Shared', 'Name' ,'Link'));
					}
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
						$sID = $this->generateHashId($iUserId, $sType, $sFilePath, $oValue->getName());
						$oItem->IsFolder = true;
					}

					if ($oValue instanceof \Afterlogic\DAV\FS\File)
					{
						$sID = $this->generateHashId($iUserId, $sType, $sFilePath, $oValue->getName());
						$oItem->IsFolder = false;
						$oItem->Size = $oValue->getSize();

						$aPathInfo = pathinfo($oItem->Name);
						if (isset($aPathInfo['extension']) && strtolower($aPathInfo['extension']) === 'url')
						{
							$aUrlFileInfo = $this->parseIniString(stream_get_contents($oValue->get()));
							if ($aUrlFileInfo && isset($aUrlFileInfo['URL']))
							{
								$oItem->IsLink = true;
								$oItem->LinkUrl = $aUrlFileInfo['URL'];
							}
							if (!$oItem->ContentType && isset($aPathInfo['filename']))
							{
								$oItem->ContentType = \api_Utils::MimeContentType($aPathInfo['filename']);
							}							
						}
						else						
						{
							$oItem->ContentType = $oValue->getContentType();
						}
						
						$this->oManager->GetModule()->broadcastEvent(
							'PopulateFileItem', 
							array(),
							$oItem
						);
						
						$oItem->LastModified = $oValue->getLastModified();
						if (!$oItem->ContentType)
						{
							$oItem->ContentType = \api_Utils::MimeContentType($oItem->Name);
						}

						if (\CApi::GetConf('labs.allow-thumbnail', true) && !$oItem->Thumb)
						{
							$oItem->Thumb = $oItem->Size < $iThumbnailLimit && \api_Utils::IsGDImageMimeTypeSuppoted($oItem->ContentType, $oItem->Name);
						}

						$oItem->Iframed = !$oItem->IsFolder && !$oItem->IsLink &&
							\CApi::isIframedMimeTypeSupported($oItem->ContentType, $oItem->Name);
					}
					
					$mMin = $oMin->getMinByID($sID);

					$oItem->Shared = isset($aProps['Shared']) ? $aProps['Shared'] : empty($mMin['__hash__']) ? false : true;
					$oItem->Owner = isset($aProps['Owner']) ? $aProps['Owner'] : $iUserId;
					
					if ($oItem && '.asc' === \strtolower(\substr(\trim($oItem->Name), -4)))
					{
						$mResult = $this->getFile($iUserId, $oItem->Type, $oItem->Path, $oItem->Name);

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
		if ($this->init($iUserId))
		{
			$oDirectory = $this->getDirectory($iUserId, $sType, $sPath);

			if ($oDirectory !== null)
			{
				$sFileName = $sName . '.url';
				
				$oDirectory->createFile(
					$sFileName, 
					"[InternetShortcut]\r\nURL=\"" . $sLink . "\"\r\n"
				);
				$oItem = $oDirectory->getChild($sFileName);
				$oItem->updateProperties(array(
					'Owner' => $iUserId
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
						$sID = $this->generateHashId($iUserId, $sType, $sChildPath, $oChild->getName());
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
								$sNewID = $this->generateHashId($iUserId, $sType, $sNewChildPath, $oChild->getName());
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
				if (strlen($sNewName) < 200)
				{
					$this->updateMin($iUserId, $sType, $sPath, $sName, $sNewName, $oItem);
					$oItem->setName($sNewName);
					return true;
				}
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
							$sID = $this->generateHashId($iUserId, $sFromType, $sChildPath, $oItem->getName());

							$sNewChildPath = substr(dirname($oItemNew->getPath()), strlen($sToRootPath));

							$mMin = $oApiMinManager->getMinByID($sID);
							if (!empty($mMin['__hash__']))
							{
								$sNewID = $this->generateHashId($iUserId, $sToType, $sNewChildPath, $oItemNew->getName());

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
							$sChildNewName = $this->getNonExistentFileName(
									$iUserId, 
									$sToType, 
									$sToPath . '/' . $sNewName, 
									$oChild->getName()
							);
							$this->copy(
								$iUserId, 
								$sFromType, 
								$sToType, 
								$sFromPath . '/' . $sName, 
								$sToPath . '/' . $sNewName, 
								$oChild->getName(), 
								$sChildNewName, 
								$bMove
							);
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
	public function getUserSpaceUsed($iUserId, $aTypes)
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
	public function getNonExistentFileName($oAccount, $iType, $sPath, $sFileName)
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
	 * @param int $iUserId
	 */
	public function clearPrivateFiles($iUserId)
	{
		if ($iUserId)
		{
			$sRootPath = $this->getRootPath($iUserId, \EFileStorageTypeStr::Personal, true);
			api_Utils::RecRmdir($sRootPath);
		}
	}

	/**
	 * @param int $iUserId
	 */
	public function clearCorporateFiles($iUserId)
	{
		// TODO
	}
	
	public function parseIniString($sIniString) 
	{
		$aResult = array(); 
		foreach (explode("\n", $sIniString) as $sLine) 
		{
			$aValues = explode("=", $sLine, 2);
			if (isset($aValues[0], $aValues[1]))
			{
				$aResult[$aValues[0]] = trim(rtrim($aValues[1], "\r"), "\"");
			}
		}
		return $aResult;
	}	
	
}

