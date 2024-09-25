<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework\Tasks;

/**
 * @property-read string $listID
 */
class RunEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $listID
     */
    public function __construct(string $listID)
    {
        $this
            ->defineProperty('listID', [
                'type' => 'string',
                'readonly' => true,
                'get' => function () use ($listID) {
                    return $listID;
                }
            ]);
    }
}
