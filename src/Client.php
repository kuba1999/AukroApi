<?php

namespace AukroApi;

use AukroApi\Driver\DriverRequestFailedException;
use AukroApi\Session\SessionHandler;
use Nette\Utils\Strings;

/**
 * @method \stdClass getMyIncomingPayments(array $params = [])
 * @method \stdClass getSellFormFieldsExt()
 *
 * @author Pavel Jurásek
 */
class Client
{

	/** @var Identity */
	private $identity;

	/** @var CountryCode */
	private $countryCode;

	/** @var string */
	private $versionKey;

	/** @var array */
	private $requestData;

	/** @var SessionHandler */
	private $sessionHandler;

	/** @var SoapClient */
	private $soapClient;

	public function __construct(Identity $identity, CountryCode $countryCode, string $versionKey, SessionHandler $sessionHandler, SoapClient $soapClient)
	{
		$this->identity = $identity;
		$this->countryCode = $countryCode;
		$this->versionKey = $versionKey;
		$this->sessionHandler = $sessionHandler;
		$this->soapClient = $soapClient;

		$this->requestData = [
			'countryId' => $countryCode->getValue(), //for old function - example: doGetShipmentData
			'countryCode' => $countryCode->getValue(), //for new function
			'webapiKey' => $identity->getApiKey(),
			'localVersion' => $versionKey,
		];
	}

	public function isLogged(): bool
	{
		return $this->sessionHandler->load() !== NULL;
	}

	public function login()
	{
		if ($this->isLogged()) {
			return;
		}

		$requestData = $this->combineRequestData([
			'userLogin' => $this->identity->getUsername(),
			'userHashPassword' => $this->identity->getPassword(),
		]);

		try {
			$this->sessionHandler->store($this->soapClient->doLoginEnc($requestData));

		} catch (DriverRequestFailedException $e) {
			throw new LoginFailedException($e);
		} catch (\SoapFault $e) {
			throw new LoginFailedException($e);
		}
	}

	public function logout()
	{
		$this->sessionHandler->clear();
	}

	private function combineRequestData(array $data): array
	{
		$requestData = $this->requestData;

		if ($this->isLogged()) {
			$loginData = $this->sessionHandler->load();

			$requestData['sessionId'] = $loginData->sessionHandlePart;
			$requestData['sessionHandle'] = $loginData->sessionHandlePart;
		}

		return array_merge($requestData, $data);
	}

	public function __call($name, array $arguments): \stdClass
	{
		$params = isset($arguments[0]) ? (array) $arguments[0] : [];

		$request = $this->combineRequestData($params);

		$fname = $this->formatMethodName($name);

		return $this->soapClient->$fname($request);
	}

	public function setSessionHandler(SessionHandler $sessionHandler)
	{
		$this->sessionHandler = $sessionHandler;
	}

	private function formatMethodName(string $name): string
	{
		if (Strings::match($name, '~^do[A-Z]~')) {
			return $name;
		}

		return 'do' . ucfirst($name);
	}

}
