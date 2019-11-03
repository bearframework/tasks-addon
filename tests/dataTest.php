<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testBasics()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function ($data) use (&$results) {
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
        $app->tasks->define('sum', function ($data) use (&$results) {
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
        $app->tasks->define('sum', function ($data) use (&$results) {
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
        $app->tasks->define('add-sums', function ($data) use (&$results, $app) {
            $results[] = 'add sums done';
            $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
            $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        });
        $app->tasks->define('sum', function ($data) use (&$results) {
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
    public function testIgnoreIfExists1()
    {
        $app = $this->getApp();
        $app->tasks->define('do-something', function ($data) use ($app) { });

        $app->tasks->add('do-something', null, ['id' => 'id1']);
        $exceptionMessage = '';
        try {
            $app->tasks->add('do-something', null, ['id' => 'id1']);
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertTrue($exceptionMessage === 'A task with the id "id1" already exists in list named \'\'!');
    }

    /**
     * 
     */
    public function testIgnoreIfExists2()
    {
        $app = $this->getApp();
        $app->tasks->define('do-something', function ($data) use ($app) { });

        $app->tasks->add('do-something', null, ['id' => 'id1']);
        $app->tasks->add('do-something', null, ['id' => 'id1', 'ignoreIfExists' => true]); // no error expected
        $this->assertTrue(true);
    }

    /**
     * 
     */
    public function testAddMultiple()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function ($data) use (&$results) {
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
    public function testAddMultipleWithIgnoreIfExists()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('stats-test', function ($data) use (&$results) {
            $results[] = $data['id'];
        });

        $app->tasks->add('stats-test', ['id' => 1], ['id' => 'idX']);

        $data = [];
        $data[] = [
            'definitionID' => 'stats-test',
            'data' => ['id' => 1],
            'options' => ['id' => 'idX', 'ignoreIfExists' => true]
        ];
        $data[] = [
            'definitionID' => 'stats-test',
            'data' => ['id' => 2],
            'options' => ['id' => 'idY', 'ignoreIfExists' => true]
        ];
        $data[] = [
            'definitionID' => 'stats-test',
            'data' => ['id' => 3],
            'options' => ['id' => 'idY', 'ignoreIfExists' => true]
        ];
        $data[] = [
            'definitionID' => 'stats-test',
            'data' => ['id' => 4],
            'options' => ['id' => 'id4', 'ignoreIfExists' => true]
        ];
        $app->tasks->addMultiple($data);
        $app->tasks->run();

        $this->assertTrue($results === [1, 2, 4]);
    }

    /**
     * 
     */
    public function testMultipleLists()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function ($data) use (&$results) {
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
        $app->tasks->define('sum', function ($data) use (&$results) {
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
        $app->tasks->define('sum', function ($data) use (&$results) {
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
    public function testEvents()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function ($data) use (&$results) {
            $results[] = $data['a'] + $data['b'];
        });

        $eventsLog = [];
        $app->tasks->addEventListener('beforeRunTask', function (\BearFramework\Tasks\BeforeRunTaskEventDetails $details) use (&$eventsLog) {
            $eventsLog[] = ['beforeRunTask', $details->definitionID, $details->taskID, $details->data];
        });

        $app->tasks->addEventListener('runTask', function (\BearFramework\Tasks\RunTaskEventDetails $details) use (&$eventsLog) {
            $eventsLog[] = ['runTask', $details->definitionID, $details->taskID, $details->data];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['id' => 'sum1']);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3], ['id' => 'sum2']);
        $app->tasks->run();

        $this->assertTrue(sizeof($eventsLog) === 4);
        $this->assertTrue($eventsLog[0] === ['beforeRunTask', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($eventsLog[1] === ['runTask', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($eventsLog[2] === ['beforeRunTask', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
        $this->assertTrue($eventsLog[3] === ['runTask', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
    }

    /**
     * 
     */
    public function testGetStats()
    {
        $app = $this->getApp();
        $app->tasks->define('sum', function ($data) { });

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
    public function testGetStatsNextPriority1()
    {
        $app = $this->getApp();
        $app->tasks->define('stats-test', function () { });

        $currentTime = time();
        $app->tasks->add('stats-test', null, ['id' => 'id1', 'priority' => 5, 'startTime' => ($currentTime - 6)]);
        $app->tasks->add('stats-test', null, ['id' => 'id2', 'priority' => 3]);
        $app->tasks->add('stats-test', null, ['id' => 'id3', 'priority' => 1, 'startTime' => ($currentTime + 4)]);
        $stats = $app->tasks->getStats();
        $this->assertTrue($stats['nextTask']['id'] === 'id2');
    }

    /**
     * 
     */
    public function testGetStatsNextPriority2()
    {
        $app = $this->getApp();
        $app->tasks->define('stats-test', function () { });

        $currentTime = time();
        $app->tasks->add('stats-test', null, ['id' => 'id1', 'priority' => 1, 'startTime' => ($currentTime - 4)]);
        $app->tasks->add('stats-test', null, ['id' => 'id2', 'priority' => 1]);
        $app->tasks->add('stats-test', null, ['id' => 'id3', 'priority' => 1, 'startTime' => ($currentTime + 4)]);
        $stats = $app->tasks->getStats();
        $this->assertTrue($stats['nextTask']['id'] === 'id1');
    }

    /**
     * 
     */
    public function testGetStatsNextByPriority()
    {
        $app = $this->getApp();
        $app->tasks->define('stats-test', function () { });

        $currentTime = time();
        $app->tasks->add('stats-test', null, ['id' => 'id1', 'priority' => 5, 'startTime' => ($currentTime - 6)]);
        $app->tasks->add('stats-test', null, ['id' => 'id2', 'priority' => 3]);
        $app->tasks->add('stats-test', null, ['id' => 'id3', 'priority' => 1]);
        $app->tasks->add('stats-test', null, ['id' => 'id4', 'priority' => 1, 'startTime' => ($currentTime - 4)]);
        $app->tasks->add('stats-test', null, ['id' => 'id5', 'priority' => 1, 'startTime' => ($currentTime + 4)]);
        $app->tasks->add('stats-test', null, ['id' => 'id6', 'priority' => 3, 'startTime' => ($currentTime + 4)]);
        $stats = $app->tasks->getStats();
        $this->assertTrue($stats['nextTasksByPriority'][0]['id'] === 'id1');
        $this->assertTrue($stats['nextTasksByPriority'][0]['priority'] === 5);
        $this->assertTrue($stats['nextTasksByPriority'][1]['id'] === 'id2');
        $this->assertTrue($stats['nextTasksByPriority'][1]['priority'] === 3);
        $this->assertTrue($stats['nextTasksByPriority'][2]['id'] === 'id4');
        $this->assertTrue($stats['nextTasksByPriority'][2]['priority'] === 1);
    }


    /**
     * 
     */
    public function testExceptionsInTasks()
    {
        $app = $this->getApp();

        $runsCount = 0;
        $app->tasks->define('sum', function () use (&$runsCount) {
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

    /**
     * 
     */
    public function testPriority()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('priority-test', function ($data) use (&$results) {
            $results[] = $data['id'];
        });

        $currentTime = time();
        $app->tasks->add('priority-test', ['id' => 'id1'], ['priority' => 5, 'startTime' => ($currentTime - 6)]);
        $app->tasks->add('priority-test', ['id' => 'id2'], ['startTime' => ($currentTime - 4)]);
        $app->tasks->add('priority-test', ['id' => 'id3'], ['priority' => 1, 'startTime' => ($currentTime - 4)]);
        $app->tasks->add('priority-test', ['id' => 'id4'], ['priority' => 1, 'startTime' => ($currentTime - 6)]);
        $app->tasks->add('priority-test', ['id' => 'id5'], ['priority' => 3, 'startTime' => ($currentTime - 8)]);
        $app->tasks->add('priority-test', ['id' => 'id6'], ['priority' => 3, 'startTime' => ($currentTime + 8)]);
        $app->tasks->run();
        $this->assertTrue($results[0] === 'id4');
        $this->assertTrue($results[1] === 'id3');
        $this->assertTrue($results[2] === 'id5');
        $this->assertTrue($results[3] === 'id2');
        $this->assertTrue($results[4] === 'id1');
    }
}
