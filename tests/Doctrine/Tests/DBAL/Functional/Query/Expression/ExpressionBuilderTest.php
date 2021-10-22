<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Query\Expression;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Result;
use Doctrine\Tests\DbalFunctionalTestCase;

final class ExpressionBuilderTest extends DbalFunctionalTestCase
{
    /**
     * @param mixed[] $literalArgs
     *
     * @dataProvider provideLiteralArgs
     */
    public function testSelectLiteral(array $literalArgs): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform) {
            self::markTestSkipped('DB2 does not support SELECTing literals.');
        }

        $qb   = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb->select($expr->literal(...$literalArgs));

        if ($platform instanceof OraclePlatform) {
            $qb->from('DUAL');
        }

        $result = $qb->execute();

        self::assertInstanceOf(Result::class, $result);
        self::assertEquals($literalArgs[0], $result->fetchOne());
    }

    /**
     * @return iterable<mixed[]>
     */
    public function provideLiteralArgs(): iterable
    {
        yield 'string' => [['foo']];
        yield 'integer' => [[42, ParameterType::INTEGER]];
    }
}
