<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Session;
use Auth;
use Carbon;

class checkForDeliveredPackages extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'check:packages';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Check undelivered shipments for delivery status.';

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
	public function fire()
	{
        \App\Helpers::loginAsAdminProcessUser();

        \App\Shipments::updateUndeliveredShipments();
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
	//		['example', InputArgument::REQUIRED, 'An example argument.'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
	//		['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
		];
	}

}