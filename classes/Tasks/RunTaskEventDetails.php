<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework\Tasks;

/**
 * @property string $definitionID
 * @property string $taskID
 * @property array $data
 */
class RunTaskEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $filename
     * @param array $options
     */
    public function __construct(string $definitionID, string $taskID, $data)
    {
        $this
            ->defineProperty('definitionID', [
                'type' => 'string'
            ])
            ->defineProperty('taskID', [
                'type' => 'string'
            ])
            ->defineProperty('data');
        $this->definitionID = $definitionID;
        $this->taskID = $taskID;
        $this->data = $data;
    }
}
