<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Override;

use function sprintf;

class MySQL90Platform extends MySQLPlatform
{
    /** @inheritDoc */
    #[Override]
    public function getVectorTypeDeclarationSQL(array $column): string
    {
        $length = '';
        if (isset($column['length'])) {
            $length = sprintf('(%d)', $column['length']);
        }

        return 'VECTOR' . $length;
    }
}
