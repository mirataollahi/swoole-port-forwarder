<?php

namespace App;

use Swoole\Server;


class Application
{
    protected array $forwarderServer = [];
    public function __construct()
    {
        register_shutdown_function(function (){
            $this->shutdownServers();
        });

        echo PHP_EOL;
        Logger::info("Application starting ... ");

        $this->createForwardingServers();

        $this->startingForwardingServers();
    }

    /**
     * Creating forwarding servers base on env file configs
     *
     * @return void
     */
    public function createForwardingServers(): void
    {
        Logger::info("Creating forwarding servers base on env configs");
        sleep(1);
        $serversList = CONFIG['forwarding_rules'];
        foreach ($serversList as $serverConfig){
            Logger::info("Creating forwarding rule from {$serverConfig['local_ip']}:{$serverConfig['local_port']} to {$serverConfig['remote_ip']}:{$serverConfig['remote_port']}");
            $this->forwarderServer [] = new ForwarderServer(
                $serverConfig['local_ip'] ,
                $serverConfig['local_port'] ,
                $serverConfig['remote_ip'] ,
                $serverConfig['remote_port'] ,
            );
        }
        sleep(1);
    }

    public function startingForwardingServers(): void
    {
        foreach ($this->forwarderServer as $server){
            /* @var $server ForwarderServer */
            $server->startServer();
        }
    }


    /**
     * Shutdown and cleaning up application before stop
     *
     * @return void
     */
    public function shutdownServers(): void
    {
        foreach ($this->forwarderServer as $server)
        {
            /* @var $server ForwarderServer */
            $server->shutdown();
            unset($server);
        }
    }
}
