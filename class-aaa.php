<?php

class aaa
{
    public $user;
    public $mac;
    public $siteIP;
    public $ap;
    public $type;
    public $responseHeader;
    public $responseBody;
    public $reqeustJson;
    public $session;
    public $result;

    public function __construct($request)
    {
        $parts = explode('/', $request);
        for ($x = 0; $x < count($parts); $x++)
        {
            switch ($parts[$x])
            {
                case "api":
                    $this->type = $parts[$x + 1];
                    break;
                case "user":
                    $this->user = new user;
                    $this->user->login = $parts[$x + 1];
                    $this->user->loadRecord();
                    break;
                case "mac":
                    $this->mac = $parts[$x + 1];
                    break;
                case "ap":
                    $this->ap = $parts[$x + 1];
                    break;
                case "site":
                    $this->siteIP = $parts[$x + 1];
                    break;
                case "result":
                    $this->result = $parts[$x + 1];
                    break;
            }

        }


    }
    public function processRequest()
    {
        switch ($this->type)
        {
            case "authorize":
                $this->authorize();
                break;
            case "post-auth":
                $this->postAuth();
                break;
            case "accounting":
                $this->accounting();
                break;

        }

    }
    public function accounting()
    {
        $acct = json_decode($this->requestJson, true);

        $this->session = new session($acct['Acct-Session-Id']['value'][0]);


        switch ($acct['Acct-Status-Type']['value'][0])
        {


            case 1:
                // Acct Start - Store session in Memcache
                $this->session->login = $acct['User-Name']['value'][0];
                $this->session->startTime = getdate();
                $this->session->mac = $acct['Calling-Station-Id']['value'][0];
                $this->session->ap = $acct['Called-Station-Id']['value'][0];
                $this->session->writeToCache();
                error_log("Accounting start: " . $this->session->login . " " . $this->session->
                    id);
                break;
            case 2:
                // Acct Stop - store record in DB
                $this->session->inOctets += $acct['Acct-Input-Octets']['value'][0];
                $this->session->outOctets += $acct['Acct-Output-Octets']['value'][0];
                $this->session->stopTime = getdate();
                $this->session->deleteFromCache();
                error_log("Accounting stop: " . $this->session->login . " " . $this->session->
                    id);
                $inMB = round($this->session->inOctets / 1000);
                $outMB = round($this->session->outOctets / 1000);
                error_log("Accounting stop: " . $this->session->login . " " . $this->session->
                    id . " InMB: " . $inMB . " OutMB: " . $outMB);
                break;
            case 3:
                // Acct Interim
                $this->session->inOctets += $acct['Acct-Input-Octets']['value'][0];
                $this->session->outOctets += $acct['Acct-Output-Octets']['value'][0];
                $this->session->writeToCache();
                error_log("Accounting update: " . $this->session->login . " " . $this->session->
                    id);
                break;
        }

    }
    public function postAuth()
    {
        if ($this->result == "Accept")
        {
            // insert a new entry into session
            $db = DB::getInstance();
            $dblink = $db->getConnection();
            $handle = $dblink->prepare('insert into sessions (start,siteIP,username,mac,ap) values (now(),:siteIP,:username,:mac,:ap)');
            $handle->bindValue(':siteIP', $this->siteIP, PDO::PARAM_STR);
            $handle->bindValue(':username', $this->user->login, PDO::PARAM_STR);
            $handle->bindValue(':mac', $this->mac, PDO::PARAM_STR);
            $handle->bindValue(':ap', $this->ap, PDO::PARAM_STR);
            $handle->execute();
        }

    }
    public function authorize()
    {
        if ($this->user->identifier->text)
        {
            $this->responseHeader = "HTTP/1.0 200 OK";
            $response['control:Cleartext-Password'] = $this->user->password;
            $this->responseBody = json_encode($response);
        } else
        {
            $this->responseHeader = "HTTP/1.0 401 Forbidden";

        }

    }
}

?>