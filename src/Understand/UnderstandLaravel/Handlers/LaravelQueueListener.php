<?php

namespace Understand\UnderstandLaravel\Handlers;

class LaravelQueueListener
{

    /**
     * Listen queue call
     *
     * @param object $job
     * @param array $data
     */
    public function listen($job, $data)
    {
        $requestData = $data['requestData'];
        $inputToken = \Config::get('understand-laravel::config.token');
        $apiUrl = \Config::get('understand-laravel::config.url', 'https://api.understand.io');
        $silent = \Config::get('understand-laravel::config.silent');

        $syncHandler = new SyncHandler($inputToken, $apiUrl, $silent);
        $syncHandler->handle($requestData);

        $job->delete();
    }

}
