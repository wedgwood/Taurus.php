# Taurus.php

Taurus.php is a local map reduce tool to utilize multiple cpu cores.

## Usage

These utlity is easy to use.
Your code will split to some steps.

`init` -> `input` -> `map` -> `reduce` -> `output`

    ...
    Taurus\Taurus::instance(__DIR__ . '/') // init. set directory of tmp files
        ->input('1 22 333 4444 55555 666666 7777777 88888888 999999999 aaaaaaaaaa') // input
        ->map(function($resource) { // map
            return explode(' ', $resource);
        })
        ->reduce(function($resource) { // reduce
            $ret = array();
            $len = strlen($resource);

            for ($i = 0; $i < $len; ++ $i) {
                if (!isset($ret[$resource[$i]])) {
                    $ret[$resource[$i]] = 0;
                }

                ++ $ret[$resource[$i]];
            }

            return $ret;
        }, 5) // set the number of concurrent processes
        ->output(function($resources) { // output
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
    ...
