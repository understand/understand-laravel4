<?php

namespace Understand\UnderstandLaravel\Handlers;

class LaravelQueueHandler extends BaseHandler
{

    /**
     * Send data to storage
     *
     * @param array $event
     * @return type
     */
    protected function send($requestData)
    {
        \Queue::push('Understand\UnderstandLaravel\Handlers\LaravelQueueListener@listen', [
            'requestData' => $requestData
        ]);
    }

    /**
     * Serialize data and send to storage
     *
     * @param array $requestData
     * @return array
     */
    public function handle(array $requestData)
    {
        return $this->send($requestData);
    }

}
