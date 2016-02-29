<?php

require ("../common.php");
// Load the configuration file into a global variable
loadconfiguration();
// Connect to the database
db_connect();
$smsreq = new sms_request();

if ($smsreq->sender->valid_mobile)
{
    $sms = new sms_response;
    $sms->to = $smsreq->sender->text;
    $sms->set_reply();

    switch ($smsreq->message_words[0])
    {
        case "security":
            error_log("SMS: Security info request from $smsreq->sender->text");
            $sms->security();
            $sms->send();
            break;

        case "new":
            error_log("SMS: Creating new password for $smsreq->sender->text");
            $user = new user();
            $user->identifier = $smsreq->sender->text;
            $user->enroll(true);
            break;

        case "help":
            error_log("SMS: Sending help information to $smsreq->sender->text");
            $sms->help($smsreq->message);
            break;

        default:
            if (!$configuration['send-terms'] or $smsreq->message_words[0] == "agree")
            {
                error_log("SMS: Creating new account for $smsreq->sender->text");
                $user = new user();
                $user->identifier = $smsreq->sender->text;
                $user->enroll();
            } else
            {
                $sms->terms();
                error_log("SMS: Initial request, sending terms to $smsreq->sender->text");
            }
            break;


    }
}

?>