<?php

namespace App\Console\Commands;

use App\Helpers\MoodleHelper;
use App\Helpers\ServiceHelper;
use Illuminate\Console\Command;

class OperatorsAutomationCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'operators:automation';
	
	protected $signature = 'operators:automation {--unsafe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize operators on Service with ActiveCampaign, Cliq and Moodle';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $today = new \DateTime();
        $this->info("[" . $today->format("Y-m-d H:i:s") . "] Script started...");
        $exceptions = array();
		$safe_mode = !$this->option('unsafe');
		if($safe_mode) {
			$this->info("[" . $today->format("Y-m-d H:i:s") . "] Script in SAFE MODE");
		}

		$this->info("[" . $today->format("Y-m-d H:i:s") . "] Getting operators from Service...");
		$parsed_body = ServiceHelper::getOperators();
		$this->info("[" . $today->format("Y-m-d H:i:s") . "] Building the arrays of operators...");
        $all_service_operators = ServiceHelper::buildSoArray($parsed_body);
        $service_operators = $all_service_operators['service_operators'];
        $service_exoperators = $all_service_operators['service_exoperators'];

        //Moodle Section
        $so_array_for_moodle_to_add = $service_operators;
        $so_array_for_moodle_to_add = MoodleHelper::toMoodleUser($so_array_for_moodle_to_add);
        $moodle_users_to_enrol = array();
        $moodle_users_to_activate = array();
        $moodle_users_to_deactivate = array();

        try {
            $this->info("[" . $today->format("Y-m-d H:i:s") . "] Trying to get Moodle users...");
            $moodle_users = MoodleHelper::getUsers();
            $this->info("[" . $today->format("Y-m-d H:i:s") . "] Done.");
            foreach ($service_operators as $so_operator) {
                foreach ($moodle_users as $moodle_user) {
                    if ($so_operator->email === $moodle_user->email) {
                        if ($moodle_user->suspended === true) {
                            $this->info("[" . $today->format("Y-m-d H:i:s") . "] The user " . $moodle_user->email . " needs activation.");
                            array_push($moodle_users_to_activate, $moodle_user);
                        }
						array_push($moodle_users_to_enrol, $moodle_user);
						$this->info("[" . $today->format("Y-m-d H:i:s") . "] The user " . $moodle_user->email . " is added to enrollment.");
                        unset($so_array_for_moodle_to_add[$so_operator->userId]);
                    }
                }
            }
            foreach ($service_exoperators as $soex_operator) {
                foreach ($moodle_users as $moodle_user) {
                    if ($soex_operator->email === $moodle_user->email) {
                        if ($moodle_user->suspended === false) {
                            $this->info("[" . $today->format("Y-m-d H:i:s") . "] The user email " . $moodle_user->email . " needs deactivation.");
                            array_push($moodle_users_to_deactivate, $moodle_user);
                        }
                    }
                }
            }

            if (!$safe_mode) {
                $this->info("[" . $today->format("Y-m-d H:i:s") . "] Checking for user status update and user course enrollment...");
                MoodleHelper::updateUsers($moodle_users_to_activate, $moodle_users_to_deactivate, $moodle_users_to_enrol);
                $this->info("[" . $today->format("Y-m-d H:i:s") . "] Checking for new users to add...");
                MoodleHelper::addUser($so_array_for_moodle_to_add);
                $this->info("[" . $today->format("Y-m-d H:i:s") . "] Trying to enrol new users to courses...");
                MoodleHelper::enrolUser($so_array_for_moodle_to_add);
            } else {
                $this->info("[" . $today->format("Y-m-d H:i:s") . "] List of new users to add and enrol");

                foreach ($so_array_for_moodle_to_add as $user) {
                    $this->info("[" . $today->format("Y-m-d H:i:s") . "] " . $user->email . "\n");
                }
            }
        } catch (\Exception $ex) {
            $exception = new \StdClass();
            $exception->data = json_encode($ex);
            $exception->error = $ex->getMessage();
            $this->info('Exception: ' . $ex->getMessage());
            array_push($exceptions, $exception);
        }

        $now = new \DateTime();
        $this->info("[" . $now->format("Y-m-d H:i:s") . "] Script finished");
    }

}
