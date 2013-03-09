<?php
namespace Taurus;

class Taurus
{
    private $context_ = array();
    private $tmp_dir_;

    public static function instance($tmp_dir)
    {
        return new static($tmp_dir);
    }

    public function __construct($tmp_dir)
    {
        $this->tmp_dir_ = $tmp_dir;
    }

    public function input($resource)
    {
        $this->context_['resource'] = $resource;
        return $this;
    }

    public function map(callable $mapper)
    {
        $this->context_['resources'] = call_user_func_array(
            $mapper,
            isset($this->context_['resource']) ?
                array($this->context_['resource']) : array()
        );

        return $this;
    }

    public function reduce(callable $reducer, $num = null)
    {
        $this->context_['pd'] = $pd = new ParallelDo($num);
        $pid = posix_getpid();
        $reduce_result_files = array();

        foreach ($this->context_['resources'] as $key => $resource) {
            $tmp_file = sprintf('%s%d.%s.tmp', $this->tmp_dir_, $pid, $key);
            $reduce_result_files[] = $tmp_file;

            $pd->addTask(function() use($reducer, $resource, $tmp_file) {
                $call_result = call_user_func_array($reducer, array($resource));
                file_put_contents($tmp_file, serialize($call_result));
            });
        }

        $this->context_['reduce_results_files'] = $reduce_result_files;
        $run_result = $pd->run();

        if ($run_result) {
            throw new \RuntimeException('failed to finish reduce tasks');
        }

        return $this;
    }

    public function output(callable $filter)
    {
        $reduce_results = array();

        foreach ($this->context_['reduce_results_files'] as $file) {
            $reduce_results[] = unserialize(file_get_contents($file));
            @unlink($file);
        }

        return call_user_func_array($filter, array($reduce_results));
    }

    public function stop()
    {
        if (isset($this->context_['pd'])) {
            $this->context_['pd']->stop();
        }
    }

    public function __invoke($resource)
    {
        return $this->input($resource);
    }
}
