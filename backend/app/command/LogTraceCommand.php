<?php

namespace app\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 链路日志检索命令
 *
 * 按 request_id 把一次请求散落在日志里的所有行捞出、按时间排序、可读输出，
 * 配合 Trace 中间件（每条日志带 request_id）与慢查询日志使用。
 *
 * 用法：
 *   php webman log:trace <request_id>
 *   php webman log:trace <request_id> --date=2026-06-24      # 指定某天的日志文件
 *   php webman log:trace <request_id> --raw                  # 输出原始日志行，不格式化
 *   php webman log:trace --slow --date=2026-06-24            # 只列当天慢查询（无需 request_id）
 *
 * request_id 来源：HTTP 响应头 X-Request-Id，或 5xx 响应体的 request_id 字段。
 */
#[AsCommand('log:trace', '按 request_id 检索一次请求的全链路日志')]
class LogTraceCommand extends Command
{
    /** 日志目录 */
    private string $logDir;

    protected function configure(): void
    {
        $this->logDir = runtime_path() . '/logs';
        $this
            ->addArgument('request_id', InputArgument::OPTIONAL, '要检索的 request_id（--slow 模式下可省略）')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, '日志日期 YYYY-MM-DD，默认匹配全部 webman 日志文件')
            ->addOption('slow', null, InputOption::VALUE_NONE, '只列出慢查询（可不带 request_id）')
            ->addOption('raw', null, InputOption::VALUE_NONE, '输出原始日志行，不做格式化')
            ->setHelp(
                "按 request_id 串起一次请求的全部日志。\n\n" .
                "示例：\n" .
                "  php webman log:trace 1a2b3c4d...\n" .
                "  php webman log:trace 1a2b3c4d... --date=2026-06-24\n" .
                "  php webman log:trace --slow --date=2026-06-24\n"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requestId = (string) ($input->getArgument('request_id') ?? '');
        $slowOnly  = (bool) $input->getOption('slow');
        $raw       = (bool) $input->getOption('raw');
        $date      = (string) ($input->getOption('date') ?? '');

        if ($requestId === '' && !$slowOnly) {
            $output->writeln('<error>请提供 request_id，或使用 --slow 列出慢查询。</error>');
            return Command::INVALID;
        }

        $files = $this->resolveFiles($date);
        if ($files === []) {
            $output->writeln("<comment>未找到日志文件（目录：{$this->logDir}）。</comment>");
            return Command::SUCCESS;
        }

        // 收集匹配行：[timestamp, 原始行]
        $rows = [];
        foreach ($files as $file) {
            foreach ($this->readMatchingLines($file, $requestId, $slowOnly) as $line) {
                $rows[] = [$this->extractTimestamp($line), rtrim($line, "\r\n")];
            }
        }

        if ($rows === []) {
            $hint = $slowOnly ? '没有慢查询记录' : "没有匹配 request_id={$requestId} 的日志";
            $output->writeln("<comment>{$hint}。</comment>");
            return Command::SUCCESS;
        }

        // 按时间排序（稳定）
        usort($rows, fn($a, $b) => $a[0] <=> $b[0]);

        $title = $slowOnly ? '慢查询' : "request_id={$requestId}";
        $output->writeln("<info>=== 链路日志（{$title}），共 " . count($rows) . " 条 ===</info>");
        $output->writeln('');

        foreach ($rows as [, $line]) {
            $raw ? $output->writeln($this->escapeForConsole($line)) : $this->renderLine($output, $line);
        }

        return Command::SUCCESS;
    }

    /**
     * 解析要扫描的日志文件列表。
     *
     * @return string[]
     */
    private function resolveFiles(string $date): array
    {
        if ($date !== '') {
            $f = "{$this->logDir}/webman-{$date}.log";
            return is_file($f) ? [$f] : [];
        }
        $files = glob("{$this->logDir}/webman-*.log") ?: [];
        sort($files); // 按文件名（含日期）升序，跨天也能正确串联
        return $files;
    }

    /**
     * 逐行读取并过滤（避免一次性载入大文件）。
     *
     * @return \Generator<string>
     */
    private function readMatchingLines(string $file, string $requestId, bool $slowOnly): \Generator
    {
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return;
        }
        try {
            while (($line = fgets($fh)) !== false) {
                if ($requestId !== '' && !str_contains($line, $requestId)) {
                    continue;
                }
                if ($slowOnly && !str_contains($line, 'Slow query')) {
                    continue;
                }
                yield $line;
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * 从日志行解析时间戳（monolog LineFormatter 默认 [Y-m-d H:i:s] 开头）。
     */
    private function extractTimestamp(string $line): string
    {
        if (preg_match('/^\[([\d\-:\s]+)\]/', $line, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * 格式化输出一行：高亮级别 + 时间，慢查询额外提取耗时。
     */
    private function renderLine(OutputInterface $output, string $line): void
    {
        $level = 'INFO';
        if (preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $m)) {
            $level = $m[1];
        }

        $tag = match ($level) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'error',
            'WARNING'                                  => 'comment',
            default                                    => 'info',
        };

        $time = $this->extractTimestamp($line);
        $body = $this->escapeForConsole($line);

        // 慢查询：突出耗时
        if (str_contains($line, 'Slow query') && preg_match('/"time_ms":([\d.]+)/', $line, $mm)) {
            $output->writeln("<{$tag}>[{$time}] [SLOW {$mm[1]}ms]</{$tag}> {$body}");
            return;
        }

        $output->writeln("<{$tag}>[{$time}] [{$level}]</{$tag}> {$body}");
    }

    /**
     * 转义 Symfony Console 标签字符，避免日志内容里的 < > 被当作标签解析。
     */
    private function escapeForConsole(string $line): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], $line);
    }
}
