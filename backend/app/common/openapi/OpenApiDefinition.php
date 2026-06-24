<?php

namespace app\common\openapi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 全局定义
 *
 * swagger-php 扫描 app/ 目录下的 #[OA\*] 注解生成 OpenAPI 3 文档。
 * 本类集中声明全局信息：API 元数据、服务器、安全方案、通用响应 Schema。
 *
 * 生成文档：
 *   composer openapi              # 生成 public/openapi.json
 *   或 php webman openapi:gen
 *
 * 查看：浏览器打开 /swagger（public/swagger/index.html，读取 openapi.json）
 *
 * 给接口加文档：在控制器方法上加 #[OA\Get]/#[OA\Post] 等注解，
 * 参考 UserController::index 的示例。
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Smart-Admin 后台 API',
    description: '前后端分离后台管理系统接口文档。统一响应格式：{ code, msg, data }。'
)]
#[OA\Server(url: '/', description: '当前服务')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: '登录后获取 token，放入 Authorization: Bearer <token>'
)]
// 统一成功响应（分页/对象通用，data 结构因接口而异）
#[OA\Schema(
    schema: 'ApiSuccess',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'msg', type: 'string', example: '操作成功'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]
)]
// 统一错误响应
#[OA\Schema(
    schema: 'ApiError',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 423),
        new OA\Property(property: 'msg', type: 'string', example: '业务处理失败'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]
)]
// 分页 data 结构
#[OA\Schema(
    schema: 'Pagination',
    properties: [
        new OA\Property(property: 'list', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'total', type: 'integer', example: 100),
        new OA\Property(property: 'page', type: 'integer', example: 1),
        new OA\Property(property: 'limit', type: 'integer', example: 15),
    ]
)]
class OpenApiDefinition
{
}
