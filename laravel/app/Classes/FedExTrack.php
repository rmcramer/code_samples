<?php
// Copyright 2009, FedEx Corporation. All rights reserved.

/**
 *  Print SOAP request and response
 */

class FedExTrack
{
    var $wsdl = "/resources/documents/TrackService_v10.wsdl";

    public function __construct()
    {
        $this->wsdl = base_path() . $this->wsdl;
    }

    /**
     * Create the array passed to Authorize.net to chanrge a credit card,
     *    given the order and charge information passed.
     *
     * @param  \App\Order $client
     * @param  array $request_values
     *
     * @return array
     */
    public function grabShipmentInfoFromResponse($trackDetails)
    {
        switch ($trackDetails->Notification->Severity)
        {
            case 'SUCCESS':
                if (isset($trackDetails->ActualDeliveryTimestamp)) $trackDate = date("Y-m-d H:i:s",
                    strtotime($trackDetails->ActualDeliveryTimestamp));
                else $trackDate = date("Y-m-d H:i:s", strtotime($trackDetails->StatusDetail->CreationTime));

                $response = [];
                $response['Service'] = 'FEDEX';
                $response['ID'] = $trackDetails->TrackingNumber;
                $response['Status'] = 'Success';
                $response['Date'] = $trackDate;
                $response['Msg'] = $trackDetails->StatusDetail->Description;
                $response['ErrorCode'] = 0;

                if (isset($trackDetails->ShipTimestamp))
                    $response['ShipDate'] = \App\Helpers::normalizeDatetimeFromUserTZtoUTC(date("Y-m-d 08:00:00",
                        strtotime($trackDetails->ShipTimestamp)));
                break;

            case 'ERROR':
                $response = [];
                $response['Service'] = 'FEDEX';
                $response['ID'] = $trackDetails->TrackingNumber;
                $response['Status'] = 'Error';
                $response['Date'] = date("Y-m-d H:i:s");
                $response['Msg'] = $trackDetails->Notification->Message;
                $response['ErrorCode'] = $trackDetails->Notification->Code;
                break;
        }
        return $response;
    }

    /**
     * Use FedEx's SOAP API to get the tracking information for a specific tracking number,
     *    send the request, get the response, and package the response for further consumption by
     *    the application. Print exceptions or failures before returning null.
     *
     * @param  string $trackingNumber
     *
     * @return array|null
     */
    public function getTracking($trackingNumber)
    {
        ini_set("soap.wsdl_cache_enabled", "0");

        $client = new SoapClient($this->wsdl, ['trace' => 1]);

        $request['WebAuthenticationDetail'] = [ 'ParentCredential' => ['Key' => getenv('FEDEX_KEY'),
                                                                       'Password' => getenv('FEDEX_PASSWORD')],
                                                'UserCredential' => ['Key' => getenv('FEDEX_KEY'),
                                                                     'Password' => getenv('FEDEX_PASSWORD')]];

        $request['ClientDetail'] = [ 'AccountNumber' => getenv('FEDEX_SHIPACCOUNT'),
                                     'MeterNumber' => getenv('FEDEX_METER') ];

        $request['TransactionDetail'] = [ 'CustomerTransactionId' => 'Wholesalers Portal' ];
        $request['Version'] = [ 'ServiceId' => 'trck',
                                'Major' => '10',
                                'Intermediate' => '0',
                                'Minor' => '0' ];

        $request['SelectionDetails'] = ['PackageIdentifier' => [ 'Type' => 'TRACKING_NUMBER_OR_DOORTAG',
                                                                 'Value' => $trackingNumber ] ];

        try
        {
            $client->__setLocation(getenv('FEDEX_ENDPOINT'));

            $response = $client->track($request);

            switch ($response->HighestSeverity)
            {
                case 'FAILURE':
                case 'ERROR':
                    var_dump($response->CompletedTrackDetails);
                    return null;
                break;
                case 'SUCCESS':
                //    dd($response);
                    if ($response->CompletedTrackDetails->HighestSeverity != 'SUCCESS')
                    {
                        return $this->grabShipmentInfoFromResponse($response->CompletedTrackDetails);
                    }
                    else return $this->grabShipmentInfoFromResponse($response->CompletedTrackDetails->TrackDetails);
                    break;
            }
        }
        catch (SoapFault $exception)
        {
            var_dump($exception);
            return null;
        }
    }

    /**
     * Check with FedEx for each tracking number in the array passed,
     *    and return an array of the results from the inquiry.
     *
     * @param  array $trackingNumbers
     *
     * @return array
     */
    public function processArrayOfTrackingNumbers($trackingNumbers)
    {
        $responses = [];

        foreach ($trackingNumbers as $trackingNumber)
        {
            $response = $this->getTracking($trackingNumber);
            if(is_array($response)) $responses[] = $response;
        }
        //dd($responses);
        return $responses;
    }
}