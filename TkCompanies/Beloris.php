<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Constants\MainShippingCities;
use App\Constants\TransportCompanies;
use App\Constants\TransportCompaniesTariffs;
use App\Providers\Delivery\Models\DeliveryTariffModel;

class Beloris extends AbstrcartTK implements TkInterface
{

    /**
     * @var int
     */
    private $tk_tariff_pickup_id;

    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        parent::__construct($shipping_city_id, $zip, $kladr, $weight, $amount);
        $this->tk_id = TransportCompanies::BELORIS;
        $this->pickup_tariff = $this->retrievePricePickup();
        $this->courer_tariff = $this->retrievePriceCourier();

        $this->tk_tariff_pickup_id = TransportCompaniesTariffs::BELORIS_PICKUP;
    }

    protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string
    {
        // Beloris не кешируем

        return '';
    }

    public function retrievePriceCourier(): ?DeliveryTariffModel
    {
        return null;
    }


    public function retrievePricePickup(): ?DeliveryTariffModel
    {

        if ($this->shipping_city_id == MainShippingCities::VORONEZH_ID) {
            return new DeliveryTariffModel([
                'transport_company_id' => $this->tk_id,
                'tariff_id' => $this->tk_tariff_pickup_id,
                'amount' => 0,
                'amount_cover' => 0,
                'days_min' => 1,
                'days_max' => 2,
            ]);
        }

        return null;


    }


}
