<?php

namespace App\Helpers;

use GuzzleHttp\Client;

class MoodleHelper
{
    const REQUEST_LENGHT = 20;
    const USER_DEFAULT_PASSWORD = 'xxxxxxxx';

    private static function request($method,$parameters){
        $path = '';
        foreach ($parameters as $key => $value) {
            if ($key != 'wstoken') {
                $path = $path.'&';
            }
            $path = $path.$key.'='.$value;
        }
        $client = new Client();        
        $url = env('MOODLE_API_URL') . '?' . $path;
        $response = $client->request($method, $url);
        $status_code = $response->getStatusCode();
        $response = json_decode($response->getBody());

        if (isset($response->exception)) {
            if (isset($response->debuginfo)) {
                throw new \Exception($response->debuginfo);
            }
            throw new \Exception($response->message);
        }

        if ($parameters['wsfunction'] == 'core_user_get_users') {
            return $response->users;
        }
        return $status_code;
    }

    public static function getUsers(){
        $parameters = [
            'wstoken' => env('MOODLE_API_KEY'),
            'wsfunction' => 'core_user_get_users',
            'criteria[0][key]' => 'email',
            'criteria[0][value]' => '%%',
            'moodlewsrestformat' => 'json'
        ];
        $moodle_users = self::request('GET', $parameters);
        foreach ($moodle_users as $user) {
            $users_fixed_index[$user->id] = $user;
        }
        return $users_fixed_index;
    }

    public static function updateUsers($users_to_activate, $users_to_deactivate, $users_to_enrol) {
        self::enrolUser($users_to_enrol);
        if (count($users_to_activate) < 1 && count($users_to_deactivate) < 1) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] No user needs to be status-updated.\n";
            return 1;
        }
        $i = 0;
        $parameters = [
            'wstoken' => env('MOODLE_API_KEY'),
            'wsfunction' => 'core_user_update_users',
            'moodlewsrestformat' => 'json'
        ];
        foreach ($users_to_activate as $user) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Preparing user ".$user->email." for the ACTIVATION\n";
            $parameter1 = array('users['.$i.'][id]' => $user->id);
            $parameter2 = array('users['.$i.'][suspended]' => '0');
            $parameters = array_merge($parameters, $parameter1, $parameter2);
            $i += 1;
        }
        foreach ($users_to_deactivate as $user) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Preparing user ".$user->email." for the DEACTIVATION\n";
            $parameter1 = array('users['.$i.'][id]' => $user->id);
            $parameter2 = array('users['.$i.'][suspended]' => '1');
            $parameters = array_merge($parameters, $parameter1, $parameter2);
            $i += 1;
        }
        if (self::request('GET', $parameters) == '200') {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] User(s) status updated.\n----------\n";
        }
            
        return 0;
    }

    public static function addUser($users_to_add) {
        if (count($users_to_add) < 1) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] No user needs to be added.\n";
            return 1;
        }
        $i = 0;
        $parameters = [
            'wstoken' => env('MOODLE_API_KEY'),
            'wsfunction' => 'core_user_create_users',
            'moodlewsrestformat' => 'json'
        ];
        foreach ($users_to_add as $user) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Preparing user ".$user->email." to be added.\n";
            $param1 = array('users['.$i.'][username]' => $user->email);
            $param2 = array('users['.$i.'][password]' => self::USER_DEFAULT_PASSWORD);
            $param3 = array('users['.$i.'][firstname]' => $user->firstName);
            $param4 = array('users['.$i.'][lastname]' => $user->lastName);
            $param5 = array('users['.$i.'][email]' => $user->email);
            $param6 = array('users['.$i.'][auth]' => 'manual');
            $parameters = array_merge($parameters, $param1, $param2, $param3, $param4, $param5, $param6);
            $i += 1;
            if ($i >= self::REQUEST_LENGHT || count($users_to_add) < self::REQUEST_LENGHT) {
                if (self::request('GET', $parameters) == '200') {
                    echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] User(s) imported\n----------\n";
                }
                $i = 0;
            }
        }
        return 0;
    }

    public static function toMoodleUser($users_to_edit) {
        foreach ($users_to_edit as $user) {
            try {
                if (preg_match('/[\'^£$%&*()}{#~?><>,|=+¬]/', $user->email)) {
                    throw new \Exception('Special character found in '. $user->email);
                }

                if (!isset($user->lastName) || $user->lastName == '') {
                    $users_to_edit[$user->userId]->lastName = $user->firstName;
                }

                $users_to_edit[$user->userId]->email = strtolower($user->email);
            } catch (\Exception $ex) {
                echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Exception: " . $ex->getMessage()."\n";
                unset($users_to_edit[$user->userId]);
                continue;
            }
        }
        return $users_to_edit;
    }

    public static function enrolUser($users_to_enrol) {
        $course_ids = [
            '61' => 'Age Check',
            '62' => 'Vascular Age',
            '60' => 'Vein Check',
            '59' => 'Sat Check',
            '58' => 'Metabolic Check',
            '57' => 'Pulmonary Check',
            '56' => 'Nail Check',
            '55' => 'Trico Check',
            '54' => 'UV Check',
            '53' => 'Skin Check',
            '52' => 'Densi Check',
            '51' => 'Stabilo Check',
            '50' => 'Body Check',
            '49' => 'Nutri Check',
            '48' => 'Body Composition',
            '47' => 'Nutri Smart',
            '46' => 'Calorimetric Check'
        ];

        if (count($users_to_enrol) < 1) {
            echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] No user needs to be enrolled into courses.\n";
            return 1;
        }
        $i = 0;
        $x = 0;
        $parameters = [
            'wstoken' => env('MOODLE_API_KEY'),
            'wsfunction' => 'enrol_manual_enrol_users',
            'moodlewsrestformat' => 'json'
        ];
        foreach ($users_to_enrol as $user) {
            foreach ($course_ids as $courseid => $course_name) {
                echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] Preparing user ".$user->email." to be enrolled into course ".$course_name."\n";
                $param1 = array('enrolments['.$i.'][roleid]' => 5);
                $param2 = array('enrolments['.$i.'][userid]' => $user->id);
                $param3 = array('enrolments['.$i.'][courseid]' => $courseid);
                $parameters = array_merge($parameters, $param1, $param2, $param3);
                $i += 1;
                if ($i >= self::REQUEST_LENGHT || (count($course_ids) * count($users_to_enrol) + $i < self::REQUEST_LENGHT)) {
                    if (self::request('GET', $parameters) == '200') {
                        echo "[" . (new \Datetime())->format("Y-m-d H:i:s") . "] User(s) enrolled\n----------\n";
                    }
                    $i = 0;
                }
            }
            unset($users_to_enrol[$x]);
            $x++;
        }
        return 0;
    }
}