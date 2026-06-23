<?php

namespace app\common\traits;

use app\common\ResponseCode;
use support\Response;

/**
 * 统一 API 响应 Trait
 *
 * 在控制器中 use 即可提供 success/error/paginate 等标准响应方法，
 * 与前端约定响应格式为 { code, msg, data, [timestamp] }。
 *
 * 业务异常请直接抛出 BusinessException，由全局 Handler 渲染——
 * 这里的 error/notFound/forbidden 等方法只用于不需要中断流程的场景。
 */
trait ApiResponse
{
    /**
     * 成功响应
     *
     * @param mixed  $data 业务数据
     * @param string $msg  提示信息
     * @param int    $code 业务码（默认 200）
     */
    protected function success(mixed $data = null, string $msg = '操作成功', int $code = ResponseCode::SUCCESS->value): Response
    {
        return $this->json($code, $msg, $data);
    }

    /**
     * 业务错误响应
     *
     * @param string $msg  错误提示
     * @param int    $code 业务码（默认 423 业务失败）
     * @param mixed  $data 附加数据
     */
    protected function error(string $msg = '操作失败', int $code = ResponseCode::BUSINESS_FAIL->value, mixed $data = null): Response
    {
        return $this->json($code, $msg, $data);
    }

    /**
     * 分页响应
     *
     * @param iterable $list  当前页数据
     * @param int      $total 总记录数
     * @param int      $page  当前页码
     * @param int      $limit 每页条数
     */
    protected function paginate(iterable $list, int $total, int $page, int $limit): Response
    {
        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 资源不存在响应（404）
     */
    protected function notFound(string $msg = '数据不存在'): Response
    {
        return $this->error($msg, ResponseCode::NOT_FOUND->value);
    }

    /**
     * 无权限响应（403）
     */
    protected function forbidden(string $msg = '无权限访问'): Response
    {
        return $this->error($msg, ResponseCode::FORBIDDEN->value);
    }

    /**
     * 未登录响应（401）
     */
    protected function unauthorized(string $msg = '请先登录'): Response
    {
        return $this->error($msg, ResponseCode::UNAUTHORIZED->value);
    }

    /**
     * 校验失败响应（422）
     */
    protected function validationFail(string $msg, mixed $data = null): Response
    {
        return $this->error($msg, ResponseCode::VALIDATION_FAIL->value, $data);
    }

    /**
     * 统一构建 JSON 响应
     */
    private function json(int $code, string $msg, mixed $data = null): Response
    {
        $body = ['code' => $code, 'msg' => $msg];
        if ($data !== null) {
            $body['data'] = $data;
        }
        return json($body);
    }
}
