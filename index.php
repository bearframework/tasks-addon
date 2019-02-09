<?php

/*
 * Tasks addon for Bear Framework
 * https://github.com/bearframework/tasks-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__FILE__);

$context->classes
        ->add('BearFramework\Tasks', 'classes/Tasks.php')
        ->add('BearFramework\Tasks\*', 'classes/Tasks/*.php');

$app->shortcuts
        ->add('tasks', function() {
            return new \BearFramework\Tasks();
        });
