<?php

namespace app\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OpenAPI 文档生成命令
 *
 * 扫描 app/ 目录下的 #[OA\*] 注解，生成 OpenAPI 3 文档到 public/openapi.json，
 * 供 /swagger 页面（public/swagger/index.html）加载展示。
 *
 * 用法：
 *   php webman openapi:gen
 *   php webman openapi:gen --output=public/openapi.json --format=json
 *
 * 依赖：zircote/swagger-php（require-dev）。需先 composer install。
 */
#[AsCommand('openapi:gen', '扫描注解生成 OpenAPI 文档')]
class OpenApiGenCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, '输出文件路径', 'public/openapi.json')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, '输出格式 json|yaml', 'json')
            ->addOption('scan', 's', InputOption::VALUE_REQUIRED, '扫描目录', 'app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!function_exists('OpenApi\\scan') && !class_exists(\OpenApi\Generator::class)) {
            $output->writeln('<error>未安装 zircote/swagger-php，请先执行：composer install</error>');
            return Command::FAILURE;
        }

        $scanDir = base_path() . '/' . ltrim((string) $input->getOption('scan'), '/');
        $format  = strtolower((string) $input->getOption('format')) === 'yaml' ? 'yaml' : 'json';
        $outOpt  = (string) $input->getOption('output');
        $outFile = str_starts_with($outOpt, '/') || preg_match('#^[A-Za-z]:#', $outOpt)
            ? $outOpt
            : base_path() . '/' . $outOpt;

        $output->writeln("<info>扫描目录：</info>{$scanDir}");

        try {
            $openapi = \OpenApi\Generator::scan([$scanDir]);
            $content = $format === 'yaml' ? $openapi->toYaml() : $openapi->toJson();

            $dir = dirname($outFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($outFile, $content);
        } catch (\Throwable $e) {
            $output->writeln('<error>生成失败：' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>已生成：</info>{$outFile}");
        $output->writeln('<info>查看：</info>浏览器打开 /swagger');
        return Command::SUCCESS;
    }
}
