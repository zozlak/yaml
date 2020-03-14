<?php

/*
 * The MIT License
 *
 * Copyright 2020 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace zozlak\yaml;

use BadMethodCallException;
use RuntimeException;
use stdClass;

/**
 * Class for merging YAML data.
 * 
 * It's not performant and supports only basic JSON paths but should do the job
 * for merging configuraiton files, etc.
 *
 * @author zozlak
 */
class Yaml {

    /**
     * YAML data parsed to an object
     * 
     * @var object
     */
    private $data;

    /**
     * Creates a YAML data representation.
     * 
     * @param $input source to create the YAML object from
     *   - Yaml class object
     *   - stdClass object
     *   - path to a YAML file
     *   - array
     *   - JSON string
     *   - YAML string
     */
    public function __construct($input) {
        if (is_object($input)) {
            if ($input instanceof self) {
                $this->data = json_decode(json_encode($input->data));
            } elseif ($input instanceof stdClass) {
                $this->data = json_decode(json_encode($input));
            } else {
                throw new BadMethodCallException();
            }
        } elseif (is_array($input)) {
            $this->data = (object) json_decode(json_encode($input));
        } elseif (file_exists($input)) {
            $this->data = (object) json_decode(json_encode(yaml_parse_file($input)));
        } elseif (empty($input)) {
            $this->data = (object) [];
        } else {
            $this->data = json_decode($input);
            if ($this->data === null) {
                $this->data = json_decode(json_encode(yaml_parse($input)));
            }
        }
    }

    /**
     * Merges provided YAML input at a given path.
     * 
     * @param string $input a YAML data source in any of data types accepted by the Yaml class constructor
     * @param string $path place in the Yaml to merge at (by default a root node)
     * @return void
     * @see __construct
     */
    public function merge($input, string $path = '$.'): void {
        if (!$input instanceof self) {
            $input = new Yaml($input);
        }
        if ($path === '$.') {
            $path = '$';
        }
        $this->processLeafs($input->data, $path);
    }

    /**
     * Returns a Yaml value at a given path.
     * 
     * Currently only paths starting at the root node are supported.
     * 
     * @param string $path path
     * @param bool $assoc should results not being scalar value be returned 
     *   as associative array (the default is to return them as objects)
     * @return mixed
     * @throws RuntimeException
     */
    public function get(string $path = '$.', bool $assoc = false) {
        $path = $this->sanitizePath($path);
        $path = $path === '' ? [] : $this->splitPath($path);

        $obj = $this->data;
        $i   = 0;
        while ($i < count($path) && isset($obj->{$path[$i]})) {
            $obj = $obj->{$path[$i]};
            $i++;
        }
        if ($i !== count($path)) {
            throw new RuntimeException('No such path');
        }
        return json_decode(json_encode($obj), $assoc);
    }

    /**
     * Sets a Yaml value at a given path.
     * 
     * Currently only paths starting at the root node are supported.
     * 
     * @param string $path path
     * @param mixed $value value to be set
     * @throws RuntimeException
     */
    public function set(string $path, $value): void {
        $path = $this->sanitizePath($path);
        $path = $this->splitPath($path);

        $obj = $this->data;
        $i   = 0;
        while ($i + 1 < count($path) && isset($obj->{$path[$i]}) && is_object($obj->{$path[$i]})) {
            $obj = $obj->{$path[$i]};
            $i++;
        }

        $lastEl  = array_pop($path);
        $newPath = array_slice($path, $i);
        foreach ($newPath as $i) {
            $obj->$i = new stdClass();
            $obj     = $obj->$i;
        }

        if (!is_object($value)) {
            if (is_array($value)) {
                $value = (object) $value;
            } elseif (!empty($value)) {
                $value = json_decode(json_encode(yaml_parse($value)));
            }
        } else {
            $value = json_decode(json_encode($value));
        }
        $obj->$lastEl = $value;
    }

    /**
     * 
     * @param string $file
     * @param type $encoding
     * @param type $linebreak
     * @return void
     */
    public function writeFile(string $file, $encoding = \YAML_ANY_ENCODING,
                              $linebreak = \YAML_ANY_BREAK): void {
        yaml_emit_file($file, json_decode(json_encode($this->data), true), $encoding, $linebreak);
    }

    /**
     * Provide nice printing.
     * 
     * @return string
     */
    public function __toString(): string {
        return yaml_emit(json_decode(json_encode($this->data), true));
    }

    /**
     * Merges a given object with a current YAML by recursively traversing all 
     * object properties and running the `set()` method on all scalar ones.
     * 
     * @param object $obj object to be merged
     * @param string $path path to merge at
     * @return void
     */
    private function processLeafs(object $obj, string $path): void {
        foreach ($obj as $p => $v) {
            $p = str_replace('.', '\.', $p);
            if (is_object($v)) {
                $this->processLeafs($v, "$path.$p");
            } else {
                $this->set("$path.$p", $v);
            }
        }
    }

    /**
     * Checks it the JSON path matches Yaml class limitations.
     * 
     * @param string $path path to be checked
     * @return string
     * @throws BadMethodCallException
     */
    private function sanitizePath(string $path): string {
        if (substr($path, 0, 2) !== '$.') {
            throw new BadMethodCallException('Only paths beginning at the root node ($.) are supported');
        }
        return substr($path, 2);
    }

    private function splitPath(string $path): array {
        $path = explode('.', str_replace('\.', chr(1), $path));
        foreach ($path as &$i) {
            $i = str_replace(chr(1), '.', $i);
        }
        unset($i);
        return $path;
    }

}
