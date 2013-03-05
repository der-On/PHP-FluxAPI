<?php
require_once "PHPUnit/Extensions/Database/TestCase.php";

abstract class FluxApi_Database_TestCase extends PHPUnit_Extensions_Database_TestCase
{
    // only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

    protected static $fluxApi;
    protected static $config;

    protected function setUp()
    {
        self::$config = $this->getConfig();

        // clear the database
        $conn = $this->getConnection();
        self::$pdo->exec('DROP TABLE IF EXISTS node');

        $loader = require __DIR__ . '/../../../vendor/autoload.php';

        // create application
        $app = new Silex\Application();

        if (self::$config['debug'] == TRUE) {
            $app['debug'] = TRUE;
        }

        self::$fluxApi = new FluxAPI\Api($app,self::$config);
    }

    protected function getConfig()
    {
        return  json_decode(file_get_contents(__DIR__ . '/../../../config/testing.json'),TRUE);
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    final public function getConnection()
    {
        $config = self::$config;

        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO('mysql:dbname='.$config['storage.options']['database'].';host='.$config['storage.options']['host'],$config['storage.options']['user'],$config['storage.options']['password']);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo,':'.$config['storage.options']['host'].':');
        }


        return $this->conn;
    }

    public function getDataSet()
    {
        return new PHPUnit_Extensions_Database_DataSet_DefaultDataSet();
    }
}