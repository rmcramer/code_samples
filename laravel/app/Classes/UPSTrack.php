<?php

error_reporting(E_ALL & ~E_NOTICE);

// require_once('XMLParser.php');

class UPSTrack
{
    //Configuration
    var $access = null;
    var $userid = null;
    var $passwd = null;
    var $wsdl = "/resources/documents/Track.wsdl";
    var $operation = "ProcessTrack";

    var $endpointurl = 'https://onlinetools.ups.com/webservices/Track';
    var $outputFileName = "XOLTResult.xml";

    /**
     * Construct, initially set object variables.
     *
     * @return false
     */
    public function __construct()
    {
        $this->wsdl = base_path() . $this->wsdl;
        $this->access = getenv('UPS_ACCESS');
        $this->userid = getenv('UPS_USERID');
        $this->passwd = getenv('UPS_PASSWD');
    }

    /**
     * Create the SOAP request array.
     *
     * @param  string $trackingNumber
     *
     * @return array
     */
    public function getTrackArray($trackingNumber)
    {
        //create soap request
        $req['RequestOption'] = '15';
        $tref['CustomerContext'] = 'Looking up tracking info.';
        $req['TransactionReference'] = $tref;
        $request['Request'] = $req;
        $request['InquiryNumber'] = $trackingNumber;
        $request['TrackingOption'] = '02';

        return $request;
    }

    /**
     * Determine the actual ship date from the details returned by UPS.
     *
     * @param  array $trackDetails
     *
     * @return null|string *DATETIME*
     */
    public function findShipDate($trackDetails)
    {
        $ship_date = null;

        if (isset($trackDetails[0]))
        {
            $count = count($trackDetails);
            $found = false;

            while ($count > 0 && !$found)
            {
                $count--;
                $trackDetail = $trackDetails[$count];
                if ($trackDetail['trk:Status']['trk:Type'] == 'I')
                {
                    $ship_date = date("Y-m-d 08:00:00", strtotime($trackDetail['trk:Date']));
                    $found = true;
                }
            }
        }
        else if (isset($trackDetails['trk:Status']) && $trackDetails['trk:Status']['trk:Type'] == 'I')
        {
            $ship_date = date("Y-m-d 08:00:00", strtotime($trackDetails['trk:Date']));
        }

        return $ship_date;
    }

    /**
     * Initiate SOAP request to get latest tracking information from UPS and return an local,
     *    denormalized array representing the response.
     *
     * @param  string $trackingNumber
     *
     * @return null|array
     */
    public function processTrack($trackingNumber = null)
    {

        unset($ex);

        $mode = array
        (
            'soap_version' => 'SOAP_1_1',  // use soap 1.1 client
            'trace' => 1
        );

        // initialize soap client
        $client = new SoapClient($this->wsdl, $mode);

        //set endpoint url
        $client->__setLocation($this->endpointurl);


        //create soap header
        $usernameToken['Username'] = $this->userid;
        $usernameToken['Password'] = $this->passwd;
        $serviceAccessLicense['AccessLicenseNumber'] = $this->access;
        $upss['UsernameToken'] = $usernameToken;
        $upss['ServiceAccessToken'] = $serviceAccessLicense;

        $header = new SoapHeader('http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0', 'UPSSecurity', $upss);
        $client->__setSoapHeaders($header);


        //get response
        try {
            $resp = $client->__soapCall($this->operation, array($this->getTrackArray($trackingNumber)));
        }
        catch (Exception $ex) {
        }
        $response = null;

        if (isset($resp))
        {
            $lastResponse = $client->__getLastResponse();

            if ($lastResponse)
            {
                $responseArray = \XML2Array::createArray($lastResponse);

                $responseStatus = $resp->Response->ResponseStatus->Description;

                if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:PickupDate']))
                    $pickupDate = date("Y-m-d", strtotime($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:PickupDate']));
                else $pickupDate = null;

                $trackingNumber = $responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:InquiryNumber']['trk:Value'];

                if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']['trk:Status']))
                    $lastStatus = $responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']['trk:Status']['trk:Description'];
                else if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity'][0]))
                    $lastStatus = $responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity'][0]['trk:Status']['trk:Description'];
                else $lastStatus = null;

                if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']['trk:Date']))
                    $lastStatusDate = $responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']['trk:Date'];
                else if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity'][0]))
                    $lastStatusDate = $responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity'][0]['trk:Date'];
                else $lastStatusDate = null;

                $response = [];
                $response['Service'] = 'UPS';
                $response['ID'] = $trackingNumber;
                $response['Status'] = $responseStatus;
                $response['Date'] = \App\Helpers::normalizeDatetimeFromUserTZtoUTC(date("Y-m-d 08:00:00", strtotime($lastStatusDate)));
                $response['Msg'] = $lastStatus;
                $response['ErrorCode'] = 0;

                $ship_date = null;

                if (isset($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']))
                    $ship_date = $this->findShipDate($responseArray['soapenv:Envelope']['soapenv:Body']['trk:TrackResponse']['trk:Shipment']['trk:Package']['trk:Activity']);

                if ($ship_date) $response['ShipDate'] = \App\Helpers::normalizeDatetimeFromUserTZtoUTC($ship_date);
            }
            else var_dump($client);
        }
        else if (isset($ex))
        {
            if ($ex)
            {
                $exceptionTrace = $ex->getTrace();
                //               dd($exceptionTrace);
                if(isset($exceptionTrace[0]['args'][1][0]['InquiryNumber']))
                {
                    $trackingNumber = $exceptionTrace[0]['args'][1][0]['InquiryNumber'];

                    //     echo $trackingNumber . '::Error::' . date("Y-m-d") . '::' . $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Description . '::' . $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Code . "\n";
//        }
                    $response = [];
                    $response['Service'] = 'UPS';
                    $response['ID'] = $trackingNumber;
                    $response['Status'] = 'Error';
                    $response['Date'] = date("Y-m-d H:i:s");
                    $response['Msg'] = $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Description;
                    $response['ErrorCode'] = $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Code;
                }
            }
            else var_dump($ex);
        }

        return $response;
    }

    /**
     * Given an array of tracking numbers, find what UPS thinks about each package
     *    and return an array of all responses.
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
            $response = $this->processTrack($trackingNumber);
            if(is_array($response)) $responses[] = $response;
        }
        return $responses;
    }
}

