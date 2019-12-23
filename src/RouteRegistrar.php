<?php

namespace Fenguoz\MachineLease;

use Illuminate\Contracts\Routing\Registrar as Router;

class RouteRegistrar
{
    /**
     * The router implementation.
     *
     * @var \Illuminate\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new route registrar instance.
     *
     * @param  \Illuminate\Contracts\Routing\Registrar  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Register routes for reward and reward order.
     *
     * @return void
     */
    public function all()
    {
        $this->forUsersMachine();
    }

    /**
     * Register the routes for reward.
     *
     * @return void
     */
    public function forUsersMachine()
    {
        $this->router->group(['middleware' => 'token:user'], function ($router) {
            $router->get('/get.user.machine.list', ['uses' => 'UsersMachineController@getMachineList']);
            $router->get('/get.user.machine.info', ['uses' => 'UsersMachineController@getUserInfo']);
            $router->get('/get.user.machine.output', ['uses' => 'UsersMachineController@getMachineOutput']);
        });
        $this->router->group(['middleware' => 'token:admin'], function ($router) {
            $router->get('/get.machine.list', ['uses' => 'UsersMachineController@machineList']);
            $router->get('/get.machine.output', ['uses' => 'UsersMachineController@machineOutput']);
        });
    }
}
