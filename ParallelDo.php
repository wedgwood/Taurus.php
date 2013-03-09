<?php
namespace Taurus;

class ParallelDo
{
    private $pids_  = array();
    private $tasks_ = array();
    private $be_worker_ = false;
    private $continued_ = false;
    private $concurrent_num_ = null;

    public function __construct($concurrent_num = null)
    {
        if ($concurrent_num) {
            $this->setConcurrentNum($concurrent_num);
        }
    }

    public function setConcurrentNum($num)
    {
        $this->concurrent_num_ = $num;
    }

    public function addTask(callable $callback/* ... */)
    {
        $parameters = null;
        $num_of_args = func_num_args();

        if ($num_of_args > 2) {
            $parameters = array_slice(func_get_args(), 1);
        } elseif (2 == $num_of_args) {
            $parameters = func_get_arg(1);
        } else {
            $parameters = array();
        }

        $this->tasks_[] = array($callback, $parameters);
        return $this;
    }

    public function run()
    {
        if (empty($this->tasks_)) {
            return 0;
        }

        $this->continued_ = true;
        $counter = 0;
        $tasks_num = count($this->tasks_);
        $concurrent_num =
            isset($this->concurrent_num_) ?
                $this->concurrent_num_ : $tasks_num;
        $num_of_processes = 0;
        $ret = 0;
        $exception = null;

        while ($this->continued_ && $counter < $tasks_num) {
            $task = $this->tasks_[$counter ++];
            $pid = pcntl_fork();

            if ($pid < 0) {
                // failed to fork
                $exception = new RuntimeException('failed to fork');
                break;
            } elseif ($pid) {
                // parent
                $this->pids_[$pid] = 0;
                $num_of_processes += 1;
            } else {
                // worker
                $this->be_worker_ = true;
                $call_result = null;

                try {
                    $call_result = call_user_func_array($task[0], $task[1]);
                } catch (Exception $e) {
                    $call_result = false;
                }

                exit(false === $call_result ? -1 : 0);
            }

            while ($num_of_processes >= $concurrent_num) {
                $pid = pcntl_wait($status, 0);

                if ($pid < 0) {
                    continue;
                } else {
                    unset($this->pids_[$pid]);
                    -- $num_of_processes;

                    if ($status) {
                        ++ $ret;
                    }
                }

                while (($pid = pcntl_wait($status, WNOHANG)) > 0) {
                    unset($this->pids_[$pid]);
                    -- $num_of_processes;

                    if ($status) {
                        ++ $ret;
                    }
                }
            }
        }

        while ($num_of_processes) {
            $pid = pcntl_wait($status, 0);

            if ($pid < 0) {
                continue;
            } else {
                -- $num_of_processes;

                if (!$exception) {
                    unset($this->pids_[$pid]);

                    if ($status) {
                        ++ $ret;
                    }
                }
            }
        }

        if ($exception) {
            throw $exception;
        }

        return $ret;
    }

    public function stop()
    {
        $this->continued_ = false;

        while ($pid = array_pop($this->pids_)) {
            posix_kill($pid, SIGTERM);
        }
    }

    public function reset()
    {
        $this->tasks_ = array();
        $this->pids_ = array();
        return $this;
    }

    public function __destruct()
    {
        if ($this->be_worker_) {
            return;
        }

        $this->stop();
    }
}
