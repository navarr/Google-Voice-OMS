<?php

ini_set('soap.wsdl_cache_enabled', '0');
require_once 'lib/googleVoice.php';
require_once 'settings.php';
$server = new SoapServer('xml/oms.wsdl');
$server->addFunction('GetServiceInfo');
$server->addFunction('GetUserInfo');
$server->addFunction('DeliverXms');
$server->addFunction('SendXms');
$server->handle();
function load_xml($file)
{
    ob_start();
    include $file;
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
}

function GetServiceInfo()
{
    global $soap;
    $soap = true;

    return ['GetServiceInfoResult' => load_xml('xml/serviceInfo.xml')];
}

function GetUserInfo($complex)
{
    global $soap;
    $soap = true;
    $xmsUser = $complex->xmsUser;
    $xmsUser = str_replace('UTF-16', 'UTF-8', $xmsUser);
    $return = '<?xml version="1.0" encoding="utf-16"?>'."\n";
    try {
        $xml = new SimpleXMLElement($xmsUser);
        $user = $xml->userId;
        $pass = $xml->password;

        try {
            $gv = new GoogleVoice($user, $pass);
            $email = $user;
            $phone = $gv->getNumber();
            $return .= '<userInfo xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS">';
            $return .= '<replyPhone>'.$phone.'</replyPhone>';
            $return .= '<smtpAddress>'.$email.'</smtpAddress>';
            $return .= '<error code="ok" severity="neutral" />';
            $return .= '</userInfo>';
        } catch (Exception $e) {
            $return .= '<userInfo xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS">';
            $return .= '<error code="invalidUser" severity="failure" />';
            $return .= '</userInfo>';
        }
    } catch (Exception $e) {
        $return .= '<userInfo xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS"><error code="invalidFormat" severity="failure" /></userInfo>';
    }

    return ['GetUserInfoResult' => $return];
}

function SendXms($complex)
{
    $t = DeliverXms($complex);

    return ['SendXmsResult' => $t['DeliverXmsResult']];
}

function DeliverXms($complex)
{
    global $soap;
    $soap = true;
    $xmsData = $complex->xmsData;
    $xmsData = str_replace('UTF-16', 'UTF-8', $xmsData);
    $return = '<?xml version="1.0" encoding="utf-16"?>'."\n";
    try {
        $xml = new SimpleXMLElement($xmsData);
        $user = ((string) $xml->user->userId);
        $pass = ((string) $xml->user->password);

        try {
            $gv = new GoogleVoice($user, $pass);
            $recps = $xml->xmsHead->to->recipient;
            $msgs = $xml->xmsBody->content;
            foreach ($recps as $to) {
                if (substr($to, 0, 1) == 1) {
                    $to = substr($to, 1);
                }
                foreach ($msgs as $msg) {
                    $gv->sms($to, $msg);
                }
            }
            $return .= '<xmsResponse xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS"><error code="ok" severity="neutral" /></xmsResponse>';
        } catch (Exception $e) {
            $return .= '<userInfo xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS"><error code="invalidUser" severity="failure" /></userInfo>';
        }
    } catch (Exception $e) {
        $return .= '<userInfo xmlns="http://schemas.microsoft.com/office/Outlook/2006/OMS"><error code="invalidFormat" severity="failure" /></userInfo>';
    }

    return ['DeliverXmsResult' => $return];
}
