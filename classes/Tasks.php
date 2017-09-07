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

    public function define(string $definitionID, callable $handler)
    {
        $this->definitions[$definitionID] = $handler;
    }

    public function add(string $definitionID, array $data = [], array $options = [])
    {
        $app = App::get();
        $taskID = isset($options['id']) ? $options['id'] : uniqid();
        $startTime = isset($options['startTime']) ? $options['startTime'] : null;
        Lock::acquire('tasks');
        $list = $app->data->getValue('tasks/list');
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            Lock::release('tasks');
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
        Lock::release('tasks');
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
        Lock::acquire('tasks');
        $list = $app->data->getValue('tasks/list');
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (isset($list[$taskID])) {
            unset($list[$taskID]);
            $app->data->setValue('tasks/list', gzcompress(json_encode($list)));
            $app->data->delete($this->getTaskDataKey($taskID));
        }
        Lock::release('tasks');
    }

    public function execute($maxExecutionTime = 30)
    {

        $app = App::get();
        $list = $app->data->getValue('tasks/list');
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        $list1 = []; // with specified start time
        $list2 = []; // without specified start time
        $currentTime = time();
        foreach ($list as $taskID => $taskListData) {
            if ($taskListData[0] === 1) {
                if ($taskListData[1] === null) {
                    $list2[$taskID] = null;
                } else {
                    if ($taskListData[1] < $currentTime) {
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
                        if ($app->hooks->exists('taskDone')) {
                            $hookDefinitionID = $definitionID;
                            $hookTaskID = $taskID;
                            $hookTaskData = $taskData[2];
                            $app->hooks->execute('taskDone', $hookDefinitionID, $hookTaskID, $hookTaskData);
                        }
                    }
                }
                $this->delete($taskID);
            }
            if (time() - $currentTime > $maxExecutionTime) {
                break;
            }
        }
    }

}
