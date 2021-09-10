<?php

namespace App\Providers\Delivery\TkCompanies;

interface TkInterfaceWithCurl
{

    public function getCurlTasks() : array;

    public function confirmAwaitCurlData();

}
