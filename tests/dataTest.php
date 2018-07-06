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
        $app->tasks->run();

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

        $currentTime = time();
        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['startTime' => $currentTime - 10]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        $app->tasks->add('sum', ['a' => 3, 'b' => 4], ['startTime' => $currentTime - 20]);
        $app->tasks->add('sum', ['a' => 4, 'b' => 5], ['startTime' => $currentTime + 10]);
        $app->tasks->run([
            'maxExecutionTime' => 5
        ]);

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 7);
        $this->assertTrue($results[1] === 3);
        $this->assertTrue($results[2] === 5);
    }

    /**
     * 
     */
    public function testGetMinStartDate1()
    {
        $app = $this->getApp();

        $currentTime = time();
        $this->assertTrue($app->tasks->getMinStartTime() === null);
        $app->tasks->add('sum', null, ['startTime' => $currentTime + 10]);
        $this->assertTrue($app->tasks->getMinStartTime() === $currentTime + 10);
        $app->tasks->add('sum', null);
        $this->assertTrue($app->tasks->getMinStartTime() === time());
        $app->tasks->add('sum', null, ['startTime' => $currentTime - 10]);
        $this->assertTrue($app->tasks->getMinStartTime() === $currentTime - 10);
        $app->tasks->add('sum', null, ['startTime' => $currentTime - 20]);
        $this->assertTrue($app->tasks->getMinStartTime() === $currentTime - 20);
    }

    /**
     * 
     */
    public function testGetMinStartDate2()
    {
        $app = $this->getApp();

        $this->assertTrue($app->tasks->getMinStartTime() === null);
        $app->tasks->add('sum', null);
        $this->assertTrue($app->tasks->getMinStartTime() === time());
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
        $app->tasks->run([
            'maxExecutionTime' => 5
        ]);

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
        $this->assertTrue($results[2] === 7);

        $results = [];

        $app->tasks->run([
            'maxExecutionTime' => 5
        ]);

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
        $app->tasks->run();

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
        $app->tasks->run();

        foreach ($expectedResults as $index => $expectedValue) {
            $this->assertTrue($results[$index] === $expectedValue);
        }
    }

    /**
     * 
     */
    public function testMultipleLists()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);

        $app->tasks->add('sum', ['a' => 3, 'b' => 4], ['listID' => 'my', 'id' => 'my1']);
        $app->tasks->add('sum', ['a' => 4, 'b' => 5], ['listID' => 'my']);

        $app->tasks->run();

        $this->assertTrue(sizeof($results) === 2);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);

        $this->assertTrue($app->tasks->exists('my1', 'my'));
        $app->tasks->delete('my1', 'my');
        $this->assertFalse($app->tasks->exists('my1', 'my'));

        $app->tasks->run([
            'listID' => 'my'
        ]);

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
        $this->assertTrue($results[2] === 9);
    }

    /**
     * 
     */
    public function testAddMultipleInMultipleLists()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[$data['index']] = $data['listID'] . ' -> ' . ($data['a'] + $data['b']);
        });

        $expectedResults = [];
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $taskOptions = [];
            $listID = 'list' . ($i % 10);
            $taskOptions['listID'] = $listID;
            $tasks[] = [
                'definitionID' => 'sum',
                'data' => ['index' => $i, 'listID' => $listID, 'a' => $i, 'b' => $i + 1],
                'options' => $taskOptions
            ];
            if (!isset($expectedResults[$listID])) {
                $expectedResults[$listID] = [];
            }
            $expectedResults[$listID][$i] = $listID . ' -> ' . ($i + $i + 1);
        }

        $app->tasks->addMultiple($tasks);

        $counter = 0;
        foreach ($expectedResults as $listID => $expectedResultsInList) {
            $this->assertTrue(sizeof($results) === $counter * 10);
            $app->tasks->run([
                'listID' => $listID
            ]);
            $this->assertTrue(sizeof($results) === ($counter + 1) * 10);
            foreach ($expectedResultsInList as $index => $expectedValue) {
                $this->assertTrue($results[$index] === $expectedValue);
            }
            $counter++;
        }
    }

    /**
     * 
     */
    public function testDifferentDataTypes()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = gettype($data);
        });

        $app->tasks->add('sum', 5);
        $app->tasks->add('sum', 'string');
        $app->tasks->add('sum', []);
        $app->tasks->run();

        $this->assertTrue(sizeof($results) === 3);
        $this->assertTrue($results[0] === 'integer');
        $this->assertTrue($results[1] === 'string');
        $this->assertTrue($results[2] === 'array');
    }

    /**
     * 
     */
    public function testHooks()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $hooksLog = [];
        $app->hooks->add('taskRun', function($definitionID, $taskID, $data) use (&$hooksLog) {
            $hooksLog[] = ['taskRun', $definitionID, $taskID, $data];
        });

        $app->hooks->add('taskRunDone', function($definitionID, $taskID, $data) use (&$hooksLog) {
            $hooksLog[] = ['taskRunDone', $definitionID, $taskID, $data];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['id' => 'sum1']);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3], ['id' => 'sum2']);
        $app->tasks->run();

        $this->assertTrue(sizeof($hooksLog) === 4);
        $this->assertTrue($hooksLog[0] === ['taskRun', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($hooksLog[1] === ['taskRunDone', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($hooksLog[2] === ['taskRun', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
        $this->assertTrue($hooksLog[3] === ['taskRunDone', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
    }

    /**
     * 
     */
    public function testGetStats()
    {
        $app = $this->getApp();
        $app->tasks->define('sum', function($data) {
            
        });

        $currentTime = time();
        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['startTime' => $currentTime - 5]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);

        $stats = $app->tasks->getStats();
        $this->assertTrue($stats['upcomingTasksCount'] === 2);
        $this->assertTrue(sizeof($stats['upcomingTasks']) === 2);
        $this->assertTrue($stats['nextTaskStartTime'] === $currentTime - 5);
    }

    /**
     * 
     */
    public function testExceptionsInTasks()
    {
        $app = $this->getApp();

        $runsCount = 0;
        $app->tasks->define('sum', function() use (&$runsCount) {
            $runsCount++;
            if ($runsCount === 1) {
                throw new \Exception('Custom error 1!');
            }
        });

        $app->tasks->add('sum', []);
        $this->assertTrue($app->tasks->getStats()['upcomingTasksCount'] === 1);

        $exceptionMessage = '';
        try {
            $app->tasks->run([
                'retryTime' => 3
            ]);
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertTrue(strpos($exceptionMessage, 'Custom error 1') !== false);
        $stats = $app->tasks->getStats();
        $this->assertTrue($app->tasks->getStats()['upcomingTasksCount'] === 1);
        sleep(5);
        $app->tasks->run();
        $this->assertTrue($runsCount === 2);
        $stats = $app->tasks->getStats();
        $this->assertTrue($app->tasks->getStats()['upcomingTasksCount'] === 0);
    }

}
