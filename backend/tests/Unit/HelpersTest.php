<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 全局 helper 函数测试（functions.php / helper.php）
 */
class HelpersTest extends TestCase
{
    public function testSafeLikeStripsWildcards(): void
    {
        $this->assertSame('abc', safe_like('a%b_c'));
        $this->assertSame('test', safe_like('te%s_t'));
    }

    public function testSafeLikePattern(): void
    {
        $this->assertSame('%abc%', safe_like_pattern('abc'));
        $this->assertSame('%abc', safe_like_pattern('abc', 'left'));
        $this->assertSame('abc%', safe_like_pattern('abc', 'right'));
        // 通配符注入应被清除
        $this->assertSame('%ab%', safe_like_pattern('a%b'));
    }

    public function testMakePasswordProducesVerifiableHash(): void
    {
        $hash = make_password('secret123');
        $this->assertNotSame('secret123', $hash);
        $this->assertTrue(password_verify('secret123', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    public function testNowDatetimeFormat(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            now_datetime()
        );
    }

    public function testCacheRememberReturnsAndCaches(): void
    {
        $calls = 0;
        $cb = function () use (&$calls) {
            $calls++;
            return ['v' => 42];
        };

        $first  = cache_remember('test_key_' . uniqid(), 60, $cb);
        $this->assertSame(['v' => 42], $first);
        $this->assertSame(1, $calls);
    }

    public function testCacheRememberCachesNull(): void
    {
        $key = 'test_null_' . uniqid();
        $calls = 0;
        $cb = function () use (&$calls) {
            $calls++;
            return null;
        };

        cache_remember($key, 60, $cb);
        cache_remember($key, 60, $cb); // 第二次应命中空值缓存
        $this->assertSame(1, $calls, 'null 结果应被缓存，避免重复回源');
    }
}
