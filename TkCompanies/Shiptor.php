<?php
namespace App\Providers\Delivery\TkCompanies;

use App\Constants\TransportCompanies;
use App\Constants\TransportCompaniesTariffs;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use App\Services\Curl\JsonRpcClient;
use Exception;
use Throwable;

class Shiptor extends AbstrcartTK implements TkInterface
{

    const SBERLOGISTICS = 'sberlogistics';

    const TARIF_COURIER = 'courier';

    const DISCOUNT = 20;

    const METHOD_URI = 'https://api.shiptor.ru/public/v1';

    /**
     * @var JsonRpcClient
     */
    protected $client;

    /**
     * @var string
     */
    private $tk_label;

    /**
     * @var int
     */
    private $tk_tariff_courier_id;


    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        parent::__construct($shipping_city_id, $zip, $kladr, $weight, $amount);

        $this->client = app(JsonRpcClient::class);
    }

    /**
     * @param string $tk_label
     */
    public function setTkLabel(string $tk_label): void
    {
        $this->tk_label = $tk_label;

        switch ($tk_label) {
            case self::SBERLOGISTICS:
                $this->tk_id = TransportCompanies::SBERLOGIST;
                $this->tk_tariff_courier_id = TransportCompaniesTariffs::SBER_COURIER;
            break;
        }
    }

    protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string
    {
        $cache_key = $this->tk_label . '_price:' . $this->kladr . '_' . (ceil($this->weight / 1000));

        if ($need_checking_active_cache && !self::CACHE_ENABLED) {
            cache()->forget($cache_key);
        }

        return $cache_key;
    }

    /**
     * @return DeliveryTariffModel|null
     * @throws \Psr\SimpleCache\InvalidArgumentException|Exception
     */
    public function retrivePriceCourier(): ?DeliveryTariffModel
    {

        if (empty($this->kladr)) return null;
        if (empty($this->tk_id)) return null;
        if (empty($this->tk_tariff_courier_id)) return null;

        $cache_key = $this->generateCacheKey(self::TARIF_COURIER);

        $calc = user_cache($cache_key, now()->addDay(), function () {
            try {

                $w = static::GABARITE['w'] ?? 15;
                $h = static::GABARITE['h'] ?? 15;
                $l = static::GABARITE['l'] ?? 15;

                $courier = $this->client->send(self::METHOD_URI, 'calculateShipping', 'JsonRpcClient.js', [
                    'stock' => false,
                    'kladr_id_from' => static::DELIVERY_FROM_KLADR,
                    'kladr_id' => $this->kladr,
                    'length' => $l,
                    'width' => $w,
                    'height' => $h,
                    'cod' => 0,
                    'declared_cost' => 0,
                    'weight' => round($this->weight / 1000),
                    'courier' => $this->tk_label,
                    'pick_up_type' => self::TARIF_COURIER,
                ]);


                if (empty($courier['result']) || !is_array($courier['result']['methods'])) {
                    return null;
                }

                // Отдаёт разные тарифы, ищем курьера
                foreach ($courier['result']['methods'] as $method) {
                    if (!empty($method['method']['id']) && $method['method']['id'] == 238) {
                        return $this->getPriceTariff($method, $this->tk_tariff_courier_id);
                    }
                }
            } catch (Throwable $t) {
                report($t);
            }

            return null;
        });

        if (empty($calc)) {
            cache()->forget($cache_key);
        }

        return $calc;
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
            if (!is_array($tariff) || empty($tariff['cost']['total']['sum'])) return null;

            // Общая сумма за пересылку, с учётом страховки
            $amount = round($tariff['cost']['total']['sum'] - $tariff['cost']['total']['sum'] / 100 * self::DISCOUNT);

            // Плата за страховку
            $amount_cover = 0;

            return new DeliveryTariffModel([
                'transport_company_id' => $this->tk_id,
                'tariff_id' => $tariff_id,
                'amount' => round($amount),
                'amount_cover' => round($amount_cover),
                // Иногда отдает 0, по этому поставим миниму 1 - 2 дня
                'days_min' => max(1, $tariff['min_days'] ?? 1),
                'days_max' => max(2, $tariff['max_days'] ?? 2),
            ]);
        } catch (Throwable $t) {
            report($t);
            return null;
        }
    }


}
