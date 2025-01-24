<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Files;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property bool $EnableUploadSizeLimit
 * @property int $UploadSizeLimitMb
 * @property int $UserSpaceLimitMb
 * @property int $TenantSpaceLimitMb
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "EnableUploadSizeLimit" => new SettingsProperty(
                true,
                "bool",
                null,
                "If true, upload file size limit is set",
            ),
            "UploadSizeLimitMb" => new SettingsProperty(
                100,
                "int",
                null,
                "Upload file size limit value, in Mbytes. Additionally to the value supplied here, the actual limitation is affected by PHP configuration values post_max_size and upload_max_filesize - the smallest of these 3 values is applied. Note that webserver may add its own limitations, client_max_body_size in Nginx for example.",
            ),
            "UserSpaceLimitMb" => new SettingsProperty(
                100,
                "int",
                null,
                "Default filestorage disk space quota for new user accounts created",
            ),
            "TenantSpaceLimitMb" => new SettingsProperty(
                1000,
                "int",
                null,
                "With multitenancy enabled, default tenant space quota; with multitenancy disabled, total disk space quota for the installation",
            ),
        ];
    }
}
