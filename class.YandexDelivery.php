<?php

/**
 * Класс для работы c доставкой через Яндекс Такси
 */
class YandexDelivery
{
	public $debugMode = true;
    public $yandexDeliveryOn = 0;
    public $langCode = 'ru_RU';
    public $responseUrl = '';
    public $taxiToken = '';
    public $geoToken = '';
    public $recipientCoordinates = [];

    public $LOG = [];
    public $ERRORS = [];
    public $MESSAGES = [];

    private $checkPriceUrl = 'b2b/cargo/integration/v1/check-price';
    private $geocoderUrl = 'https://geocode-maps.yandex.ru/1.x/';

    public function __construct() {
        $this->yandexDeliveryOn = getVal('YANDEX_DELIVERY_ON');
        $this->responseUrl = getVal('YANDEX_DELIVERY_URL');
        $this->taxiToken = getVal('YANDEX_DELIVERY_TOKEN');
        $this->geoToken = getVal('YANDEX_GEOKODER_TOKEN');
    }

    public function checkCoordinates($coordinates)
    {
        if (empty($coordinates)) {
            return [];
        }

        if (!is_array($coordinates)) {
            $coordinates = $this->parseCoordinates($coordinates);
        }

        return $coordinates;
    }

    public function parseCoordinates($coordinates) {

        if (is_string($coordinates)) {
            $coords = explode(',', $coordinates);
            rsort($coords);

            return [
                'lat' => (float)$coords[0], // Широта в градусах.
                'lng' => (float)$coords[1], // Долгота в градусах.
            ];
        }

        return [];
    }

    public function checkDeliveryPrice(int $quantity, array $senderCoordinates, array $recipientCoordinates)
    {
        if (empty($quantity) || empty($senderCoordinates) || empty($recipientCoordinates)) {
            return false;
        }

        $body = $this->getCheckPriceBodyFields($quantity, $senderCoordinates, $recipientCoordinates);

        if (empty($body)) {
            return false;
        }

        return $this->sendPostResponse($body, $this->responseUrl.$this->checkPriceUrl);
    }

    private function getPostHeader()
    {
        if (empty($this->langCode)) {
         //   requestError(string $error_code);
            return false;
        }

        if (empty($this->taxiToken)) {
            return false;
        }

        return [
            'Accept-Language: '.$this->langCode,
            'Authorization: Bearer '.$this->taxiToken,
            'Content-Type: application/json',
        ];
    }

    private function getCheckPriceBodyFields(int $quantity, array $senderCoordinates, array $recipientCoordinates)
    {
        if (empty($quantity) || empty($senderCoordinates) || empty($senderCoordinates)) {
            return false;
        }


        return json_encode([
            'items' => [
                [
                    'quantity' => $quantity
                ]
            ],
            'requirements' => [
                'pro_courier' => false,
                'taxi_class' => 'express'
            ],
            'route_points' => [
                [
                    'coordinates' => $senderCoordinates
                ],
                [
                    'coordinates' => $recipientCoordinates
                ],
            ],
            'skip_door_to_door' => false
        ]);
    }



    /**
     * Пример $address = 'Москва, Тверская, д.7';
     */
    public function getCoordinatesByAddress(string $address)
    {
        global $_delivery;

        if (empty($_delivery->yandexDeliveryOn)) {
            return [];
        }

        $checkAddress = mb_strtolower($address);

        if (!preg_match("/(алматы|[шч]{1}[иы]{1}мкент)/isu", $checkAddress, $matches)) {
            return [];
        }

        $responseUrl = $this->geocoderUrl.'?apikey='.$this->geoToken.'&format=json&geocode=' . urlencode($address);
        $result = $this->sendGetResponse($responseUrl);

        if (!empty($result) && !empty($result['response']) && !empty($result['response']['GeoObjectCollection']['featureMember'])) {
            return $result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'];
        }

        return [];
    }

    private function sendGetResponse(string $url)
    {
        if (empty($url)) {
            return false;
        }

        $request = curl_init($url);

        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_HEADER         => FALSE,
        ]);

        $result = curl_exec($request);
        curl_close($request);

        return $result ? json_decode($result, true) : [];
    }

    private function sendPostResponse(string $body, string $url)
    {

        if (empty($body) || empty($url)) {
            return false;
        }

        // https://b2b.taxi.yandex.net/b2b/cargo/integration/v1/check-price

        $request = curl_init();

        curl_setopt_array($request, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Accept-Language: '.$this->langCode,
                'Authorization: Bearer '.$this->taxiToken,
                'Content-Type: application/json',
            ],
        ]);

        $result = curl_exec($request);
        curl_close($request);

        return !empty($result) ? json_decode($result, true) : [];
    }

	private function requestError(string $error_code)
    {
		$this->ERRORS[$error_code] = $error_code;
	}

	private function setErrorLog(string $phone, string $smsText, string $sendDateTime = '')
    {

        if (!$this->debugMode) {
            return false;
        }

        if (empty($sendDateTime)) {
            $sendDateTime = date('d-m-Y H:i:s');
        }

		$this->LOG[] = "В ".$sendDateTime." на телефон ".$phone." будет отправлено сообщение: ".$smsText." <br> \n";
	}
}
