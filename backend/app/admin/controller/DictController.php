<?php

namespace app\admin\controller;

use app\admin\service\DictService;
use app\admin\validation\DictDataValidator;
use app\admin\validation\DictValidator;
use app\common\attribute\RequiresPermission;
use app\model\SysDict;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 字典管理（后台）
 *
 * 路由前缀：/admin/dict、/admin/dict-data
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class DictController extends BaseController
{
    private DictService $dictService;

    public function __construct()
    {
        parent::__construct();
        $this->dictService = DictService::getInstance();
    }

    /**
     * 字典分页列表
     */
    #[Get('/dict')]
    #[Validate(rules: [
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'status'  => 'string|in:0,1',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->dictService->pageList($request));
    }

    /**
     * 所有启用字典（下拉框）
     */
    #[Get('/dict/all')]
    public function all(Request $request): Response
    {
        return $this->success(
            SysDict::where('status', SysDict::STATUS_NORMAL)
                   ->orderBy('id', 'asc')
                   ->get(['id', 'name', 'code', 'type'])
        );
    }

    /**
     * 字典详情（含数据）
     */
    #[Get('/dict/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->dictService->detail($id));
    }

    /**
     * 按编码获取字典数据
     */
    #[Get('/dict/code/{code}')]
    public function getByCode(Request $request, string $code): Response
    {
        return $this->success($this->dictService->dataByCode($code));
    }

    /**
     * 创建字典
     */
    #[Post('/dict')]
    #[RequiresPermission('system:dict:add')]
    #[Validate(validator: DictValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $dict = $this->dictService->create($request->post(), $this->userId);
        return $this->success(['id' => $dict->id], '创建成功');
    }

    /**
     * 更新字典
     */
    #[Put('/dict/{id}')]
    #[RequiresPermission('system:dict:edit')]
    #[Validate(validator: DictValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->dictService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除字典
     */
    #[Delete('/dict/{id}')]
    #[RequiresPermission('system:dict:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->dictService->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 批量获取字典数据
     */
    #[Post('/dict/batch')]
    #[Validate(validator: DictValidator::class, scene: 'batch')]
    public function batch(Request $request): Response
    {
        return $this->success(
            $this->dictService->batchByCodes((array) $request->post('codes', []))
        );
    }

    // -------------------------------------------------------------------------
    // 字典数据管理
    // -------------------------------------------------------------------------

    /**
     * 字典数据分页列表
     */
    #[Get('/dict-data')]
    #[Validate(rules: [
        'dict_id' => 'required|integer|min:1',
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
    ])]
    public function dataIndex(Request $request): Response
    {
        return $this->pageResponse($this->dictService->dataPageList($request));
    }

    /**
     * 创建字典数据
     */
    #[Post('/dict-data')]
    #[RequiresPermission('system:dict:add')]
    #[Validate(validator: DictDataValidator::class, scene: 'create')]
    public function dataStore(Request $request): Response
    {
        $item = $this->dictService->dataCreate($request->post(), $this->userId);
        return $this->success(['id' => $item->id], '创建成功');
    }

    /**
     * 更新字典数据
     */
    #[Put('/dict-data/{id}')]
    #[RequiresPermission('system:dict:edit')]
    #[Validate(validator: DictDataValidator::class, scene: 'update')]
    public function dataUpdate(Request $request, int $id): Response
    {
        $this->dictService->dataUpdate($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除字典数据
     */
    #[Delete('/dict-data/{id}')]
    #[RequiresPermission('system:dict:del')]
    public function dataDestroy(Request $request, int $id): Response
    {
        $this->dictService->dataDelete($id);
        return $this->success(msg: '删除成功');
    }
}
