<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework;

use BearFramework\App;

/**
 * 
 * @event \BearFramework\Tasks\BeforeRunEventDetails beforeRun
 * @event \BearFramework\Tasks\RunEventDetails run
 * @event \BearFramework\Tasks\RunTaskEventDetails addTask
 * @event \BearFramework\Tasks\BeforeRunTaskEventDetails beforeRunTask
 * @event \BearFramework\Tasks\RunTaskEventDetails runTask
 */
class Tasks
{

    use \BearFramework\EventsTrait;

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
        $this->define('--internal-add-multiple-task-definition', function ($tasks): void {
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
     * 
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
     * 
     * @param string $definitionID The ID of the definition.
     * @param mixed $data A task data that will be passed to the handler.
     * @param array $options Available options:
     *      id - the ID of the task
     *      listID - the ID of the tasks list
     *      startTime - the earliest time to start the task
     *      priority - the task priority (1 - highest, 5 - lowest, 3 - default)
     *      ignoreIfExists - dont throw exception if task exists (default is false)
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function add(string $definitionID, $data = null, array $options = []): \BearFramework\Tasks
    {
        $app = App::get();
        $taskID = isset($options['id']) ? (string) $options['id'] : uniqid();
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $startTime = isset($options['startTime']) && strlen($options['startTime']) > 0 ? (int) $options['startTime'] : null;
        $priority = isset($options['priority']) && strlen($options['priority']) > 0 ? (int) $options['priority'] : 3;
        $ignoreIfExists = isset($options['ignoreIfExists']) ? (int) $options['ignoreIfExists'] > 0 : false;
        if ($priority < 1 || $priority > 5) {
            $priority = 3;
        }
        $this->lockList($listID);
        $list = $this->getListData($listID);
        if (isset($list[$taskID])) {
            $this->unlockList($listID);
            if ($ignoreIfExists) {
                return $this;
            }
            throw new \Exception('A task with the id "' . $taskID . '" already exists in list named \'' . $listID . '\'!');
        }
        $list[$taskID] = [2, $startTime, $priority]; // format version, start time, priority
        $this->setListData($listID, $list);
        $taskData = [
            2, // format version
            $definitionID,
            $data,
            $priority
        ];
        $app->data->setValue($this->getTaskDataKey($taskID, $listID), gzcompress(json_encode($taskData)));
        $this->unlockList($listID);
        if ($this->hasEventListeners('addTask')) {
            $eventDetails = new \BearFramework\Tasks\AddTaskEventDetails($definitionID, $taskID, $listID, $startTime, $priority, $data);
            $this->dispatchEvent('addTask', $eventDetails);
        }
        return $this;
    }

    /**
     * Adds multiple tasks.
     * 
     * @param array $tasks Format: [['definitionID'=>'...', 'data'=>'...', 'options'=>'...']]
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function addMultiple(array $tasks): \BearFramework\Tasks
    {
        $taskLists = [];
        $listsCache = [];
        $addedTaskIDs = [];
        $exists = function (string $taskID, string $listID) use (&$listsCache, &$addedTaskIDs) {
            if (isset($addedTaskIDs[$listID], $addedTaskIDs[$listID][$taskID])) {
                return true;
            }
            if (!isset($listsCache[$listID])) {
                $listsCache[$listID] = $this->getListData($listID);
            }
            return isset($listsCache[$listID][$taskID]);
        };
        foreach ($tasks as $index => $task) {
            if (!isset($task['definitionID'])) {
                throw new \Exception('The definitionID key is missing for task with index ' . $index);
            }
            if (!is_string($task['definitionID'])) {
                throw new \Exception('The definitionID key must be of type string for index ' . $index);
            }
            $listID = '';
            $startTime = null;
            $priority = 3;
            if (isset($task['options'])) {
                if (is_array($task['options'])) {
                    if (isset($task['options']['listID'])) {
                        $listID = (string) $task['options']['listID'];
                    }
                    if (isset($task['options']['startTime'])) {
                        $startTime = (int) $task['options']['startTime'];
                    }
                    if (isset($task['options']['priority'])) {
                        $priority = (int) $task['options']['priority'];
                        if ($priority < 1 || $priority > 5) {
                            $priority = 3;
                        }
                    }
                    if (isset($task['options']['id'])) {
                        $taskID = $task['options']['id'];
                        if (isset($task['options']['ignoreIfExists']) && (int) $task['options']['ignoreIfExists'] > 0) {
                            if ($exists($taskID, $listID)) {
                                continue;
                            }
                        }
                        if (!isset($addedTaskIDs[$listID])) {
                            $addedTaskIDs[$listID] = [];
                        }
                        $addedTaskIDs[$listID][$taskID] = true;
                    }
                } else {
                    throw new \Exception('The \'options\' key must be of type array for index ' . $index);
                }
            }
            if (!isset($taskLists[$listID])) {
                $taskLists[$listID] = [
                    'minStartTime' => null,
                    'priority' => 3,
                    'data' => []
                ];
            }
            if ($startTime !== null && ($taskLists[$listID]['minStartTime'] === null || $startTime < $taskLists[$listID]['minStartTime'])) {
                $taskLists[$listID]['minStartTime'] = $startTime;
            }
            if ($priority < $taskLists[$listID]['priority']) {
                $taskLists[$listID]['priority'] = $priority;
            }
            $taskLists[$listID]['data'][] = $task;
        }

        foreach ($taskLists as $listID => $taskListData) {
            $options = [];
            $options['id'] = uniqid() . '--internal-multiple-' . count($taskListData['data']); // for debugging purposes
            $options['listID'] = $listID;
            if ($taskListData['minStartTime'] !== null) {
                $options['startTime'] = $taskListData['minStartTime'];
            }
            $options['priority'] = $taskListData['priority'];
            $this->add('--internal-add-multiple-task-definition', $taskListData['data'], $options);
        }
        return $this;
    }

    /**
     * Checks if a task exists.
     * 
     * @param string $taskID The ID of the task.
     * @param string $listID The ID of the tasks lists.
     * @return boolean
     */
    public function exists(string $taskID, string $listID = ''): bool
    {
        $list = $this->getListData($listID);
        if (isset($list[$taskID])) {
            $app = App::get();
            return $app->data->exists($this->getTaskDataKey($taskID, $listID));
        }
        return false;
    }

    /**
     * Deletes a task.
     * 
     * @param string $taskID The ID of the task.
     * @param string $listID The ID of the tasks lists.
     * @return \BearFramework\Tasks Returns an instance to itself.
     */
    public function delete(string $taskID, string $listID = ''): \BearFramework\Tasks
    {
        $this->deleteMultiple([$taskID], $listID);
        return $this;
    }

    /**
     * Deletes multiple task.
     * 
     * @param string $taskIDs The IDs of the tasks to delete.
     * @param string $listID The ID of the tasks lists.
     * @return \BearFramework\Tasks Returns an instance to itself.
     */
    public function deleteMultiple(array $taskIDs, string $listID = ''): \BearFramework\Tasks
    {
        $app = App::get();
        $this->lockList($listID);
        $list = $this->getListData($listID);
        $dataKeysToDelete = [];
        foreach ($taskIDs as $taskID) {
            if (isset($list[$taskID])) {
                unset($list[$taskID]);
                $dataKeysToDelete[] = $this->getTaskDataKey($taskID, $listID);
            }
        }
        if (!empty($dataKeysToDelete)) {
            $this->setListData($listID, $list);
            foreach ($dataKeysToDelete as $dataKeyToDelete) {
                $app->data->delete($dataKeyToDelete);
            }
        }
        $this->unlockList($listID);
        return $this;
    }

    /**
     * Run the tasks.
     * 
     * @param array $options Available values:
     *      listID - the ID of the list whose tasks to run
     *      maxExecutionTime - max time in seconds to run tasks (default is 30)
     *      retryTime - time in seconds when a failed task will be run again (default is 1 hour)
     * @return \BearFramework\Tasks Returns an instance to itself.
     * @throws \Exception
     */
    public function run(array $options = []): \BearFramework\Tasks
    {
        $app = App::get();
        $listID = isset($options['listID']) ? (string) $options['listID'] : '';
        $retryTime = isset($options['retryTime']) ? (string) $options['retryTime'] : 3600;

        if ($this->hasEventListeners('beforeRun')) {
            $eventDetails = new \BearFramework\Tasks\BeforeRunEventDetails($listID);
            $this->dispatchEvent('beforeRun', $eventDetails);
        }

        $dispatchOnRunEvent = function () use ($listID): void {
            if ($this->hasEventListeners('run')) {
                $eventDetails = new \BearFramework\Tasks\RunEventDetails($listID);
                $this->dispatchEvent('run', $eventDetails);
            }
        };

        $lockKey = 'tasks-run-' . md5($listID);
        if ($app->locks->exists($lockKey)) {
            $dispatchOnRunEvent();
            return $this;
        }
        $app->locks->acquire($lockKey);
        $maxExecutionTime = isset($options['maxExecutionTime']) ? (int) $options['maxExecutionTime'] : 30;
        try {
            $run = function ($maxExecutionTime) use ($listID, $retryTime) { // returns TRUE if no more tasks to run now, FALSE - otherwise
                $list = $this->getListData($listID);
                if (empty($list)) {
                    return true;
                }
                $tempList = [];
                $currentTime = time();
                foreach ($list as $taskID => $taskListData) {
                    if ($taskListData[0] === 1) { // version check
                        $taskListData[0] = 2; // set to version 2
                        $taskListData[2] = 3; // set default value for the priority
                    }
                    if ($taskListData[0] === 2) { // version check
                        if ($taskListData[1] === null) { // does not have start time
                            $tempList[$taskID] = [$taskListData[2], $currentTime, count($tempList)];
                        } else {
                            if ($taskListData[1] <= $currentTime) { // has start time and it is lower than the current time
                                $tempList[$taskID] = [$taskListData[2], $taskListData[1], count($tempList)];
                            }
                        }
                    }
                }
                uasort($tempList, function ($a, $b) {
                    if ($a[0] !== $b[0]) {
                        return $a[0] < $b[0] ? -1 : 1;
                    }
                    if ($a[1] !== $b[1]) {
                        return $a[1] < $b[1] ? -1 : 1;
                    }
                    return $a[2] < $b[2] ? -1 : 1;
                });
                $sortedList = array_keys($tempList);
                unset($tempList);
                if (empty($sortedList)) {
                    return true;
                }
                foreach ($sortedList as $taskID) {
                    $breakAfterThisTask = false;
                    $retryTaskLater = false;
                    $exceptionToThrow = null;
                    $taskData = $this->getTaskData($listID, $taskID);
                    $priority = 3;
                    if ($taskData !== null && isset($taskData[0])) {
                        if ($taskData[0] === 1 || $taskData[0] === 2) { // format version
                            $definitionID = $taskData[1];
                            $handlerData = $taskData[2];
                            if ($taskData[0] === 2) {
                                $priority = $taskData[3];
                            }
                            $isInternalTask = $definitionID === '--internal-add-multiple-task-definition';
                            if ($isInternalTask) {
                                $breakAfterThisTask = true;
                            }
                            if (!$isInternalTask && $this->hasEventListeners('beforeRunTask')) {
                                $eventDetails = new \BearFramework\Tasks\BeforeRunTaskEventDetails($definitionID, $taskID, $handlerData);
                                $this->dispatchEvent('beforeRunTask', $eventDetails);
                            }
                            if (isset($this->definitions[$definitionID])) {
                                try {
                                    call_user_func($this->definitions[$definitionID], $taskData[2]);
                                } catch (\Exception $e) {
                                    $exceptionToThrow = new \Exception('Cannot process task ' . $taskID . ' (list: ' . $listID . '). Reason: ' . $e->getMessage(), 0, $e);
                                }
                                if ($exceptionToThrow === null && !$isInternalTask && $this->hasEventListeners('runTask')) {
                                    $eventDetails = new \BearFramework\Tasks\RunTaskEventDetails($definitionID, $taskID, is_object($handlerData) ? clone ($handlerData) : $handlerData);
                                    $this->dispatchEvent('runTask', $eventDetails);
                                }
                            } else {
                                $exceptionToThrow = new \Exception('Cannot process task ' . $taskID . ' (list: ' . $listID . '). Reason: definition not found (' . $definitionID . ')!');
                            }
                            if ($exceptionToThrow !== null) {
                                $retryTaskLater = true;
                            }
                        } else {
                            $exceptionToThrow = new \Exception('Cannot process task ' . $taskID . ' (list: ' . $listID . '). Reason: corrupted task data!');
                        }
                    } else {
                        $exceptionToThrow = new \Exception('Cannot process task ' . $taskID . ' (list: ' . $listID . '). Reason: corrupted task data!');
                    }
                    if ($retryTaskLater) {
                        $this->lockList($listID);
                        $list = $this->getListData($listID);
                        if (isset($list[$taskID])) {
                            $list[$taskID] = [2, time() + $retryTime, $priority]; // format version, start time, priority
                            $this->setListData($listID, $list);
                        } else {
                            new \Exception('Cannot schedule retry for task ' . $taskID . ' (list: ' . $listID . ')!');
                        }
                        $this->unlockList($listID);
                    } else {
                        $this->delete($taskID, $listID);
                    }
                    if ($exceptionToThrow !== null) {
                        throw $exceptionToThrow;
                    }
                    if ($breakAfterThisTask || time() - $currentTime >= $maxExecutionTime) {
                        return false;
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
        $dispatchOnRunEvent();
        return $this;
    }

    /**
     * Returns the raw information about a task
     * 
     * @param string $listID The list ID.
     * @param string $taskID The task ID.
     * @return array|null The task raw data.
     */
    private function getTaskData(string $listID, string $taskID): ?array
    {
        $app = App::get();
        $taskData = $app->data->getValue($this->getTaskDataKey($taskID, $listID));
        try {
            return $taskData === null ? null : json_decode(gzuncompress($taskData), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Returns the minimum start time of the tasks in the list specified.
     * 
     * @param string $listID The list ID.
     * @return int|null The minimum start time of the tasks in the list specified. Returns NULL if no tasks exists.
     */
    public function getMinStartTime(string $listID = ''): ?int
    {
        $list = $this->getListData($listID);
        if (empty($list)) {
            return null;
        }
        $minStartTime = null;
        $hasTaskWithoutStartDate = false;
        foreach ($list as $taskListData) {
            if ($taskListData[0] === 1 || $taskListData[0] === 2) { // version check
                if ($taskListData[1] === null) { // does not have start time
                    $hasTaskWithoutStartDate = true;
                } else { // has start time
                    if ($minStartTime === null || $taskListData[1] < $minStartTime) {
                        $minStartTime = $taskListData[1];
                    }
                }
            }
        }
        $currentTime = time();
        if ($minStartTime === null) {
            return $currentTime;
        }
        if ($hasTaskWithoutStartDate) {
            return $minStartTime < $currentTime ? $minStartTime : $currentTime;
        } else {
            return $minStartTime;
        }
    }

    /**
     * Returns information about the tasks in the list specified.
     * 
     * @param string $listID
     * @param array $stats List ot stat names to return. Leave empty to return all.
     * @return array
     */
    public function getStats(string $listID = '', array $stats = []): array
    {
        $emptyStats = empty($stats);
        $result = [];
        $returnUpcomingTasksCount = $emptyStats || array_search('upcomingTasksCount', $stats) !== false;
        $returnUpcomingTasks = $emptyStats || array_search('upcomingTasks', $stats) !== false;
        $returnNextTask = $emptyStats || array_search('nextTask', $stats) !== false;
        $returnNextTaskStartTime = $emptyStats || array_search('nextTaskStartTime', $stats) !== false;
        $returnNextTasksByPriority = $emptyStats || array_search('nextTasksByPriority', $stats) !== false;
        if ($returnUpcomingTasksCount) {
            $result['upcomingTasksCount'] = 0;
        }
        if ($returnUpcomingTasks) {
            $result['upcomingTasks'] = [];
        }
        if ($returnUpcomingTasks || $returnNextTask || $returnNextTaskStartTime) {
            $upcomingTasks = [];
        }
        if ($returnNextTask) {
            $result['nextTask'] = null; //deprecated - remove in the future
        }
        if ($returnNextTaskStartTime) {
            $result['nextTaskStartTime'] = null; //deprecated - remove in the future
        }
        if ($returnNextTasksByPriority) {
            $result['nextTasksByPriority'] = [];
        }

        $currentTime = time();
        $tempList = [];
        $list = $this->getListData($listID);
        foreach ($list as $taskID => $taskListData) {
            if ($returnUpcomingTasksCount) {
                $result['upcomingTasksCount']++;
            }
            if ($taskListData[0] === 1 || $taskListData[0] === 2) { // version check
                $startTime = $taskListData[1];
                $priority = $taskListData[0] === 2 ? $taskListData[2] : 3;
                $taskData = ['id' => $taskID, 'startTime' => $startTime, 'priority' => $priority];
                if ($returnUpcomingTasks || $returnNextTask || $returnNextTaskStartTime) {
                    $upcomingTasks[$taskID] = $taskData;
                }
                if ($returnNextTasksByPriority) {
                    if (!isset($result['nextTasksByPriority'][$priority]) || ((isset($result['nextTasksByPriority'][$priority]['startTime']) ? $result['nextTasksByPriority'][$priority]['startTime'] : $currentTime) > (isset($taskData['startTime']) ? $taskData['startTime'] : $currentTime))) {
                        $result['nextTasksByPriority'][$priority] = $taskData;
                    }
                }
                if ($startTime === null) {
                    $startTime = $currentTime;
                }
                $tempList[$taskID] = [$priority, $startTime];
            }
        }
        uasort($tempList, function ($a, $b) use ($currentTime) {
            if ($a[1] > $currentTime && $b[1] <= $currentTime) {
                return 1;
            }
            if ($a[1] <= $currentTime && $b[1] > $currentTime) {
                return -1;
            }
            if ($a[0] !== $b[0]) {
                return $a[0] < $b[0] ? -1 : 1;
            }
            if ($a[1] !== $b[1]) {
                return $a[1] < $b[1] ? -1 : 1;
            }
            return 0;
        });
        if ($returnNextTask || $returnNextTaskStartTime) {
            $nextTaskID = key($tempList);
            if ($nextTaskID !== null) {
                if ($returnNextTask) {
                    $result['nextTask'] = $upcomingTasks[$nextTaskID];
                    $nextTaskData = $this->getTaskData($listID, $nextTaskID);
                    $result['nextTask']['data'] = $nextTaskData === null ? null : $nextTaskData[2];
                }
                if ($returnNextTaskStartTime) {
                    $result['nextTaskStartTime'] = $upcomingTasks[$nextTaskID]['startTime']; // backwards compatibility
                }
            }
        }
        if ($returnUpcomingTasks) {
            $result['upcomingTasks'] = array_values($upcomingTasks);
        }
        if ($returnUpcomingTasks || $returnNextTask || $returnNextTaskStartTime) {
            unset($upcomingTasks);
        }
        if ($returnNextTasksByPriority) {
            $result['nextTasksByPriority'] = array_values($result['nextTasksByPriority']);
        }
        //print_r($result);
        return $result;
    }

    /**
     * Returns information about the task in the list specified.
     * 
     * @param string $listID
     * @param string $taskID
     * @return array|null
     */
    public function getTaskStats(string $listID, string $taskID): ?array
    {
        $taskData = $this->getTaskData($listID, $taskID);
        if ($taskData !== null) {
            $result = [];
            $list = $this->getListData($listID);
            if (isset($list[$taskID])) {
                $taskListData = $list[$taskID];
                if ($taskData[0] === 1 || $taskData[0] === 2) {
                    $result['definitionID'] = $taskData[1];
                    $result['data'] = $taskData[2];
                    $result['priority'] = $taskData[0] === 2 ? $taskData[3] : 3;
                }
                if ($taskListData[0] === 1 || $taskListData[0] === 2) {
                    $result['startTime'] = $taskListData[1];
                }
                return $result;
            }
        }
        return null;
    }

    /**
     * 
     * @param string $listID
     * @return array
     * @throws \Exception
     */
    private function getListData(string $listID): array
    {
        $app = App::get();
        $listDataKey = $this->getListDataKey($listID);
        $list = $app->data->getValue($listDataKey);
        $list = $list === null ? [] : json_decode(gzuncompress($list), true);
        if (!is_array($list)) {
            throw new \Exception('Corruped tasks list data (' . $listID . ')!');
        }
        return $list;
    }

    /**
     * 
     * @param string $listID
     * @param array $data
     */
    private function setListData(string $listID, array $data): void
    {
        $app = App::get();
        $listDataKey = $this->getListDataKey($listID);
        if (empty($data)) {
            $app->data->delete($listDataKey);
        } else {
            $app->data->setValue($listDataKey, gzcompress(json_encode($data)));
        }
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

    /**
     * 
     * @param string $listID
     */
    private function lockList(string $listID): void
    {
        $app = App::get();
        $app->locks->acquire('tasks-update-' . md5($listID));
    }

    /**
     * 
     * @param string $listID
     */
    private function unlockList(string $listID): void
    {
        $app = App::get();
        $app->locks->release('tasks-update-' . md5($listID));
    }
}
