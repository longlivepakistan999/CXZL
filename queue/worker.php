<?php
/**
 * 队列消费者 - 后台运行处理扫描任务
 *
 * 使用方式:
 * php queue/worker.php        # 前台运行
 * php queue/worker.php &      # 后台运行
 * nohup php queue/worker.php > /dev/null 2>&1 &  # 守护进程
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\ScanTask;
use App\Services\ScannerService;

class QueueWorker
{
    private int $maxConcurrent;
    private int $sleepInterval;
    private bool $running = true;
    private array $runningTasks = [];

    public function __construct()
    {
        $this->maxConcurrent = (int)setting('concurrent_tasks', 50);
        $this->sleepInterval = 1; // 秒

        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
    }

    /**
     * 启动工作进程
     */
    public function run(): void
    {
        logInfo('队列工作进程启动', ['max_concurrent' => $this->maxConcurrent]);
        echo "队列工作进程启动, 最大并发: {$this->maxConcurrent}\n";

        while ($this->running) {
            // 处理信号
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $this->processTasks();
            sleep($this->sleepInterval);
        }

        logInfo('队列工作进程停止');
        echo "队列工作进程停止\n";
    }

    /**
     * 处理任务
     */
    private function processTasks(): void
    {
        // 获取待处理任务
        $availableSlots = $this->maxConcurrent - count($this->runningTasks);

        if ($availableSlots <= 0) {
            return;
        }

        $tasks = ScanTask::getNextBatch($availableSlots);

        if (empty($tasks)) {
            return;
        }

        foreach ($tasks as $task) {
            $this->executeTask($task);
        }
    }

    /**
     * 执行单个任务
     */
    private function executeTask(array $task): void
    {
        $taskId = $task['id'];

        echo "[" . date('Y-m-d H:i:s') . "] 处理任务 #{$taskId}: {$task['domain']}\n";
        logInfo("处理任务", ['task_id' => $taskId, 'domain' => $task['domain']]);

        try {
            $result = ScannerService::executeTask($taskId);

            $status = $result['success'] ? '成功' : '失败';
            echo "[" . date('Y-m-d H:i:s') . "] 任务 #{$taskId} {$status}\n";

            if (!$result['success']) {
                logError("任务执行失败", ['task_id' => $taskId, 'error' => $result['error'] ?? '']);
            }

        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] 任务 #{$taskId} 异常: {$e->getMessage()}\n";
            logError("任务执行异常", ['task_id' => $taskId, 'error' => $e->getMessage()]);
            ScanTask::complete($taskId, false, $e->getMessage());
        }
    }

    /**
     * 处理信号
     */
    public function handleSignal(int $signal): void
    {
        echo "\n收到停止信号 ({$signal}), 正在优雅退出...\n";
        $this->running = false;
    }

    /**
     * 获取队列状态
     */
    public static function getStatus(): array
    {
        return ScanTask::getQueueStats();
    }
}

// 运行工作进程
$worker = new QueueWorker();
$worker->run();
