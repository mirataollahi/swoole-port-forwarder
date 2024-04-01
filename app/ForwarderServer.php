<?php

namespace App;

use Swoole\Client;
use Swoole\Coroutine;
use Swoole\Server;

class ForwarderServer
{
    protected string $localIp;
    protected int $localPort;
    protected string $remoteIp;
    protected int $remotePort;

    protected Server $server;
    protected array $clientIds = [];
    protected float $requestTimeout = 6.0;


    public function __construct(string $localIp , int $localPort , string $remoteIp , string $remotePort)
    {
        $this->localIp = $localIp;
        $this->localPort = $localPort;
        $this->remoteIp = $remoteIp;
        $this->remotePort = $remotePort;
        $this->initServer();
    }

    /**
     * @return void
     */
    public function initServer(): void
    {
        $this->server = new Server($this->localIp, $this->localPort, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->server->on('start', [$this , 'onServerStart']);
        $this->server->on('connect', [$this , 'onClientConnect']);
        $this->server->on('receive',[$this , 'onClientRequest']);
        $this->server->on('close', [$this , 'onClientClose']);
        Logger::info("Starting Forwarder server ... ");
    }

    public function startServer(): void
    {
        $this->server->start();
    }

    public function onServerStart(): void
    {
        Logger::success("Forwarding server started at {$this->localIp}:{$this->localPort} to remote server $this->remoteIp:$this->remotePort");
    }


    public function onClientRequest(Server $server,int $fd, int $reactor_id , mixed $data): void
    {
        Logger::info("Request from client ID #$fd and rector id #$reactor_id");


        Coroutine::create(function () use ($server, $fd, $data){
            $remoteClient = new Client(SWOOLE_SOCK_TCP);
            if (!$remoteClient->connect($this->remoteIp, $this->remotePort, 1)) {
                Logger::error("Error in connection to remote server $this->remoteIp:$this->remotePort with error $remoteClient->errCode");
                return;
            }

            $remoteClient->send($data);
            Logger::info("Send the received data from client #$fd to the remote server");

            $date = null;
            $delay = 0.1;
            while (is_null($date)){
                Coroutine::sleep($delay);
                try {
                    $response = $remoteClient->recv();
                }
                catch (\Throwable $exception){}
                if ($response !== false) {
                    $date = $response;
                }
                else {
                    $delay += 0.1;
                    Logger::info("Waiting for remote server ...");
                }
                if ($delay >= $this->requestTimeout)
                {
                    $this->server->send($fd, '');
                    $remoteClient->close();
                    $this->server->close($fd);
                }
            }
            Logger::info("Receive data from the remote server sent with client #$fd");


            // Send the received data back to the client
            $this->server->send($fd, $date);
            Logger::info("Send the received data back to the client #$fd");

            // Close the connection to the remote server
            $remoteClient->close();
            Logger::info("Client connection to remote server closed using with client #$fd");


            $this->server->close($fd);
        });
    }



    /**
     * New client connection started with unique client identifier
     *
     * @param Server $server
     * @param int $fd
     * @return void
     */
    public function onClientConnect(Server $server , int $fd): void
    {
        Logger::info("New client connection established with ID #$fd");
        $this->addClient($fd);
    }

    /**
     * Client connection closed and the client will be deleted form client connection list
     *
     * @param Server $server
     * @param int $fd
     * @return void
     */
    public function onClientClose(Server $server , int $fd): void
    {
        $this->removeClient($fd);
        Logger::info("Client connection closed with ID #$fd");
    }


    /**
     * Add unique client identifier to connections list
     *
     * @param int $fd
     * @return void
     */
    public function addClient(int $fd): void
    {
        $this->clientIds [] = $fd;
    }

    /**
     * Remove unique client identifier from connections list
     *
     * @param int $fd
     * @return void
     */
    public function removeClient(int $fd): void
    {
        $key = array_search($fd, $this->clientIds);
        if ($key)
            unset($this->clientIds[$key]);
    }

    /**
     * Close client connections and stop server
     *
     * @return void
     */
    public function shutdown(): void
    {
        try {
            foreach ($this->clientIds as $fd)
                $this->server->close($fd);
        } catch (\Throwable $exception){}
        try {
            $this->server->shutdown();
        }
        catch (\Throwable $exception){}

    }

    /**
     * @param float $timeout
     * @return $this
     */
    public function setRequestTimeout(float $timeout): static
    {
        $this->requestTimeout = $timeout;
        return $this;
    }
}
