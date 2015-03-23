<?php

namespace Understand\UnderstandLaravel\Handlers;

class LaravelQueueHandler extends BaseHandler
{

    /**
     * Send data to storage
     *
     * @param array $requestData
     * @return type
     */
    protected function send($requestData)
    {
        try
        {
            \Queue::push('Understand\UnderstandLaravel\Handlers\LaravelQueueListener@listen', [
                'requestData' => $requestData
            ]);
        }
        catch (\Exception $ex)
        {
            if ( ! $this->silent)
            {
                throw new $ex;
            }
        }
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
