<?php

namespace app\admin\controller;

use app\admin\service\FileService;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;

/**
 * 文件管理（后台）
 *
 * 路由前缀：/admin/file
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class FileController extends BaseController
{
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->fileService = FileService::getInstance();
    }

    /**
     * 文件分页列表
     */
    #[Get('/file')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->fileService->pageList($request));
    }

    /**
     * 文件统计（按扩展名）
     */
    #[Get('/file/statistics')]
    public function statistics(Request $request): Response
    {
        return $this->success($this->fileService->statistics());
    }

    /**
     * 文件详情
     */
    #[Get('/file/{id:\d+}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->fileService->detail($id));
    }

    /**
     * 下载文件（增加下载次数）
     */
    #[Get('/file/{id:\d+}/download')]
    public function download(Request $request, int $id): Response
    {
        $file     = $this->fileService->incrementDownload($id);
        $filePath = public_path($file->file_path);
        if (!file_exists($filePath)) {
            return $this->notFound('文件不存在');
        }

        return response()->download($filePath, (string) $file->original_name);
    }

    /**
     * 上传通用文件
     */
    #[Post('/file')]
    public function store(Request $request): Response
    {
        return $this->success(
            $this->fileService->upload($request, $this->userId, isImage: false),
            '上传成功'
        );
    }

    /**
     * 上传图片
     */
    #[Post('/file/image')]
    public function uploadImage(Request $request): Response
    {
        return $this->success(
            $this->fileService->upload($request, $this->userId, isImage: true),
            '上传成功'
        );
    }

    /**
     * 删除文件
     */
    #[Delete('/file/{id:\d+}')]
    public function destroy(Request $request, int $id): Response
    {
        $this->fileService->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 批量删除文件
     */
    #[Delete('/file/batch')]
    public function batchDelete(Request $request): Response
    {
        $count = $this->fileService->batchDelete((array) $request->input('ids', []));
        return $this->success(['count' => $count], '删除成功');
    }
}
