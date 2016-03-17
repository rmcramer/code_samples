<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class checkForRetailSiteChanges extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'check:retailSites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and download any changes to retail sites.';

    var $site = null;
    var $retailer = null;
    var $possible_retailers = [ 'Shopify', 'Amazon' ];
    var $possible_retailers_list = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->generatePossibleRetailList();
        parent::__construct();
    }

    public function generatePossibleRetailList()
    {
        $possible_retail_list = '';
        $last_retailer = '';
        foreach($this->possible_retailers as $retailer)
        {
            $possible_retail_list .= $last_retailer;
            if ($last_retailer) $possible_retail_list .= ', ';
            $last_retailer = $retailer;
        }
        if ($possible_retail_list) $possible_retail_list .= 'or ';
        $possible_retail_list .= $last_retailer;
        $this->possible_retail_list = $possible_retail_list;
    }

    /**
     * Check if the options included are valid.
     *
     * @return true|false
     */
    public function checkIfOptionsValid()
    {
        $errors = [];

        $options = $this->option();

        if (!$options['site'] &&
            $options['site'] !== '0')
        {
            $errors[] = "Option --site is required. Site's ID, numeric. Ex. 1, 2, 3, ...";
        }
        else if (!is_numeric($options['site']) ||
            (string)(int)$options['site'] !== (string)$options['site'])
        {
            $errors[] = "Option --site must be an integer, the site's ID.";
        }

        if (!isset($options['retailer']))
        {
            $errors[] = "Option --retailer is required. Ex. ". $this->possible_retailers_list . ", etc.";
        }
        else if (!in_array($options['retailer'],$this->possible_retailers))
        {
            $errors[] = "Option --retailer can only be one of: ". $this->possible_retailers_list . ".";
        }

        if (!$options['days_ago'] &&
            $options['days_ago'] !== '0')
        {
            $errors[] = "Option --days_ago is required.";
        }
        else if (!is_numeric($options['days_ago']) ||
            (string)(int)$options['days_ago'] !== (string)$options['days_ago'] ||
            (int)$options['days_ago'] < 0)
        {
            $errors[] = "Option --days_ago must be a positive integer.";
        }

        if (count($errors))
        {
            $error_msg = "The following error" . (count($errors) > 1 ? 's were' : ' was') . " detected:\n\n";

            foreach ($errors as $error)
            {
                $error_msg .= '- ' . $error . "\n";
            }

            $this->error($error_msg);

            return false;
        }

        $this->site = (int)$options['site'];
        $this->retailer = $options['retailer'];
        $this->days_ago = (int)$options['days_ago'];

        return true;
    }

    /**
     * Check if the options included are appropriate values.
     *
     * @return true|false
     */
    public function checkIfOptionsAreAllowed()
    {
        $errors = [];

        if (!$this->site)
        {
            $errors[] = 'The Site ID provided is not found.';
        }
        else if (!isset($this->site->cfg_array[strtoupper($this->retailer) . '_SYNC']) ||
            !$this->site->cfg_array[strtoupper($this->retailer) . '_SYNC']->typed_value)
        {
            $errors[] = 'This site is not permitted to sync with ' . $this->retailer . '.';
        }

        if (count($errors))
        {
            $error_msg = "The following error" . (count($errors) > 1 ? 's were' : ' was') . " detected:\n\n";

            foreach ($errors as $error)
            {
                $error_msg .= '- ' . $error . "\n";
            }

            $this->error($error_msg);

            return false;
        }

        return true;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {

        if (!$this->checkIfOptionsValid()) return false;
        if (!$this->checkIfOptionsAreAllowed()) return false;

        //  $this->info('Some info'); // Regular Text

        $update_after = date("Y-m-d",mktime(0,0,0,
            (int)date("n"),
            ((int)date("j") - $this->days_ago),
            (int)date("Y")));

        //   var_dump($update_after);

        switch($this->retailer)
        {
            case 'Amazon':
                \AmazonAPI::findAndUpdateOrInsertAllOrdersLastUpdateAfter($update_after,$this->site->id,$this->site);
                break;
            case 'Shopify':
                \ShopifyAPI::findAndUpdateOrInsertAllOrdersUpdatedAfter($update_after,$this->site);
                break;
            default:
                return $this->error("No logic set for retailer: " . $this->retailer);
                break;

        }
        $this->comment("Command Finished.");
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
            ['site', null, InputOption::VALUE_REQUIRED, "The site's numeric ID.", false],
            ['retailer', null, InputOption::VALUE_REQUIRED, "The retailer to check.", false],
            ['days_ago', null, InputOption::VALUE_OPTIONAL, "The number of days back to start looking.", false],
        ];
    }

}
