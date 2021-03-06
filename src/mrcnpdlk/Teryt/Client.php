<?php
/**
 * TERYT-API
 *
 * Copyright (c) 2017 pudelek.org.pl
 *
 * @license MIT License (MIT)
 *
 * For the full copyright and license information, please view source file
 * that is bundled with this package in the file LICENSE
 *
 * @author  Marcin Pudełek <marcin@pudelek.org.pl>
 *
 */

declare (strict_types=1);

namespace mrcnpdlk\Teryt;

use mrcnpdlk\Psr16Cache\Adapter;
use mrcnpdlk\Teryt\Exception\Connection;
use mrcnpdlk\Teryt\Exception\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Class Client
 *
 * @package mrcnpdlk\Teryt
 */
class Client
{
    const SERVICE_URL_TEST      = 'https://uslugaterytws1test.stat.gov.pl/wsdl/terytws1.wsdl';
    const SERVICE_URL           = 'https://uslugaterytws1.stat.gov.pl/wsdl/terytws1.wsdl';
    const SERVICE_USER_TEST     = 'TestPubliczny';
    const SERVICE_PASSWORD_TEST = '1234abcd';
    /**
     * SoapClient handler
     *
     * @var \mrcnpdlk\Teryt\TerytSoapClient
     */
    private $soapClient;
    /**
     * Cache handler
     *
     * @var CacheInterface
     */
    private $oCache;
    /**
     * @var Adapter
     */
    private $oCacheAdapter;
    /**
     * Logger handler
     *
     * @var LoggerInterface
     */
    private $oLogger;
    /**
     * @var string
     */
    private $sServiceUrl;
    /**
     * @var string
     */
    private $sServiceUsername;
    /**
     * @var string
     */
    private $sServicePassword;

    /**
     * Client constructor.
     */
    public function __construct()
    {
        $this->setConfig();
        $this->setLoggerInstance();
        $this->setCacheInstance();
    }

    /**
     * @return array
     *
     */
    public function __debugInfo()
    {
        return ['Top secret'];
    }

    /**
     * Get logger instance
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->oLogger;
    }

    /**
     * Get SoapClient
     *
     * @return \mrcnpdlk\Teryt\TerytSoapClient
     */
    private function getSoap()
    {
        try {
            if (!$this->soapClient) {
                $this->reinitSoap();
            }

        } catch (\Exception $e) {
            Helper::handleException($e);
        }

        return $this->soapClient;
    }

    /**
     * Reinit Soap Client
     *
     * @return $this
     * @throws Connection
     * @throws Exception
     */
    private function reinitSoap()
    {
        try {
            $this->soapClient = new TerytSoapClient($this->sServiceUrl, [
                'soap_version' => SOAP_1_1,
                'exceptions'   => true,
                'cache_wsdl'   => WSDL_CACHE_BOTH,
            ]);
            $this->soapClient->addUserToken($this->sServiceUsername, $this->sServicePassword);
        } catch (\Exception $e) {
            throw Helper::handleException($e);
        }

        return $this;
    }

    /**
     * Making request to Teryt WS1 API
     *
     * @param string  $method  Method name
     * @param array   $args    Parameters
     * @param boolean $addDate Add DataStanu to request
     *
     * @return mixed
     * @throws \mrcnpdlk\Teryt\Exception
     * @throws \mrcnpdlk\Teryt\Exception\Connection
     */
    public function request(string $method, array $args = [], bool $addDate = true)
    {
        try {
            if (!array_key_exists('DataStanu', $args) && $addDate) {
                $args['DataStanu'] = (new \DateTime())->format('Y-m-d');
            }
            $self    = $this;
            $this->oLogger->debug(sprintf('REQ: %s', $method), $args);


            $resp = $this->oCacheAdapter->useCache(
                function () use ($self, $method, $args) {
                    $res       = $self->getSoap()->__soapCall($method, [$args]);
                    $resultKey = $method . 'Result';
                    if (!property_exists($res, $resultKey)) {
                        throw new Response(sprintf('%s doesnt exist in response', $resultKey));
                    }

                    return $res->{$resultKey};
                },
                [__METHOD__, $method, $args]
            );

            $this->oLogger->debug(sprintf('RESP: %s, type is %s', $method, gettype($resp)));

            return $resp;

        } catch (\Exception $e) {
            throw Helper::handleException($e);
        }
    }

    /**
     * Setting Cache Adapter
     *
     * @return $this
     */
    private function setCacheAdapter()
    {
        $this->oCacheAdapter = new Adapter($this->oCache, $this->oLogger);

        return $this;
    }

    /**
     * Set Cache handler (PSR-16)
     *
     * @param CacheInterface|null $oCache
     *
     * @return \mrcnpdlk\Teryt\Client
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md PSR-16
     */
    public function setCacheInstance(CacheInterface $oCache = null)
    {
        $this->oCache = $oCache;
        $this->setCacheAdapter();

        return $this;
    }

    /**
     * Set Teryt configuration parameters
     *
     * @param string|null $username     Service username
     * @param string|null $password     Service password
     * @param bool        $isProduction Default FALSE
     *
     * @return $this
     *
     */
    public function setConfig(string $username = null, string $password = null, bool $isProduction = false)
    {
        $this->sServiceUrl      = $isProduction ? Client::SERVICE_URL : Client::SERVICE_URL_TEST;
        $this->sServiceUsername = $username ?? Client::SERVICE_USER_TEST;
        $this->sServicePassword = $password ?? Client::SERVICE_PASSWORD_TEST;

        $this->reinitSoap();

        return $this;
    }

    /**
     * Set Logger handler (PSR-3)
     *
     * @param LoggerInterface|null $oLogger
     *
     * @return $this
     */
    public function setLoggerInstance(LoggerInterface $oLogger = null)
    {
        $this->oLogger = $oLogger ?: new NullLogger();
        $this->setCacheAdapter();

        return $this;
    }

}
