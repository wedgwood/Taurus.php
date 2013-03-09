<?php
require './ParallelDo.php';
require './Taurus.php';

$result = Taurus\Taurus::instance(__DIR__ . '/')
    ->input('1 22 333 4444 55555 666666 7777777 88888888 999999999 aaaaaaaaaa')
    ->map(function($resource) {
        return explode(' ', $resource);
    })
    ->reduce(function($resource) {
        $ret = array();
        $len = strlen($resource);

        for ($i = 0; $i < $len; ++ $i) {
            if (!isset($ret[$resource[$i]])) {
                $ret[$resource[$i]] = 0;
            }

            ++ $ret[$resource[$i]];
        }

        return $ret;
    }, 5)
    ->output(function($resources) {
        $ret = array();

        foreach ($resources as $resource) {
            foreach ($resource as $key => $value) {
                if (!isset($ret[$key])) {
                    $ret[$key] = 0;
                }

                $ret[$key] += $value;
            }
        }

        return $ret;
    });

print_r($result);
