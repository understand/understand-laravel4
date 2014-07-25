<?php

namespace Understand\UnderstandLaravel\Handlers;

class SyncHandler extends BaseHandler
{

    /**
     * Send data to storage
     *
     * @param array $event
     * @return type
     */
    protected function send($requestData)
    {
        $endpoint = $this->getEndpoint();

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}
