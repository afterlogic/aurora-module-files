<?php

namespace Aurora\Modules\Files\Tests\Unit;

use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Files\Module;
use Aurora\System\Api;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use PHPUnit\Framework\TestCase;

class StorageAccessTest extends TestCase
{
    /** @dataProvider listingContexts */
    public function testTenantAdminCannotListAnotherUsersStorage($type, $shared): void
    {
        $admin = new User();
        $admin->Id = 7;
        $admin->Role = UserRole::TenantAdmin;
        $session = new \ReflectionProperty(Api::class, 'oAuthenticatedUser');
        $previous = $session->getValue();
        $session->setValue(null, $admin);
        try {
            $module = (new \ReflectionClass(Module::class))->newInstanceWithoutConstructor();
            $this->expectException(ApiException::class);
            $this->expectExceptionCode(Notifications::AccessDenied);
            $module->GetFiles(8, $type, '', '', $shared);
        } finally {
            $session->setValue(null, $previous);
        }
    }

    public static function listingContexts(): array
    {
        return [['personal', false], ['personal', true], ['favorites', false]];
    }
}
