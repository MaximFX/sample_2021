<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Providers\Delivery\Models\DeliveryTariffModel;

interface TkInterface
{



    /**
     * @param int|null $shipping_city_id
     * @param int $zip
     * @param string|null $kladr
     * @param int $weight
     * @param int $amount
     */
    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount);

    /**
     * @return DeliveryTariffModel|null
     */
    public function getPriceCourier(): ?DeliveryTariffModel;

    /**
     * @return DeliveryTariffModel|null
     */
    public function getPricePickup(): ?DeliveryTariffModel;

}
