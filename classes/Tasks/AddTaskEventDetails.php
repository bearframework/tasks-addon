<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework\Tasks;

/**
 * @property-read string $definitionID
 * @property-read string $taskID
 * @property-read string $listID
 * @property-read int|null $startTime
 * @property-read int|null $priority
 * @property-read mixed $data
 */
class AddTaskEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $definitionID
     * @param string $taskID
     * @param string $listID
     * @param int|null $startTime
     * @param int|null $priority
     * @param mixed $data
     */
    public function __construct(string $definitionID, string $taskID, string $listID, int $startTime = null, int $priority = null, $data = null)
    {
        $this
            ->defineProperty('definitionID', [
                'type' => 'string',
                'readonly' => true,
                'get' => function () use ($definitionID) {
                    return $definitionID;
                }
            ])
            ->defineProperty('taskID', [
                'type' => 'string',
                'readonly' => true,
                'get' => function () use ($taskID) {
                    return $taskID;
                }
            ])
            ->defineProperty('listID', [
                'type' => 'string',
                'readonly' => true,
                'get' => function () use ($listID) {
                    return $listID;
                }
            ])
            ->defineProperty('startTime', [
                'type' => '?int',
                'readonly' => true,
                'get' => function () use ($startTime) {
                    return $startTime;
                }
            ])
            ->defineProperty('priority', [
                'type' => '?int',
                'readonly' => true,
                'get' => function () use ($priority) {
                    return $priority;
                }
            ])
            ->defineProperty('data', [
                'readonly' => true,
                'get' => function () use ($data) {
                    return $data;
                }
            ]);
    }
}
