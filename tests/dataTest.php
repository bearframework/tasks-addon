<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataTest extends BearFrameworkAddonTestCase
{

    /**
     * 
     */
    public function testBasics()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        $app->tasks->execute();

        $this->assertTrue(sizeof($results) === 2);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
    }

    /**
     * 
     */
    public function testStartDate()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['startTime' => time() - 10]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        $app->tasks->add('sum', ['a' => 3, 'b' => 4], ['startTime' => time() - 20]);
        $app->tasks->add('sum', ['a' => 4, 'b' => 5], ['startTime' => time() + 10]);
        $app->tasks->execute();

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 7);
        $this->assertTrue($results[1] === 3);
        $this->assertTrue($results[2] === 5);
    }

}
