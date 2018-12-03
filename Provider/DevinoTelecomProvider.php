<?php

namespace Avtonom\Sms\DevinoTelecomBundle\Provider;

use SmsSender\Exception as Exception;
use SmsSender\HttpAdapter\HttpAdapterInterface;
use SmsSender\Result\ResultInterface;
use SmsSender\Provider\AbstractProvider;
use SmsSender\Exception\ResponseException;
use Psr\Log\LoggerAwareTrait;

class DevinoTelecomProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    const URL_AUTH = 'https://integrationapi.net/rest/user/sessionid';
    const URL_SEND = 'https://integrationapi.net/rest/v2/Sms/Send';
    const URL_BALANCE = 'https://integrationapi.net/Rest/v2/User/Balance';
    const URL_SMS_STATUS = 'https://integrationapi.net/rest/v2/Sms/State';

    /**
     * @var string
     */
    protected $login;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $authToken;

    /**
     * @var array
     */
    protected $originators;

    /**
     * {@inheritDoc}
     */
    public function __construct(HttpAdapterInterface $adapter, $login, $password, array $originators = array())
    {
        parent::__construct($adapter);
        $this->login = $login;
        $this->password = $password;
        $this->originators = $originators;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Returns the HTTP adapter class for logger.
     *
     * @return string
     */
    public function getAdapterClass()
    {
        return get_class($this->adapter);
    }

    /**
     * Send a message to the given phone number.
     *
     * @param string $recipient  The phone number.
     * @param string $body       The message to send.
     * @param string $originator The name of the person which sends the message (from).
     *
     * @return array The data returned by the API.
     *
     * @throws Exception\InvalidArgumentException
     * @throws ResponseException
     */
    public function send($recipient, $body, $originator = '')
    {
        if (empty($originator)) {
            throw new Exception\InvalidArgumentException('The originator parameter is required for this provider.');
        }

        $params = $this->getParameters(array(
            'DestinationAddress'    => $recipient,
            'Data'  => $body, // <= 2000
            'SourceAddress'  => $originator, //  До 11 латинских символов или до 15 цифровых.
        ));
        $this->getLogger()->addDebug('$params: '.print_r($params, true));

        $smsData = $this->executeQuery(self::URL_SEND, $params);
        $smsData = $this->parseResponseSingleArray($smsData);
        $this->getLogger()->addDebug('Result: '.print_r($smsData, true));
        return $smsData;
    }

    /**
     * @param $messageId
     * @return array
     *
     * @throws ResponseException
     *
     * http://docs.devinotele.com/httpapiv2.html#id12
     */
    public function getSmsStatus($messageId)
    {
        $params = $this->getParameters(array(
            'messageId' => $messageId,
        ));
        $smsData = $this->executeQuery(self::URL_SMS_STATUS, $params);
        if(empty($smsData['response']) || !isset($smsData['response']['State'])){
            throw new ResponseException('Response is empty');
        }
        $smsData['id'] = $messageId;
        switch($smsData['response']['State']){
            case -1:
                $smsData['status'] = ResultInterface::STATUS_SENT;
                break;
            case 0:
                $smsData['status'] = ResultInterface::STATUS_DELIVERED;
                break;
            case 42:
                $smsData['status'] = ResultInterface::STATUS_FAILED;
                break;
            case 46: // Expired (Lifetime expired messages)
                $smsData['status'] = ResultInterface::STATUS_FAILED;
                break;
            case 255: // Expired (*сообщение еще не успело попасть в БД / *сообщение старше 48 часов.)
                $smsData['status'] = ResultInterface::STATUS_QUEUED;
                break;
            default:
                throw new ResponseException(vsprintf('Unknown status "%s": "%s"', array($smsData['response']['State'], (!empty($smsData['response']['StateDescription'])?$smsData['response']['StateDescription']:''))));
        }
        return $smsData;
    }

    /**
     * @return array The data returned by the API.
     *
     * @throws ResponseException
     */
    public function getBalance()
    {
        $params = $this->getParameters([]);
        $smsData = $this->executeQuery(self::URL_BALANCE, $params);
        $smsData = $this->parseResponseSingle($smsData);
        return $smsData;
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        if(!$this->authToken){
            $resultAuth = $this->executeQuery(self::URL_AUTH, array(
                'Login'     => $this->login,
                'Password'  => $this->password,
            ));
            $resultAuth = $this->parseResponseSingle($resultAuth);
            $this->authToken = $resultAuth['id'];
        }
        return $this->authToken;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'devinotelecom';
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $extraResultData
     *
     * @return array
     */
    protected function executeQuery($url, array $data = array(), array $extraResultData = array())
    {
        $response = $this->getAdapter()->getContent($url, 'POST', array('Content-type: application/x-www-form-urlencoded'), $data);
        $this->getLogger()->addDebug(print_r($this->getAdapter()->getLastRequest(), true));
        $this->getLogger()->addDebug('Response: '.$response);
        $smsData = $this->parseResponse($response);
        $result = array_merge($this->getDefaults(), $extraResultData, $smsData);
        $this->getLogger()->addDebug('Result base prepare response: '.print_r($result, true));
        return $result;
    }

    /**
     * Builds the parameters list to send to the API.
     *
     * @param array $additionnalParameters
     *
     * @return array
     */
    public function getParameters(array $additionnalParameters = array())
    {
        return array_merge(array(
            'Login'     => $this->login,
            'Password'  => $this->password,
        ), $additionnalParameters);
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $response The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponse($response)
    {
        if(empty($response)){
            return array();
        }
        $responseData = json_decode($response, true);
//        $this->getLogger()->addDebug('Response data: '.print_r($responseData, true));
        $smsData = array(
            'response' => $responseData
        );

        if(!empty($responseData['Code'])){
            $smsData['status'] = ResultInterface::STATUS_FAILED;
            $smsData['message'] = !empty($responseData['Desc']) ? $responseData['Desc'] : 'Result status code: '.$responseData['Code'];
            $responseException = new ResponseException($smsData['message'], $responseData['Code']);
            $responseException->getData($responseData);
            throw $responseException;
        }
        return $smsData;
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $smsData The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponseSingle($smsData)
    {
        if(!array_key_exists('response', $smsData) || (!is_string($smsData['response']) && !is_numeric($smsData['response']))){
            throw new ResponseException('Incorrect single value');
        }
        $smsData['id'] = $smsData['response'];
        $smsData['status'] = ResultInterface::STATUS_DELIVERED;
        return $smsData;
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $smsData The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponseSingleArray($smsData)
    {
        if(!array_key_exists('response', $smsData) || !is_array($smsData['response']) || !isset($smsData['response'][0])){
            throw new ResponseException('Incorrect single array value');
        }
        $smsData['id'] = json_encode($smsData['response']);
        $smsData['status'] = ResultInterface::STATUS_DELIVERED;
        return $smsData;
    }
}
