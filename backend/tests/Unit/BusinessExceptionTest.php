<?php

namespace Tests\Unit;

use app\common\exception\BusinessException;
use app\common\ResponseCode;
use PHPUnit\Framework\TestCase;

/**
 * 业务异常与响应码测试
 */
class BusinessExceptionTest extends TestCase
{
    public function testConflictHelperSetsCorrectCode(): void
    {
        $e = BusinessException::conflict('用户名已存在');
        $this->assertSame(ResponseCode::CONFLICT->value, $e->getBusinessCode());
        $this->assertSame('用户名已存在', $e->getMessage());
    }

    public function testNotFoundHelper(): void
    {
        $e = BusinessException::notFound();
        $this->assertSame(ResponseCode::NOT_FOUND->value, $e->getBusinessCode());
    }

    public function testDefaultCodeIsBusinessFail(): void
    {
        $e = new BusinessException('出错了');
        $this->assertSame(ResponseCode::BUSINESS_FAIL->value, $e->getBusinessCode());
    }

    public function testAcceptsResponseCodeEnum(): void
    {
        $e = new BusinessException('禁用', ResponseCode::FORBIDDEN);
        $this->assertSame(403, $e->getBusinessCode());
    }

    public function testResponseCodeClassification(): void
    {
        $this->assertTrue(ResponseCode::BAD_REQUEST->isClientError());
        $this->assertFalse(ResponseCode::BAD_REQUEST->isServerError());
        $this->assertTrue(ResponseCode::SERVER_ERROR->isServerError());
        $this->assertSame('请求过于频繁，请稍后再试', ResponseCode::TOO_MANY_REQUESTS->label());
    }
}
