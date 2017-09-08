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
                $this->add($task['definitionID'], isset($task['data']) ? $task['data'] : [], isset($task['options']) ? $task['options'] : []);
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

    public function add(string $definitionID, array $data = [], array $options = [])
    {
        $app = App::get();
        $taskID = isset($options['id']) ? (string) $options['id'] : uniqid();
        $startTime = isset($options['startTime']) ? (int) $options['startTime'] : null;
        Lock::acquire('tasks-update');
        $list = $app->data->getValue('tasks/list');
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            Lock::release('tasks-update');
            throw new \Exception('A task with the id "' . $taskID . '" already exists!');
        }
        $list[$taskID] = [1, $startTime]; // format version, start time
        $app->data->setValue('tasks/list', gzcompress(json_encode($list)));
        $taskData = [
            1, // format version
            $definitionID,
            $data
        ];
        $app->data->setValue($this->getTaskDataKey($taskID), gzcompress(json_encode($taskData)));
        Lock::release('tasks-update');
        return $this;
    }

    public function addMultiple(array $tasks)
    {
        $minStartTime = null;
        foreach ($tasks as $index => $task) {
            if (!isset($task['definitionID'])) {
                throw new \Exception('The definitionID key is missing for task with index ' . $index);
            }
            if (isset($task['data']) && !is_array($task['data'])) {
                throw new \Exception('The \'data\' key must be of type array for index ' . $index);
            }
            if (isset($task['options'])) {
                if (is_array($task['options'])) {
                    if (isset($task['options']['startTime'])) {
                        $startTime = (int) $task['options']['startTime'];
                        if ($minStartTime === null || $startTime < $minStartTime) {
                            $minStartTime = $startTime;
                        }
                    }
                } else {
                    throw new \Exception('The \'options\' key must be of type array for index ' . $index);
                }
            }
        }
        $options = [];
        if ($minStartTime !== null) {
            $options['startTime'] = $minStartTime;
        }
        $this->add('--internal-add-multiple-task-definition', $tasks, $options);
    }

    private function getTaskDataKey($taskID)
    {
        return 'tasks/task/' . substr(md5($taskID), 0, 2) . '/' . md5($taskID);
    }

    public function exists($taskID)
    {
        $app = App::get();
        return $app->data->exists($this->getTaskDataKey($taskID));
    }

    public function delete($taskID)
    {
        $app = App::get();
        Lock::acquire('tasks-update');
        $list = $app->data->getValue('tasks/list');
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            unset($list[$taskID]);
            $app->data->setValue('tasks/list', gzcompress(json_encode($list)));
            $app->data->delete($this->getTaskDataKey($taskID));
        }
        Lock::release('tasks-update');
    }

    public function run($maxExecutionTime = 30)
    {
        if (Lock::exists('tasks-run')) {
            return;
        }
        $app = App::get();
        Lock::acquire('tasks-run');
        try {
            $run = function($maxExecutionTime) use ($app) {
                $list = $app->data->getValue('tasks/list');
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
                    $taskData = $app->data->getValue($this->getTaskDataKey($taskID));
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
                                    $app->hooks->run('taskDone', $hookDefinitionID, $hookTaskID, $hookTaskData);
                                }
                            }
                        }
                        $this->delete($taskID);
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
            Lock::release('tasks-run');
            throw $e;
        }
        Lock::release('tasks-run');
    }

}
