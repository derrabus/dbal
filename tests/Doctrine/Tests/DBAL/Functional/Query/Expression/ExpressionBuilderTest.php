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
    public function testSelectStringLiteral(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform) {
            self::markTestSkipped('DB2 does not support SELECTing literals.');
        }

        $qb   = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb->select($expr->literal('foo'));

        if ($platform instanceof OraclePlatform) {
            $qb->from('DUAL');
        }

        $result = $qb->execute();

        self::assertInstanceOf(Result::class, $result);
        self::assertSame('foo', $result->fetchOne());
    }

    public function testSelectIntegerLiteral(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform) {
            self::markTestSkipped('DB2 does not support SELECTing literals.');
        }

        $qb   = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb->select($expr->literal(42, ParameterType::INTEGER));

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof OraclePlatform) {
            $qb->from('DUAL');
        }

        $result = $qb->execute();

        self::assertInstanceOf(Result::class, $result);
        self::assertEquals(42, $result->fetchOne());
    }
}
