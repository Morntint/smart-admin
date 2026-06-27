<?php

namespace app\model;

/**
 * 微信菜单模型
 */
class WeChatMenu extends BaseModel
{
    protected $table = 'wechat_menu';

    protected $fillable = [
        'app_type',
        'parent_id',
        'name',
        'type',
        'key',
        'url',
        'appid',
        'pagepath',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * 获取子菜单
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort', 'asc');
    }

    /**
     * 获取树形结构
     *
     * onlyActive=true 时，禁用的父菜单与子菜单都不会出现在结果中
     * （避免发布到微信时把禁用项一并提交）。
     */
    public static function getTree(string $appType = 'official_account', bool $onlyActive = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('app_type', $appType)
            ->with(['children' => function ($q) use ($onlyActive) {
                if ($onlyActive) {
                    $q->where('status', 1);
                }
                $q->orderBy('sort', 'asc');
            }]);

        if ($onlyActive) {
            $query->where('status', 1);
        }

        return $query->where('parent_id', 0)
            ->orderBy('sort', 'asc')
            ->get();
    }

    /**
     * 转换为微信 API 格式
     */
    public static function toWechatFormat(string $appType = 'official_account'): array
    {
        $menus = self::getTree($appType);

        return $menus->map(function ($menu) {
            $button = [
                'name' => $menu->name,
            ];

            if ($menu->children->isNotEmpty()) {
                $button['sub_button'] = $menu->children->map(function ($child) {
                    return self::formatSingleButton($child);
                })->values()->all();
            } else {
                $button = array_merge($button, self::formatSingleButton($menu));
            }

            return $button;
        })->values()->all();
    }

    /**
     * 格式化单个按钮
     */
    protected static function formatSingleButton(self $button): array
    {
        $data = [
            'name' => $button->name,
        ];

        switch ($button->type) {
            case 'click':
                $data['type'] = 'click';
                $data['key'] = $button->key;
                break;

            case 'view':
                $data['type'] = 'view';
                $data['url'] = $button->url;
                break;

            case 'miniprogram':
                $data['type'] = 'miniprogram';
                $data['url'] = $button->url;
                $data['appid'] = $button->appid;
                $data['pagepath'] = $button->pagepath;
                break;

            case 'scancode_push':
            case 'scancode_waitmsg':
            case 'pic_sysphoto':
            case 'pic_photo_or_album':
            case 'pic_weixin':
            case 'location_select':
                $data['type'] = $button->type;
                $data['key'] = $button->key;
                break;
        }

        return $data;
    }
}
