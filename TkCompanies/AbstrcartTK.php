<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Providers\Delivery\Models\DeliveryCurlTaskModel;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use Exception;

abstract class AbstrcartTK implements TkInterface
{

    const CACHE_ENABLED = false;

    const DELIVERY_FROM_ZIP = 394961;

    const DELIVERY_FROM_KLADR = "36000001000";

    const GABARITE = [
        'w' => 15,
        'h' => 15,
        'l' => 15,
    ];

    /**
     * @var int
     */
    protected $tk_id;

    /**
     * @var string|null
     */
    protected $kladr;

    /**
     * @var int|null
     */
    protected $shipping_city_id;

    /**
     * @var int
     */
    protected $zip;
    /**
     * @var int
     */
    protected $weight;
    /**
     * @var int
     */
    protected $amount;
    /**
     * @var DeliveryTariffModel|null
     */
    protected $courer_tariff = null;
    /**
     * @var DeliveryTariffModel|null
     */
    protected $pickup_tariff = null;

    /**
     * @var DeliveryCurlTaskModel[]
     */
    protected $curl_tasks = [];

    /**
     * @param int|null $shipping_city_id
     * @param int $zip
     * @param string|null $kladr
     * @param int $weight
     * @param int $amount
     */
    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        $this->shipping_city_id = $shipping_city_id;
        $this->zip = $zip;
        $this->kladr = $kladr;
        $this->weight = $weight;
        $this->amount = $amount;
    }


    /**
     * @param $tk_tarif_code
     * @param bool $need_checking_active_cache - если передали true то будем чистить кэш, если его нельзя использовать.
     *                                           Если передали false - просто отдадим ключ
     * @return string
     * @throws Exception
     */
    abstract protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string;

    /**
     * @return DeliveryTariffModel|null
     */
    public function getPriceCourier(): ?DeliveryTariffModel
    {
        return $this->courer_tariff;
    }

    /**
     * @return DeliveryTariffModel|null
     */
    public function getPricePickup(): ?DeliveryTariffModel
    {
        return $this->pickup_tariff;
    }

}
