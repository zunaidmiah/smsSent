<?php
$json_url = "https://api.ebulksms.com:8080/sendsms.json";
$xml_url = "https://api.ebulksms.com:8080/sendsms.xml";
$http_get_url = "https://api.ebulksms.com:8080/sendsms";
$username = '';
$apikey = '';

if (isset($_POST['button'])) {
    $username = $_POST['username'];
    $apikey = $_POST['apikey'];
    $sendername = substr($_POST['sender_name'], 0, 11);
    $recipients = $_POST['telephone'];
    $message = $_POST['message'];
    $flash = 0;
    $message = substr($_POST['message'], 0, 160);//Limit this message to one page.
    $Ebulksms = new Ebulksms();

    if($_POST['method'] == 'JSON'){
        #Uncomment the next line for HTTP POST with JSON
        $result = $Ebulksms->useJSON($json_url, $username, $apikey, $flash, $sendername, $message, $recipients);
    }elseif($_POST['method'] == 'XML'){
        #Uncomment the next line and comment the one above if you want to use HTTP POST with XML
        $result = $Ebulksms->useXML($xml_url, $username, $apikey, $flash, $sendername, $message, $recipients);
    }else{
        #Use the next line and comment the ones above if you want to use simple HTTP GET
        $result = $Ebulksms->useHTTPGet($http_get_url, $username, $apikey, $flash, $sendername, $message, $recipients);
    }
}

class Ebulksms {

    public function useJSON($url, $username, $apikey, $flash, $sendername, $messagetext, $recipients) {
        $gsm = array();
        $country_code = '234';
        $arr_recipient = explode(',', $recipients);
        foreach ($arr_recipient as $recipient) {
            $mobilenumber = trim($recipient);
            if (substr($mobilenumber, 0, 1) == '0') {
                $mobilenumber = $country_code . substr($mobilenumber, 1);
            } elseif (substr($mobilenumber, 0, 1) == '+') {
                $mobilenumber = substr($mobilenumber, 1);
            }
            $generated_id = uniqid('int_', false);
            $generated_id = substr($generated_id, 0, 30);
            $gsm['gsm'][] = array('msidn' => $mobilenumber, 'msgid' => $generated_id);
        }
        $message = array(
            'sender' => $sendername,
            'messagetext' => $messagetext,
            'flash' => "{$flash}",
        );

        $request = array('SMS' => array(
                'auth' => array(
                    'username' => $username,
                    'apikey' => $apikey
                ),
                'message' => $message,
                'recipients' => $gsm
        ));
        $json_data = json_encode($request);
        if ($json_data) {
            $response = $this->doPostRequest($url, $json_data, array('Content-Type: application/json'));
            $result = json_decode($response);
            return $result->response->status;
        } else {
            return false;
        }
    }

    public function useXML($url, $username, $apikey, $flash, $sendername, $messagetext, $recipients) {
        $country_code = '234';
        $arr_recipient = explode(',', $recipients);
        $count = count($arr_recipient);
        $msg_ids = array();
        $recipients = '';

        $xml = new SimpleXMLElement('<SMS></SMS>');
        $auth = $xml->addChild('auth');
        $auth->addChild('username', $username);
        $auth->addChild('apikey', $apikey);

        $msg = $xml->addChild('message');
        $msg->addChild('sender', $sendername);
        $msg->addChild('messagetext', $messagetext);
        $msg->addChild('flash', $flash);

        $rcpt = $xml->addChild('recipients');
        for ($i = 0; $i < $count; $i++) {
            $generated_id = uniqid('int_', false);
            $generated_id = substr($generated_id, 0, 30);
            $mobilenumber = trim($arr_recipient[$i]);
            if (substr($mobilenumber, 0, 1) == '0') {
                $mobilenumber = $country_code . substr($mobilenumber, 1);
            } elseif (substr($mobilenumber, 0, 1) == '+') {
                $mobilenumber = substr($mobilenumber, 1);
            }
            $gsm = $rcpt->addChild('gsm');
            $gsm->addchild('msidn', $mobilenumber);
            $gsm->addchild('msgid', $generated_id);
        }
        $xmlrequest = $xml->asXML();

        if ($xmlrequest) {
            $result = $this->doPostRequest($url, $xmlrequest, array('Content-Type: application/xml'));
            $xmlresponse = new SimpleXMLElement($result);
            return $xmlresponse->status;
        }
        return false;
    }

//Function to connect to SMS sending server using HTTP GET
    public function useHTTPGet($url, $username, $apikey, $flash, $sendername, $messagetext, $recipients) {
        $query_str = http_build_query(array('username' => $username, 'apikey' => $apikey, 'sender' => $sendername, 'messagetext' => $messagetext, 'flash' => $flash, 'recipients' => $recipients));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$url}?{$query_str}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
        //return file_get_contents("{$url}?{$query_str}");
    }

//Function to connect to SMS sending server using HTTP POST
    private function doPostRequest($url, $arr_params, $headers = array('Content-Type: application/x-www-form-urlencoded')) {
        $response = array('code' => '', 'body' => '');
        $final_url_data = $arr_params;
        if (is_array($arr_params)) {
            $final_url_data = http_build_query($arr_params, '', '&');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $final_url_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	try{
            $response['body'] = curl_exec($ch);
            $response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    if ($response['code'] != '200') {
                throw new Exception("Problem reading data from $url");
            }
            curl_close($ch);
	} catch(Exception $e){
	    echo 'cURL error: ' . $e->getMessage();
	}
        return $response['body'];
    }

}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>EbulkSMS Send SMS API Sample</title>
    </head>

    <body>
        <h2 style="text-align: center">Ebulk SMS Integration Sample Code</h2>
        <div style="border: 1px solid #333; padding: 5px 10px; width: 40%; margin: 0 auto">
            <form id="form1" name="form1" method="post" action="">

                <?php
                if (!empty($_POST)) {
                    if (stristr($result, 'SUCCESS')) {
                        ?>
                        <p style="border: 1px dotted #333; background: #33ff33; padding: 5px;">Message sent</p>
                        <?php
                    } else {
                        ?>
                        <p style="border: 1px dotted #333; background: #FFDACC; padding: 5px;">Message not sent - <?php echo $result; ?></p>
                        <?php
                    }
                }
                ?>
                <p>
                    <label for="">Select API method: </label>
                    <select class="form-select" name="method" aria-label="Default select example">
                        <option selected value="PHP">PHP</option>
                        <option value="JSON">JSON</option>
                        <option value="XML">XML</option>
                    </select>
                </p>

                <p>
                    <label>Username:
                        <input name="username" type="text" id="username"/>
                    </label>
                </p>
                <p>
                    <label>API Key:
                        <input name="apikey" type="password" id="passwd" />
                    </label>
                </p>
                <p>
                    <label>Sender name:
                        <input name="sender_name" type="text" id="name" value="Integration" />
                    </label>
                </p>
                <p>
                    <label>Recipients
                        <textarea name="telephone" id="telephone" cols="45" rows="2"></textarea>
                    </label>
                </p>
                <p>
                    <label>Message
                        <textarea name="message" id="message" cols="45" rows="5"></textarea>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="submit" name="button" id="button" value="Submit" />
                    </label>
                    <label>
                        <input type="reset" name="button2" id="button2" value="Reset" />
                    </label>
                </p>
            </form>
        </div>
    </body>
</html>