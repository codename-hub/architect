<?php

namespace codename\architect\context;

use codename\core\context;
use codename\core\exception;
use ReflectionException;

/**
 * deployment context
 */
class deployment extends context
{
    /**
     * list available app configs for deployment
     * @return void
     */
    public function view_apps(): void
    {
    }

    /**
     * view available tasks from a given deployment config
     * @return void
     */
    public function view_tasks(): void
    {
    }

    /**
     * run a given deployment configuration
     * @return void
     * @throws ReflectionException
     * @throws exception
     */
    public function view_run(): void
    {
        $vendor = $this->getRequest()->getData('vendor');
        $app = $this->getRequest()->getData('app');
        $deploy = $this->getRequest()->getData('deploy');

        $instance = \codename\architect\deploy\deployment::createFromConfig($vendor, $app, $deploy);

        $result = $instance->run();

        $this->getResponse()->setData('deploymentresult', $result);
    }

    /**
     * [view_default description]
     * @return void
     */
    public function view_default(): void
    {
    }
}
