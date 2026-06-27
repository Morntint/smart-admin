<?php

namespace app\admin\controller;

use app\admin\service\WeChat\MiniProgramService;
use app\admin\service\WeChat\WeChatConfigService;
use app\admin\service\WeChat\WeChatMaterialService;
use app\admin\service\WeChat\WeChatMenuService;
use app\admin\service\WeChat\WeChatMessageService;
use app\admin\service\WeChat\WeChatUserService;
use app\common\attribute\RequiresPermission;
use app\common\WeChat\WeChatEventHandler;
use app\common\WeChat\WeChatFactory;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;

/**
 * 微信管理控制器
 *
 * 路由前缀：/admin
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class WeChatController extends BaseController
{
    /**
     * 获取 JSSDK 配置
     */
    #[Get('/wechat/jssdk')]
    #[RequiresPermission('wechat:jssdk:view')]
    public function jssdk(Request $request): Response
    {
        $url = (string) $request->get('url', '');
        $apis = (string) $request->get('apis', '');
        $debug = filter_var($request->get('debug', false), FILTER_VALIDATE_BOOL);

        if ($url === '') {
            return $this->validationFail('URL 不能为空');
        }

        try {
            $app = WeChatFactory::officialAccount();
            $ticket = $app->getTicket();

            $nonceStr = bin2hex(random_bytes(8));
            $timestamp = time();
            $signature = $ticket->configSignature($url, $nonceStr, $timestamp);

            $config = array_merge($signature, [
                'debug'    => $debug,
                'jsApiList'=> $apis !== '' ? explode(',', $apis) : [],
            ]);

            return $this->success($config);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取微信用户列表
     */
    #[Get('/wechat/users')]
    #[RequiresPermission('wechat:user:list')]
    public function getUserList(Request $request): Response
    {
        ['page' => $page, 'limit' => $limit] = $this->pageParams($request, 20);
        $appType = (string) $request->get('app_type', 'official_account');
        $keyword = trim((string) $request->get('keyword', ''));

        $query = \app\model\WeChatUser::where('app_type', $appType);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', "%{$keyword}%")
                    ->orWhere('openid', 'like', "%{$keyword}%");
            });
        }

        $total = (int) $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginate($list, $total, $page, $limit);
    }

    /**
     * 同步微信用户
     */
    #[Post('/wechat/users/sync')]
    #[RequiresPermission('wechat:user:sync')]
    public function syncUsers(Request $request): Response
    {
        $appType = $request->post('app_type', 'official_account');
        $count = WeChatUserService::syncAllUsers($appType);

        return $this->success(['count' => $count], "成功同步 {$count} 个用户");
    }

    /**
     * 获取用户详情
     */
    #[Get('/wechat/user/detail')]
    #[RequiresPermission('wechat:user:list')]
    public function getUserDetail(Request $request): Response
    {
        $openid = trim((string) $request->get('openid', ''));
        $appType = (string) $request->get('app_type', 'official_account');

        if ($openid === '') {
            return $this->validationFail('openid 不能为空');
        }

        $user = \app\model\WeChatUser::findByOpenid($openid, $appType);

        if ($user === null) {
            return $this->notFound('用户不存在');
        }

        return $this->success($user);
    }

    /**
     * 获取消息记录
     */
    #[Get('/wechat/messages')]
    #[RequiresPermission('wechat:message:list')]
    public function getMessageList(Request $request): Response
    {
        ['page' => $page, 'limit' => $limit] = $this->pageParams($request, 20);
        $appType = (string) $request->get('app_type', 'official_account');
        $msgType = (string) $request->get('msg_type', '');

        $query = \app\model\WeChatMessage::where('app_type', $appType);

        if ($msgType !== '') {
            $query->where('msg_type', $msgType);
        }

        $total = (int) $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginate($list, $total, $page, $limit);
    }

    /**
     * 发送模板消息
     */
    #[Post('/wechat/message/send')]
    #[RequiresPermission('wechat:message:send')]
    public function sendTemplateMessage(Request $request): Response
    {
        $openid = trim((string) $request->post('openid', ''));
        $templateId = trim((string) $request->post('template_id', ''));
        $data = (array) $request->post('data', []);
        $url = (string) $request->post('url', '');
        $appType = (string) $request->post('app_type', 'official_account');

        if ($openid === '' || $templateId === '') {
            return $this->validationFail('openid / template_id 不能为空');
        }

        $result = WeChatMessageService::sendTemplateMessage(
            $openid,
            $templateId,
            $data,
            $url,
            null,
            null,
            $appType
        );

        if (is_array($result) && ($result['errcode'] ?? -1) === 0) {
            return $this->success($result, '发送成功');
        }

        $errmsg = is_array($result) ? ($result['errmsg'] ?? '发送失败') : '发送失败：SDK 无响应';
        return $this->error($errmsg);
    }

    /**
     * 获取模板列表（返回结构与前端 ArtTable 一致：{list,total,page,limit}）
     */
    #[Get('/wechat/templates')]
    #[RequiresPermission('wechat:template:list')]
    public function getTemplateList(Request $request): Response
    {
        $appType = (string) $request->get('app_type', 'official_account');

        $list = \app\model\WeChatTemplate::where('app_type', $appType)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        return $this->paginate($list, $list->count(), 1, max($list->count(), 1));
    }

    /**
     * 同步模板列表
     */
    #[Post('/wechat/templates/sync')]
    #[RequiresPermission('wechat:template:sync')]
    public function syncTemplates(Request $request): Response
    {
        $appType = $request->post('app_type', 'official_account');
        $count = WeChatMessageService::syncTemplates($appType);

        return $this->success(['count' => $count], "成功同步 {$count} 个模板");
    }

    /**
     * 获取菜单配置
     */
    #[Get('/wechat/menu')]
    #[RequiresPermission('wechat:menu:list')]
    public function getMenuConfig(Request $request): Response
    {
        $appType = (string) $request->get('app_type', 'official_account');
        $onlyActive = filter_var($request->get('only_active', false), FILTER_VALIDATE_BOOL);
        $menus = WeChatMenuService::getMenuTree($appType, $onlyActive);

        return $this->success($menus);
    }

    /**
     * 保存菜单配置（落库，不发布）
     */
    #[Post('/wechat/menu/save')]
    #[RequiresPermission('wechat:menu:edit')]
    public function saveMenuConfig(Request $request): Response
    {
        $appType = (string) $request->post('app_type', 'official_account');
        $button = (array) $request->post('button', []);

        $count = WeChatMenuService::replaceTree($appType, $button);

        return $this->success(['count' => $count], "已保存 {$count} 个菜单");
    }

    /**
     * 发布菜单
     */
    #[Post('/wechat/menu/publish')]
    #[RequiresPermission('wechat:menu:publish')]
    public function publishMenu(Request $request): Response
    {
        $appType = (string) $request->post('app_type', 'official_account');
        $button = (array) $request->post('button', []);

        // 若提交了 button，则先持久化再发布；保持本地与微信一致
        if ($button !== []) {
            WeChatMenuService::replaceTree($appType, $button);
        }

        $result = WeChatMenuService::publishMenus($appType);

        if (is_array($result) && (($result['errcode'] ?? 0) === 0)) {
            return $this->success($result, '发布成功');
        }

        $errmsg = is_array($result) ? ($result['errmsg'] ?? '发布失败') : '发布失败：SDK 无响应';
        return $this->error($errmsg);
    }

    /**
     * 获取素材列表
     */
    #[Get('/wechat/materials')]
    #[RequiresPermission('wechat:material:list')]
    public function getMaterialList(Request $request): Response
    {
        ['page' => $page, 'limit' => $limit] = $this->pageParams($request, 20);
        $appType = (string) $request->get('app_type', 'official_account');
        $type = (string) $request->get('type', 'image');

        $query = \app\model\WeChatMaterial::where('app_type', $appType)
            ->where('type', $type);

        $total = (int) $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->paginate($list, $total, $page, $limit);
    }

    /**
     * 同步素材
     */
    #[Post('/wechat/materials/sync')]
    #[RequiresPermission('wechat:material:sync')]
    public function syncMaterials(Request $request): Response
    {
        $appType = (string) $request->post('app_type', 'official_account');
        $type = $request->post('type'); // 可选：仅同步某一类型
        $count = WeChatMaterialService::syncMaterials($appType, $type ? (string) $type : null);

        return $this->success(['count' => $count], "成功同步 {$count} 个素材");
    }

    /**
     * 小程序登录（仅返回 openid 与 loginTicket，session_key 不下发）
     */
    #[Post('/wechat/mini/login')]
    public function miniLogin(Request $request): Response
    {
        $code = trim((string) $request->post('code', ''));

        if ($code === '') {
            return $this->validationFail('code 不能为空');
        }

        $result = MiniProgramService::miniLogin($code);

        if (!is_array($result) || empty($result['openid'])) {
            $errmsg = is_array($result) ? ($result['errmsg'] ?? '登录失败') : '登录失败：SDK 无响应';
            return $this->error($errmsg);
        }

        return $this->success($result);
    }

    /**
     * 获取小程序码
     */
    #[Get('/wechat/mini/qrcode')]
    #[RequiresPermission('wechat:mini:qrcode')]
    public function getMiniQRCode(Request $request): Response
    {
        $path = (string) $request->get('path', 'pages/index/index');
        $width = (int) $request->get('width', 430);
        $width = max(280, min(1280, $width));

        $content = MiniProgramService::getQRCode($path, $width);

        if ($content !== null && $content !== '') {
            return response($content, 200, ['Content-Type' => 'image/png']);
        }

        return $this->error('获取小程序码失败');
    }

    /**
     * 获取不限制的小程序码
     */
    #[Get('/wechat/mini/unlimited-qrcode')]
    #[RequiresPermission('wechat:mini:qrcode')]
    public function getMiniUnlimitedQRCode(Request $request): Response
    {
        $scene = trim((string) $request->get('scene', ''));

        if ($scene === '') {
            return $this->validationFail('scene 不能为空');
        }

        $content = MiniProgramService::getUnlimitedQRCode($scene);

        if ($content !== null && $content !== '') {
            return response($content, 200, ['Content-Type' => 'image/png']);
        }

        return $this->error('获取小程序码失败');
    }

    /**
     * 获取微信配置列表（敏感字段已掩码）
     */
    #[Get('/wechat/config')]
    #[RequiresPermission('wechat:config:list')]
    public function getConfig(): Response
    {
        $service = new WeChatConfigService();
        return $this->success($service->groups());
    }

    /**
     * 获取单个敏感字段明文（需独立权限）
     */
    #[Get('/wechat/config/secret')]
    #[RequiresPermission('wechat:config:view-secret')]
    public function getConfigSecret(Request $request): Response
    {
        $key = trim((string) $request->get('key', ''));
        if ($key === '') {
            return $this->validationFail('key 不能为空');
        }

        $service = new WeChatConfigService();
        $value = $service->getSecret($key);

        if ($value === null) {
            return $this->notFound('配置项不存在或不可读取');
        }
        return $this->success(['key' => $key, 'value' => $value]);
    }

    /**
     * 批量更新微信配置
     */
    #[Post('/wechat/config')]
    #[RequiresPermission('wechat:config:edit')]
    public function updateConfig(Request $request): Response
    {
        $data = (array) $request->post();

        $service = new WeChatConfigService();
        $count = $service->batchUpdate($data, $this->userId);

        return $this->success(['count' => $count], "成功更新 {$count} 项配置");
    }

    /**
     * 微信公众号消息回调
     * 注意：此路由在 config/route.php 中单独注册，不经过 Admin 中间件
     */
    public function callback(Request $request): Response
    {
        try {
            $app = WeChatFactory::officialAccount();
            return WeChatEventHandler::handleOfficialServer($app);
        } catch (\Throwable $e) {
            \support\Log::error('[WeChat] 公众号回调异常', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response('success');
        }
    }

    /**
     * 微信小程序消息回调
     * 注意：此路由在 config/route.php 中单独注册，不经过 Admin 中间件
     */
    public function miniCallback(Request $request): Response
    {
        try {
            $app = WeChatFactory::miniProgram();
            return WeChatEventHandler::handleMiniServer($app);
        } catch (\Throwable $e) {
            \support\Log::error('[WeChat] 小程序回调异常', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response('success');
        }
    }
}
