<?php

namespace App\Providers\Delivery\Models;

use App\Constants\ShippingTypes;

class DeliveryTariffModel
{
    /**
     * @var int
     */
    private $transport_company_id;

    /**
     * @var int
     */
    private $tariff_id;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var float
     */
    private $amount_cover;

    /**
     * @var int
     */
    private $days_min;

    /**
     * @var int
     */
    private $days_max;

    /**
     * @param array{transport_company_id: int, tariff_id:int, amount: float, amount_cover: float, days_max: int, days_min: int} $data
     */
    public function __construct(array $data)
    {
        extract($data);

        $this->transport_company_id = $transport_company_id ?? null;
        $this->tariff_id = $tariff_id ?? null;
        $this->amount = $amount ?? ShippingTypes::TK_RUSSIAN_POST_DEFAULT_PRICE;
        $this->amount_cover = $amount_cover ?? 0;
        $this->days_min = $days_min ?? 2;
        $this->days_max = $days_max ?? 3;
    }

    /**
     * @return array
     */
    public function toArray() : array
    {
        return [
            'transport_company_id' => $this->transport_company_id,
            'tariff_id' => $this->tariff_id,
            'amount' => $this->amount,
            'amount_cover' => $this->amount_cover,
            'days_min' => $this->days_min,
            'days_max' => $this->days_max,
        ];
    }

    /**
     * @return int
     */
    public function getTransportCompanyId(): int
    {
        return $this->transport_company_id;
    }

    /**
     * @return int
     */
    public function getTariffId(): int
    {
        return $this->tariff_id;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return float
     */
    public function getAmountCover(): float
    {
        return $this->amount_cover;
    }

    /**
     * @return int
     */
    public function getDaysMin(): int
    {
        return $this->days_min;
    }

    /**
     * @return int
     */
    public function getDaysMax(): int
    {
        return $this->days_max;
    }


}
