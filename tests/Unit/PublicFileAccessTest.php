<?php

namespace Aurora\Modules\Files\Tests\Unit;

use Afterlogic\DAV\Server;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Files\Module;
use Aurora\System\Api;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use PHPUnit\Framework\TestCase;

class PublicFileAccessTest extends TestCase
{
    private const OWNER_ID = 42;
    private const OWNER_PUBLIC_ID = 'public-file-owner';

    /** @dataProvider deniedFileRequests */
    public function testPublicFileOwnerLookupDeniesUnsharedResources($share, $type, $path, $name): void
    {
        $this->withShare($share, function () use ($type, $path, $name) {
            $module = (new \ReflectionClass(Module::class))->newInstanceWithoutConstructor();
            $resolveOwner = new \ReflectionMethod(Module::class, 'resolvePublicFileOwner');
            $resolveOwner->setAccessible(true);

            $this->expectException(ApiException::class);
            $this->expectExceptionCode(Notifications::AccessDenied);
            $resolveOwner->invokeArgs($module, ['test-share', $type, &$path, $name]);
        });
    }

    public static function deniedFileRequests(): array
    {
        $fileShare = ['Type' => 'personal', 'Path' => '', 'Name' => 'Published.txt', 'IsFolder' => false];
        $folderShare = ['Type' => 'personal', 'Path' => '', 'Name' => 'Published', 'IsFolder' => true];

        return [
            'different personal file' => [$fileShare, 'personal', '', 'Private.txt'],
            'different storage' => [$fileShare, 'corporate', '', 'Published.txt'],
            'same name in another directory' => [$fileShare, 'personal', '/Other', 'Published.txt'],
            'outside published folder' => [$folderShare, 'personal', '/Other', 'Published.txt'],
            'similarly named folder' => [$folderShare, 'personal', '/Published-archive', 'Published.txt'],
            'invalid path segment' => [$folderShare, 'personal', '/Published/..', 'Published.txt'],
            'revoked link' => [false, 'personal', '', 'Published.txt'],
            'expired link' => [$fileShare + ['ExpireDate' => 1], 'personal', '', 'Published.txt'],
        ];
    }

    /** @dataProvider publishedFileRequests */
    public function testRawDataReadsPublishedResourceAsLinkOwner($share, $path): void
    {
        $this->withShare($share, function () use ($share, $path) {
            $module = $this->getMockBuilder(Module::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['broadcastEvent'])
                ->getMock();
            $module->expects($this->once())->method('broadcastEvent')
                ->willReturnCallback(function ($event, &$arguments, &$result) use ($share, $path) {
                    $this->assertSame('GetFile', $event);
                    $this->assertSame(self::OWNER_ID, $arguments['UserId']);
                    $this->assertSame(self::OWNER_PUBLIC_ID, Server::getUser());
                    $this->assertSame($share['Type'], $arguments['Type']);
                    $this->assertSame($path, $arguments['Path']);
                    $this->assertSame('Published.txt', $arguments['Id']);
                    $result = 'published content';
                });

            $this->assertSame('published content', $module->getRawFileData(
                0,
                $share['Type'],
                $path,
                'Published.txt',
                'test-share',
                'view'
            ));
        });
    }

    public static function publishedFileRequests(): array
    {
        return [
            'root file' => [['Type' => 'personal', 'Path' => '', 'Name' => 'Published.txt'], ''],
            'corporate file' => [['Type' => 'corporate', 'Path' => '/Docs.v2', 'Name' => 'Published.txt'], '/Docs.v2'],
            'published folder descendant' => [
                ['Type' => 'personal', 'Path' => '/Parent', 'Name' => 'Published.v2', 'IsFolder' => true],
                '/Parent/Published.v2/Nested',
            ],
        ];
    }

    public function testViewFilePassesPublishedFileToStorage(): void
    {
        $share = ['Type' => 'personal', 'Path' => '', 'Name' => 'Published.txt', 'IsFolder' => false];
        $this->withShare($share, function () {
            $module = $this->getMockBuilder(Module::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['broadcastEvent'])
                ->getMock();
            $module->expects($this->once())->method('broadcastEvent')
                ->willReturnCallback(function ($event, &$arguments) {
                    $this->assertSame('GetFile', $event);
                    $this->assertSame(self::OWNER_ID, $arguments['UserId']);
                    $this->assertSame('Published.txt', $arguments['Id']);
                });

            $module->ViewFile(self::OWNER_ID, 'personal', '', 'Published.txt', 'test-share');
        });
    }

    public function testFolderListingDeniesPathsOutsidePublishedFolder(): void
    {
        $share = ['Type' => 'personal', 'Path' => '/Parent', 'Name' => 'Published', 'IsFolder' => true];
        $this->withShare($share, function () {
            $module = (new \ReflectionClass(Module::class))->newInstanceWithoutConstructor();

            $this->assertSame([], $module->GetPublicFiles('test-share', '/Parent/Unshared'));
            $this->assertSame([], $module->GetPublicFiles('test-share', '/Parent/Published-archive'));
        });
    }

    private function withShare($share, callable $test): void
    {
        $share = is_array($share) ? $share + ['UserId' => self::OWNER_PUBLIC_ID, '__hash__' => 'test-share'] : false;
        $originalDecorators = Api::$aModuleDecorators;
        $usersCache = new \ReflectionProperty(Api::class, 'usersCache');
        $usersCache->setAccessible(true);
        $originalUsers = $usersCache->getValue();
        $originalDavUser = Server::getUser();
        $originalSkipRole = Api::skipCheckUserRole(true);

        Api::$aModuleDecorators['Min'] = new class ($share) {
            private $share;

            public function __construct($share)
            {
                $this->share = $share;
            }

            public function GetMinByHash($hash)
            {
                return $hash === 'test-share' ? $this->share : false;
            }
        };
        $owner = new User();
        $owner->Id = self::OWNER_ID;
        $owner->PublicId = self::OWNER_PUBLIC_ID;
        $usersCache->setValue(null, [self::OWNER_ID => $owner]);

        try {
            $test();
        } finally {
            Api::$aModuleDecorators = $originalDecorators;
            $usersCache->setValue(null, $originalUsers);
            Server::setUser($originalDavUser);
            Api::skipCheckUserRole($originalSkipRole);
        }
    }
}
