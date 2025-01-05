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
        $app->tasks->define('sum', function ($data) use (&$results): void {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        $app->tasks->run();

        $this->assertTrue(count($results) === 2);
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
        $app->tasks->define('sum', function ($data) use (&$results): void {
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

        $this->assertTrue(count($results) === 3);
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
        $app->tasks->define('sum', function ($data) use (&$results): void {
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

        $this->assertTrue(count($results) === 3);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
        $this->assertTrue($results[2] === 7);

        $results = [];

        $app->tasks->run([
            'maxExecutionTime' => 5
        ]);

        $this->assertTrue(count($results) === 1);
        $this->assertTrue($results[0] === 9);
    }

    /**
     * 
     */
    public function testAddTasksInTask()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('add-sums', function ($data) use (&$results, $app): void {
            $results[] = 'add sums done';
            $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
            $app->tasks->add('sum', ['a' => 2, 'b' => 3]);
        });
        $app->tasks->define('sum', function ($data) use (&$results): void {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('add-sums');
        $app->tasks->run();

        $this->assertTrue(count($results) === 3);
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
        $app->tasks->define('do-something', function ($data) use ($app): void {});

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
        $app->tasks->define('do-something', function ($data) use ($app): void {});

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
        $app->tasks->define('sum', function ($data) use (&$results): void {
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
        $app->tasks->define('stats-test', function ($data) use (&$results): void {
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
        $app->tasks->define('sum', function ($data) use (&$results): void {
            $results[] = $data['a'] + $data['b'];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);

        $app->tasks->add('sum', ['a' => 3, 'b' => 4], ['listID' => 'my', 'id' => 'my1']);
        $app->tasks->add('sum', ['a' => 4, 'b' => 5], ['listID' => 'my']);

        $app->tasks->run();

        $this->assertTrue(count($results) === 2);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);

        $this->assertTrue($app->tasks->exists('my1', 'my'));
        $app->tasks->delete('my1', 'my');
        $this->assertFalse($app->tasks->exists('my1', 'my'));

        $app->tasks->run([
            'listID' => 'my'
        ]);

        $this->assertTrue(count($results) === 3);
        $this->assertTrue($results[0] === 3);
        $this->assertTrue($results[1] === 5);
        $this->assertTrue($results[2] === 9);
    }

    /**
     * 
     */
    public function testMultipleListsPriority()
    {
        $app = $this->getApp();

        $results = [];
        $app->tasks->define('test', function ($data) use (&$results): void {
            $results[] = $data;
        });

        $app->tasks->add('test', 'last', ['priority' => 4]);
        $app->tasks->add('test', 'first', ['priority' => 3]);
        $tasks = [];
        for ($i = 0; $i < 20; $i++) {
            $tasks[] = ['definitionID' => 'test', 'data' => 'other' . ($i + 1), 'options' => ['priority' => 3]];
        }
        $app->tasks->addMultiple($tasks);
        $app->tasks->run();
        $this->assertTrue($results === array(
            0 => 'first',
            1 => 'other1',
            2 => 'other2',
            3 => 'other3',
            4 => 'other4',
            5 => 'other5',
            6 => 'other6',
            7 => 'other7',
            8 => 'other8',
            9 => 'other9',
            10 => 'other10',
            11 => 'other11',
            12 => 'other12',
            13 => 'other13',
            14 => 'other14',
            15 => 'other15',
            16 => 'other16',
            17 => 'other17',
            18 => 'other18',
            19 => 'other19',
            20 => 'other20',
            21 => 'last',
        ));
    }

    /**
     * 
     */
    public function testAddMultipleInMultipleLists()
    {
        $app = $this->getApp();
        $results = [];
        $app->tasks->define('sum', function ($data) use (&$results): void {
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
            $this->assertTrue(count($results) === $counter * 10);
            $app->tasks->run([
                'listID' => $listID
            ]);
            $this->assertTrue(count($results) === ($counter + 1) * 10);
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
        $app->tasks->define('sum', function ($data) use (&$results): void {
            $results[] = gettype($data);
        });

        $app->tasks->add('sum', 5);
        $app->tasks->add('sum', 'string');
        $app->tasks->add('sum', []);
        $app->tasks->run();

        $this->assertTrue(count($results) === 3);
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
        $app->tasks->define('sum', function ($data) use (&$results): void {
            $results[] = $data['a'] + $data['b'];
        });

        $eventsLog = [];

        $app->tasks->addEventListener('beforeRun', function (\BearFramework\Tasks\BeforeRunEventDetails $details) use (&$eventsLog): void {
            $eventsLog[] = ['beforeRun', $details->listID];
        });

        $app->tasks->addEventListener('run', function (\BearFramework\Tasks\RunEventDetails $details) use (&$eventsLog): void {
            $eventsLog[] = ['run', $details->listID];
        });

        $app->tasks->addEventListener('addTask', function (\BearFramework\Tasks\AddTaskEventDetails $details) use (&$eventsLog): void {
            $eventsLog[] = ['addTask', $details->definitionID, $details->taskID, $details->listID, $details->startTime, $details->priority, $details->data];
        });

        $app->tasks->addEventListener('beforeRunTask', function (\BearFramework\Tasks\BeforeRunTaskEventDetails $details) use (&$eventsLog): void {
            $eventsLog[] = ['beforeRunTask', $details->definitionID, $details->taskID, $details->data];
        });

        $app->tasks->addEventListener('runTask', function (\BearFramework\Tasks\RunTaskEventDetails $details) use (&$eventsLog): void {
            $eventsLog[] = ['runTask', $details->definitionID, $details->taskID, $details->data];
        });

        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['id' => 'sum1', 'startTime' => 1000000001, 'priority' => 2]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3], ['id' => 'sum2']);

        $app->tasks->run();

        $this->assertTrue(count($eventsLog) === 8);
        $this->assertTrue($eventsLog[0] === ['addTask', 'sum', 'sum1', '', 1000000001, 2, ['a' => 1, 'b' => 2]]);
        $this->assertTrue($eventsLog[1] === ['addTask', 'sum', 'sum2', '',  null, 3, ['a' => 2, 'b' => 3]]);
        $this->assertTrue($eventsLog[2] === ['beforeRun', '']);
        $this->assertTrue($eventsLog[3] === ['beforeRunTask', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($eventsLog[4] === ['runTask', 'sum', 'sum1', ['a' => 1, 'b' => 2]]);
        $this->assertTrue($eventsLog[5] === ['beforeRunTask', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
        $this->assertTrue($eventsLog[6] === ['runTask', 'sum', 'sum2', ['a' => 2, 'b' => 3]]);
        $this->assertTrue($eventsLog[7] === ['run', '']);
    }

    /**
     * 
     */
    public function testGetStats()
    {
        $app = $this->getApp();
        $app->tasks->define('sum', function ($data): void {});

        $currentTime = time();
        $app->tasks->add('sum', ['a' => 1, 'b' => 2], ['startTime' => $currentTime - 5]);
        $app->tasks->add('sum', ['a' => 2, 'b' => 3]);

        $checkUpcomingTasksCount = function ($stats): void {
            $this->assertEquals(2, $stats['upcomingTasksCount']);
        };
        $checkUpcomingTasks = function ($stats): void {
            $this->assertEquals(2, count($stats['upcomingTasks']));
        };
        $checkNextTask = function ($stats) use ($currentTime): void {
            $this->assertEquals($currentTime - 5, $stats['nextTask']['startTime']);
        };
        $checkNextTaskStartTime = function ($stats) use ($currentTime): void {
            $this->assertEquals($currentTime - 5, $stats['nextTaskStartTime']);
        };
        $checkNextTasksByPriority = function ($stats, bool $checkSize = false) use ($currentTime): void {
            $this->assertEquals(1, count($stats['nextTasksByPriority']));
            $this->assertEquals($currentTime - 5, $stats['nextTasksByPriority'][0]['startTime']);
            if ($checkSize) {
                $this->assertEquals(1, count(array_keys($stats)));
            }
        };

        $stats = $app->tasks->getStats();
        $checkUpcomingTasksCount($stats);
        $checkUpcomingTasks($stats);
        $checkNextTask($stats);
        $checkNextTaskStartTime($stats);
        $checkNextTasksByPriority($stats);

        $checkUpcomingTasksCount($app->tasks->getStats('', ['upcomingTasksCount']), true);
        $checkUpcomingTasks($app->tasks->getStats('', ['upcomingTasks']), true);
        $checkNextTask($app->tasks->getStats('', ['nextTask']), true);
        $checkNextTaskStartTime($app->tasks->getStats('', ['nextTaskStartTime']), true);
        $checkNextTasksByPriority($app->tasks->getStats('', ['nextTasksByPriority']), true);
    }

    /**
     * 
     */
    public function testGetStatsNextPriority1()
    {
        $app = $this->getApp();
        $app->tasks->define('stats-test', function (): void {});

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
        $app->tasks->define('stats-test', function (): void {});

        $currentTime = time();
        $app->tasks->add('stats-test', 'data1', ['id' => 'id1', 'priority' => 1, 'startTime' => ($currentTime - 4)]);
        $app->tasks->add('stats-test', 'data2', ['id' => 'id2', 'priority' => 1]);
        $app->tasks->add('stats-test', 'data3', ['id' => 'id3', 'priority' => 1, 'startTime' => ($currentTime + 4)]);
        $stats = $app->tasks->getStats();
        $this->assertTrue($stats['nextTask']['id'] === 'id1');
        $this->assertTrue($stats['nextTask']['data'] === 'data1');
    }

    /**
     * 
     */
    public function testGetStatsNextByPriority()
    {
        $app = $this->getApp();
        $app->tasks->define('stats-test', function (): void {});

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
        $app->tasks->define('sum', function () use (&$runsCount): void {
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
        $app->tasks->define('priority-test', function ($data) use (&$results): void {
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

    /**
     * 
     * @return void
     */
    public function testTaskStats()
    {
        $app = $this->getApp();
        $app->tasks->define('task-stats-test', function (): void {});

        $currentTime = time();
        $app->tasks->add('task-stats-test', ['value' => '1'], ['id' => 'id1', 'priority' => 5, 'startTime' => ($currentTime - 6)]);
        $app->tasks->add('task-stats-test', ['value' => '2'], ['id' => 'id2']);
        $stats = $app->tasks->getTaskStats('', 'id1');
        $this->assertTrue($stats['definitionID'] === 'task-stats-test');
        $this->assertTrue($stats['data'] === ['value' => '1']);
        $this->assertTrue($stats['priority'] === 5);
        $this->assertTrue($stats['startTime'] === ($currentTime - 6));
        $stats = $app->tasks->getTaskStats('', 'id2');
        $this->assertTrue($stats['definitionID'] === 'task-stats-test');
        $this->assertTrue($stats['data'] === ['value' => '2']);
        $this->assertTrue($stats['priority'] === 3);
        $this->assertTrue($stats['startTime'] === null);
        $stats = $app->tasks->getTaskStats('', 'id3');
        $this->assertTrue($stats === null);
    }
}
