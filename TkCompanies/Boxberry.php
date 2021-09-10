<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Constants\ShippingTypes;
use App\Constants\TransportCompanies;
use App\Constants\TransportCompaniesTariffs;
use App\Providers\Delivery\Models\DeliveryCurlTaskModel;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use App\Services\Curl\Curl;
use App\Services\Order\MakeOrder\GetShippingPriceForSaveOrder;
use Exception;
use Throwable;

class Boxberry extends AbstrcartTK implements TkInterface, TkInterfaceWithCurl
{
    const TARGET_START = '36201';

    const DISCOUNT = 15;


    /**
     * @var int
     */
    private $tk_tariff_courier_id;

    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        parent::__construct($shipping_city_id, $zip, $kladr, $weight, $amount);

        $this->tk_id = TransportCompanies::BOXBERRY;
        $this->tk_tariff_courier_id = TransportCompaniesTariffs::BOXBERRY_COURIER;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getCurlTasks(): array
    {
        if (empty($this->curl_tasks)){
            $courier_cache_key = $this->generateCacheKey($this->tk_tariff_courier_id);
            $this->courer_tariff = cache()->get($courier_cache_key);
            if (is_null($this->courer_tariff)){
                $this->createCurlResource(ShippingTypes::COURIER_ID, $courier_cache_key);
            }


            $this->pickup_tariff = $this->retrievePricePickup();
        }

        return $this->curl_tasks;
    }


    /**
     * @throws Exception
     */
    public function confirmAwaitCurlData()
    {
        $curl_tasks = collect($this->curl_tasks);

        /**
         * @var $tariff_data DeliveryCurlTaskModel
         */
        $tariff_data = $curl_tasks->where('method', ShippingTypes::COURIER_ID)->first();

        if (!empty($tariff_data)) {

            $this->courer_tariff = $this->getPriceTariff(
                $tariff_data->getResult(),
                $this->tk_tariff_courier_id
            );

            if (self::CACHE_ENABLED) {
                $courier_cache_key = $this->generateCacheKey($this->tk_tariff_courier_id, false);
                cache()->put($courier_cache_key, $this->courer_tariff, now()->addDays(3));
            }
        }

        $this->curl_tasks = [];

    }


    /**
     * @param $tk_tarif_code
     * @param bool $need_checking_active_cache - если передали true то будем чистить кэш, если его нельзя использовать.
     *                                           Если передали false - просто отдадим ключ
     * @return string
     * @throws Exception
     */
    protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string
    {
        $key_code = $this->zip;
        $cache_key = 'boxberry_price_' . $tk_tarif_code . ':' . $key_code . '_' . (ceil($this->weight/1000));

        if ($need_checking_active_cache && !self::CACHE_ENABLED) {
            cache()->forget($cache_key);
        }

        return $cache_key;
    }

    /**
     * @return string
     */
    private function getUrl(): string
    {
        $token = config('delivery.boxberry_token');

        $amount = 0;

        $weight = round($this->weight);

        $w = static::GABARITE['w'] ?? 15;
        $h = static::GABARITE['h'] ?? 15;
        $l = static::GABARITE['l'] ?? 15;

        return "https://api.boxberry.ru/json.php?token=$token&method=DeliveryCosts&weight=$weight&targetstart=".self::TARGET_START."&ordersum=0&deliverysum=0&paysum=$amount&height=$w&width=$h&depth=$l&zip={$this->zip}";
    }

    private function createCurlResource(int $method)
    {
        $this->curl_tasks[] = new DeliveryCurlTaskModel(
            Curl::initCurl($this->getUrl()),
            $this->tk_id,
            $method
        );
    }


    /**
     * @return DeliveryTariffModel|null
     */
    public function retrievePricePickup(): ?DeliveryTariffModel
    {
        // Получаем цену и тариф доставки для этого заказа
        $ship_price_config = GetShippingPriceForSaveOrder::getPriceShipTariff(
            $this->shipping_city_id,
            ShippingTypes::PICKUP_ID,
            $this->amount,
            $this->weight,
            $this->tk_id
        );

        return new DeliveryTariffModel([
            'transport_company_id' => $this->tk_id,
            'tariff_id' => $ship_price_config['tariff_id'],
            'amount' => $ship_price_config['shipping_price'],
            'amount_cover' => 0,
            'days_min' => $ship_price_config['days_min'] ?? 1,
            'days_max' => $ship_price_config['days_max'] ?? 2,
        ]);


    }

    /**
     * @param array|bool $tariff
     * @param int $tariff_id
     * @return DeliveryTariffModel|null
     * @throws Exception
     */
    private function getPriceTariff($tariff, int $tariff_id): ?DeliveryTariffModel
    {
        try {
            if (!is_array($tariff) || empty($tariff['price']) || empty($tariff['price_base'])) return null;

            // Общая сумма за пересылку, с учётом страховки
            $amount = round($tariff['price'] - $tariff['price_base'] / 100 * self::DISCOUNT);

            return new DeliveryTariffModel([
                'transport_company_id' => $this->tk_id,
                'tariff_id' => $tariff_id,
                'amount' => $amount,
                'amount_cover' => 0,
                'days_min' => $tariff['delivery_period'] ?? 1,
                'days_max' => $tariff['delivery_period'] ?? 2,
            ]);
        } catch (Throwable $t) {
            report($t);
            return null;
        }
    }
}
