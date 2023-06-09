<?php

/*Auteur : warkx
  Version : 2.2
  Développé le : 04/05/2018
  MAJ: le 24/05/2023 par Cbe
  Description : Support du compte gratuit et premium*/

if (!defined('LOGIN_FAIL')) {
  define('LOGIN_FAIL', 4);
}
if (!defined('USER_IS_FREE')) {
  define('USER_IS_FREE', 5);
}
if (!defined('USER_IS_PREMIUM')) {
  define('USER_IS_PREMIUM', 6);
}
if (!defined('ERR_FILE_NO_EXIST')) {
  define('ERR_FILE_NO_EXIST', 114);
}
if (!defined('ERR_REQUIRED_PREMIUM')) {
  define('ERR_REQUIRED_PREMIUM', 115);
}
if (!defined('ERR_NOT_SUPPORT_TYPE')) {
  define('ERR_NOT_SUPPORT_TYPE', 116);
}
if (!defined('DOWNLOAD_STATION_USER_AGENT')) {
  define('DOWNLOAD_STATION_USER_AGENT', "Mozilla/4.0 (compatible; MSIE 6.1; Windows XP)");
}
if (!defined('DOWNLOAD_URL')) {
  define('DOWNLOAD_URL', 'downloadurl'); // Real download url
}
if (!defined('DOWNLOAD_FILENAME')) {
  define('DOWNLOAD_FILENAME', 'filename'); // Saved file name define('DOWNLOAD_COUNT', 'count'); // Number of seconds to wait
}
if (!defined('DOWNLOAD_ISQUERYAGAIN')) {
  define('DOWNLOAD_ISQUERYAGAIN', 'isqueryagain'); // 1: Use the original url query from the user again. 2: Use php output url query again.
}
if (!defined('DOWNLOAD_ISPARALLELDOWNLOAD')) {
  define('DOWNLOAD_ISPARALLELDOWNLOAD', 'isparalleldownload');//Task can download parallel flag.
}
if (!defined('DOWNLOAD_COOKIE')) {
  define('DOWNLOAD_COOKIE', 'cookiepath');
}

if (!defined('DOWNLOAD_ERROR')) {
  define('DOWNLOAD_ERROR', 'downloaderror');
}
if (!defined('INFO_NAME')) {
  define('INFO_NAME', 'infoname');
}


class SynoFileHostingUptobox
{
    private $Url;
    private $Token;
    private $HostInfo;
    private $FileID;
    private $WAITINGTOKEN_VALUE;
    private $WAITINGTOKEN_FILE;
    private $ENABLE_DEBUG = FALSE;

    private $PREMIUM_EXPIRATION_REGEX = '/premium_expire":"(.*?)"/i';
    private $ERROR_REGEX = '/"error/i';
    private $FILE_ID_REGEX = '/https?:\/\/uptobox\.(?:eu|com)\/(.+)/';
    private $FILE_URL_REGEX = '/"dllink":"(.*)"/i';
    private $DOWNLOAD_WAIT_REGEX = '/"waiting":(\d*)/i';
    //private $DEBUG_REGEX = '/(https?:\/\/uptobox\.com\/.+)\/debug/i';
    private $DEBUG_REGEX = "@^(?:https?://)?uptobox.(?:eu|com)/(?:.+)debug/(.+)@i";
    private $WAITINGTOKEN_REGEX = '/"waitingtoken":"(.*?)"/i';

    private $WAITINGTOKEN_FILEPATH = '/tmp/';
    private $LOG_FILE = '/tmp/uptobox.log';

    private $QUERYAGAIN = 1;
    private $PARSEURL = 2;
    private $STRING_COUNT = 'count';


    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Token = $Password;
		    $this->HostInfo = $HostInfo;

        //verifie si le debug est activé avec un "/debug"
        preg_match($this->DEBUG_REGEX, $Url, $debugmatch);

        if(!empty($debugmatch[1]))
        {
            $this->Url = $debugmatch[1];
            $this->ENABLE_DEBUG = TRUE;
        }else
        {
            $this->Url = $Url;
        }

        // redirige les URL .com en .eu
        $thisUrl = str_replace('uptobox.com', 'uptobox.eu', $this->Url);
        $this->DebugMessage("TOKEN: ".$this->Token);
        $this->DebugMessage("URL: ".$this->Url);
    }

    //Renvoie le type de compte
    public function Verify($ClearCookie)
    {
        $ret = false;

        $ret = $this->AccountType();
        if($ret == false)
        {
            $this->DebugMessage("LOGIN FAILED");
            $ret = LOGIN_FAIL;
        }

        return $ret;
    }

    private function GenerateCurl($url) {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        return $curl;
    }

    //Fait les verifications sur le type de compte
    private function AccountType()
    {
        $ret = false;

        $url =  'https://uptobox.eu/api/user/me?token='.$this->Token;

        $this->DebugMessage("URL ACCOUNT: ".$url);

        $curl = $this->GenerateCurl($url);
        if( ! $ret = curl_exec($curl)) {
          $err = curl_error($curl);
          $this->DebugMessage("ERREUR RENCONTREE: ".$err);
        }
        curl_close($curl);

        $this->DebugMessage("ACCOUNTTYPEPAGE: ".$ret);

        preg_match($this->PREMIUM_EXPIRATION_REGEX, $ret, $premiumexpirematch);
        $this->DebugMessage("PREMIUM_EXPIRATION_REGEX:".json_encode($premiumexpirematch));
        if(!empty($premiumexpirematch[1]))
        {
            date_default_timezone_set('UTC');
            $time = strtotime($premiumexpirematch[1]) - strtotime(date('Y-m-d H:i:s')) ;
            if($time > 0)
            {
                $this->DebugMessage("PREMIUM ACCOUNT");
                $ret = USER_IS_PREMIUM;
            }else
            {
                $this->DebugMessage("FREE ACCOUNT");
                $ret = USER_IS_FREE;
            }
        }else
        {
           $ret = false;
        }

        return $ret;
    }

    //Lance le telechargement en fonction du type de compte
    public function GetDownloadInfo()
    {
        $ret = false;
        $VerifyRet = $this->Verify(false);

        $this->GetFileID();

        if(USER_IS_PREMIUM == $VerifyRet)
        {
            $ret = $this->DownloadPremium();

        }else if(USER_IS_FREE == $VerifyRet)
        {
            $ret = $this->DownloadFree();
        }else
        {
            $ret = $this->DownloadWithoutAccount();
        }

        if($ret != false)
        {
            $ret[INFO_NAME] = trim($this->HostInfo[INFO_NAME]);
        }

        return $ret;
    }

    //recuperer l'url premium
    private function DownloadPremium()
    {
        $page = false;
        $DownloadInfo = array();
        $page = $this->DownloadPage();

        if($page == false)
        {
            $this->DebugMessage("PREMIUM DOWNLOAD - FILE DOES NOT EXIST");
            $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
        }else
        {
          preg_match($this->FILE_URL_REGEX,$page,$urlmatch);
          if(!empty($urlmatch[1]))
          {
            $DOWNLOAD_URL = trim(stripslashes($urlmatch[1]));
            $DownloadInfo[DOWNLOAD_URL] = $DOWNLOAD_URL;
            $this->DebugMessage("URL_PREMIUM: ".$DOWNLOAD_URL);
          }else
          {
            $this->DebugMessage("PREMIUM DOWNLOAD - FILE DOES NOT EXIST");
            $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
          }

          $DownloadInfo[DOWNLOAD_ISPARALLELDOWNLOAD] = true;
        }
        return $DownloadInfo;
    }

    //recupere l'url d'un compte gratuit
    private function DownloadFree()
    {
        $page = false;
        $DownloadInfo = array();

        $this->CheckToken();

        $page = $this->DownloadPage();

        if($page == false)
        {
            $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
        }else
        {
          preg_match($this->FILE_URL_REGEX,$page,$urlmatch);
          if(!empty($urlmatch[1]))
          {
            $DOWNLOAD_URL = trim(stripslashes($urlmatch[1]));
            $DownloadInfo[DOWNLOAD_URL] = $DOWNLOAD_URL;
            $this->DebugMessage("URL_FREE: ".$DOWNLOAD_URL);
          }else
          {
            preg_match($this->DOWNLOAD_WAIT_REGEX, $page, $waitingmatch);
            if(!empty($waitingmatch[1]))
            {
                $waitingtime = $waitingmatch[1] + 3;
                $DownloadInfo[DOWNLOAD_COUNT] = $waitingtime;
                $DownloadInfo[DOWNLOAD_ISQUERYAGAIN] = $this->QUERYAGAIN;
                $this->DebugMessage("WAITING_TIME: ".$waitingtime);

                $this->SearchWaitingToken($page);
            }else
            {
                $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
            }
          }

        }
        return $DownloadInfo;
    }

    private function CheckToken()
    {
        $this->GenerateWaitingTokenPath();
        $waitigtoken = $this->FindWaitingToken();
    }


    //recherche un waitigtoken sur la page et l'enregistre dans un fichier
    private function SearchWaitingToken($page)
    {
        preg_match($this->WAITINGTOKEN_REGEX, $page, $waitingtokenmatch);
        if(!empty($waitingtokenmatch[1]))
        {
            $this->WritewaitingToken($waitingtokenmatch[1]);
            $this->DebugMessage("waitINGTOKEN_FIND_ON_PAGE: ".$waitingtokenmatch[1]);
        }

    }

    //ecrit l'id du waitingtoken dans un fichier
    private function WritewaitingToken($waitigtoken)
    {
        $myfile = fopen($this->WAITINGTOKEN_FILE, "w");
        fwrite($myfile,$waitigtoken);
        fclose($myfile);
    }

    //creer le fichier dans lequel l'id du waitingtoken sera stocké
    private function GenerateWaitingTokenPath()
    {
        $this->WAITINGTOKEN_FILE = ($this->WAITINGTOKEN_FILEPATH).($this->FileID).(".uptobox.token");
        $this->DebugMessage("waitINGTOKEN_FILE_CREATED: ".$this->WAITINGTOKEN_FILE);
    }

    //cherche un fichier contenant un id de waitigtoken. Renvoie false s'il n'y e a pas et recupere
    //la valeur s'il y en a une
    private function FindWaitingToken()
    {
        $ret = false;
        If(file_exists($this->WAITINGTOKEN_FILE))
        {
            $ret = file_get_contents($this->WAITINGTOKEN_FILE);
            unlink($this->WAITINGTOKEN_FILE);
            $this->WAITINGTOKEN_VALUE = $ret;
            $this->DebugMessage("WAITINGTOKEN_FIND_ON_NAS: ".$ret);
            $ret = true;
        }
        return $ret;
    }

    //Trouve l'ID de l'URL
    private function GetFileID()
    {
        preg_match($this->FILE_ID_REGEX, $this->Url, $fileidmatch);
        if(!empty($fileidmatch[1]))
        {
            $this->FileID = $fileidmatch[1];
            $this->DebugMessage("FILEID: ".$this->FileID);
        }
    }

    //Telecharge la page via les API
    private function DownloadPage()
    {
        $ret = false;

        $url =  'https://uptobox.eu/api/link?token='.$this->Token.'&id='.$this->FileID;

        if(!empty($this->WAITINGTOKEN_VALUE))
        {
            $url = $url.'&waitingToken='.$this->WAITINGTOKEN_VALUE;
        }

        $curl = $this->GenerateCurl($url);
        $ret = curl_exec($curl);
        curl_close($curl);

        // .com -> .eu
        $ret = str_replace('uptobox.com', 'uptobox.eu', $ret);

        $this->DebugMessage("DOWNLOADINFO: ".$ret);
        return $ret;
    }

    private function DownloadWithoutAccount()
    {
        $page = false;
        $DownloadInfo = array();
        $DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_ACCOUNT;
        return $DownloadInfo;
    }


    //ecrit un message dans un fichier afin de debug le programme
    private function DebugMessage($texte)
    {
        If($this->ENABLE_DEBUG == TRUE)
        {
            $myfile = fopen($this->LOG_FILE, "a");
            fwrite($myfile,$texte);
            fwrite($myfile,"\n\n");
            fclose($myfile);
        }
    }
}

/*
$urlToDl = 'https://uptobox.com/y1fwfdwq5390/debug/https://uptobox.com/y1fwfdwq5390';
//$urlToDl = 'https://uptobox.com/y1fwfdwq5390';
//$urlToDl = '';
$my = new SynoFileHostingUptobox($urlToDl, 'token', '32f7ab65ecbc6a3866caf49212ff85d5767sl', array(INFO_NAME=>"UPTOBOX"));
echo $my->Verify(true);
print_r( $my->GetDownloadInfo() );
*/
?>
