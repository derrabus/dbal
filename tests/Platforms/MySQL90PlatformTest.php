<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL90Platform;

class MySQL90PlatformTest extends MySQLPlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQL90Platform();
    }

    public function testCreateVectorUnspecifiedDimensions(): void
    {
        self::assertSame('VECTOR', $this->platform->getVectorTypeDeclarationSQL([]));
    }

    public function testCreateVectorSpecifiedDimensions(): void
    {
        self::assertSame(
            'VECTOR(1536)',
            $this->platform->getVectorTypeDeclarationSQL(['dimensions' => 1536]),
        );
    }
}
