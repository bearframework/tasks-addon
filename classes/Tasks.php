<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework;

use BearFramework\App;
use IvoPetkov\Lock;

class Tasks
{

    private $definitions = [];

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

    public function define(string $definitionID, callable $handler)
    {
        $this->definitions[$definitionID] = $handler;
        return $this;
    }

    public function add(string $definitionID, $data = null, array $options = [])
    {
        $app = App::get();
        $taskID = isset($options['id']) ? (string) $options['id'] : uniqid();
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $startTime = isset($options['startTime']) ? (int) $options['startTime'] : null;
        $lockKey = 'tasks-update-' . md5($listID);
        Lock::acquire($lockKey);
        $listDataKey = $this->getListDataKey($listID);
        $list = $app->data->getValue($listDataKey);
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            Lock::release($lockKey);
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
        Lock::release($lockKey);
        return $this;
    }

    public function addMultiple(array $tasks)
    {
        $taskLists = [];
        foreach ($tasks as $index => $task) {
            if (!isset($task['definitionID'])) {
                throw new \Exception('The definitionID key is missing for task with index ' . $index);
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

            if ($startTime !== null && $taskLists[$listID]['minStartTime'] === null || $startTime < $taskLists[$listID]['minStartTime']) {
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
    }

    public function exists(string $taskID, string $listID = '')
    {
        $app = App::get();
        return $app->data->exists($this->getTaskDataKey($taskID, $listID));
    }

    public function delete(string $taskID, string $listID = '')
    {
        $app = App::get();
        $lockKey = 'tasks-update-' . md5($listID);
        Lock::acquire($lockKey);
        $listDataKey = $this->getListDataKey($listID);
        $list = $app->data->getValue($listDataKey);
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            unset($list[$taskID]);
            $app->data->setValue($listDataKey, gzcompress(json_encode($list)));
            $app->data->delete($this->getTaskDataKey($taskID, $listID));
        }
        Lock::release($lockKey);
    }

    public function run(array $options = [])
    {
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $lockKey = 'tasks-run-' . md5($listID);
        if (Lock::exists($lockKey)) {
            return;
        }
        $maxExecutionTime = isset($options['maxExecutionTime']) ? (int) $options['maxExecutionTime'] : 30;
        $app = App::get();
        Lock::acquire($lockKey);
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
                foreach ($sortedList as $taskID) {
                    $taskData = $app->data->getValue($this->getTaskDataKey($taskID, $listID));
                    $taskData = $taskData === null ? [] : json_decode(gzuncompress($taskData), true);
                    if (isset($taskData[0])) {
                        if ($taskData[0] === 1) {
                            $definitionID = $taskData[1];
                            if (isset($this->definitions[$definitionID])) {
                                call_user_func($this->definitions[$definitionID], $taskData[2]);
                                if ($definitionID !== '--internal-add-multiple-task-definition' && $app->hooks->exists('taskDone')) {
                                    $hookDefinitionID = $definitionID;
                                    $hookTaskID = $taskID;
                                    $hookTaskData = $taskData[2];
                                    $app->hooks->execute('taskDone', $hookDefinitionID, $hookTaskID, $hookTaskData);
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
            for ($i = 0; $i < 100000; $i++) {
                $currentMaxExecutionTime = $maxExecutionTime - time() + $startTime;
                if ($currentMaxExecutionTime <= 0) {
                    break;
                }
                if ($run($currentMaxExecutionTime) === true) {
                    break;
                }
            }
        } catch (\Exception $e) {
            Lock::release($lockKey);
            throw $e;
        }
        Lock::release($lockKey);
    }

    private function getListDataKey(string $listID)
    {
        return 'tasks/list' . ($listID === '' ? '' : '.' . md5($listID));
    }

    private function getTaskDataKey(string $taskID, string $listID)
    {
        return 'tasks/task' . ($listID === '' ? '' : '.' . md5($listID)) . '/' . substr(md5($taskID), 0, 2) . '/' . md5($taskID);
    }

}
