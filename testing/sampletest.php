
    <?php 
        //Data, connection, auth
        $dataFromTheForm = $_POST['fieldName']; // request data from the form
        $soapUrl = "http://www.thlrentals.com/Aurora/OTA/VehAvailRate"; // asmx URL of WSDL
        $soapUser = "driveacampervan@gmail.com";  //  username
        $soapPassword = "davido"; // password

        // xml post structure

        $xml_post_string = '<?xml version="1.0" encoding="utf-8"?>
                            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                              <soap:Body>
                                <VehAvailRate xmlns="http://www.thlrentals.com/Aurora/OTA" />
                              </soap:Body>
                            </soap:Envelope>';   // data from the form, e.g. some ID number

           $headers = array(
                        "Content-type: text/xml;charset=\"utf-8\"",
                        "Accept: text/xml",
                        "Cache-Control: no-cache",
                        "Pragma: no-cache",
                        "SOAPAction: http://www.thlrentals.com/Aurora/OTA/VehAvailRate", 
                        "Content-length: ".strlen($xml_post_string),
                    ); //SOAPAction: your op URL

            $url = $soapUrl;

            // PHP cURL  for https connection with auth
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $soapUser.":".$soapPassword); // username and password - declared at the top of the doc
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string); // the SOAP request
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // converting
            $response = curl_exec($ch); 
            curl_close($ch);

            // converting
            $response1 = str_replace("<soap:Body>","",$response);
            $response2 = str_replace("</soap:Body>","",$response1);

            // convertingc to XML
            $parser = simplexml_load_string($response2);
			echo "<pre>";print_r($parser);echo "</pre>";
            // user $parser to get your data out of XML response and to display it.
 /*   ?>

send-->
destination
currency
transmission
pickup
pickupdate
pickuptime
drop loc
drop date
drop time
mechinetype

get->

images
descrioption
price
max 
rooms
id


*/?>