<?php

namespace App\Installer\Helpers;

use App\Support\EnvWriter;
use Exception;
use Illuminate\Http\Request;

class EnvironmentManager
{
    /**
     * @var string
     */
    private $envPath;

    /**
     * @var string
     */
    private $envExamplePath;

    /**
     * Set the .env and .env.example paths.
     */
    public function __construct()
    {
        $this->envPath = base_path('.env');
        $this->envExamplePath = base_path('.env.example');
    }

    /**
     * Get the content of the .env file.
     *
     * @return string
     */
    public function getEnvContent()
    {
        if (! file_exists($this->envPath)) {
            if (file_exists($this->envExamplePath)) {
                copy($this->envExamplePath, $this->envPath);
            } else {
                touch($this->envPath);
            }
        }

        return file_get_contents($this->envPath);
    }

    /**
     * Save the edited content to the file.
     *
     * @return string
     */
    public function saveFile(Request $input)
    {

        $message = trans('messages.environment.success');

        $env = $this->getEnvContent();
        $dbName = $input->get('database');
        $dbHost = $input->get('hostname');
        $dbUsername = $input->get('username');
        $dbPassword = $input->get('password');

        $databaseSetting = 'DB_HOST='.EnvWriter::value((string) $dbHost)."\n"
            .'DB_DATABASE='.EnvWriter::value((string) $dbName)."\n"
            .'DB_USERNAME='.EnvWriter::value((string) $dbUsername)."\n"
            .'DB_PASSWORD='.EnvWriter::value((string) $dbPassword)."\n"
            .'APP_URL='.EnvWriter::value(request()->getSchemeAndHttpHost())."\n";

        $cleanString = EnvWriter::stripKeys($env, ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_URL']);

        $env = $cleanString."\n".$databaseSetting;
        try {
            $dbh = new \PDO('mysql:host='.$dbHost, $dbUsername, $dbPassword);

            $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $stmt = $dbh->query('CREATE DATABASE IF NOT EXISTS `'.$dbName.'` CHARACTER SET utf8 COLLATE utf8_general_ci;');
            $message = 'Database settings correct';

            try {
                EnvWriter::write($this->envPath, $env);
            } catch (\Throwable $e) {
                $message = trans('messages.environment.errors');
            }

            return Reply::redirect(route('LaravelInstaller::superAdmin'), $message);

        } catch (\PDOException $e) {
            return Reply::error('DB Error: '.$e->getMessage());

        } catch (Exception $e) {

            return Reply::error('DB Error: '.$e->getMessage());

        }
    }
}
