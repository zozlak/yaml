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

namespace zozlak\yaml\tests;

use zozlak\yaml\Yaml;

/**
 * Description of YamlTest
 *
 * @author zozlak
 */
class YamlTest extends \PHPUnit\Framework\TestCase {

    const TESTFILE = __DIR__ . '/sample.yaml';

    public function testFromFile(): void {
        $yaml = new Yaml(self::TESTFILE);
        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml);
    }

    public function testFromString(): void {
        $yaml = new Yaml(file_get_contents(self::TESTFILE));
        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml);

        $yaml = new Yaml('');
        $this->assertEquals("--- []\n...\n", (string) $yaml);

        $yaml = new Yaml('{"x": 123}');
        $this->assertEquals("---\nx: 123\n...\n", (string) $yaml);
    }

    public function testFromObjectArray(): void {
        $yaml1 = new Yaml(file_get_contents(self::TESTFILE));

        $obj = $yaml1->get();
        $this->assertIsObject($obj);
        $arr = $yaml1->get('$.', true);
        $this->assertIsArray($arr);

        $yaml2                 = new Yaml($obj);
        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml2);
        $obj->oai->repoBaseUrl = 123;
        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml2);

        $yaml3 = new Yaml($arr);
        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml3);
    }

    public function testFromYaml(): void {
        $yaml1 = new Yaml(file_get_contents(self::TESTFILE));
        $yaml2 = new Yaml($yaml1);
        $yaml1->set('$.oai.repoBaseUrl', 123);

        $this->assertEquals(file_get_contents(self::TESTFILE), (string) $yaml2);
        $this->assertNotEquals((string) $yaml1, (string) $yaml2);
    }

    public function testUnsupportedPath(): void {
        $yaml = new Yaml(self::TESTFILE);
        $this->expectErrorMessage('Only paths beginning at the root node ($.) are supported');
        $yaml->get('');
    }

    public function testNonexistingPath(): void {
        $yaml = new Yaml(self::TESTFILE);
        $this->expectErrorMessage('No such path');
        $yaml->get('$.oai.bbb.ccc');
    }

    public function testGet(): void {
        $yaml = new Yaml(self::TESTFILE);
        $this->assertEquals('http://127.0.0.1/rest/', $yaml->get('$.oai.repoBaseUrl'));
        $data = yaml_parse('metadataPrefix: oai_dc
schema: http://www.openarchives.org/OAI/2.0/oai_dc.xsd
metadataNamespace: http://www.openarchives.org/OAI/2.0/oai_dc/
class: \acdhOeaw\oai\metadata\DcMetadata');
        $this->assertEquals($data, $yaml->get('$.oai.formats.oai_dc', true));
        $this->assertEquals((object) $data, $yaml->get('$.oai.formats.oai_dc'), false);
        $this->assertEquals((object) $data, $yaml->get('$.oai.formats.oai_dc'));
    }

    public function testGetEscaped(): void {
        $yaml = new Yaml(self::TESTFILE);
        $this->assertEquals('https://www.geonames.org/\2', $yaml->get('$.oai.|^https?://([^\.]*[\.])?geonames[\.]org/([0-9]+)(/\.*)?$|'));
    }

    public function testSet(): void {
        $dataA = ['a' => 12, 'v' => 'abd'];
        $dataO = (object) $dataA;

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix', 123);
        $this->assertEquals(123, $yaml->get('$.oai.formats.acdhdc.metadataPrefix'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix', $dataA);
        $this->assertEquals($dataO, $yaml->get('$.oai.formats.acdhdc.metadataPrefix'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix', $dataO);
        $this->assertEquals($dataO, $yaml->get('$.oai.formats.acdhdc.metadataPrefix'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc', 123);
        $this->assertEquals(123, $yaml->get('$.oai.formats.acdhdc'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc', $dataO);
        $this->assertEquals($dataO, $yaml->get('$.oai.formats.acdhdc'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix.completely.new.path', 123);
        $this->assertEquals(123, $yaml->get('$.oai.formats.acdhdc.metadataPrefix.completely.new.path'));

        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix.completely.new.path', $dataO);
        $this->assertEquals($dataO, $yaml->get('$.oai.formats.acdhdc.metadataPrefix.completely.new.path'));
    }

    public function testSetEscaped(): void {
        $yaml = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.|^https?://([^\.]*[\.])?geonames[\.]org/([0-9]+)(/\.*)?$|', 'foo');
        $this->assertEquals('foo', $yaml->get('$.oai.|^https?://([^\.]*[\.])?geonames[\.]org/([0-9]+)(/\.*)?$|'));
    }

    public function testSetInvariance(): void {
        $data    = (object) ['a' => 12, 'v' => 'abd'];
        $yaml    = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix', $data);
        $data->a = 'xyz';
        $this->assertEquals(12, $yaml->get('$.oai.formats.acdhdc.metadataPrefix.a'));

        $data      = ['a' => 12, 'v' => 'abd'];
        $yaml      = new Yaml(self::TESTFILE);
        $yaml->set('$.oai.formats.acdhdc.metadataPrefix', $data);
        $data['a'] = 'xyz';
        $this->assertEquals(12, $yaml->get('$.oai.formats.acdhdc.metadataPrefix.a'));
    }

    public function testMerge(): void {
        $input1 = '
a:
    x: 123
    "y": 456
b: abc
';
        $input2 = '
a:
    "y": 
        z: 543
c:
    x: 1234
    "y": 4567
';

        $a      = new Yaml($input1);
        $b      = new Yaml($input2);
        $a->merge($b);
        $output = [
            'a' => ['x' => 123, 'y' => ['z' => 543]],
            'b' => 'abc',
            'c' => ['x' => 1234, 'y' => 4567],
        ];
        $this->assertEquals($output, $a->get('$.', true));

        $a      = new Yaml($input1);
        $b      = new Yaml($input2);
        $a->merge($b->get('$.a'), '$.b.foo.bar');
        $output = [
            'a' => ['x' => 123, 'y' => 456],
            'b' => ['foo' => ['bar' => ['y' => ['z' => 543]]]],
        ];
        $this->assertEquals($output, $a->get('$.', true));
    }

    public function testMergeEscaped(): void {
        $a      = new Yaml('a: 1');
        $b      = new Yaml("'|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|': foo");
        $a->merge($b);
        $output = [
            'a'                                                     => 1,
            '|^https?://([^.]*[.])?geonames[.]org/([0-9]+)(/.*)?$|' => 'foo',
        ];
        $this->assertEquals($output, $a->get('$.', true));
    }

    public function testMergeColon(): void {
        $a      = new Yaml('a: 1');
        $b      = new Yaml("b: 'aaa: bbb'");
        $a->merge($b);
        $output = [
            'a' => 1,
            'b' => 'aaa: bbb',
        ];
        $this->assertEquals($output, $a->get('$.', true));
    }

    public function testMergeArray(): void {
        $a      = new Yaml('a: 1');
        $b      = new Yaml('
a:
- x
- z: 2
');
        $a->merge($b);
        $output = ['a' => ['x', ['z' => 2]]];
        $this->assertEquals($output, $a->get('$.', true));
    }

    public function testWriteFile(): void {
        $yaml = new Yaml(self::TESTFILE);
        $yaml->writeFile(__DIR__ . '/out.yaml');
        $this->assertEquals(file_get_contents(self::TESTFILE), file_get_contents(__DIR__ . '/out.yaml'));
    }

    public function testMergeScalar(): void {
        $src = new Yaml('a: "b: 1"');
        $target = new Yaml('');
        $target->merge(new Yaml($src->get('$.a'), true), '$.c');
        $output = ['c' => 'b: 1'];
        $this->assertEquals($output, $target->get('$.', true));

        $src = new Yaml('a: "b: 1"');
        $target = new Yaml('');
        $target->merge(new Yaml($src->get('$.'), true), '$.c');
        $output = ['c' => ['a' => 'b: 1']];
        $this->assertEquals($output, $target->get('$.', true));
    }
}
