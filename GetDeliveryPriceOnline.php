<?php

namespace App\Providers\Delivery;

use App\Constants\ShippingTypes;
use App\Constants\TransportCompanies;
use App\Services\Curl\Curl;
use Exception;
use Throwable;

class GetDeliveryPriceOnline
{

    /**
     * @var array
     */
    private $costs = [];

    /**
     * @param int|null $shipping_city_id
     * @param int $zip
     * @param $kladr
     * @param int $weight
     * @param int $amount
     * @param string $addressto
     * @throws Exception
     */
    public function __construct(?int $shipping_city_id, int $zip, $kladr, int $weight, int $amount, string $addressto)
    {

        $costs = [
            ShippingTypes::COURIER_ID => [
                TransportCompanies::RUSSIA_POST => null,
                TransportCompanies::BOXBERRY => null,
                TransportCompanies::CDEK => null,
                TransportCompanies::SBERLOGIST => null,
            ],
            ShippingTypes::PICKUP_ID => [
                TransportCompanies::RUSSIA_POST => null,
                TransportCompanies::BOXBERRY => null,
                TransportCompanies::CDEK => null,
                TransportCompanies::BELORIS => null,
            ],
        ];

        $tk_contracts = $this->getTkContracts([
            TransportCompanies::RUSSIA_POST,
            TransportCompanies::BOXBERRY,
            TransportCompanies::CDEK,
            TransportCompanies::SBERLOGIST,
            TransportCompanies::BELORIS,
        ], $shipping_city_id, $zip, $kladr, $weight, $amount);


        foreach ($costs as $method => $tks) {
            foreach ($tks as $tk_id => $conditions) {
                if (empty($tk_contracts[$tk_id])) {
                    unset($costs[$method][$tk_id]);
                    continue;
                }
                $contract = $tk_contracts[$tk_id];

                if ($method == ShippingTypes::COURIER_ID) {
                    $costs[$method][$tk_id] = $contract->getPriceCourier();
                }
                if ($method == ShippingTypes::PICKUP_ID) {
                    $costs[$method][$tk_id] = $contract->getPricePickup();
                }
            }
        }

        $this->costs = $costs;
    }

    public function getPriceCourier() : ?array
    {
        return $this->costs[ShippingTypes::COURIER_ID] ?? null;
    }


    public function getPricePickup() : ?array
    {
        return $this->costs[ShippingTypes::PICKUP_ID] ?? null;
    }


    /**
     * @return TransportCompanyContract[]
     * @throws Exception
     */
    private function getTkContracts(array $tk_ids, ?int $shipping_city_id, int $zip, $kladr, int $weight, int $amount): array
    {
        $tk_contracts = [];

        $curl_tasks = [];
        $tk_with_task = [];
        foreach ($tk_ids as $tk_id) {
            try {
                $tk_contract = new TransportCompanyContract($tk_id, $shipping_city_id, $zip, $kladr, $weight, $amount);

                if (!empty($tk_contract->getCurlTasks())) {
                    $tk_with_task[] = $tk_id;
                    $curl_tasks = array_merge($curl_tasks, $tk_contract->getCurlTasks());
                }

                $tk_contracts[$tk_id] = $tk_contract;
            } catch (Throwable $t) {
                report($t);
                continue;
            }
        }

        if (!empty($curl_tasks)){
            Curl::getMultiCurlData($curl_tasks);
        }

        foreach ($tk_with_task as $tk_id) {
            $tk_contracts[$tk_id]->confirmAwaitCurlData();
        }

        return $tk_contracts;
    }

}
