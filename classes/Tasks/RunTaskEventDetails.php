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
 * @property-read mixed $data
 */
class RunTaskEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $definitionID
     * @param string $taskID
     * @param mixed $data
     */
    public function __construct(string $definitionID, string $taskID, $data)
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
            ->defineProperty('data', [
                'readonly' => true,
                'get' => function () use ($data) {
                    return $data;
                }
            ]);
    }
}
