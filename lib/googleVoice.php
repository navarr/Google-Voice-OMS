<?php
class GoogleVoice
{
        public $username;
        public $password;

        private $lastURL;
        private $crumb;

        public $useragent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.6 (KHTML, like Gecko) Chrome/7.0.503.0 Safari/534.6";

        public $debug = FALSE;

        public function __construct($username,$password)
        {
        	$this->username = $username;
                $this->password = $password;
                $this->login();
        }
        // Login to Google Voice
        private function login()
        {
		$html = $this->curl("http://www.google.com/voice/m");

                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $form = $dom->getElementById("gaia_loginform");
                $inputList = $form->getElementsByTagName("input");
                $inputAmt  = $inputList->length;
                $inputs = array();


                for($i = 0; $i < $inputAmt;$i++)
                {
                        $inputs[$inputList->item($i)->getAttribute('name')] = $inputList->item($i)->getAttribute('value');
                }
                $action = $form->getAttribute('action');
                print($action);
                print_r($inputs);
                $inputs["Email"] = $this->username;
                $inputs["Passwd"] = $this->password;
                $post = http_build_query($inputs);

                $html = $this->curl($action, $this->lastURL, $post);

                $this->crumb = urlencode($this->match('!<input.*?name="_rnr_se".*?value="(.*?)"!ms', $html, 1));
                if(!$this->crumb) { throw new Exception("Couldn't get the Crumb!"); }
        }
        // Connect $you to $them. Takes two 10 digit US phone numbers.
        public function call($you, $them)
        {
                $you = preg_replace('/[^0-9]/', '', $you);
                $them = preg_replace('/[^0-9]/', '', $them);

                $crumb = $this->crumb;

                $post = "_rnr_se=$crumb&number=$them&call=Call";
                $html = $this->curl("https://www.google.com/voice/m/callsms", $this->lastURL, $post);

                preg_match_all('!<input.*?type="hidden".*?name="(.*?)".*?value="(.*?)"!ms', $html, $hidden);
                $post = '';
                for ($i = 0; $i < count($hidden[0]); $i++)
                $post .= '&'.$hidden[1][$i].'='.urlencode($hidden[2][$i]);
                $post .= "&phone=+1$you&Call=";

                $html = $this->curl("https://www.google.com/voice/m/sendcall", $this->lastURL, $post);
        }
        public function sms($them, $smtxt)
        {
                $them = preg_replace('/[^0-9]/', '', $them);

                $crumb = $this->crumb;

                $post = "_rnr_se=$crumb&number=$them&smstext=$smtxt&submit=Send";
                $html = $this->curl("https://www.google.com/voice/m/sendsms", $this->lastURL, $post);

                preg_match_all('!<input.*?type="hidden".*?name="(.*?)".*?value="(.*?)"!ms', $html, $hidden);
                $post = '';
                for ($i = 0; $i < count($hidden[0]); $i++)
                $post .= '&'.$hidden[1][$i].'='.urlencode($hidden[2][$i]);
                $post .= "&submit=";

                $html = $this->curl("https://www.google.com/voice/m/sendcall", $this->lastURL, $post);
        }
        public function get_number()
	{
		$raw = $this->curl("https://www.google.com/voice/m");
		preg_match("#\<b class=\"ms3\"\>([^<]+)\</b\>#i",$raw,$matches);
		$number = str_replace
		(
			array(" ","(",")","-"),
		"",$matches[1]);
		return "1".$number;
	}
        protected function curl($url, $referer = null, $post = null, $return_header = false)
        {
                static $tmpfile;

                if (! isset ($tmpfile) || ($tmpfile == ''))
                        { $tmpfile = tempnam("\temp", "FOO"); }

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfile);
                curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfile);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Accept" => "application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5"
                ));
                if($this->useragent)
                        { curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent); }

                if ($referer)
                        { curl_setopt($ch, CURLOPT_REFERER, $referer); }

                if (!is_null($post))
                {
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                }

                if ($return_header)
                {
                        curl_setopt($ch, CURLOPT_HEADER, 1);
                        $html = curl_exec($ch);
                        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                        $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                        return substr($html, 0, $header_size);
                }
                else
                {
                        $html = curl_exec($ch);
                        $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                        return $html;
                }
        }
        protected function match($regex, $str, $i = 0)
        {
                return preg_match($regex, $str, $match) == 1?$match[$i]:false;
        }
}