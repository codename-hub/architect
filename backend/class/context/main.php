<?php

namespace codename\architect\context;

use codename\architect\app;
use codename\architect\dbdoc\dbdoc;
use codename\architect\dbdoc\task;
use codename\core\catchableException;
use codename\core\context;
use codename\core\exception;
use codename\core\ui\frontend\element\table;
use ReflectionException;

/**
 * main context
 * for listing apps
 * and their models (on demand)
 */
class main extends context
{
    /**
     * view "default"
     * @throws ReflectionException
     * @throws exception
     */
    public function view_default(): void
    {
        $this->view_listapps();
        $this->getResponse()->setData('view', 'listapps');
    }

    /**
     * [view_listapps description]
     * @return void
     * @throws ReflectionException
     * @throws exception
     */
    public function view_listapps(): void
    {
        $apps = app::getSiblingApps();
        $this->getResponse()->setData('apps', $apps);

        $table = new table([
          'templateengine' => $this->getResponse()->getData('templateengine') ?? 'default',
        ], $apps);

        $this->getResponse()->setData('table', $table->outputString());
    }

    /**
     * Displays a list of available models
     * for a given vendor and app name
     * @return void
     * @throws ReflectionException
     * @throws catchableException
     * @throws exception
     */
    public function view_listmodels(): void
    {
        if ($this->getRequest()->getData('filter>vendor') != null && $this->getRequest()->getData('filter>app') != null) {
            $app = $this->getRequest()->getData('filter>app');
            $vendor = $this->getRequest()->getData('filter>vendor');

            $exec_tasks = $this->getRequest()->getData('exec_tasks') ? array_values($this->getRequest()->getData('exec_tasks')) : [task::TASK_TYPE_REQUIRED]; // by default, only execute required tasks

            $dbdoc = new dbdoc($app, $vendor);

            $stats = $dbdoc->run(
                $this->getRequest()->getData('exec') == '1',
                $exec_tasks
            );

            // store dbdoc output
            $this->getResponse()->setData('dbdoc_stats', $stats);

            // store models dbdoc found
            $this->getResponse()->setData('models', $dbdoc->models);

            // create a table
            $table = new table([
              'templateengine' => $this->getResponse()->getData('templateengine') ?? 'default',
              'columns' => [ /* 'vendor', 'app', */ 'identifier', 'model', 'schema', 'driver'],
            ], $dbdoc->models);

            $this->getResponse()->setData('table', $table->outputString());
        } else {
            if ($this->getRequest()->getData('filter>vendor') == null) {
                throw new catchableException("EXCEPTION_ARCHITECT_CONTEXT_MAIN_MISSING_FILTER_VENDOR", catchableException::$ERRORLEVEL_ERROR);
            }
            if ($this->getRequest()->getData('filter>app') == null) {
                throw new catchableException("EXCEPTION_ARCHITECT_CONTEXT_MAIN_MISSING_FILTER_APP", catchableException::$ERRORLEVEL_ERROR);
            }
        }
    }
}
