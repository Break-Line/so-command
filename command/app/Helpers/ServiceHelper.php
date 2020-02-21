<?php

namespace App\Helpers;

use Art4\JsonApiClient\Helper\Parser;
use Exception;
use GuzzleHttp\Client;

class ServiceHelper
{
    private static function request($method, $parameters, $path = '')
    {
        $path = $path . '?';
        $is_first_parameter = true;
        foreach ($parameters as $key => $value) {
            if (!$is_first_parameter) {
                $path = $path . '&';
            }
            $path = $path . $key . '=' . $value;
            $is_first_parameter = false;
        }
        $client = new Client();
        $url = env('SERVICE2_API_URL') . $path;
        $options['headers'] = array(
            'x-api-key' => env('SERVICE2_API_KEY'),
            'Accept' => 'application/json',
        );
        $response = $client->request($method, $url, $options);

        return $response->getBody()->__toString();
    }

    public static function getOperators($pageLimit = 1000)
    {
        $resource = 'operators';
        $parameters = [
            'include' => 'user',
            'fields[operators]' => 'activeCampaignEmail,user,address,phone',
            'page[limit]' => $pageLimit,
        ];
        $body_string = self::request('GET', $parameters, $resource);

     if (Parser::isValidResponseString($body_string)) {
            $parsed_body = Parser::parseResponseString($body_string);
        } else {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Parser couldn't parse the operators response. Diying.\n";
            die();
        }

        return $parsed_body;
    }

    public static function buildSoArray($parsed_body)
    {
        $service_operators = array();
        $service_exoperators = array();
        $elementsProperties = $parsed_body->get('meta.page');
        $totalElements = $elementsProperties->total;

        for ($i = 0; $i < $totalElements; $i++) {
            if ($parsed_body->get('included.' . $i . '.type') != 'users') {
                continue;
            }

            $userId = $parsed_body->get('included.' . $i . '.id'); 

            $operator[$userId] = new \StdClass();

            $operator[$userId]->userId = $userId;
            $operator[$userId]->firstName = $parsed_body->get('included.' . $i . '.attributes.firstName');
            $operator[$userId]->lastName = $parsed_body->get('included.' . $i . '.attributes.lastName');
            $operator[$userId]->email = $parsed_body->get('included.' . $i . '.attributes.email');
            $operator[$userId]->active = $parsed_body->get('included.' . $i . '.attributes.active');

            if ($operator[$userId]->active == 1) {
                $service_operators[$userId] = $operator[$userId];
            } else {
                $service_exoperators[$userId] = $operator[$userId];
            }
        }
        $jointedArray = array('service_operators' => $service_operators,
            'service_exoperators' => $service_exoperators);
        return $jointedArray;
    }
}
