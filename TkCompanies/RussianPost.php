<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Constants\ShippingTypes;
use App\Constants\TransportCompanies;
use App\Constants\TransportCompaniesTariffs;
use App\Providers\Delivery\Models\DeliveryCurlTaskModel;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use App\Services\Curl\Curl;
use Exception;
use Throwable;

class RussianPost extends AbstrcartTK implements TkInterface, TkInterfaceWithCurl
{

    const COURIER_TARIFF_CODE = '24040';
    const PICKUP_TARIFF_CODE = '23020';
    /**
     * @var int
     */
    private $tk_tariff_courier_id;
    /**
     * @var int
     */
    private $tk_tariff_pickup_id;

    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        parent::__construct($shipping_city_id, $zip, $kladr, $weight, $amount);

        $this->tk_id = TransportCompanies::RUSSIA_POST;
        $this->tk_tariff_courier_id = TransportCompaniesTariffs::RUSSIA_POST_COURIER_ONLINE;
        $this->tk_tariff_pickup_id = TransportCompaniesTariffs::RUSSIA_POST_ONLINE;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getCurlTasks(): array
    {
        if (empty($this->curl_tasks)){
            $this->courer_tariff = cache()->get($this->generateCacheKey(self::COURIER_TARIFF_CODE));
            $this->pickup_tariff = cache()->get($this->generateCacheKey(self::PICKUP_TARIFF_CODE));

            if (is_null($this->courer_tariff)){
                $this->createCurlResource(self::COURIER_TARIFF_CODE, ShippingTypes::COURIER_ID);
            }

            if (is_null($this->pickup_tariff)){
                $this->createCurlResource(self::PICKUP_TARIFF_CODE, ShippingTypes::PICKUP_ID);
            }
        }

        return $this->curl_tasks;
    }

    /**
     * @throws Exception
     */
    public function confirmAwaitCurlData()
    {
        $curl_tasks = collect($this->curl_tasks);


        $await_courier = $curl_tasks->where('method', ShippingTypes::COURIER_ID);

        if (isNotEmptyCollection($await_courier) && count($await_courier) == 2) {
            /**
             * @var $tariff_data DeliveryCurlTaskModel
             * @var $delivery_data DeliveryCurlTaskModel
             */
            $tariff_data = $await_courier->where('key', 'tariff')->first();
            $delivery_data = $await_courier->where('key', 'delivery')->first();

            $this->courer_tariff = $this->getPriceTariff(
                $tariff_data->getResult(),
                $delivery_data->getResult(),
                $this->tk_tariff_courier_id
            );

            if (self::CACHE_ENABLED) {
                $courier_cache_key = $this->generateCacheKey(self::COURIER_TARIFF_CODE, false);
                cache()->put($courier_cache_key, $this->courer_tariff, now()->addDays(3));
            }
        }

        $await_pickup = $curl_tasks->where('method', ShippingTypes::PICKUP_ID);
        if (isNotEmptyCollection($await_pickup) && count($await_pickup) == 2) {
            /**
             * @var $tariff_data DeliveryCurlTaskModel
             * @var $delivery_data DeliveryCurlTaskModel
             */
            $tariff_data = $await_pickup->where('key', 'tariff')->first();
            $delivery_data = $await_pickup->where('key', 'delivery')->first();

            $this->pickup_tariff = $this->getPriceTariff(
                $tariff_data->getResult(),
                $delivery_data->getResult(),
                $this->tk_tariff_pickup_id
            );

            if (self::CACHE_ENABLED) {
                $courier_cache_key = $this->generateCacheKey(self::PICKUP_TARIFF_CODE, false);
                cache()->put($courier_cache_key, $this->pickup_tariff, now()->addDays(3));
            }
        }

        $this->curl_tasks = [];

    }

    /**
     * @param $tk_tarif_code
     * @param bool $need_checking_active_cache
     * @return string
     * @throws Exception
     */
    protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string
    {
        $key_code = $this->zip;
        $cache_key = 'russian_post_price_' . $tk_tarif_code . ':' . $key_code . '_' . (ceil($this->weight / 1000)) . '_' . (ceil($this->amount / 1000));

        if ($need_checking_active_cache && !self::CACHE_ENABLED) {
            cache()->forget($cache_key);
        }

        return $cache_key;
    }


    /**
     * @param string $tk_tarif_code
     * @param string $method
     * @return string
     */
    private function getUrl(string $tk_tarif_code, string $method = 'tariff'): string
    {
        $weight = round($this->weight);
        $amount = round($this->amount * 100);
        return "https://tariff.pochta.ru/v1/calculate/$method?object=$tk_tarif_code&from=" . static::DELIVERY_FROM_ZIP ."&to={$this->zip}&weight={$weight}&group=0&sumoc={$amount}&countinpack=1&sumin={$amount}&json&errorcode=0";
    }

    private function createCurlResource(string $tariff_code, int $method)
    {
        $this->curl_tasks[] = new DeliveryCurlTaskModel(
            Curl::initCurl($this->getUrl($tariff_code)),
            $this->tk_id,
            $method,
            'tariff'
        );

        $this->curl_tasks[] = new DeliveryCurlTaskModel(
            Curl::initCurl($this->getUrl($tariff_code, 'delivery')),
            $this->tk_id,
            $method,
            'delivery'
        );
    }




    /**
     * @param array|bool $tariff
     * @param array|bool|null $delivery
     * @param int $tariff_id
     * @return DeliveryTariffModel|null
     * @throws Exception
     */
    private function getPriceTariff($tariff, $delivery, int $tariff_id): ?DeliveryTariffModel
    {
        try {
            if (!is_array($tariff) || empty($tariff['paynds'])) return null;

            // Общая сумма за пересылку, с учётом страховки
            $amount = $tariff['paynds'];

            // Плата за страховку
            $amount_cover = $tariff['cover']['valnds'] ?? 0;

            return new DeliveryTariffModel([
                'transport_company_id' => $this->tk_id,
                'tariff_id' => $tariff_id,
                'amount' => round($amount / 100),
                'amount_cover' => round($amount_cover / 100),
                'days_min' => max(1, $delivery['delivery']['min'] ?? 7),
                'days_max' => max(1, $delivery['delivery']['max'] ?? 7),
            ]);
        } catch (Throwable $t) {
            report($t);
            return null;
        }
    }
}
