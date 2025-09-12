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
        $dimensions = '';
        if (isset($column['dimensions'])) {
            $dimensions = sprintf('(%d)', $column['dimensions']);
        }

        return 'VECTOR' . $dimensions;
    }
}
