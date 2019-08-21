<?php
namespace AfipClient\Clients\Auth;

use AfipClient\Clients\Auth\AuthClient;
use AfipClient\Clients\Auth\AccessTicket;
use AfipClient\Clients\Auth\AccessTicketLoader;
use AfipClient\Clients\Auth\AccessTicketStore;
use AfipClient\Clients\Auth\LoginTicketRequest;
use AfipClient\Clients\Auth\LoginTicketResponse;
use AfipClient\Clients\Client;
use AfipClient\AuthParamsProvider;
use AfipClient\ACException;
use AfipClient\ACHelper;

/**
 * Clase encargada de manejar la respuesta del ws cuando le mandamos el ticket de requerimiento de acceso
 */
class AccessTicketProcessor implements AuthParamsProvider
{
    private $auth_client;
    private $access_ticket;
    private $store;
    private $loader;
    private $login_ticket_request;
    private $login_ticket_response;
    private $should_cache_access_ticket;

    public function __construct(
                                  AuthClient $auth_client,
                                  AccessTicket $access_ticket,
                                  AccessTicketStore $store,
                                  AccessTicketLoader $loader,
                                  LoginTicketRequest $login_ticket_request,
                                  LoginTicketResponse $login_ticket_response,
                                  bool $should_cache_access_ticket
    ) {
        $this->auth_client = $auth_client;
        $this->access_ticket = $access_ticket;
        $this->store = $store;
        $this->loader = $loader;
        $this->login_ticket_request = $login_ticket_request;
        $this->login_ticket_response = $login_ticket_response;
        $this->should_cache_access_ticket = $should_cache_access_ticket;
    }

    /**
     * Manda a procesar el access ticket y Crea array con datos de acceso
     * @return array ['token' => '', 'sign' => '', 'cuit' => '']
     * @throws A
     */
    public function getAuthParams(Client $service_client)
    {
        $this->_processAccessTicket($service_client);

        if ($this->access_ticket->isEmptyOrExpired()) {
            throw new ACException("Error procesando ticket de acceso", $service_client);
        }

        return [ 'Token' => $this->access_ticket->getToken(),
                 'Sign' => $this->access_ticket->getSign(),
                 'Cuit' => $this->access_ticket->getTaxId() ];
    }

    /**
     * @param Client $service_client cliente que requiere acceso
     * @throws ACException
     */
    private function _processAccessTicket(Client $service_client)
    {
        if(!$this->should_cache_access_ticket) {
            $this->_createAccessTicket($service_client);
            return;
        }

        $at_name = $service_client->getClientName(); 

        if ($this->access_ticket->isEmpty()) {
            $this->loader->loadFromStorage($at_name, $this->store, $this->access_ticket);
        }

        if ($this->access_ticket->isExpired()) {

            $access_ticket_data = $this->_createAccessTicket($service_client);
            $this->store->saveDataToStorage($at_name, $access_ticket_data);
        }
    }

    private function _createAccessTicket(Client $service_client) {

        //obtengo el cms para requerimiento de acceso
        $ltr_cms = $this->login_ticket_request->getCms($service_client);

        //envio el cms al WS
        $response = $this->auth_client->sendCms($ltr_cms);

        //Extraigo de la respuesta el xml con los datos de acceso
        $access_ticket_data = $this->login_ticket_response->getAccessTicketData($response);

        //cargo la data en el ticket
        $this->loader->load($this->access_ticket, $access_ticket_data);
    }
}
