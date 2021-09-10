<?php

namespace App\Providers\Delivery\TkCompanies;

use App\Constants\ShippingTypes;
use App\Constants\TransportCompanies;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use App\Services\Order\MakeOrder\GetShippingPriceForSaveOrder;

class Cdek extends AbstrcartTK implements TkInterface
{

    public function __construct(?int $shipping_city_id, int $zip, ?string $kladr, int $weight, int $amount)
    {
        parent::__construct($shipping_city_id, $zip, $kladr, $weight, $amount);
        $this->tk_id = TransportCompanies::CDEK;
        $this->pickup_tariff = $this->retrievePricePickup();
        $this->courer_tariff = $this->retrievePriceCourier();
    }

    protected function generateCacheKey($tk_tarif_code, bool $need_checking_active_cache = true): string
    {
        // В СДЭК не кешурем
        return '';
    }

    public function retrievePriceCourier(): ?DeliveryTariffModel
    {
        // Получаем цену и тариф доставки для этого заказа
        $ship_price_config = GetShippingPriceForSaveOrder::getPriceShipTariff(
            $this->shipping_city_id,
            ShippingTypes::COURIER_ID,
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


}
