<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework;

use BearFramework\App;

/**
 * 
 */
class Tasks
{

    /**
     *
     * @var array 
     */
    private $definitions = [];

    /**
     * 
     */
    public function __construct()
    {
        $this->define('--internal-add-multiple-task-definition', function($tasks) {
            $counter = 0;
            foreach ($tasks as $index => $task) {
                $this->add($task['definitionID'], isset($task['data']) ? $task['data'] : null, isset($task['options']) ? $task['options'] : []);
                unset($tasks[$index]);
                $counter++;
                if ($counter >= 10) {
                    break;
                }
            }
            if (!empty($tasks)) {
                $this->addMultiple($tasks);
            }
        });
    }

    /**
     * Defines a new task.
     * @param string $definitionID The ID of the definition.
     * @param callable $handler The function that will be called when a task of this type is ran.
     * @return \BearFramework\Tasks Returns an instance to itself.
     */
    public function define(string $definitionID, callable $handler): \BearFramework\Tasks
    {
        $this->definitions[$definitionID] = $handler;
        return $this;
    }

    /**
     * Adds a new tasks.
     * @param string $definitionID The ID of the definition.
     * @param mixed $data A task data that will be passed to the handler.
     * @param array $options Available options: id - the ID of the task, listID - the ID of the tasks list, startTime - the earliest time to start the task
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function add(string $definitionID, $data = null, array $options = []): \BearFramework\Tasks
    {
        $app = App::get();
        $taskID = isset($options['id']) ? (string) $options['id'] : uniqid();
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $startTime = isset($options['startTime']) ? (int) $options['startTime'] : null;
        $lockKey = 'tasks-update-' . md5($listID);
        $app->locks->acquire($lockKey);
        $listDataKey = $this->getListDataKey($listID);
        $list = $app->data->getValue($listDataKey);
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            $app->locks->release($lockKey);
            throw new \Exception('A task with the id "' . $taskID . '" already exists in list named \'' . $listID . '\'!');
        }
        $list[$taskID] = [1, $startTime]; // format version, start time
        $app->data->setValue($listDataKey, gzcompress(json_encode($list)));
        $taskData = [
            1, // format version
            $definitionID,
            $data
        ];
        $app->data->setValue($this->getTaskDataKey($taskID, $listID), gzcompress(json_encode($taskData)));
        $app->locks->release($lockKey);
        $app->hooks->execute('taskAdded');
        return $this;
    }

    /**
     * Adds multiple tasks.
     * @param array $tasks Format: [['definitionID'=>'...', 'data'=>'...', 'options'=>'...']]
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function addMultiple(array $tasks): \BearFramework\Tasks
    {
        $taskLists = [];
        foreach ($tasks as $index => $task) {
            if (!isset($task['definitionID'])) {
                throw new \Exception('The definitionID key is missing for task with index ' . $index);
            }
            if (!is_string($task['definitionID'])) {
                throw new \Exception('The definitionID key must be of type string for index ' . $index);
            }
            $listID = '';
            $startTime = null;
            if (isset($task['options'])) {
                if (is_array($task['options'])) {
                    if (isset($task['options']['listID'])) {
                        $listID = (string) $task['options']['listID'];
                    }
                    if (isset($task['options']['startTime'])) {
                        $startTime = (int) $task['options']['startTime'];
                    }
                } else {
                    throw new \Exception('The \'options\' key must be of type array for index ' . $index);
                }
            }
            if (!isset($taskLists[$listID])) {
                $taskLists[$listID] = [
                    'minStartTime' => null,
                    'data' => []
                ];
            }
            if ($startTime !== null && ($taskLists[$listID]['minStartTime'] === null || $startTime < $taskLists[$listID]['minStartTime'])) {
                $taskLists[$listID]['minStartTime'] = $startTime;
            }
            $taskLists[$listID]['data'][] = $task;
        }
        foreach ($taskLists as $listID => $taskListData) {
            $options = [];
            $options['listID'] = $listID;
            if ($taskListData['minStartTime'] !== null) {
                $options['startTime'] = $taskListData['minStartTime'];
            }
            $this->add('--internal-add-multiple-task-definition', $taskListData['data'], $options);
        }
        return $this;
    }

    /**
     * Checks if a task exists.
     * @param string $taskID The ID of the task.
     * @param string $listID The ID of the tasks lists.
     * @return boolean
     */
    public function exists(string $taskID, string $listID = ''): bool
    {
        $app = App::get();
        return $app->data->exists($this->getTaskDataKey($taskID, $listID));
    }

    /**
     * Deletes a task.
     * @param string $taskID The ID of the task.
     * @param string $listID The ID of the tasks lists.
     * @return \BearFramework\Tasks Returns an instance to itself.
     */
    public function delete(string $taskID, string $listID = ''): \BearFramework\Tasks
    {
        $app = App::get();
        $lockKey = 'tasks-update-' . md5($listID);
        $app->locks->acquire($lockKey);
        $listDataKey = $this->getListDataKey($listID);
        $list = $app->data->getValue($listDataKey);
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            unset($list[$taskID]);
            $app->data->setValue($listDataKey, gzcompress(json_encode($list)));
            $app->data->delete($this->getTaskDataKey($taskID, $listID));
        }
        $app->locks->release($lockKey);
        return $this;
    }

    /**
     * Run the tasks.
     * @param array $options Available values: listID - the ID of the list whose tasks to run, maxExecutionTime - max time in seconds to run tasks (default is 30).
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function run(array $options = []): \BearFramework\Tasks
    {
        $app = App::get();
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $lockKey = 'tasks-run-' . md5($listID);
        if ($app->locks->exists($lockKey)) {
            return $this;
        }
        $maxExecutionTime = isset($options['maxExecutionTime']) ? (int) $options['maxExecutionTime'] : 30;
        $app = App::get();
        $app->locks->acquire($lockKey);
        $listDataKey = $this->getListDataKey($listID);
        try {
            $run = function($maxExecutionTime) use ($app, $listDataKey, $listID) {
                $list = $app->data->getValue($listDataKey);
                $list = $list === null ? [] : json_decode(gzuncompress($list), true);
                if (empty($list)) {
                    return true;
                }
                $list1 = []; // with specified start time
                $list2 = []; // without specified start time
                $currentTime = time();
                foreach ($list as $taskID => $taskListData) {
                    if ($taskListData[0] === 1) {
                        if ($taskListData[1] === null) {
                            $list2[$taskID] = null;
                        } else {
                            if ($taskListData[1] <= $currentTime) {
                                $list1[$taskID] = $taskListData[1];
                            }
                        }
                    }
                }
                asort($list1);
                $sortedList = array_merge(array_keys($list1), array_keys($list2));
                if (empty($sortedList)) {
                    return true;
                }
                foreach ($sortedList as $taskID) {
                    $taskData = $app->data->getValue($this->getTaskDataKey($taskID, $listID));
                    $taskData = $taskData === null ? [] : json_decode(gzuncompress($taskData), true);
                    if (isset($taskData[0])) {
                        if ($taskData[0] === 1) {
                            $definitionID = $taskData[1];
                            $handlerData = $taskData[2];
                            $isInternalTask = $definitionID === '--internal-add-multiple-task-definition';
                            if (!$isInternalTask && $app->hooks->exists('taskRun')) {
                                $definitionIDCopy = $definitionID;
                                $taskIDCopy = $taskID;
                                $app->hooks->execute('taskRun', $definitionIDCopy, $taskIDCopy, $handlerData);
                            }
                            if (isset($this->definitions[$definitionID])) {
                                call_user_func($this->definitions[$definitionID], $taskData[2]);
                                if (!$isInternalTask && $app->hooks->exists('taskRunDone')) {
                                    $definitionIDCopy = $definitionID;
                                    $taskIDCopy = $taskID;
                                    $handlerDataCopy = is_object($handlerData) ? clone($handlerData) : $handlerData;
                                    $app->hooks->execute('taskRunDone', $definitionIDCopy, $taskIDCopy, $handlerDataCopy);
                                }
                            }
                        }
                        $this->delete($taskID, $listID);
                    }
                    if (time() - $currentTime > $maxExecutionTime) {
                        break;
                    }
                }
                return false;
            };
            $startTime = time();
            for ($i = 0; $i < 10000; $i++) {
                $currentMaxExecutionTime = $maxExecutionTime - time() + $startTime;
                if ($currentMaxExecutionTime <= 0) {
                    break;
                }
                if ($run($currentMaxExecutionTime) === true) {
                    break;
                }
            }
        } catch (\Exception $e) {
            $app->locks->release($lockKey);
            throw $e;
        }
        $app->locks->release($lockKey);
        return $this;
    }

    /**
     * 
     * @param string $listID
     * @return string
     */
    private function getListDataKey(string $listID): string
    {
        return 'tasks/list' . ($listID === '' ? '' : '.' . md5($listID));
    }

    /**
     * 
     * @param string $taskID
     * @param string $listID
     * @return string
     */
    private function getTaskDataKey(string $taskID, string $listID): string
    {
        return 'tasks/task' . ($listID === '' ? '' : '.' . md5($listID)) . '/' . substr(md5($taskID), 0, 2) . '/' . md5($taskID);
    }

}
