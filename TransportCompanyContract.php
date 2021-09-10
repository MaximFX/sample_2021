<?php

namespace App\Providers\Delivery;

use App\Constants\TransportCompanies;
use App\Providers\Delivery\Models\DeliveryTariffModel;
use App\Providers\Delivery\TkCompanies\Beloris;
use App\Providers\Delivery\TkCompanies\Boxberry;
use App\Providers\Delivery\TkCompanies\Cdek;
use App\Providers\Delivery\TkCompanies\RussianPost;
use App\Providers\Delivery\TkCompanies\Shiptor;
use App\Providers\Delivery\TkCompanies\TkInterface;
use App\Providers\Delivery\TkCompanies\TkInterfaceWithCurl;
use Exception;


class TransportCompanyContract
{
    /**
     * @var TkInterface
     */
    private $tk;

    /**
     * @param $tk_id
     * @param int|null $shipping_city_id
     * @param int $zip
     * @param $kladr
     * @param int $weight
     * @param int $amount
     * @param string $addressto
     * @throws Exception
     */
    public function __construct($tk_id, ?int $shipping_city_id, int $zip, $kladr, int $weight, int $amount, string $addressto = '')
    {
        switch ($tk_id) {
            case TransportCompanies::RUSSIA_POST:
                $this->tk = new RussianPost($shipping_city_id, $zip, $kladr, $weight, $amount);
                break;
            case TransportCompanies::BOXBERRY:
                $this->tk = new Boxberry($shipping_city_id, $zip, $kladr, $weight, $amount);
                break;
            case TransportCompanies::CDEK:
                $this->tk = new Cdek($shipping_city_id, $zip, $kladr, $weight, $amount);
                break;
            case TransportCompanies::SBERLOGIST:
                $this->tk = new Shiptor($shipping_city_id, $zip, $kladr, $weight, $amount);
                $this->tk->setTkLabel(Shiptor::SBERLOGISTICS);
                break;
            case TransportCompanies::BELORIS:
                $this->tk = new Beloris($shipping_city_id, $zip, $kladr, $weight, $amount);
                break;
            default: throw new Exception('Неизвестная транспортная компания');
        }
    }


    public function getPriceCourier(): ?DeliveryTariffModel
    {
        return $this->tk->getPriceCourier();
    }

    public function getPricePickup(): ?DeliveryTariffModel
    {
        return $this->tk->getPricePickup();
    }


    /**
     * @return array
     * @throws Exception
     */
    public function getCurlTasks() : array
    {
        return ($this->tk instanceof TkInterfaceWithCurl) ? $this->tk->getCurlTasks() : [];
    }

    public function confirmAwaitCurlData()
    {
        if ($this->tk instanceof TkInterfaceWithCurl) {
            $this->tk->confirmAwaitCurlData();
        }
    }
}
