<?php

namespace app\admin\validation;

use support\validation\Validator;

/**
 * 后台管理基础验证器
 *
 * 子类约定：
 *  - 通过 $rules / $scenes / $messages / $attributes 声明字段规则、场景、错误消息、字段名
 *  - 公共错误消息（required/integer/email/...）由本类提供，子类只需覆盖业务相关消息
 *  - 公共规则（id/page/limit/status/keyword）通过 $commonRules 提供，子类可在 $rules 中复用
 *
 * 控制器用法：
 *   #[Validate(validator: UserValidator::class, scene: 'create')]
 *   public function store(Request $request) { ... }
 */
abstract class BaseValidator extends Validator
{
    /**
     * 通用错误消息（中文友好）。
     *
     * 子类可在 $messages 中覆盖更具体的提示，示例：
     *   'username.required' => '请输入用户名'
     *
     * @var array<string,string>
     */
    protected array $messages = [
        'required'   => ':attribute 不能为空',
        'integer'    => ':attribute 必须是整数',
        'numeric'    => ':attribute 必须是数字',
        'string'     => ':attribute 必须是字符串',
        'array'      => ':attribute 必须是数组',
        'email'      => ':attribute 必须是有效的邮箱地址',
        'phone'      => ':attribute 必须是有效的手机号码',
        'url'        => ':attribute 必须是有效的URL地址',
        'ip'         => ':attribute 必须是有效的IP地址',
        'min'        => ':attribute 不能少于 :min 个字符',
        'max'        => ':attribute 不能超过 :max 个字符',
        'between'    => ':attribute 必须在 :min 到 :max 之间',
        'in'         => ':attribute 的值不在允许范围内',
        'regex'      => ':attribute 格式不正确',
        'unique'     => ':attribute 已存在',
        'exists'     => ':attribute 不存在',
        'date'       => ':attribute 必须是有效的日期',
        'date_format' => ':attribute 日期格式不正确',
        'alpha'      => ':attribute 只能包含字母',
        'alpha_num'  => ':attribute 只能包含字母和数字',
        'alpha_dash' => ':attribute 只能包含字母、数字、下划线和破折号',
    ];

    /**
     * 常用通用规则（子类可在 $rules 中合并使用）。
     *
     * @var array<string,string>
     */
    protected array $commonRules = [
        'id'      => 'integer|min:1',
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'status'  => 'in:0,1',
        'sort'    => 'integer|min:0',
        'keyword' => 'string|max:100',
    ];

    /**
     * 通用分页参数验证规则（用于 pageRules() 复用）。
     *
     * @return array<string,string>
     */
    protected function pageRules(): array
    {
        return [
            'page'    => 'integer|min:1',
            'limit'   => 'integer|min:1|max:100',
            'keyword' => 'string|max:100',
            'status'  => 'in:0,1',
        ];
    }

    /**
     * 通用日期区间规则。
     *
     * @return array<string,string>
     */
    protected function dateRangeRules(): array
    {
        return [
            'start_date' => 'date_format:Y-m-d',
            'end_date'   => 'date_format:Y-m-d',
        ];
    }

    /**
     * 提取与规则字段对应的输入数据（过滤未声明的字段）。
     *
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @return array<string,mixed>
     */
    protected function getValidatedData(array $data, array $rules): array
    {
        $result = [];
        foreach ($rules as $field => $_rule) {
            if (isset($data[$field])) {
                $result[$field] = $data[$field];
            }
        }
        return $result;
    }
}
