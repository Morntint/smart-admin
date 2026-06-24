<?php

namespace Tests\Unit;

use app\common\support\Snowflake;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * 雪花 ID 生成器测试
 */
class SnowflakeTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('SNOWFLAKE_WORKER_ID=1');
        // 重置单例，确保读到上面的 worker id
        $ref = new ReflectionClass(Snowflake::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testGeneratesPositiveId(): void
    {
        $id = Snowflake::next();
        $this->assertGreaterThan(0, $id);
        $this->assertIsInt($id);
    }

    public function testIdsAreUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 5000; $i++) {
            $ids[] = Snowflake::next();
        }
        $this->assertCount(5000, array_unique($ids), 'ID 应全部唯一');
    }

    public function testIdsAreMonotonicallyIncreasing(): void
    {
        $prev = Snowflake::next();
        for ($i = 0; $i < 1000; $i++) {
            $next = Snowflake::next();
            $this->assertGreaterThan($prev, $next, 'ID 应趋势递增');
            $prev = $next;
        }
    }

    public function testInvalidWorkerIdThrows(): void
    {
        putenv('SNOWFLAKE_WORKER_ID=99999'); // 超出 0~1023
        $ref = new ReflectionClass(Snowflake::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        Snowflake::next();
    }

    protected function tearDown(): void
    {
        putenv('SNOWFLAKE_WORKER_ID');
    }
}
