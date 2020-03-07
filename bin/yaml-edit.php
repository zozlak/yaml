#!/usr/bin/php
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

use zozlak\yaml\Yaml;

require_once __DIR__ . '/../vendor/autoload.php';

$usage = <<<AAA
yaml-edit.php [--src src [--srcPath path] [--targetPath path]]* targetFile
    
    --src src - source data to be merged with the targetFile
            either a path to a YAML file, a YAML string or a JSON string
    --srcPath path - an optional path within the source data (if not provided
            the whole source is used)
    --targetPath path - path to perform marge at in the target file (if not
            provided merge is performed at target file root element)
    targetFile target file to merge against (if it doesn't exist it is assumed 
            it's an empty one)

AAA;

$inputs     = [];
$targetFile = null;
$error      = null;
for ($i = 1; $i < count($argv) && empty($error); $i++) {
    switch ($argv[$i]) {
        case '--src':
            if (isset($input['src'])) {
                $inputs[] = $input;
            }
            $i++;
            $input = ['src' => $argv[$i]];
            break;
        case '--srcPath':
        case '--targetPath':
            $p = substr($argv[$i], 2);
            if (!isset($input['src'])) {
                $error = $argv[$i] . ' without matching --src argument';
                break;
            }
            if (isset($input[$p])) {
                $error = $argv[$i] . ' redeclared for one of sources';
                break;
            }
            $i++;
            $input[$p] = $argv[$i];
            break;
        default:
            if (substr($argv[$i], 0, 2) === '--') {
                $error = 'unknown argument ' . $argv[$i];
                break;
            }
            $targetFile       = $argv[$i];
            $i                = count($argv);
    }
}
if (isset($input['src'])) {
    $inputs[] = $input;
}
if (empty($error)){
    if (count($inputs) === 0) {
        $error = 'No sources';
    } elseif (empty($targetFile)) {
        $error = 'No target file';
    }
}
if (!empty($error)) {
    echo "ERROR - $error\n\nUsage:\n$usage";
}

if (file_exists($targetFile)) {
    $target = new Yaml($targetFile);
} else {
    $target = new Yaml('');
}
foreach ($inputs as $i) {
    $i = (object) $i;
    $yaml = new Yaml($i->src);
    $target->merge($yaml->get($i->srcPath ?? '$.'), $i->targetPath ?? '$.');
}
$target->writeFile($targetFile);
