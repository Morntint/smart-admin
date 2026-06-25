<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysFile;
use support\Request;
use Throwable;

/**
 * 文件业务服务
 *
 * 负责文件列表/统计/上传/删除的核心逻辑，统一处理：
 *  - 文件大小/扩展名/MIME 校验（多重校验防伪装）
 *  - 文件名清洗（防路径遍历 / XSS）
 *  - 物理文件的安全删除（限定在 uploads 目录内，防越权）
 *  - 错误消息脱敏（不泄露绝对路径）
 *
 * 上传流程：
 *  1. 取参 → 2. 大小校验 → 3. 扩展名校验 → 4. MIME 校验
 *  → 5. 文件名清洗 → 6. 路径生成 → 7. 物理移动 → 8. 落库
 */
class FileService extends BaseService
{
    /** 允许上传的图片扩展名 */
    public const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    /** 图片 MIME 类型白名单（防扩展名伪装） */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
    ];

    /** 默认通用文件大小上限（MB） */
    private const DEFAULT_FILE_SIZE_MB = 10;

    /** 默认图片大小上限（MB） */
    private const DEFAULT_IMAGE_SIZE_MB = 5;

    /** 默认通用文件允许的扩展名（逗号分隔） */
    private const DEFAULT_ALLOWED_EXT = 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip';

    protected string $modelClass = SysFile::class;

    /**
     * 文件分页列表（支持关键字、扩展名、存储类型、日期范围过滤）。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'file_ext'     => $request->get('file_ext', ''),
            'storage_type' => $request->get('storage', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['name', 'original_name']
        );
        $this->applyDateRange(
            $query,
            'created_at',
            (string) $request->get('start_date', ''),
            (string) $request->get('end_date', '')
        );
        return $this->paginate($query, $request);
    }

    /**
     * 文件详情。
     */
    public function detail(int $id): SysFile
    {
        /** @var SysFile $file */
        $file = $this->findOrFail($id, [], '文件不存在');
        return $file;
    }

    /**
     * 文件统计（按扩展名 Top 10 + 总数）。
     *
     * @return array{stats:\Illuminate\Support\Collection,total:array{count:int,size:int}}
     */
    public function statistics(): array
    {
        $stats = SysFile::selectRaw('file_ext, COUNT(*) as count, SUM(file_size) as total_size')
                        ->groupBy('file_ext')
                        ->orderBy('count', 'desc')
                        ->limit(10)
                        ->get();

        $total = SysFile::selectRaw('COUNT(*) as count, SUM(file_size) as total_size')->first();

        return [
            'stats' => $stats,
            'total' => [
                'count' => (int) ($total->count ?? 0),
                'size'  => (int) ($total->total_size ?? 0),
            ],
        ];
    }

    /**
     * 通用文件上传（区分图片与普通文件）。
     *
     * @return array<string,mixed>
     */
    public function upload(Request $request, int $operatorId, bool $isImage = false): array
    {
        $fileKey = $isImage ? 'image' : 'file';
        $file    = $request->file($fileKey);
        if (!$file) {
            throw new BusinessException($isImage ? '请选择要上传的图片' : '请选择要上传的文件');
        }

        // 1. 计算大小上限与允许的扩展名
        if ($isImage) {
            $maxSize    = (int) sysConfig('upload_image_size', self::DEFAULT_IMAGE_SIZE_MB) * 1024 * 1024;
            $allowedExt = self::IMAGE_EXTS;
        } else {
            $maxSize    = (int) sysConfig('upload_file_size', self::DEFAULT_FILE_SIZE_MB) * 1024 * 1024;
            $allowedExt = array_map('trim', explode(',', (string) sysConfig('upload_allowed_ext', self::DEFAULT_ALLOWED_EXT)));
        }

        // 2. 大小校验（move 后は一時ファイルが消えて getSize() が stat 失敗するため、ここでサイズを確保）
        $fileSize = $file->getSize();
        if ($fileSize > $maxSize) {
            throw new BusinessException($isImage ? '图片大小超过限制' : '文件大小超过限制');
        }

        // 3. 扩展名校验
        // 注意：必须用 getUploadExtension()（基于原始上传文件名），
        // 不能用 SplFileInfo::getExtension()——那取的是磁盘临时文件名，无扩展名恒为空。
        $ext = strtolower($file->getUploadExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw new BusinessException($isImage ? '只支持图片文件' : '不支持的文件类型');
        }

        // 4. MIME 校验（防扩展名伪装）
        // 同理用 getUploadMimeType()，Webman\File(SplFileInfo) 无 getMimeType()。
        $mimeType = $file->getUploadMimeType();
        if ($isImage && !in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
            throw new BusinessException('图片文件类型不正确');
        }

        // 5. 文件名清洗（防路径遍历 / XSS）
        // 原始上传文件名用 getUploadName()，Webman\Http\UploadFile 无 getClientFilename()。
        $originalName = $this->sanitizeFilename((string) $file->getUploadName());

        // 6. 构建存储路径（uploads/Y/m/d/uniqid.ext）
        $savePath = 'uploads/' . date('Y/m/d/') . uniqid('', true) . '.' . $ext;
        $absDir   = dirname(public_path($savePath));
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new BusinessException('创建上传目录失败');
        }

        // 7. 物理移动文件
        try {
            $file->move(public_path($savePath));
            chmod(public_path($savePath), 0644);
        } catch (Throwable $e) {
            throw new BusinessException('文件上传失败: ' . $this->sanitizeErrorMessage($e->getMessage()));
        }

        // 8. 落库
        $record = SysFile::create([
            'name'           => pathinfo($savePath, PATHINFO_FILENAME),
            'original_name'  => $originalName,
            'file_path'      => $savePath,
            'file_url'       => '/' . $savePath,
            'file_size'      => $fileSize,
            'file_ext'       => $ext,
            'file_type'      => $mimeType,
            'storage_type'   => SysFile::STORAGE_LOCAL,
            'upload_ip'      => $request->getRealIp(),
            'upload_user_id' => $operatorId,
            'status'         => SysFile::STATUS_NORMAL,
            'created_at'     => $this->now(),
        ]);

        $data = ['url' => '/' . $savePath, 'src' => '/' . $savePath];
        if (!$isImage) {
            $data = array_merge(
                ['id' => $record->id, 'name' => $record->name, 'size' => $record->formatted_size],
                $data
            );
        }
        return $data;
    }

    /**
     * 删除文件（含物理文件清理）。
     */
    public function delete(int $id): void
    {
        /** @var SysFile $file */
        $file = $this->findOrFail($id, [], '文件不存在');
        $this->deletePhysicalFile($file->file_path);
        $file->delete();
    }

    /**
     * 批量删除文件。
     *
     * @param int[] $ids
     */
    public function batchDelete(array $ids): int
    {
        if ($ids === []) {
            throw BusinessException::badRequest('请选择要删除的文件');
        }
        $count = 0;
        /** @var \Illuminate\Database\Eloquent\Collection<int,SysFile> $files */
        $files = SysFile::whereIn('id', $ids)->get();
        $files->each(function (SysFile $file) use (&$count) {
            $this->deletePhysicalFile($file->file_path);
            $file->delete();
            $count++;
        });
        return $count;
    }

    /**
     * 增加下载次数。
     */
    public function incrementDownload(int $id): SysFile
    {
        /** @var SysFile $file */
        $file = $this->findOrFail($id, [], '文件不存在');
        $file->incrementDownload();
        return $file;
    }

    /**
     * 清理文件名（防路径遍历 / XSS）。
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['/', '\\', '..'], '', $filename);
        $filename = preg_replace('/[<>:"|?*]/', '', $filename) ?? '';
        return substr($filename, 0, 255);
    }

    /**
     * 清理错误消息（脱敏处理，避免泄露绝对路径）。
     */
    private function sanitizeErrorMessage(string $message): string
    {
        return preg_replace('/[a-zA-Z]:\\\\[^\s]+|\/[^\s]+/', '[path]', $message) ?? $message;
    }

    /**
     * 安全删除物理文件（限定在 uploads 目录内，防越权）。
     */
    private function deletePhysicalFile(string $relativePath): void
    {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, '\\')) {
            return;
        }
        $abs = public_path($relativePath);
        if (!file_exists($abs)) {
            return;
        }

        $realAbs    = realpath($abs);
        $realUpload = realpath(public_path('uploads'));
        if ($realAbs !== false && $realUpload !== false && str_starts_with($realAbs, $realUpload)) {
            @unlink($abs);
        }
    }
}
