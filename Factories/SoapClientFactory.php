<?php
namespace AfipClient\Factories;
use AfipClient\Traits\FileManager;

Class SoapClientFactory{

	use FileManager;

	/**
	 * Crea un cliente soap
	 * @param string $wsdl
	 * @param string $end_point
	 * @return SoapClient
	 */ 
	public static function create( $wsdl, $end_point ){
		
		return new \SoapClient( $wsdl, 
                [
                    'soap_version'   => SOAP_1_2,
                    'location'       => $end_point,
                ]);

	}


}