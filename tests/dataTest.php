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
        $app->tasks->execute(5);

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 7);
        $this->assertTrue($results[1] === 3);
        $this->assertTrue($results[2] === 5);
    }

    /**
     * 
     */
    public function testMaxExecutionTime()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
            sleep(2);
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        $app->tasks->add('sum', ['a' => 3, 'b' => 4]);
        $app->tasks->add('sum', ['a' => 4, 'b' => 5]);
        $app->tasks->execute(5);

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
        $this->assertTrue($results[2] === 7);

        $results = [];

        $app->tasks->execute(5);

        $this->assertTrue(sizeof($results) === 1);
        $this->assertTrue($results[0] === 9);
    }

    /**
     * 
     */
    public function testAddTasksInTask()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('add-sums', function($data) use (&$results, $app) {
            $results[] = 'add sums done';
            $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
            $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        });
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('add-sums');
        $app->tasks->execute();

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 'add sums done');
        $this->assertTrue($results[1] === 3);
        $this->assertTrue($results[2] === 5);
    }

    /**
     * 
     */
    public function testAddMultiple()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[$data['index']] = $data['a'] + $data['b'];
        });

        $expectedResults = [];
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $taskOptions = [];
            if ($i > 50) {
                $taskOptions['startTime'] = time() - rand(1, 50);
            }
            $tasks[] = [
                'definitionID' => 'sum',
                'data' => ['index' => $i, 'a' => $i, 'b' => $i + 1],
                'options' => $taskOptions
            ];
            $expectedResults[$i] = $i + $i + 1;
        }

        $app->tasks->addMultiple($tasks);
        $app->tasks->execute();

        foreach ($expectedResults as $index => $expectedValue) {
            $this->assertTrue($results[$index] === $expectedValue);
        }
    }

}
